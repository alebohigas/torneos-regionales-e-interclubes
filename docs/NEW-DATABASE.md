# Apuntar el proyecto a otra base de datos (ej. `golftour` en otra IP)

El código **no** tiene el nombre de la base de datos escrito en ninguna consulta:
todas usan `$conn` de `server/api/config.php`. Cambiar de `torneos` a `golftour`
es solo cambiar `credentials.php` **y** replicar las tablas propias de la app.

## 1. Credenciales (único cambio de código)

En el servidor, `/api/credentials.php` (nunca en Git):

```php
$DB_HOST = '203.0.113.10';   // IP del nuevo MySQL
$DB_USER = 'usuario_golftour';
$DB_PASS = '********';
$DB_NAME = 'golftour';
$DB_PORT = 3306;
```

Requisitos del nuevo servidor MySQL:

- Permitir conexiones remotas desde la IP del hosting (usuario `user@'%'` o la IP
  concreta) y abrir el puerto 3306 en el firewall.
- Charset `utf8mb4` (si no, `config.php` cae a `utf8` y aparecen acentos partidos).

Verifica con: `https://tudominio/api/health.php` → debe responder JSON sin error.

## 2. Qué NECESITA existir en `golftour`

### A. Del sistema legacy de torneos (las lee la app, no las crea)

Tablas: `torneos`, `categorias`, `jugadores`, `clubs`, `calendario_juego`,
`salidagrupo`, `resultados`/tarjetas, `oyes`, `oyesx`, `putt`, `skins`,
`elimin_salidas_cat`, `bracket_config`, `usuarios`, `cuentas_correo`, `campo_tee`.

Funciones y vistas: las listadas en `server/api/README.md`
(`f_torneosax`, `f_score_dia_sax`, `f_stl_gross`, `f_ultact`, `f_logo`,
`v_jugadores`, `v_sal_jug`, `v_oyes*`, `v_putt*`, `v_ult_tarjeta0`, `v_equipo_ed`, …).

Si `golftour` es una instalación del mismo sistema, ya las tiene. Si falta alguna,
el endpoint correspondiente devuelve vacío (patrón de resiliencia) en vez de 500,
así que la página se ve pero sin datos.

### B. Propias de la app (hay que crearlas)

| Tabla | Para qué | De dónde |
|---|---|---|
| `site_config` | torneoid por dominio, menús, visibilidad, tema, heros, **módulos de /setup** | bootstrap |
| `convocatoria_content` | /convocatoria y /reglas editables | bootstrap |
| `registro_form_fields` | campos del Pre-Registro | bootstrap |
| `usuario_areas`, `usuario_sesion` | login de staff en /admin | bootstrap |
| `registro_precios` (+ cols hcp en `registro`) | costos del Pre-Registro | `2026_05_19` + `2026_05_20` |
| `categorias_reglas` | elegibilidad por categoría | `2026_05_22` |
| `banderas` | /banderas | `2026_06_21` + `2026_06_22` |
| `registro_preferente_config` | ventana de registro preferente | `2026_07_16` |
| `registro_socio_tipos` | etiquetas de tipo de socio | `2026_07_21` |
| brackets / 3er lugar | Putt finales y Match Play | `2026_05_18`, `2026_07_03` |

## 3. Pasos, en orden

1. **Backup** de `golftour` antes de tocar nada.
2. Corre `server/migrations/2026_08_21_bootstrap_new_db.sql` (idempotente, sin GRANTs).
3. Corre, solo de los módulos que vayas a usar, las migraciones de la tabla de
   arriba. Todas son `CREATE TABLE IF NOT EXISTS` / `ALTER` seguros.
4. Sube `dist/` a la raíz y `server/api/` a `/api/`, con `credentials.php` nuevo.
5. Abre `/api/health.php` y `/api/site_config.php` — ambos deben dar 200.
6. Entra a `/admin` con la contraseña de superadmin y fija el **torneoid** del
   dominio. Sin esto, `tournament.php` responde 400 (ya limitado a 10 intentos).
7. Entra a `/setup` y apaga los módulos que este club no use.
8. Ajusta `/admin → Página` (visibilidad y orden del menú), Heros, Tema,
   Patrocinadores y Convocatoria.
9. Da permisos de escritura a `api/uploads/` (se crea una subcarpeta por dominio
   para heros, pósters y PDFs).

## 4. /setup y las páginas apagadas: cómo evitar problemas

- Un módulo apagado se guarda en `site_config.modules_config`. La ruta deja de
  existir en el router y su tab de admin desaparece; **la configuración previa no
  se borra**, así que volver a encenderlo (solo superadmin) la recupera.
- Apaga módulos **antes** de configurar menús: así el orden y la visibilidad se
  guardan ya sin las páginas que no usarás.
- Si un módulo tiene dependencias (por ejemplo los Skins entre sí), el registro de
  módulos las respeta y no deja apagar una pieza que otra necesita.
- Módulos apagados = migraciones que puedes **no** correr. Si más adelante lo
  enciendes, corre entonces su migración.
- Solo cuando la selección sea definitiva, congela el proyecto con
  `bun scripts/prune-modules.ts --keep=... --apply` (ver `docs/MODULES.md`).

## 5. Checklist de verificación rápida

- `/api/health.php` → 200
- `/api/site_config.php` → devuelve `torneoid` correcto
- `/api/tournament.php?torneoid=NNN` → 200 con nombre del torneo
- `/` muestra hero, logo y stats; `/convocatoria` muestra secciones
- `/registro` muestra solo los campos habilitados
- `/setup` accesible como superadmin y guarda sin error
