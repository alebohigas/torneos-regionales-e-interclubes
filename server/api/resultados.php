<?php
/**
 * Resultados Master Endpoint
 * GET /api/resultados.php?torneoid=XXX
 * Returns categories list with their scoring systems for results navigation
 * Separates Stroke Play from Match Play (Eliminación Directa)
 */
require_once 'config.php';

$torneoid = require_param('torneoid');
$tid = esc($conn, $torneoid);

// Get categories with active results
$sql = "SELECT a.categoria_id, a.torneo_id, a.categoria, a.abreviatura,
               a.sistema, a.formato, a.estilo, a.gross,
               a.hcpIdxMin, a.hcpIdxMax, a.porcentaje,
               a.hoyosajugar, a.hoyosacorte, a.salida, a.catrel,
               COUNT(b.id) as playerCount
        FROM categorias a
        JOIN jugadores b ON (a.categoria_id = b.categoriaid)
        WHERE a.estatus > 0 AND a.torneo_id = $tid
        GROUP BY a.categoria_id, a.torneo_id, a.categoria, a.abreviatura,
                 a.sistema, a.formato, a.estilo, a.gross,
                 a.hcpIdxMin, a.hcpIdxMax, a.porcentaje,
                 a.hoyosajugar, a.hoyosacorte, a.salida, a.catrel
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
