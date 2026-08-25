<?php
/**
 * Stats — Estadísticas Stroke Play por Jugador (hoyo por hoyo)
 *
 * Two modes:
 *   1) List mode  → GET /api/stats_jugador.php?torneoid=XXX
 *      Returns { players: [{ id, name, club, categoria }, ...] } for
 *      the player-search autocomplete.
 *
 *   2) Detail mode → GET /api/stats_jugador.php?torneoid=XXX&jugadorid=YYY
 *      Returns per-round scores hole-by-hole plus per-hole promedio and
 *      difficulty rango for the player's category.
 *      {
 *        player: { id, name, club, categoria, tee, course },
 *        holes:  [{ hole, par, rango }],
 *        rounds: [{ label, date, scores:[..18], out, in, total }],
 *        averages: [ per-hole averages ],
 *      }
 *
 * Resilient: on missing data returns empty arrays.
 */
require_once 'config.php';

$torneoid = require_torneoid($conn);
$tid = esc($conn, $torneoid);

/** Safe helpers (never abort). */
function s_all($conn, $sql) {
    $r = @$conn->query($sql);
    if (!$r) { error_log('stats_jugador s_all: ' . $conn->error); return []; }
    $rows = [];
    while ($row = $r->fetch_assoc()) { $rows[] = $row; }
    $r->free();
    return $rows;
}
function s_one($conn, $sql) {
    $r = @$conn->query($sql);
    if (!$r) { error_log('stats_jugador s_one: ' . $conn->error); return null; }
    $row = $r->fetch_assoc();
    $r->free();
    return $row;
}

$jugadorid = optional_param('jugadorid', null);

// ============= LIST MODE =============
if ($jugadorid === null) {
    $rows = s_all($conn, "SELECT j.id, CONCAT(j.nombre, ' ', j.apellido) AS name,
                                 j.club, cat.categoria
                            FROM jugadores j
                            LEFT JOIN categorias cat ON (j.categoriaid = cat.categoria_id)
                           WHERE j.torneoid = $tid
                             AND (j.estatus IS NULL OR j.estatus <> 'BAJA')
                             AND (j.numjugador IS NULL OR j.numjugador NOT LIKE '%-1')
                           ORDER BY j.apellido, j.nombre ASC");
    $players = array_map(function ($r) {
        return [
            'id'        => $r['id'],
            'name'      => $r['name'],
            'club'      => $r['club'] ?? '',
            'categoria' => $r['categoria'] ?? '',
        ];
    }, $rows);
    json_response(['players' => $players]);
}

// ============= DETAIL MODE =============
$jid = esc($conn, $jugadorid);

// Player + category
$player = s_one($conn, "SELECT j.id, CONCAT(j.nombre, ' ', j.apellido) AS name,
                              j.club, j.categoriaid, cat.categoria, cat.salida
                         FROM jugadores j
                         LEFT JOIN categorias cat ON (j.categoriaid = cat.categoria_id)
                        WHERE j.id = '$jid' AND j.torneoid = $tid LIMIT 1");
if (!$player) {
    json_response(['player' => null, 'holes' => [], 'rounds' => [], 'averages' => []]);
}

$cid = (int)$player['categoriaid'];
$salidaid = (int)$player['salida'];
$camp = s_one($conn, "SELECT campo FROM caljuego WHERE categoriaid = $cid AND campo > 0 LIMIT 1");
$campoid = $camp ? (int)$camp['campo'] : 0;
$course = $campoid ? s_one($conn, "SELECT campo FROM campos WHERE id = $campoid LIMIT 1") : null;
$teeRow = $salidaid ? s_one($conn, "SELECT tee, color FROM salidas WHERE id = $salidaid LIMIT 1") : null;

// Par + handicap-index rango per hole (from hoyosxsalida.ventaja)
$holeRows = ($campoid && $salidaid)
    ? s_all($conn, "SELECT numero, par, ventaja FROM hoyosxsalida
                     WHERE campoid = $campoid AND salidaid = $salidaid
                     ORDER BY numero ASC")
    : [];
$parByHole = [];
$rangoByHole = [];
foreach ($holeRows as $hr) {
    $parByHole[(int)$hr['numero']] = (int)$hr['par'];
    $rangoByHole[(int)$hr['numero']] = (int)$hr['ventaja'];
}

// Rounds: all v_sal_jug rows for the player
$scoreCols = [];
for ($h = 1; $h <= 18; $h++) { $scoreCols[] = "t.h{$h}"; }
$scoreColsSql = implode(', ', $scoreCols);

$roundRows = s_all($conn, "SELECT $scoreColsSql, v.fecha_juego, t.SO
                              FROM v_sal_jug v
                              JOIN tarjetas t ON (v.tarjetaid = t.id)
                             WHERE v.jugadorid = '$jid' AND v.categoriaid = $cid
                             ORDER BY v.fecha_juego ASC");

$rounds = [];
$holeSums = array_fill(1, 18, 0);
$holeCounts = array_fill(1, 18, 0);

foreach ($roundRows as $idx => $row) {
    $scores = [];
    $outT = 0; $inT = 0;
    for ($h = 1; $h <= 18; $h++) {
        $v = $row["h{$h}"] ?? null;
        $sv = ($v !== null && $v !== '' && (int)$v > 0) ? (int)$v : null;
        $scores[] = $sv;
        if ($sv !== null) {
            $holeSums[$h]   += $sv;
            $holeCounts[$h] += 1;
            if ($h <= 9) $outT += $sv; else $inT += $sv;
        }
    }
    $rounds[] = [
        'label'  => 'R ' . ($idx + 1),
        'date'   => $row['fecha_juego'] ?? null,
        'scores' => $scores,
        'out'    => $outT,
        'in'     => $inT,
        'total'  => (int)($row['SO'] ?? ($outT + $inT)),
    ];
}

$averages = [];
for ($h = 1; $h <= 18; $h++) {
    $averages[] = $holeCounts[$h] > 0
        ? round($holeSums[$h] / $holeCounts[$h], 1)
        : null;
}

$holes = [];
for ($h = 1; $h <= 18; $h++) {
    $holes[] = [
        'hole'  => $h,
        'par'   => $parByHole[$h] ?? null,
        'rango' => $rangoByHole[$h] ?? null,
    ];
}

json_response([
    'player' => [
        'id'        => $player['id'],
        'name'      => $player['name'],
        'club'      => $player['club'] ?? '',
        'categoria' => $player['categoria'] ?? '',
        'tee'       => $teeRow['tee'] ?? '',
        'teeColor'  => $teeRow['color'] ?? '',
        'course'    => $course['campo'] ?? '',
    ],
    'holes'    => $holes,
    'rounds'   => $rounds,
    'averages' => $averages,
]);