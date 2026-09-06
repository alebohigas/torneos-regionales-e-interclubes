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

$sql = "SELECT MIN(c.id) AS caljgoid, c.fecha,
               DATE_FORMAT(c.fecha, '%W %e de %M %Y') AS fecha_formato,
               MIN(c.categoriaid) AS categoriaid,
               'Grupos de Juego' AS categoria, 'Grupos de Juego' AS abreviatura,
               '' AS sistema, '' AS formato, '' AS tee,
               (SELECT ca.campo FROM campos ca WHERE ca.id = MIN(c.campo)) AS campo_nombre
        FROM caljuego c
        WHERE $where
        GROUP BY c.fecha
        ORDER BY c.fecha ASC";

debug_log_query('salidas_master', $sql);
$rows = query_all($conn, $sql);

// Fallback: algunas giras no usan `estatus = 2` / `cierre = 0` en caljuego.
// Si el filtro estricto no devuelve días, se repite sin esas condiciones.
if (empty($rows)) {
    $sqlLoose = "SELECT MIN(c.id) AS caljgoid, c.fecha,
                        DATE_FORMAT(c.fecha, '%W %e de %M %Y') AS fecha_formato,
                        MIN(c.categoriaid) AS categoriaid,
                        'Grupos de Juego' AS categoria, 'Grupos de Juego' AS abreviatura,
                        '' AS sistema, '' AS formato, '' AS tee,
                        (SELECT ca.campo FROM campos ca WHERE ca.id = MIN(c.campo)) AS campo_nombre
                 FROM caljuego c
                 WHERE c.torneoid = $tid
                 GROUP BY c.fecha
                 ORDER BY c.fecha ASC";
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
