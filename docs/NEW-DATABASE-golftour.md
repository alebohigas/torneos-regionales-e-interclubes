# Base de datos `golftour` (70.35.203.117) — inventario y plan de integración

Referencia del esquema real: `server/schema/golftour_schema.sql` (dump solo-estructura).

## 1. Buena noticia: el núcleo legacy es el MISMO

Estas tablas existen con los mismos nombres y columnas que usa hoy la app, así que
NO hay que reescribir los endpoints que las consultan:

`torneo`, `categorias`, `jugadores`, `tarjetas`, `salidagrupo`, `salidas`,
`salidasTorneo`, `caljuego`, `diasjuego`, `campos`, `campo_tee`, `par_campo`,
`hoyos`, `hoyosxsalida`, `clubs`, `avance`, `registro`, `estatuspago`,
`estatusjug`, `catego_estatus`, `puntuacion`, `puntuacion_torneo`, `gira`,
`copa`, `copas`, `tipotorneo`, `zona`, `sino`, `usuarios`, `dd_*`.

Vistas legacy que sí están: `v_salidas*`, `v_sal_jug`, `v_resultar`, `v_resultar_ec`,
`v_lista_jug`, `v_jugxcat`, `v_diasjgo*`, `v_parcampo*`, `v_mejor_score*`,
`v_difpar_*`, `v_cd_ulttar`, `v_cd_ulttar_ec`, `v_livesacor_pos`, `v_horariox`,
`v_estatorneo`, `v_gira_so*`, `v_posicionptos`, `v_sumsa_normal`, `result_ult_tar*`.

Funciones legacy presentes: `f_club`, `f_clubid`, `f_empates`, `f_score_dia`,
`f_parcampo`, `f_numjugcat`, `f_limite_neto`, `f_getsalid(s)`, `f_maxsalgpoid`,
`f_minsalgpoid`, `f_posicionptos`, `f_puntos_torneo`, `f_torneoso`, `f_gira`,
`f_catgira`, `f_jugcategoria(reg)`, `f_avgpuntos`, `f_posgira`, `f_numjugTor`.

## 2. Lo que FALTA en `golftour`

### 2.1 Tablas de la app (nuestra customización) — se crean con el bootstrap
`site_config`, `convocatoria_content`, `registro_form_fields`, `registro_campos`,
`registro_precios`, `registro_socio_tipos`, `registro_preferente_config`,
`categorias_reglas`, `banderas`, `bracket_config`, `bracket_matches`,
`clubs_registro`, `premios`, `menu`, `patrocinadores`, `usuario_areas`,
`usuario_sesion`, `countries` / `states` / `cities`.

Migraciones base (idempotentes, correr en orden):
1. `server/migrations/2026_08_21_bootstrap_new_db.sql` — `site_config` (con todas
   sus columnas `*_config`), `convocatoria_content`, `registro_form_fields`,
   `usuario_areas`, `usuario_sesion`.
2. `server/migrations/2026_08_21_bootstrap_new_db_part2.sql` — `registro_precios`,
   `registro_socio_tipos`, `registro_preferente_config`, `clubs_registro`,
   `categorias_reglas`, `banderas`, `menu`, `patrocinadores`, `bracket_config`,
   `bracket_matches`, `countries/states/cities` **y los `ALTER TABLE registro`**
   con todas las columnas nuevas (socio, precios, token, correos, akron_*).

**No existe `reglas_config`.** Las condiciones de competencia y reglas locales
viven en `convocatoria_content`, en las filas `section_id = 'reglas_intro_cards'`
y `section_id = 'reglas_locales'` (JSON). Igual que el resto de la convocatoria.

### 2.2 Tablas/vistas legacy de competencias — NO existen en esta BD
Competencias laterales y skins no tienen soporte aquí:

- Tablas: `oyesx`, `oyesxjug`, `putt`, `puttjug`, `driver`, `driverjug`,
  `driverp`, `driverjugp`, `approach`, `approachjug`, `premiosjug`,
  `skeen_tarjetas`, `valorstable`, `mejorscorep`, `elimin_salidas_cat`.
- Vistas: `v_oyes`, `v_oyesx`, `v_oyesunicas`, `v_oyesunicasxoyo`, `v_putt`,
  `v_puttunico`, `v_driver`, `v_driverp`, `v_driverunico(p)`, `v_approach`,
  `v_approachunico`, `v_jugadores`, `v_jugadores_parejas`, `v_sal_jug_par`,
  `v_sumsa`, `v_sumsarr`, `v_cd_ulttar_sa`, `v_cd_ulttar_so`, `v_mejorscorejugp`,
  `v_ult_tarjeta0`.
- Funciones: `f_ultfecha{oyesx,putt,driver,driverp,approach}`, `f_score_dia_sax`,
  `f_score_dia_sox`, `f_torneosa(x)`, `f_torneosox`, `f_stl_gross`, `f_mingross`,
  `f_hdccampo`, `f_hdccamponeto`, `f_getventajajug`, `f_logo`, `f_correo`, `f_ultact`.

**Decisión requerida por módulo:** o (a) se apaga en `/setup` (Competencias,
Skins, Mejor Score, Brackets, Parejas), o (b) se replican esas vistas/funciones
en `golftour` (existen en la BD `torneos`; se pueden exportar con
`mysqldump --no-data --routines torneos <objeto>`).

### 2.3 `registro` legacy vs. nuestro pre-registro
La tabla `registro` existe pero le faltan las columnas que agregamos:
`reg_es_socio`, `reg_tipo_socio`, `reg_numsocio`, `reg_cargo_socio`, `reg_ghin`,
`reg_sexo`, `reg_fechanac`, `reg_edad`, `reg_talla_*`, `reg_token`,
`reg_precio_estimado`, `reg_precio_regla_id`, `reg_precio_moneda`,
`reg_monto_confirmado`, `reg_pago_verificado`, `reg_notas`, `reg_archivo_mime`,
`reg_email_count/last`, `reg_welcome_count/last`, `reg_client_utc`,
`reg_client_tz_offset`.
→ Se resuelven con `ALTER TABLE` idempotentes antes de habilitar Pre-Registro.

## 3. Orden recomendado de trabajo

1. `credentials.php` apuntando a `golftour` (hecho).
2. Correr `2026_08_21_bootstrap_new_db.sql` y luego `..._part2.sql`.
3. Insertar la fila de `site_config` con `domain` + `torneoid` correctos
   (o hacerlo desde `/admin`).
4. `/setup`: apagar módulos sin soporte de datos (competencias, skins, brackets,
   parejas, mejor score) hasta decidir si se replican las vistas.
5. Integrar página por página validando con `/api/<endpoint>.php?torneoid=X&debug=1`.
6. Al final: menús, heros, visibilidad, permisos de `api/uploads/`.

## 4. Migración de la customización existente (opcional)
Se puede copiar tal cual desde la BD `torneos`:

```bash
mysqldump -h IP_VIEJA -u USER -p torneos site_config convocatoria_content > custom.sql
mysql -h 70.35.203.117 -u USER -p golftour < custom.sql
```
Luego actualizar `domain` y `torneoid` de las filas copiadas.
