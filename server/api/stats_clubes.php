<?php
/**
 * Stats — Clubes Asistentes
 * GET /api/stats_clubes.php?torneoid=XXX
 *
 * Returns aggregated player counts per club for the tournament, split
 * into four dynamic branches (Caballeros, Seniors, Super Senior, Damas)
 * AND broken down per tee (salida). The frontend can filter by tee id
 * to show/hide subsets on the fly.
 *
 * Excludes duplicate player rows whose `numjugador` ends with `-1`
 * (stale copies left when a player switches format within a tournament).
 *
 * Response shape:
 *   {
 *     total:  int,
 *     clubs:  [{
 *        id, name, abr, logo,
 *        byTee: { <salidaId>: { caballeros, seniors, supersenior, damas, total } },
 *        total
 *     }, ...],
 *     tees:   [{ id, color, tee }, ...],
 *     noShow: { retiro, noShow, descalificado, noContiende, total }
 *   }
 *
 * Resilient: on missing tables/columns returns { total:0, clubs:[] }.
 */
require_once 'config.php';

$torneoid = require_torneoid($conn);
$tid = esc($conn, $torneoid);

/** Safe wrapper: return [] on failure instead of aborting with json_error. */
function safe_rows($conn, $sql) {
    $r = @$conn->query($sql);
    if (!$r) { error_log('stats_clubes safe_rows failed: ' . $conn->error . ' SQL=' . $sql); return []; }
    $rows = [];
    while ($row = $r->fetch_assoc()) { $rows[] = $row; }
    $r->free();
    return $rows;
}

/** Schema check — does clubs.abr exist? (abbreviation column) */
function clubs_has_abr($conn) {
    static $has = null;
    if ($has !== null) return $has;
    $r = @$conn->query("SHOW COLUMNS FROM clubs LIKE 'abr'");
    $has = $r && $r->num_rows > 0;
    if ($r) $r->free();
    return $has;
}
$abrSelect = clubs_has_abr($conn) ? 'c.abr AS club_abr,' : "'' AS club_abr,";

// Base filter: exclude BAJA and duplicate -1 rows (stale format-switch copies).
$baseWhere = "j.torneoid = $tid
              AND (j.estatus IS NULL OR j.estatus <> 'BAJA')
              AND (j.numjugador IS NULL OR j.numjugador NOT LIKE '%-1')";

// Aggregate per (club, salida). Each row carries branch counts + tee metadata
// so the frontend can filter dynamically by tee color/salida id.
// ---- Clasificación de rama "Super Sr." ----
// Cuenta como Super Sr. en TODOS los torneos cualquier categoría que:
//   • contenga la palabra SUPER   -> SUPER SENIOR, SUPER SR., SÚPER SR., SUPERSENIOR
//   • use abreviatura de SUPER    -> SUP. SR., SUP SENIOR
//   • use la inicial S al inicio  -> S. SENIOR, SSR, S SR.
// `$catKey` normaliza a MAYÚSCULAS, quita acentos (Ú), espacios y puntos, de modo
// que "SUP. SR." -> "SUPSR" y "S. SENIOR" -> "SSENIOR".
// Las formas con inicial suelta (SSENIOR/SSR) se ancoran al INICIO para no
// capturar falsos positivos como "DAMAS SENIOR" ("DAMASSENIOR").
$catKey = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(COALESCE(cat.categoria,'')),'Ú','U'),'ú','u'),' ',''),'.',''),'-','')";
$isSuper = "($catKey LIKE '%SUPER%'"
    . " OR $catKey LIKE '%SUPSR%'"
    . " OR $catKey LIKE '%SUPSENIOR%'"
    . " OR $catKey LIKE 'SSENIOR%'"
    . " OR $catKey LIKE 'SSR%')";
$isSeniorOnly = "UPPER(COALESCE(cat.categoria,'')) LIKE '%SENIOR%' AND NOT ($isSuper)";

$sql = "SELECT c.id AS club_id, c.nombre AS club_name, $abrSelect c.logo AS club_logo,
               COALESCE(cat.salida, 0) AS salida_id,
               s.tee     AS tee_name,
               s.color   AS tee_color,
               s.bgcolor AS tee_bgcolor,
               SUM(CASE WHEN $isSuper THEN 1 ELSE 0 END) AS supersenior,
               SUM(CASE WHEN $isSeniorOnly THEN 1 ELSE 0 END) AS seniors,
               SUM(CASE WHEN UPPER(COALESCE(cat.categoria,'')) NOT LIKE '%SENIOR%' AND NOT ($isSuper) AND UPPER(j.sexo)='M' THEN 1 ELSE 0 END) AS caballeros,
               SUM(CASE WHEN UPPER(COALESCE(cat.categoria,'')) NOT LIKE '%SENIOR%' AND NOT ($isSuper) AND UPPER(j.sexo)='F' THEN 1 ELSE 0 END) AS damas,
               COUNT(*) AS total
          FROM jugadores j
          LEFT JOIN clubs c ON (j.clubid = c.id)
          LEFT JOIN categorias cat ON (j.categoriaid = cat.categoria_id)
          LEFT JOIN salidas s ON (cat.salida = s.id)
         WHERE $baseWhere
         GROUP BY c.id, c.nombre, c.logo, cat.salida, s.tee, s.color, s.bgcolor
         ORDER BY club_name ASC";

$rows = safe_rows($conn, $sql);

/** @var array<string,array> $clubMap  key = club_id (or 'null') → club aggregate */
$clubMap = [];
/** @var array<int,array> $teeMap  key = salida_id → { id, tee, color } */
$teeMap = [];
$grandTotal = 0;

foreach ($rows as $r) {
    $t = (int)$r['total'];
    $grandTotal += $t;
    $sid = (int)$r['salida_id'];
    if ($sid > 0 && !isset($teeMap[$sid])) {
        $teeMap[$sid] = [
            'id'    => $sid,
            'tee'   => $r['tee_name']  ?? '',
            'color' => $r['tee_color'] ?? '',
            'bgcolor' => $r['tee_bgcolor'] ?? '',
        ];
    }
    $ckey = $r['club_id'] !== null ? (string)$r['club_id'] : 'null';
    if (!isset($clubMap[$ckey])) {
        $clubMap[$ckey] = [
            'id'    => $r['club_id'] !== null ? (int)$r['club_id'] : null,
            'name'  => $r['club_name'] ?? '— Sin club —',
            'abr'   => trim((string)($r['club_abr'] ?? '')),
            'logo'  => !empty($r['club_logo']) ? $LOGOS_BASE_URL . $r['club_logo'] : null,
            'byTee' => [],
            'total' => 0,
        ];
    }
    $clubMap[$ckey]['byTee'][(string)$sid] = [
        'caballeros'  => (int)$r['caballeros'],
        'seniors'     => (int)$r['seniors'],
        'supersenior' => (int)$r['supersenior'],
        'damas'       => (int)$r['damas'],
        'total'       => $t,
    ];
    $clubMap[$ckey]['total'] += $t;
}

// Sort clubs by total desc, then name asc.
$clubs = array_values($clubMap);
usort($clubs, function ($a, $b) {
    if ($b['total'] !== $a['total']) return $b['total'] - $a['total'];
    return strcmp($a['name'], $b['name']);
});

// Sort tees by id ascending for stable display.
$tees = array_values($teeMap);
usort($tees, fn($a, $b) => $a['id'] - $b['id']);

// ============= NO SHOW summary =============
$nsRows = safe_rows($conn, "SELECT UPPER(TRIM(j.estatus)) AS est, COUNT(*) AS n
                              FROM jugadores j
                             WHERE j.torneoid = $tid
                               AND (j.numjugador IS NULL OR j.numjugador NOT LIKE '%-1')
                               AND j.estatus IS NOT NULL AND j.estatus <> ''
                               AND UPPER(TRIM(j.estatus)) NOT IN ('NORMAL','BAJA')
                             GROUP BY UPPER(TRIM(j.estatus))");
$retiro = 0; $noshow = 0; $desc = 0; $nocont = 0;
foreach ($nsRows as $r) {
    $e = $r['est']; $n = (int)$r['n'];
    if ($e === 'RETIRO' || $e === 'ABANDONO') $retiro += $n;
    else if ($e === 'NO SHOW' || $e === 'NO-SHOW' || $e === 'SHOW-NO') $noshow += $n;
    else if ($e === 'DESCALIFICADO' || $e === 'DQ') $desc += $n;
    // "No contiende" (N): jugador inscrito que no compite por premios.
    else if ($e === 'NO CONTIENDE' || $e === 'NO-CONTIENDE' || $e === 'N') $nocont += $n;
}

json_response([
    'total'  => $grandTotal,
    'clubs'  => $clubs,
    'tees'   => $tees,
    'noShow' => [
        'retiro'        => $retiro,
        'noShow'        => $noshow,
        'descalificado' => $desc,
        'noContiende'   => $nocont,
        'total'         => $retiro + $noshow + $desc + $nocont,
    ],
]);
