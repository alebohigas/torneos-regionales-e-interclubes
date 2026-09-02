---
name: golftour schema differences + schema_diag
description: Columnas que NO existen en la BD golftour (categorias.abreviatura, catrel, numganadorneto/gross) y uso de /api/schema_diag.php para troubleshooting de 500 "Unknown column"
type: feature
---

# BD `golftour` — diferencias de esquema

Los 500 opacos en este proyecto casi siempre son `Unknown column`. Regla: en
endpoints legacy construir SELECT/GROUP BY **dinámicos** con
`api_column_exists()` / `api_first_existing_column()` (definidos en `config.php`).

## Troubleshooting
`GET /api/schema_diag.php?tables=categorias,jugadores,caljuego,tarjetas,campo_tee,torneo&routines=1`
devuelve tablas, columnas y funciones reales. Además `&debug=1` en los endpoints
devuelve `_debug.last_sql` con el error exacto de MySQL.

## Columnas que NO existen en golftour
- `categorias`: sin `abreviatura`, `catrel`, `numganadorneto`, `numganadorgross`.
  Sí hay `numjugprem`, `numjuggross`, `corte`, `criterioDesempate`,
  `criteriodesempatecorte`, `campoid`, `hoyosxronda`.
- `campo_tee`: usa `id_campo` / `id_tee` (no `campoid` / `salidaid`).
- `tarjetas`: usa `id_campo`, tiene `statlsc`, `parcampohoyo`, `parcampo`.
- `clubs`: `id`, `nombre`, `abr`, `logo` (no `club`).
- `v_cd_ulttar`: `torneoid, jugadorid, c1..c6`. NO existe `v_cd_ulttar_sa/_so`.
- `caljuego`: `categoriaid`, `campo`, `estatus`, `fecha`.
- `categorias.salida` es varchar: puede traer id del tee o nombre ("AZULES");
  resolver contra `salidas.tee/color` antes de unir con `campo_tee.id_tee`.
- Funciones disponibles: `f_score_dia`, `f_torneoso`, `f_torneosa`, `f_parcampo`
  (por eso `/resultados` despacha a `resultados_jug_gira.php`).
- `jugadores.clubid` puede ser NULL → usar LEFT JOIN a `clubs`.
