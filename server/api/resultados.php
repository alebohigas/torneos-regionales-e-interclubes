<?php
/**
 * Resultados Master Endpoint
 * GET /api/resultados.php?torneoid=XXX
 * Returns categories list with their scoring systems for results navigation
 * Separates Stroke Play from Match Play (Eliminación Directa)
 */
require_once 'config.php';

$torneoid = require_torneoid($conn);
$tid = esc($conn, $torneoid);

/**
 * Columnas opcionales de `categorias`: el esquema `golftour` no tiene
 * abreviatura/estilo/catrel/hcpIdx*/hoyosacorte. Se detectan en runtime para
 * no romper el endpoint con "Unknown column".
 */
$optional = ['abreviatura', 'estilo', 'gross', 'hcpIdxMin', 'hcpIdxMax',
             'porcentaje', 'hoyosajugar', 'hoyosacorte', 'salida', 'catrel'];
$sel = ['a.categoria_id', 'a.torneo_id', 'a.categoria', 'a.sistema', 'a.formato'];
$grp = ['a.categoria_id', 'a.torneo_id', 'a.categoria', 'a.sistema', 'a.formato'];
foreach ($optional as $col) {
    if (api_column_exists($conn, 'categorias', $col)) {
        $sel[] = "a.`$col`";
        $grp[] = "a.`$col`";
    } else {
        $sel[] = "NULL AS `$col`";
    }
}

$sql = "SELECT " . implode(', ', $sel) . ",
               COUNT(b.id) as playerCount
        FROM categorias a
        JOIN jugadores b ON (a.categoria_id = b.categoriaid)
        WHERE a.estatus > 0 AND a.torneo_id = $tid
        GROUP BY " . implode(', ', $grp) . "
        ORDER BY a.categoria_id ASC";

$rows = query_all($conn, $sql);
debug_log_query('Categories with results', $sql);


// Separate by system type
$strokePlay = [];
$matchPlay = [];

foreach ($rows as $row) {
    $cat = [
        'categoryId'  => $row['categoria_id'],
        'name'        => $row['categoria'],
        'shortName'   => $row['abreviatura'],
        'system'      => $row['sistema'],
        'format'      => $row['formato'],
        'style'       => $row['estilo'],
        'gross'       => (int)$row['gross'],
        'playerCount' => (int)$row['playerCount'],
        'relatedCat'  => $row['catrel'],
        /** Detección de torneo de parejas — la categoría es de parejas cuando formato='PAREJAS'. */
        'isParejas'   => (strtoupper($row['formato']) === 'PAREJAS')
    ];

    if (strtoupper($row['sistema']) === 'MATCH PLAY') {
        $matchPlay[] = $cat;
    } else {
        $strokePlay[] = $cat;
    }
}

json_response([
    'strokePlay' => $strokePlay,
    'matchPlay'  => $matchPlay
]);
