<?php
/**
 * Salidas Master Endpoint (golftour / gira schema)
 * GET /api/salidas.php?giraid=XXX[&torneoid=NN]
 *
 * Réplica del legacy `salidas.php`:
 *   - Días de juego: caljuego WHERE torneoid=? AND estatus=2 AND cierre=0 AND campo>0
 *   - Una sola salida por día; los grupos mezclan jugadores de distintas categorías.
 *
 * Todas las columnas opcionales se detectan en runtime porque el esquema
 * `golftour` no tiene varias columnas legacy (abreviatura, cierre, etc.).
 */
require_once 'config.php';

$torneoid = require_torneoid($conn);
$tid = esc($conn, $torneoid);

// Spanish month/day names
$conn->query("SET lc_time_names = 'es_ES'");

// ============= Tournament info =============
$torneoIdCol = api_first_existing_column($conn, 'torneo', ['torneo_id', 'torneoid', 'id_torneo']) ?: 'torneo_id';
$hasTipoSalida = api_column_exists($conn, 'torneo', 'tiposalida');
$clubIdCol = api_first_existing_column($conn, 'torneo', ['club_id', 'clubid']);

$sel = "a.nombre";
$sel .= $hasTipoSalida ? ", a.tiposalida" : ", 0 AS tiposalida";
if ($clubIdCol) {
    $sql = "SELECT $sel, b.nombre AS club
            FROM torneo a LEFT JOIN clubs b ON (a.`$clubIdCol` = b.id)
            WHERE a.`$torneoIdCol` = $tid";
} else {
    $sql = "SELECT $sel, '' AS club FROM torneo a WHERE a.`$torneoIdCol` = $tid";
}
$torneo = query_one($conn, $sql);
// ============= Optional columns on caljuego =============
$hasCierre   = api_column_exists($conn, 'caljuego', 'cierre');

$where  = "c.torneoid = $tid AND c.estatus = 2 AND c.campo > 0";
if ($hasCierre)   $where .= " AND c.cierre = 0";

// tiposalida: 1 = salida ÚNICA (se juntan las categorías en "Grupos de Juego");
//             0 = salida NORMAL (una salida por categoría).
$tipoSalida = (int)($torneo['tiposalida'] ?? 1);

// Columnas opcionales de categorias (esquema golftour varía)
$catIdCol   = api_first_existing_column($conn, 'categorias', ['categoria_id', 'categoriaid', 'id']) ?: 'categoria_id';
$catNameCol = api_first_existing_column($conn, 'categorias', ['categoria', 'nombre', 'categorian']) ?: 'categoria';
$catSisCol  = api_first_existing_column($conn, 'categorias', ['sistema']);
$catForCol  = api_first_existing_column($conn, 'categorias', ['formato']);
$catTeeCol  = api_first_existing_column($conn, 'categorias', ['salida', 'tee']);

if ($tipoSalida === 0) {
    // NORMAL: separar por categoría
    $selCat  = "c.categoriaid AS categoriaid, cat.`$catNameCol` AS categoria, cat.`$catNameCol` AS abreviatura";
    $selCat .= $catSisCol ? ", cat.`$catSisCol` AS sistema" : ", '' AS sistema";
    $selCat .= $catForCol ? ", cat.`$catForCol` AS formato" : ", '' AS formato";
    $selCat .= $catTeeCol ? ", cat.`$catTeeCol` AS tee" : ", '' AS tee";
    $buildSql = function ($w) use ($selCat, $catIdCol) {
        return "SELECT MIN(c.id) AS caljgoid, c.fecha,
                       DATE_FORMAT(c.fecha, '%W %e de %M %Y') AS fecha_formato,
                       $selCat,
                       MIN(ca.campo) AS campo_nombre
                FROM caljuego c
                LEFT JOIN campos ca ON (c.campo = ca.id)
                LEFT JOIN categorias cat ON (c.categoriaid = cat.`$catIdCol`)
                WHERE $w
                GROUP BY c.fecha, c.categoriaid, c.campo
                ORDER BY c.fecha ASC, CAST(c.categoriaid AS UNSIGNED) ASC, caljgoid ASC";
    };
} else {
    // ÚNICA: mezclar categorías
    $buildSql = function ($w) {
        return "SELECT MIN(c.id) AS caljgoid, c.fecha,
                       DATE_FORMAT(c.fecha, '%W %e de %M %Y') AS fecha_formato,
                       MIN(c.categoriaid) AS categoriaid,
                       'Grupos de Juego' AS categoria, 'Grupos de Juego' AS abreviatura,
                       '' AS sistema, '' AS formato, '' AS tee,
                       MIN(ca.campo) AS campo_nombre
                FROM caljuego c
                LEFT JOIN campos ca ON (c.campo = ca.id)
                WHERE $w
                GROUP BY c.fecha
                ORDER BY c.fecha ASC";
    };
}

$sql = $buildSql($where);
debug_log_query('salidas_master', $sql);
$rows = query_all($conn, $sql);

// Fallback: algunas giras no usan `estatus = 2` / `cierre = 0` en caljuego.
// Si el filtro estricto no devuelve días, se repite sin esas condiciones.
if (empty($rows)) {
    $sqlLoose = $buildSql("c.torneoid = $tid");
    debug_log_query('salidas_master_loose', $sqlLoose);
    $rows = query_all($conn, $sqlLoose);
}


// ============= Group by date =============
$dayMap = [];
foreach ($rows as $row) {
    $fecha = $row['fecha'];
    if (!isset($dayMap[$fecha])) {
        $dayMap[$fecha] = [
            'date'          => $fecha,
            'dateFormatted' => $row['fecha_formato'],
            'course'        => $row['campo_nombre'] ?? '',
            'categories'    => []
        ];
    }
    if (empty($dayMap[$fecha]['course']) && !empty($row['campo_nombre'])) {
        $dayMap[$fecha]['course'] = $row['campo_nombre'];
    }
    $caljgoid = (int)$row['caljgoid'];
    $groupCountRow = query_one($conn, "SELECT COUNT(*) AS total FROM salidagrupo WHERE caljuegoid = $caljgoid");
    $dayMap[$fecha]['categories'][] = [
        'caljgoid'     => $row['caljgoid'],
        'categoryId'   => $row['categoriaid'],
        'categoryName' => $row['categoria'],
        'shortName'    => $row['abreviatura'] ?: $row['categoria'],
        'system'       => $row['sistema'] ?? '',
        'format'       => $row['formato'] ?? '',
        'tee'          => $row['tee'] ?? '',
        'course'       => $row['campo_nombre'] ?? '',
        'groupCount'   => (int)($groupCountRow['total'] ?? 0)
    ];
}

// Orden definitivo: categorías por categoria_id de menor a mayor (salida normal)
if ($tipoSalida === 0) {
    foreach ($dayMap as &$d) {
        usort($d['categories'], function ($a, $b) {
            return ((int)$a['categoryId'] <=> (int)$b['categoryId'])
                ?: ((int)$a['caljgoid'] <=> (int)$b['caljgoid']);
        });
    }
    unset($d);
}

json_response([
    'tournament' => $torneo['nombre'] ?? '',
    'club'       => $torneo['club'] ?? '',
    'typeSalida' => $tipoSalida,
    'days'       => array_values($dayMap)
]);
