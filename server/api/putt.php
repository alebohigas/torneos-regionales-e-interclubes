<?php
/**
 * Putt Endpoint
 * GET /api/putt.php?torneoid=XXX
 * Returns putt competition winners
 * Uses putt/puttjug tables and v_putt/v_puttunico views
 */
require_once 'config.php';

$torneoid = require_torneoid($conn);
$tid = esc($conn, $torneoid);

// ============= Pre-update: mark best putts =============
$conn->query("UPDATE puttjug SET orden = 0 WHERE torneoid = $tid");
$conn->query("UPDATE puttjug a
              JOIN v_puttunico b ON (a.jugadorid = b.jugadorid AND a.torneoid = b.torneoid AND a.premio = b.premio)
              SET a.orden = 1
              WHERE a.torneoid = $tid");

// ============= Get prize groups =============
$sql = "SELECT DISTINCT premio, descripcion, hoyo
        FROM puttjug
        WHERE torneoid = $tid
        ORDER BY premio ASC";
$prizeRows = query_all($conn, $sql);

// ============= Get number of prizes =============
$sql = "SELECT oyesnumprem FROM torneo WHERE torneo_id = $tid";
$torneoInfo = query_one($conn, $sql);
$numPrem = (int)($torneoInfo['oyesnumprem'] ?? 3);

// ============= Get winners per prize =============
$results = [];
foreach ($prizeRows as $prize) {
    $premioId = esc($conn, $prize['premio']);

    $sql = "SELECT a.jugadorid,
                   CONCAT(j.nombre, ' ', j.apellido) as jugador,
                   a.distancia, a.hoyo, a.descripcion,
                   c.logo, c.nombre as club,
                   f_ultfechaputt(a.torneoid) as lastUpdated
            FROM puttjug a
            JOIN v_putt b ON (a.jugadorid = b.jugadorid AND a.torneoid = b.torneoid AND a.premio = b.premio)
            JOIN jugadores j ON (a.jugadorid = j.id)
            JOIN clubs c ON (j.clubid = c.id)
            WHERE a.torneoid = $tid AND a.premio = $premioId AND a.orden = 1
            ORDER BY a.distancia ASC
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
        'lastUpdated' => $winners[0]['lastUpdated'] ?? null,
        'players'     => $players
    ];
}

json_response([
    'prizes' => $results
]);
