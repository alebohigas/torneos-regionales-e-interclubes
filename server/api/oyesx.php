<?php
/**
 * O'Yes-X (Extended O'Yes / Driver / Approach variants) Endpoint
 * GET /api/oyesx.php?torneoid=XXX&tipo=driver|precision|approach
 * Returns O'Yes-X winners from oyesx/oyesxjug tables
 *
 * tipo parameter determines the competition type filter
 */
require_once 'config.php';

$torneoid = require_torneoid($conn);
$tipo     = optional_param('tipo', '');

$tid = esc($conn, $torneoid);

// ============= Pre-update: mark best distances =============
$conn->query("UPDATE oyesxjug SET orden = 0 WHERE torneoid = $tid");
$conn->query("UPDATE oyesxjug a
              JOIN v_oyesx b ON (a.jugadorid = b.jugadorid AND a.torneoid = b.torneoid AND a.premio = b.premio)
              SET a.orden = 1
              WHERE a.torneoid = $tid");

// ============= Get prize groups =============
$sql = "SELECT DISTINCT premio, descripcion, hoyo
        FROM oyesxjug
        WHERE torneoid = $tid";

if ($tipo !== '') {
    $tipoEsc = esc($conn, $tipo);
    $sql .= " AND LOWER(descripcion) LIKE '%$tipoEsc%'";
}

$sql .= " ORDER BY premio ASC";
$prizeRows = query_all($conn, $sql);

// ============= Get number of prizes =============
$sql = "SELECT oyesnumprem FROM torneo WHERE torneo_id = $tid";
$torneoInfo = query_one($conn, $sql);
$numPrem = (int)($torneoInfo['oyesnumprem'] ?? 3);

// ============= Get winners per prize =============
$results = [];
foreach ($prizeRows as $prize) {
    $premioId = esc($conn, $prize['premio']);

    // Determine sort order based on description
    $desc = strtolower($prize['descripcion']);
    $sortOrder = (strpos($desc, 'distancia') !== false || strpos($desc, 'driver') !== false)
        ? 'DESC' : 'ASC';

    $sql = "SELECT a.jugadorid,
                   CONCAT(j.nombre, ' ', j.apellido) as jugador,
                   a.distancia, a.hoyo, a.descripcion,
                   c.logo, c.nombre as club,
                   f_ultfechaoyesx(a.descripcion, a.torneoid) as lastUpdated
            FROM oyesxjug a
            JOIN v_oyesx b ON (a.jugadorid = b.jugadorid AND a.torneoid = b.torneoid AND a.premio = b.premio)
            JOIN jugadores j ON (a.jugadorid = j.id)
            JOIN clubs c ON (j.clubid = c.id)
            WHERE a.torneoid = $tid AND a.premio = $premioId AND a.orden = 1
            ORDER BY a.distancia $sortOrder
            LIMIT $numPrem";

    $winners = query_all($conn, $sql);

    $players = [];
    $pos = 0;
    foreach ($winners as $w) {
        $pos++;
        $players[] = [
            'position'  => $pos,
            'playerId'  => $w['jugadorid'],
            'name'      => $w['jugador'],
            'distance'  => (float)$w['distancia'],
            'hole'      => (int)$w['hoyo'],
            'club'      => $w['club'],
            'clubLogo'  => $w['logo'] ? $LOGOS_BASE_URL . $w['logo'] : '',
        ];
    }

    $results[] = [
        'prizeId'     => $prize['premio'],
        'description' => $prize['descripcion'],
        'hole'        => (int)$prize['hoyo'],
        'sortOrder'   => $sortOrder,
        'lastUpdated' => $winners[0]['lastUpdated'] ?? null,
        'players'     => $players
    ];
}

json_response([
    'type'   => $tipo,
    'prizes' => $results
]);
