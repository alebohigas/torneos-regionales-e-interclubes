<?php
/**
 * Live Scoring Leaderboard Endpoint
 * GET /api/live_scoring.php?catid=XXX&torneoid=XXX&tipo=stroke|stableford&gross=0|1
 * 
 * Returns real-time leaderboard using legacy views:
 *   Stableford: v_sumsa, v_sumsarr, v_difpar_ulttarjeta_stb
 *   Stableford Neto: v_sumsa, v_sumsarr, v_difpar_ulttarjeta_neto
 *   Stroke:     v_difpar_jugador, v_difpar_ulttarjeta
 */
require_once 'config.php';

$catid    = require_param('catid');
$torneoid = require_torneoid($conn);
$tipo     = optional_param('tipo', 'stroke');
$gross    = optional_param('gross', '0');

$cid = esc($conn, $catid);
$tid = esc($conn, $torneoid);

// ── Category info ──
$sql = "SELECT categoria_id, categoria, abreviatura, sistema, formato, salida, porcentaje
        FROM categorias WHERE categoria_id = $cid";
$catInfo = query_one($conn, $sql);
if (!$catInfo) { json_error('Category not found', 404); }

// ── Total scheduled rounds for this category (from caljuego) ──
// Used to determine when a player has all their cards closed (statlsc=1)
$sqlRounds = "SELECT COUNT(*) AS total FROM caljuego
              WHERE torneoid = $tid AND categoriaid = $cid";
$roundsRow = query_one($conn, $sqlRounds);
$totalRounds = (int)($roundsRow['total'] ?? 0);

// ── Current live-round closure status ──
// Live closes a category only when EVERY eligible NORMAL player has a closed
// tarjeta (`statlsc = 1`) for the latest played/created round date. This is
// intentionally round-based, not whole-tournament based, so completed rounds
// move out of Live and become visible in Resultados.
$eligibleWhere = "j.categoriaid = $cid AND j.torneoid = $tid AND j.estatus = 'NORMAL'";
if ($gross != '1') { $eligibleWhere .= " AND j.campgross = 0"; }
$expectedRow = query_one($conn, "SELECT COUNT(*) AS total FROM jugadores j WHERE $eligibleWhere");
$expectedPlayers = (int)($expectedRow['total'] ?? 0);
$currentRoundRow = query_one($conn, "SELECT DATE(MAX(t.fecha_juego)) AS fecha
                                     FROM tarjetas t
                                     JOIN jugadores j ON (j.id = t.jugadorid)
                                     WHERE $eligibleWhere
                                       AND t.torneoid = $tid");
$currentRoundDate = $currentRoundRow['fecha'] ?? null;
$currentClosedPlayers = 0;
if ($currentRoundDate) {
    $fecEsc = esc($conn, $currentRoundDate);
    $closedRoundRow = query_one($conn, "SELECT COUNT(DISTINCT t.jugadorid) AS total
                                        FROM tarjetas t
                                        JOIN jugadores j ON (j.id = t.jugadorid)
                                        WHERE $eligibleWhere
                                          AND t.torneoid = $tid
                                          AND DATE(t.fecha_juego) = '$fecEsc'
                                          AND t.statlsc = 1");
    $currentClosedPlayers = (int)($closedRoundRow['total'] ?? 0);
}
$categoryClosed = ($expectedPlayers > 0 && $currentRoundDate && $currentClosedPlayers >= $expectedPlayers) ? 1 : 0;

// ── Course info (par, rating, slope) ──
$salidaid = esc($conn, $catInfo['salida']);
$sql = "SELECT b.campoid, rating, slope, tee, parcampo
        FROM caljuego a
        JOIN campo_tee b ON (a.campo = b.campoid AND categoriaid = $cid AND salidaid = $salidaid)
        JOIN salidas s ON (b.salidaid = s.id)
        LIMIT 1";
$courseInfo = query_one($conn, $sql);
$parcampo = (int)($courseInfo['parcampo'] ?? 72);

// ── Determine scoring system ──
$sistema = strtoupper($catInfo['sistema'] ?? '');
$isStableford = ($sistema === 'STABLEFORD' || $tipo === 'stableford');

// ── Build leaderboard query based on scoring system ──
if ($isStableford) {
    /**
     * Stableford query — mirrors legacy livescoring_stableford.php
     * Joins: jugadores → clubs, v_sumsa (total SA), v_sumsarr (previous round SA + progress),
     *        v_difpar_ulttarjeta_stb (current round stableford detail)
     * For NETO uses v_difpar_ulttarjeta_neto instead
     */
    $ultTarView = ($gross == '1') ? 'v_difpar_ulttarjeta_stb' : 'v_difpar_ulttarjeta_neto';
    $orderDir   = 'DESC';  // Stableford: higher = better

    $sql = "SELECT a.id AS jugadorid, numjugador,
                   CONCAT(a.nombre, ' ', a.apellido) AS jugador,
                   IF(c.avance IS NULL, 0, c.avance) AS avance,
                   IF(c.sumsa IS NULL, 0, c.sumsa) AS sumsault,
                   IF(b.sumsa IS NULL, 0, b.sumsa) AS sumsa,
                   a.estatus AS estatjug,
                   club,
                   cl.logo AS juglogoclub,
                   v.sa,
                   (SELECT COUNT(*) FROM tarjetas t
                      WHERE t.jugadorid = a.id AND t.torneoid = $tid AND t.statlsc = 1) AS cardsclosed
            FROM jugadores AS a
            JOIN clubs AS cl ON (a.clubid = cl.id)
            LEFT JOIN v_sumsa AS b ON (a.id = b.jugadorid)
            LEFT JOIN v_sumsarr AS c ON (a.id = c.jugadorid)
            LEFT JOIN $ultTarView AS v ON (a.id = v.jugadorid)
            WHERE a.estatus = 'NORMAL' AND a.categoriaid = $cid
            ORDER BY IF(b.sumsa IS NULL, 0, b.sumsa) $orderDir";

} else {
    /**
     * Stroke Play query
     * Gross: v_difpar_jugador + v_difpar_ulttarjeta
     * Neto:  v_difpar_jugador_neto + v_difpar_ulttarjeta_neto
     * ORDER: players with no progress (avance=0) go to bottom
     */
    $difView    = ($gross == '1') ? 'v_difpar_jugador'      : 'v_difpar_jugador_neto';
    $ultTarView = ($gross == '1') ? 'v_difpar_ulttarjeta'   : 'v_difpar_ulttarjeta_neto';

    $sql = "SELECT b.id AS jugadorid, b.nombre, apellido,
                   b.estatus AS estatjug,
                   club,
                   b.indexjgo,
                   cl.logo AS juglogoclub,
                   dif.difpar,
                   v.difpar_ulttar AS difparulttar,
                   v.avance AS avance_ulttar,
                   (SELECT COUNT(*) FROM tarjetas t
                      WHERE t.jugadorid = b.id AND t.torneoid = $tid AND t.statlsc = 1) AS cardsclosed
            FROM jugadores AS b
            JOIN clubs AS cl ON (b.clubid = cl.id)
            LEFT JOIN $difView AS dif ON (dif.jugadorid = b.id)
            LEFT JOIN $ultTarView AS v ON (b.id = v.jugadorid)
            WHERE b.ESTATUS = 'NORMAL' AND b.categoriaid = $cid
            ORDER BY if(IFNULL(v.avance,0)=0,999,IFNULL(dif.difpar,999)) ASC, IFNULL(dif.difpar,999) ASC, IFNULL(v.avance,0) DESC";
}

$rows = query_all($conn, $sql);

/**
 * Pre-fetch previous round dates per player.
 * For each player in this category, list the dates (YYYY-MM-DD) of all
 * scorecards with statlsc=1 (closed/registered). The frontend will use
 * these to render stacked previous-day scorecards in the Live view.
 */
$prevDatesByPlayer = [];
$sqlPrev = "SELECT t.jugadorid, DATE_FORMAT(t.fecha_juego, '%Y-%m-%d') AS fecha
            FROM tarjetas t
            JOIN jugadores j ON (j.id = t.jugadorid)
            WHERE t.torneoid = $tid
              AND j.categoriaid = $cid
              AND t.statlsc = 1
            ORDER BY t.jugadorid, t.fecha_juego ASC";
$prevRows = query_all($conn, $sqlPrev);
foreach ($prevRows as $pr) {
    $pid = (string)$pr['jugadorid'];
    if (!isset($prevDatesByPlayer[$pid])) { $prevDatesByPlayer[$pid] = []; }
    $prevDatesByPlayer[$pid][] = $pr['fecha'];
}

/**
 * Per-player flag: latest scorecard (most recent fecha_juego) is closed (statlsc=1).
 * Used by the frontend to display "F" in the Thru column when the player has
 * finished their CURRENT round, even if the tournament has more rounds pending.
 */
$todayClosedByPlayer = [];
$sqlTodayClosed = "SELECT t.jugadorid, t.statlsc
                   FROM tarjetas t
                   JOIN jugadores j ON (j.id = t.jugadorid)
                   JOIN (
                       SELECT jugadorid, MAX(fecha_juego) AS maxfecha
                       FROM tarjetas
                       WHERE torneoid = $tid
                       GROUP BY jugadorid
                   ) m ON (m.jugadorid = t.jugadorid AND m.maxfecha = t.fecha_juego)
                   WHERE t.torneoid = $tid AND j.categoriaid = $cid";
$todayRows = query_all($conn, $sqlTodayClosed);
foreach ($todayRows as $tr) {
    $todayClosedByPlayer[(string)$tr['jugadorid']] = ((int)$tr['statlsc'] === 1) ? 1 : 0;
}

/**
 * Closed-only Total per player.
 * The leaderboard "Total" column must reflect ONLY scorecards that are
 * already registered/closed (statlsc=1). The in-progress round is shown
 * separately under "Hoy" and is NOT included in the Total.
 *
 *   Stableford → SUM(SA) over closed cards (higher is better)
 *   Stroke     → SUM(SO - parcampohoyo_total) over closed cards (diff to par)
 *                For Neto we substitute SA in place of SO (handicap-adjusted).
 *
 * `parcampohoyo` is a CSV of par-per-hole stored on tarjetas; we sum it via
 * a portable replace-trick to avoid creating a UDF.
 */
$closedScoreByPlayer = [];
$closedCountByPlayer = [];
if ($isStableford) {
    // Stableford: total accumulated SA on closed cards
    $sqlClosed = "SELECT t.jugadorid, COALESCE(SUM(t.SA),0) AS total_score, COUNT(*) AS cnt
                  FROM tarjetas t
                  JOIN jugadores j ON (j.id = t.jugadorid)
                  WHERE t.torneoid = $tid
                    AND j.categoriaid = $cid
                    AND t.statlsc = 1
                  GROUP BY t.jugadorid";
} else {
    // Stroke: difpar = SUM(score) - SUM(par per round) on closed cards
    // Gross uses SO; Neto uses SA (handicap-adjusted score)
    $scoreCol = ($gross == '1') ? 't.SO' : 't.SA';
    // Sum par-per-hole CSV via numeric trick:
    //   par_total = (length(csv) - length(replace(csv, ',', '')) + 1) is wrong for sums;
    //   instead we cast and sum via JSON_TABLE if available; fallback to SUM of t.parcampohoyo (single-int field on legacy tables).
    // Most reliable across schemas: sum the integer column tarjetas.parcampo (total par) when present,
    // otherwise fall back to summing the per-hole CSV via a portable expression.
    $sqlClosed = "SELECT t.jugadorid,
                         COALESCE(SUM($scoreCol),0) AS total_score_raw,
                         COALESCE(SUM(
                           (CHAR_LENGTH(t.parcampohoyo) - CHAR_LENGTH(REPLACE(t.parcampohoyo, ',', '')) + 1) * 0
                           + (
                              SELECT COALESCE(SUM(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(t.parcampohoyo, ',', n.n), ',', -1) AS SIGNED)),0)
                              FROM (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
                                    UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
                                    UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13
                                    UNION SELECT 14 UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18) n
                              WHERE n.n <= (CHAR_LENGTH(t.parcampohoyo) - CHAR_LENGTH(REPLACE(t.parcampohoyo, ',', '')) + 1)
                             )
                         ),0) AS total_par,
                         COUNT(*) AS cnt
                  FROM tarjetas t
                  JOIN jugadores j ON (j.id = t.jugadorid)
                  WHERE t.torneoid = $tid
                    AND j.categoriaid = $cid
                    AND t.statlsc = 1
                  GROUP BY t.jugadorid";
}
$closedRows = query_all($conn, $sqlClosed);
foreach ($closedRows as $cr) {
    $pid = (string)$cr['jugadorid'];
    if ($isStableford) {
        $closedScoreByPlayer[$pid] = (int)$cr['total_score'];
    } else {
        $closedScoreByPlayer[$pid] = (int)$cr['total_score_raw'] - (int)$cr['total_par'];
    }
    $closedCountByPlayer[$pid] = (int)$cr['cnt'];
}

// ── Format response ──
$players = [];
foreach ($rows as $row) {
    if ($isStableford) {
        /**
         * Stableford player mapping:
         *   score    = sumsa (total accumulated stableford points)
         *   prevRoundScore = sumsault (previous round accumulated)
         *   thru     = avance (holes completed in current round)
         *   todayScore = sa (current round stableford points)
         */
        $pid = (string)$row['jugadorid'];
        $closedScore = $closedScoreByPlayer[$pid] ?? 0;
        $todayStatus = $currentCardStatusByPlayer[$pid] ?? null;
        // Per user request: include the in-progress (live) round in the
        // displayed Total. The latest card's points (`sa`) are already
        // counted in $closedScore when statlsc=1, so only add when the
        // current card is still open.
        $isTodayOpen = (($todayClosedByPlayer[$pid] ?? 0) !== 1);
        $liveAdd = $isTodayOpen ? (int)($row['sa'] ?? 0) : 0;
        $players[] = [
            'position'       => 0, // re-assigned after sorting by closed-only Total
            'playerId'       => $row['jugadorid'],
            'number'         => $row['numjugador'] ?? '',
            'name'           => $row['jugador'],
            'clubLogo'       => $row['juglogoclub'] ? $LOGOS_BASE_URL . $row['juglogoclub'] : '',
            'club'           => $row['club'] ?? '',
            // Total = closed cards + in-progress live round (Stableford points).
            'score'          => $closedScore + $liveAdd,
            'prevRoundScore' => (int)($row['sumsault'] ?? 0),
            'todayScore'     => (int)($row['sa'] ?? 0),
            'thru'           => (int)($row['avance'] ?? 0),
            'status'         => $row['estatjug'] ?? '',
            'cardsClosed'    => (int)($row['cardsclosed'] ?? 0),
            'cardsTotal'     => $totalRounds,
            'finished'       => ($totalRounds > 0 && (int)($row['cardsclosed'] ?? 0) >= $totalRounds) ? 1 : 0,
            'todayClosed'    => $todayClosedByPlayer[$pid] ?? 0,
            'prevRoundDates' => $prevDatesByPlayer[$pid] ?? [],
        ];
    } else {
        /**
         * Stroke player mapping:
         *   score       = difpar (accumulated difference to par, negative = under par)
         *   todayScore  = difparulttar (current round diff to par)
         *   thru        = avance_ulttar (holes completed in current round)
         *   handicap    = indexjgo (player's playing handicap index)
         */
        $playerName = trim(($row['nombre'] ?? '') . ' ' . ($row['apellido'] ?? ''));
        $pid = (string)$row['jugadorid'];
        $closedScore = $closedScoreByPlayer[$pid] ?? 0;
        $todayStatus = $currentCardStatusByPlayer[$pid] ?? null;
        // Per user request: include the in-progress live round's diff-to-par
        // in the displayed Total. Only add when the current card is still open
        // (closed cards are already in $closedScore).
        $isTodayOpen = (($todayClosedByPlayer[$pid] ?? 0) !== 1);
        $liveAdd = $isTodayOpen ? (int)($row['difparulttar'] ?? 0) : 0;
        $players[] = [
            'position'       => 0, // re-assigned after sorting by closed-only Total
            'playerId'       => $row['jugadorid'],
            'name'           => $playerName,
            'clubLogo'       => $row['juglogoclub'] ? $LOGOS_BASE_URL . $row['juglogoclub'] : '',
            'club'           => $row['club'] ?? '',
            // Total = closed cards + in-progress live round diff-to-par.
            'score'          => $closedScore + $liveAdd,
            'todayScore'     => (int)($row['difparulttar'] ?? 0),
            'thru'           => (int)($row['avance_ulttar'] ?? 0),
            'handicap'       => $row['indexjgo'] ?? '',
            'status'         => $row['estatjug'] ?? '',
            'cardsClosed'    => (int)($row['cardsclosed'] ?? 0),
            'cardsTotal'     => $totalRounds,
            'finished'       => ($totalRounds > 0 && (int)($row['cardsclosed'] ?? 0) >= $totalRounds) ? 1 : 0,
            'todayClosed'    => $todayClosedByPlayer[$pid] ?? 0,
            'prevRoundDates' => $prevDatesByPlayer[$pid] ?? [],
        ];
    }
}

/**
 * Re-sort by the same value shown in the main leaderboard column.
 *   - With previous closed rounds: use closed-only Total so the live "Hoy"
 *     column does NOT reshuffle R2/R3 group/leaderboard order.
 *   - With no closed rounds yet: use today's live value, matching legacy Live.
 *   - Stroke: lower first. Stableford: higher first.
 * Tie-break keeps the original SQL order deterministically.
 */
foreach ($players as $i => &$player) {
    $player['_originalIndex'] = $i;
    // Match the EXACT value rendered in the frontend "Dif Par / Total" cell:
    //   - hasPrevClosed && todayClosed → score (today is already inside score)
    //   - hasPrevClosed && today open  → score + todayScore
    //   - no prev closed               → todayScore (R1 in progress)
    // Sorting on this single value (no F/no-F grouping, no thru tiers).
    //   Stableford → DESC, Stroke → ASC.
    $prevDates    = $player['prevRoundDates'] ?? [];
    $hasPrevClosed = is_array($prevDates) && count($prevDates) > 0;
    $todayClosed  = ($currentRoundDate !== null) && in_array($currentRoundDate, $prevDates, true);
    $score        = (int)($player['score'] ?? 0);
    $todayScore   = (int)($player['todayScore'] ?? 0);
    if ($hasPrevClosed) {
        $player['_sortScore'] = $score + ($todayClosed ? 0 : $todayScore);
    } else {
        $player['_sortScore'] = $todayScore;
    }
}
unset($player);

usort($players, function($a, $b) use ($isStableford) {
    if ($a['_sortScore'] !== $b['_sortScore']) {
        return $isStableford
            ? ($b['_sortScore'] <=> $a['_sortScore'])
            : ($a['_sortScore'] <=> $b['_sortScore']);
    }
    return $a['_originalIndex'] <=> $b['_originalIndex'];
});

foreach ($players as $i => $_) {
    unset($players[$i]['_originalIndex'], $players[$i]['_sortScore']);
    $players[$i]['position'] = $i + 1;
}

json_response([
    'categoryId'   => $catInfo['categoria_id'],
    'categoryName' => $catInfo['categoria'],
    'shortName'    => $catInfo['abreviatura'],
    'system'       => $catInfo['sistema'],
    'type'         => $isStableford ? 'stableford' : 'stroke',
    'gross'        => (int)$gross,
    'par'          => $parcampo,
    'totalRounds'  => $totalRounds,
    'currentRoundDate' => $currentRoundDate,
    'expectedPlayers' => $expectedPlayers,
    'currentClosedPlayers' => $currentClosedPlayers,
    'categoryClosed' => $categoryClosed,
    'course'       => $courseInfo ? [
        'rating' => (float)($courseInfo['rating'] ?? 0),
        'slope'  => (int)($courseInfo['slope'] ?? 0),
        'tee'    => $courseInfo['tee'] ?? ''
    ] : null,
    'players'      => $players
]);
