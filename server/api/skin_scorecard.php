<?php
/**
 * Skin Scorecards Endpoint
 * ------------------------------------------------------------
 * GET /api/skin_scorecard.php?torneoid=X
 *      → MASTER: list of skin-game dates, each with its Skin_grupo_id
 *        groups (and the id_campo used to load the scorecard).
 *        Mirrors legacy `enca_score_skin.php`.
 *
 * GET /api/skin_scorecard.php?torneoid=X&gpoid=G&fecha=YYYY-MM-DD
 *                            &campoid=C&tipo=gross|neto
 *      → DETAIL: par per hole plus the player rows for the given
 *        group / date / tipo. Mirrors legacy
 *        `skin_score_card_gross.php` and `skin_score_card_neto.php`,
 *        including the yellow-highlight logic driven by
 *        `resultmingross/resulttotgross` (or `_neto`).
 */
require_once 'config.php';

$torneoid = require_torneoid($conn);
$gpoid    = optional_param('gpoid', null);
$fecha    = optional_param('fecha', null);
$campoid  = optional_param('campoid', null);
$tipo     = optional_param('tipo', 'gross');

$tid = esc($conn, $torneoid);

// ================================================================
// MASTER: dates + groups
// ================================================================
if (!$gpoid || !$fecha || !$campoid) {
    $conn->query("SET lc_time_names = 'es_ES'");

    // Skin dates: only rounds that are closed and marked as skin
    $sql = "SELECT LCASE(DATE_FORMAT(fecha, '%W %M %d, %Y')) AS fechax, fecha
            FROM caljuego
            WHERE torneoid = $tid AND estatus = 3 AND cierre = 1 AND campo > 0 AND skin = 1
            GROUP BY fecha
            ORDER BY fecha";
    $dateRows = query_all($conn, $sql);

    $days = [];
    foreach ($dateRows as $dr) {
        $fec = esc($conn, $dr['fecha']);

        // Groups whose ALL categories have `cierre=1` for this date
        // (faltan=0 in the legacy query) → scorecard is publishable.
        // hasGross/hasNeto flag whether any category in the group has
        // non-empty resultmingross / resultminneto CSVs.
        $sql = "SELECT b.Skin_grupo_id AS gpoid,
                       id_campo AS campoid,
                       SUM(IF(c.cierre=0,1,0)) AS faltan,
                       MAX(CASE WHEN c.resultmingross IS NOT NULL AND c.resultmingross <> '' THEN 1 ELSE 0 END) AS has_gross,
                       MAX(CASE WHEN c.resultminneto  IS NOT NULL AND c.resultminneto  <> '' THEN 1 ELSE 0 END) AS has_neto
                FROM Skeen_tarjetas a
                JOIN categorias b ON (b.categoria_id = a.categoriaid)
                JOIN caljuego c ON (a.fecha_juego = c.fecha
                                     AND a.categoriaid = c.categoriaid
                                     AND c.skin = 1)
                WHERE a.fecha_juego = '$fec' AND a.torneoid = $tid
                GROUP BY b.Skin_grupo_id, id_campo
                HAVING faltan = 0
                ORDER BY b.Skin_grupo_id";
        $groupRows = query_all($conn, $sql);

        $groups = [];
        foreach ($groupRows as $gr) {
            $hg = (int)$gr['has_gross'] === 1;
            $hn = (int)$gr['has_neto']  === 1;
            if (!$hg && !$hn) continue;
            $groups[] = [
                'groupId'  => (int)$gr['gpoid'],
                'campoId'  => (int)$gr['campoid'],
                'hasGross' => $hg,
                'hasNeto'  => $hn,
            ];
        }

        // Only surface dates that actually have publishable groups
        if (count($groups) > 0) {
            $days[] = [
                'date'          => $dr['fecha'],
                'dateFormatted' => $dr['fechax'],
                'groups'        => $groups,
            ];
        }
    }

    json_response(['days' => $days]);
}

// ================================================================
// DETAIL: scorecard for one group / date / tipo
// ================================================================
$gid  = (int)$gpoid;
$cid  = (int)$campoid;
$fec  = esc($conn, $fecha);
$isGross = ($tipo === 'gross');

$conn->query("SET lc_time_names = 'es_ES'");

// Header date label
$dateInfo = query_one(
    $conn,
    "SELECT LCASE(DATE_FORMAT('$fec', '%W %M %d, %Y')) AS fechax"
);

// Par per hole (salidaid=2 → tee "blancas/hombres" per legacy convention)
$parRows = query_all(
    $conn,
    "SELECT numero, par
     FROM hoyosxsalida
     WHERE campoid = $cid AND salidaid = 2
     ORDER BY numero"
);
$pars = [];
$parTotal = 0;
foreach ($parRows as $pr) {
    $p = (int)$pr['par'];
    $pars[] = $p;
    $parTotal += $p;
}

// Players in this skin group for the given fecha
// - `h1..h18`  → gross strokes per hole
// - `h1_a..h18_a` → net strokes per hole
// - `SO` → total gross,  `SA` → total neto
// - `resultmingross` / `resulttotgross` (or `_neto`) are CSVs of length 18
//   used to decide which hole cells are highlighted (winner of the skin).
$sql = "SELECT a.*,
               CONCAT(b.nombre, ' ', b.apellido) AS jugador,
               cl.logo, cl.nombre AS club,
               c.abreviatura AS categoria,
               cj.resultmingross, cj.resulttotgross,
               cj.resultminneto,  cj.resulttotneto
        FROM Skeen_tarjetas a
        JOIN jugadores b ON (a.jugadorid = b.id)
        JOIN categorias c ON (a.categoriaid = c.categoria_id)
        JOIN clubs cl ON (b.clubid = cl.id)
        JOIN caljuego cj ON (a.fecha_juego = cj.fecha
                              AND a.categoriaid = cj.categoriaid)
        WHERE a.fecha_juego = '$fec'
          AND a.torneoid = $tid
          AND c.Skin_grupo_id = $gid
        ORDER BY b.nombre, b.apellido";
$rows = query_all($conn, $sql);

$players = [];
foreach ($rows as $rw) {
    // Yellow-highlight vectors — CSV strings ("3,4,..." length 18)
    $minCsv = $isGross ? ($rw['resultmingross'] ?? '') : ($rw['resultminneto'] ?? '');
    $totCsv = $isGross ? ($rw['resulttotgross'] ?? '') : ($rw['resulttotneto'] ?? '');
    $minArr = $minCsv !== '' ? explode(',', $minCsv) : [];
    $totArr = $totCsv !== '' ? explode(',', $totCsv) : [];

    $scores    = [];
    $highlight = [];
    for ($h = 1; $h <= 18; $h++) {
        $col = $isGross ? "h$h" : "h{$h}_a";
        $val = isset($rw[$col]) && $rw[$col] !== null && $rw[$col] !== ''
                    ? (int)$rw[$col] : null;
        $scores[] = $val;

        // Highlight when this player's score equals the group minimum
        // for the hole AND exactly one player reached it (tot === 1).
        $idx = $h - 1;
        $isWin = $val !== null
              && isset($minArr[$idx]) && isset($totArr[$idx])
              && (int)$minArr[$idx] === $val
              && (int)$totArr[$idx] === 1;
        $highlight[] = $isWin;
    }

    // SO = total gross, SA = total neto (per legacy columns)
    $total = $isGross
        ? (isset($rw['SO']) ? (int)$rw['SO'] : null)
        : (isset($rw['SA']) ? (int)$rw['SA'] : null);

    $players[] = [
        'id'        => (int)$rw['id'],
        'name'      => trim($rw['jugador']),
        'club'      => $rw['club'],
        'clubLogo'  => !empty($rw['logo']) ? $LOGOS_BASE_URL . $rw['logo'] : '',
        'category'  => $rw['categoria'],
        'scores'    => $scores,
        'highlight' => $highlight,
        'total'     => $total,
    ];
}

json_response([
    'groupId'       => $gid,
    'campoId'       => $cid,
    'date'          => $fecha,
    'dateFormatted' => $dateInfo['fechax'] ?? $fecha,
    'type'          => $isGross ? 'gross' : 'neto',
    'pars'          => $pars,
    'parTotal'      => $parTotal,
    'players'       => $players,
]);