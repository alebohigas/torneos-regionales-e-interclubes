<?php
/**
 * Salidas Master Endpoint (golftour / gira schema)
 * GET /api/salidas.php?giraid=XXX[&torneoid=NN]
 *
 * Réplica del legacy `salidas.php`:
 *   - Días de juego: caljuego WHERE torneoid=? AND estatus=2 AND cierre=0 AND campo>0
 *   - Categorías por día: JOIN categorias (estatus>0), etiqueta = categorias.categoria
 *   - Si torneo.tiposalida = 1 → una sola entrada "Salida Unica" por día
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
$tipoSalida = (int)($torneo['tiposalida'] ?? 0);

// ============= Optional columns on caljuego / categorias =============
$hasCierre   = api_column_exists($conn, 'caljuego', 'cierre');
$hasCatEstat = api_column_exists($conn, 'categorias', 'estatus');
$hasAbrev    = api_column_exists($conn, 'categorias', 'abreviatura');
$hasSistema  = api_column_exists($conn, 'categorias', 'sistema');
$hasFormato  = api_column_exists($conn, 'categorias', 'formato');
$hasSalidaFk = api_column_exists($conn, 'categorias', 'salida');

$where  = "c.torneoid = $tid AND c.estatus = 2 AND c.campo > 0";
if ($hasCierre)   $where .= " AND c.cierre = 0";
if ($hasCatEstat) $where .= " AND cat.estatus > 0";

if ($tipoSalida === 1) {
    // Salida única: un solo bloque por fecha
    $sql = "SELECT MIN(c.id) AS caljgoid, c.fecha,
                   DATE_FORMAT(c.fecha, '%W %e de %M %Y') AS fecha_formato,
                   MIN(c.categoriaid) AS categoriaid,
                   'Salida Unica' AS categoria, 'Salida Unica' AS abreviatura,
                   '' AS sistema, '' AS formato, '' AS tee,
                   (SELECT ca.campo FROM campos ca WHERE ca.id = MIN(c.campo)) AS campo_nombre
            FROM caljuego c
            WHERE c.torneoid = $tid AND c.estatus = 2 AND c.campo > 0"
            . ($hasCierre ? " AND c.cierre = 0" : "") .
           " GROUP BY c.fecha
            ORDER BY c.fecha ASC";
} else {
    $cols = "c.id AS caljgoid, c.fecha,
             DATE_FORMAT(c.fecha, '%W %e de %M %Y') AS fecha_formato,
             c.campo, ca.campo AS campo_nombre,
             c.categoriaid, cat.categoria";
    $cols .= $hasAbrev   ? ", cat.abreviatura" : ", cat.categoria AS abreviatura";
    $cols .= $hasSistema ? ", cat.sistema"     : ", '' AS sistema";
    $cols .= $hasFormato ? ", cat.formato"     : ", '' AS formato";
    $cols .= $hasSalidaFk ? ", s.tee" : ", '' AS tee";

    $join = $hasSalidaFk ? " LEFT JOIN salidas s ON (cat.salida = s.id)" : "";

    $sql = "SELECT $cols
            FROM caljuego c
            JOIN categorias cat ON (c.categoriaid = cat.categoria_id)
            LEFT JOIN campos ca ON (c.campo = ca.id)$join
            WHERE $where
            ORDER BY c.fecha ASC, cat.categoria_id ASC";
}

debug_log_query('salidas_master', $sql);
$rows = query_all($conn, $sql);

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
    $dayMap[$fecha]['categories'][] = [
        'caljgoid'     => $row['caljgoid'],
        'categoryId'   => $row['categoriaid'],
        'categoryName' => $row['categoria'],
        'shortName'    => $row['abreviatura'] ?: $row['categoria'],
        'system'       => $row['sistema'] ?? '',
        'format'       => $row['formato'] ?? '',
        'tee'          => $row['tee'] ?? ''
    ];
}

json_response([
    'tournament' => $torneo['nombre'] ?? '',
    'club'       => $torneo['club'] ?? '',
    'days'       => array_values($dayMap)
]);
