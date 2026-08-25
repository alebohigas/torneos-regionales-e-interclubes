<?php
/**
 * Stats — Estadísticas Stroke Play por Categoría (hoyo por hoyo)
 * GET /api/stats_categoria.php?torneoid=XXX&categoriaid=YYY
 *
 * For every hole (1..N), aggregates all captured scorecards belonging
 * to the category and returns:
 *   par, average, rank (1 = hardest), and counts of
 *   aguilas / birdies / pares / bogeys / doubles / triples-or-worse.
 * Includes OUT / IN / TOTAL subtotals.
 *
 * Response shape:
 *   {
 *     categoryName, tee, course, rounds, updatedAt,
 *     holes:  [{ hole, par, promedio, rank, aguilas, birdies, pares, bogeys, dobles, triples }],
 *     subtotals: { out: {...}, in: {...}, total: {...} }
 *   }
 *
 * Resilient: returns null-heavy structure on missing tables.
 */
require_once 'config.php';

$torneoid    = require_torneoid($conn);
$categoriaid = require_param('categoriaid');
$tid = esc($conn, $torneoid);
$cid = esc($conn, $categoriaid);

/** Safe query_one — returns null on failure. */
function safe_one($conn, $sql) {
    $r = @$conn->query($sql);
    if (!$r) { error_log('stats_categoria safe_one: ' . $conn->error); return null; }
    $row = $r->fetch_assoc();
    $r->free();
    return $row;
}
/** Safe query_all — returns [] on failure. */
function safe_all($conn, $sql) {
    $r = @$conn->query($sql);
    if (!$r) { error_log('stats_categoria safe_all: ' . $conn->error); return []; }
    $rows = [];
    while ($row = $r->fetch_assoc()) { $rows[] = $row; }
    $r->free();
    return $rows;
}

// ============= Category info + course/tee =============
$cat = safe_one($conn, "SELECT categoria_id, categoria, salida, hoyosajugar
                          FROM categorias
                         WHERE categoria_id = $cid AND torneo_id = $tid LIMIT 1");
if (!$cat) {
    json_response([
        'categoryName' => '',
        'tee'          => '',
        'course'       => '',
        'rounds'       => 0,
        'updatedAt'    => null,
        'holes'        => [],
        'subtotals'    => ['out' => null, 'in' => null, 'total' => null],
    ]);
}

$salidaid = (int)$cat['salida'];
$holesToPlay = max(9, min(18, (int)($cat['hoyosajugar'] ?? 18)));

// Get campoid via caljuego (same pattern as players.php)
$camp = safe_one($conn, "SELECT campo FROM caljuego WHERE categoriaid = $cid AND campo > 0 LIMIT 1");
$campoid = $camp ? (int)$camp['campo'] : 0;

// Tee name + course name
$teeRow = safe_one($conn, "SELECT tee, color FROM salidas WHERE id = $salidaid LIMIT 1");
$courseRow = $campoid ? safe_one($conn, "SELECT campo FROM campos WHERE id = $campoid LIMIT 1") : null;

// Par per hole
$parRows = ($campoid && $salidaid)
    ? safe_all($conn, "SELECT numero, par FROM hoyosxsalida
                        WHERE campoid = $campoid AND salidaid = $salidaid
                        ORDER BY numero ASC")
    : [];
$parByHole = [];
foreach ($parRows as $pr) { $parByHole[(int)$pr['numero']] = (int)$pr['par']; }

// ============= Aggregated hole-by-hole scores =============
// Pull raw hole scores from tarjetas joined via v_sal_jug (which ties
// player+category+round to tarjetaid). Use SO fields (h1..h18) = raw scores.
$scoreCols = [];
for ($h = 1; $h <= 18; $h++) { $scoreCols[] = "t.h{$h}"; }
$scoreColsSql = implode(', ', $scoreCols);

$rounds = 0;
$updatedAt = null;
$scoresByHole = []; // hole => array of raw scores
for ($h = 1; $h <= 18; $h++) { $scoresByHole[$h] = []; }

$sql = "SELECT $scoreColsSql, t.fecha_cap
          FROM v_sal_jug v
          JOIN tarjetas t ON (v.tarjetaid = t.id)
         WHERE v.categoriaid = $cid";
$rows = safe_all($conn, $sql);
foreach ($rows as $row) {
    $rounds++;
    if (!empty($row['fecha_cap']) && (!$updatedAt || $row['fecha_cap'] > $updatedAt)) {
        $updatedAt = $row['fecha_cap'];
    }
    for ($h = 1; $h <= 18; $h++) {
        $v = $row["h{$h}"] ?? null;
        if ($v !== null && $v !== '' && (int)$v > 0) {
            $scoresByHole[$h][] = (int)$v;
        }
    }
}

// Build per-hole stats
$holes = [];
$rankData = []; // hole => avg-par diff (for ranking)
for ($h = 1; $h <= 18; $h++) {
    $par = $parByHole[$h] ?? null;
    $scores = $scoresByHole[$h];
    $n = count($scores);
    $sum = array_sum($scores);
    $prom = $n > 0 ? round($sum / $n, 2) : null;

    $aguilas = 0; $birdies = 0; $pares = 0; $bogeys = 0; $dobles = 0; $triples = 0;
    if ($par !== null) {
        foreach ($scores as $s) {
            $diff = $s - $par;
            if ($diff <= -2)      $aguilas++;
            else if ($diff === -1) $birdies++;
            else if ($diff === 0)  $pares++;
            else if ($diff === 1)  $bogeys++;
            else if ($diff === 2)  $dobles++;
            else if ($diff >= 3)   $triples++;
        }
    }

    $holes[] = [
        'hole'     => $h,
        'par'      => $par,
        'promedio' => $prom,
        'rank'     => null, // filled below
        'aguilas'  => $aguilas,
        'birdies'  => $birdies,
        'pares'    => $pares,
        'bogeys'   => $bogeys,
        'dobles'   => $dobles,
        'triples'  => $triples,
    ];
    if ($prom !== null && $par !== null) {
        $rankData[$h] = $prom - $par;
    }
}

// Rank holes by difficulty (higher avg-par diff = harder = rank 1)
arsort($rankData);
$rankPos = 1;
foreach ($rankData as $h => $diff) {
    foreach ($holes as &$row) {
        if ($row['hole'] === $h) { $row['rank'] = $rankPos; break; }
    }
    unset($row);
    $rankPos++;
}

/** Compute subtotal row for a range of holes (1..9 or 10..18). */
function subtotal_row($holes, $from, $to) {
    $par = 0; $prom = 0; $counted = 0;
    $a = $b = $p = $bo = $d = $t = 0;
    foreach ($holes as $h) {
        if ($h['hole'] < $from || $h['hole'] > $to) continue;
        if ($h['par'] !== null) $par += (int)$h['par'];
        if ($h['promedio'] !== null) { $prom += (float)$h['promedio']; $counted++; }
        $a  += (int)$h['aguilas'];
        $b  += (int)$h['birdies'];
        $p  += (int)$h['pares'];
        $bo += (int)$h['bogeys'];
        $d  += (int)$h['dobles'];
        $t  += (int)$h['triples'];
    }
    return [
        'par'      => $par,
        'promedio' => $counted > 0 ? round($prom, 2) : null,
        'aguilas'  => $a,
        'birdies'  => $b,
        'pares'    => $p,
        'bogeys'   => $bo,
        'dobles'   => $d,
        'triples'  => $t,
    ];
}

$out = subtotal_row($holes, 1, 9);
$in  = subtotal_row($holes, 10, 18);
$totalRow = [
    'par'      => $out['par'] + $in['par'],
    'promedio' => ($out['promedio'] !== null && $in['promedio'] !== null)
                    ? round($out['promedio'] + $in['promedio'], 2) : null,
    'aguilas'  => $out['aguilas'] + $in['aguilas'],
    'birdies'  => $out['birdies'] + $in['birdies'],
    'pares'    => $out['pares'] + $in['pares'],
    'bogeys'   => $out['bogeys'] + $in['bogeys'],
    'dobles'   => $out['dobles'] + $in['dobles'],
    'triples'  => $out['triples'] + $in['triples'],
];

json_response([
    'categoryName' => $cat['categoria'] ?? '',
    'tee'          => $teeRow['tee'] ?? '',
    'teeColor'     => $teeRow['color'] ?? '',
    'course'       => $courseRow['campo'] ?? '',
    'rounds'       => $rounds,
    'updatedAt'    => $updatedAt,
    'holesToPlay'  => $holesToPlay,
    'holes'        => $holes,
    'subtotals'    => ['out' => $out, 'in' => $in, 'total' => $totalRow],
]);