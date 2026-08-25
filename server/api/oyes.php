<?php
/**
 * O'Yes (Approach / Closest to Pin) Endpoint
 * GET /api/oyes.php?torneoid=XXX&modo=general|grupos|hoyo
 * Returns O'Yes winners by groups, hole, or general
 *
 * modo=general  → Top winners across all prizes (default)
 * modo=grupos   → Winners grouped by prize group
 * modo=hoyo     → Winners grouped by hole
 */
require_once 'config.php';

$torneoid = require_torneoid($conn);
$modo     = optional_param('modo', 'general');

$tid = esc($conn, $torneoid);

// ============= Pre-update: mark best distances =============
// Reset all
$conn->query("UPDATE premiosjug SET orden = 0 WHERE torneoid = $tid");

// Set orden=1 for unique winners based on mode
if ($modo === 'hoyo') {
    // Per-hole unique winners
    $conn->query("UPDATE premiosjug a
                  JOIN v_oyesunicasxoyo b ON (a.jugadorid = b.jugadorid AND a.torneoid = b.torneoid AND a.premio = b.premio)
                  SET a.orden = 1
                  WHERE a.torneoid = $tid");
} else {
    // Global unique winners
    $conn->query("UPDATE premiosjug a
                  JOIN v_oyesunicas b ON (a.jugadorid = b.jugadorid AND a.torneoid = b.torneoid AND a.premio = b.premio)
                  SET a.orden = 1
                  WHERE a.torneoid = $tid");
}

// ============= Get prize groups =============
$sql = "SELECT DISTINCT premio, descripcion, hoyo
        FROM premiosjug
        WHERE torneoid = $tid
        ORDER BY premio ASC";
$prizeRows = query_all($conn, $sql);

// ============= Get number of prizes per group from tournament config =============
$sql = "SELECT oyesnumprem FROM torneo WHERE torneo_id = $tid";
$torneoInfo = query_one($conn, $sql);
$numPrem = (int)($torneoInfo['oyesnumprem'] ?? 3);

// ============= Get winners per prize group =============
$results = [];
foreach ($prizeRows as $prize) {
    $premioId = esc($conn, $prize['premio']);

    $sql = "SELECT a.jugadorid,
                   CONCAT(j.nombre, ' ', j.apellido) as jugador,
                   a.distancia, a.hoyo, a.descripcion,
                   c.logo, c.nombre as club,
                   f_ultact(a.torneoid, a.premio) as lastUpdated
            FROM premiosjug a
            JOIN v_oyes b ON (a.jugadorid = b.jugadorid AND a.torneoid = b.torneoid AND a.premio = b.premio)
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
    'mode'    => $modo,
    'prizes'  => $results
]);
