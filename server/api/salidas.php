<?php
/**
 * Salidas Master Endpoint
 * GET /api/salidas.php?torneoid=XXX
 * Returns list of play days with categories (for tee time navigation)
 */
require_once 'config.php';

$torneoid = require_torneoid($conn);
$tid = esc($conn, $torneoid);

// Set Spanish locale for date formatting
$conn->query("SET lc_time_names = 'es_ES'");

// Tournament info
$sql = "SELECT a.nombre, b.nombre as club
        FROM torneo a JOIN clubs b ON (a.club_id = b.id)
        WHERE a.torneo_id = $tid";
debug_log_query('salidas_master_torneo', $sql);
$torneo = query_one($conn, $sql);

// Get play days with their calendar game IDs
// Only include categories that have at least one salidagrupo (generated tee times)
$sql = "SELECT c.id as caljgoid, c.fecha,
               DATE_FORMAT(c.fecha, '%W %e de %M %Y') as fecha_formato,
               c.campo, ca.campo as campo_nombre,
               c.categoriaid, cat.categoria, cat.abreviatura,
               cat.sistema, cat.formato,
               s.tee
        FROM caljuego c
        JOIN categorias cat ON (c.categoriaid = cat.categoria_id)
        LEFT JOIN campos ca ON (c.campo = ca.id)
        LEFT JOIN salidas s ON (cat.salida = s.id)
        WHERE c.torneoid = $tid AND c.campo > 0 AND c.estatus = 2
          AND EXISTS (
              SELECT 1 FROM salidagrupo sg
              WHERE sg.caljuegoid = c.id AND sg.categoriaid = c.categoriaid
          )
        ORDER BY c.fecha ASC, cat.categoria_id ASC";
debug_log_query('salidas_master_dias_categorias', $sql);

$rows = query_all($conn, $sql);

// Group by date
$dayMap = [];
foreach ($rows as $row) {
    $fecha = $row['fecha'];
    if (!isset($dayMap[$fecha])) {
        $dayMap[$fecha] = [
            'date'          => $fecha,
            'dateFormatted' => $row['fecha_formato'],
            'course'        => $row['campo_nombre'],
            'categories'    => []
        ];
    }
    $dayMap[$fecha]['categories'][] = [
        'caljgoid'     => $row['caljgoid'],
        'categoryId'   => $row['categoriaid'],
        'categoryName' => $row['categoria'],
        'shortName'    => $row['abreviatura'],
        'system'       => $row['sistema'],
        'format'       => $row['formato'],
        'tee'          => $row['tee']
    ];
}

json_response([
    'tournament' => $torneo['nombre'] ?? '',
    'club'       => $torneo['club'] ?? '',
    'days'       => array_values($dayMap)
]);
