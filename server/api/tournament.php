<?php
/**
 * Tournament Info Endpoint
 * GET /api/tournament.php?torneoid=XXX[&debug=1]
 *
 * Schema-tolerant version: works both with the legacy `torneos` database and
 * the reduced `golftour` database, where some columns/tables (clubs, logo,
 * imagen_gif, telefono, ...) may not exist.
 *
 * Returns tournament details, its logos (served through /api/logo.php) and
 * best-effort statistics. Any sub-query that fails is skipped instead of
 * breaking the whole endpoint.
 */
require_once 'config.php';

$torneoid = require_torneoid($conn);
$tid = esc($conn, $torneoid);
$debug = isset($_GET['debug']) && $_GET['debug'] == '1';
$diag = ['torneoid' => $torneoid, 'notes' => []];

/** True when the given table exists in the current database. */
function tbl_exists($conn, $table) {
    $t = str_replace('`', '', $table);
    $res = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $t) . "'");
    if (!$res) return false;
    $exists = mysqli_num_rows($res) > 0;
    mysqli_free_result($res);
    return $exists;
}

// ============= Columns available on `torneo` =============
$wanted = [
    'torneo_id', 'nombre', 'fecha_ini', 'fecha_fin', 'status', 'logo', 'formato',
    'estilo', 'sistemajuego', 'tipotorneo', 'color_cinta', 'imagen_gif',
    'telefono', 'correotorne', 'logo_fondo', 'logo_header', 'club_id', 'giraid',
];
$cols = [];
foreach ($wanted as $c) {
    if (api_column_exists($conn, 'torneo', $c)) $cols[] = $c;
}
if (!in_array('torneo_id', $cols, true)) {
    json_error('Tabla torneo sin columna torneo_id', 500);
}
$diag['torneo_columns'] = $cols;

$select = [];
foreach ($cols as $c) $select[] = "a.`$c`";
$torneo = query_one($conn, 'SELECT ' . implode(', ', $select) . " FROM torneo a WHERE a.torneo_id = $tid");
if (!$torneo) {
    json_error('Tournament not found', 404, $diag);
}

// ============= Club / sede info (optional) =============
$club = ['nombre' => '', 'logo' => null, 'ciudad' => '', 'estado' => ''];
$clubTable = null;
if (!empty($torneo['club_id'])) {
    foreach (['clubs', 'campos'] as $cand) {
        if (tbl_exists($conn, $cand)) { $clubTable = $cand; break; }
    }
}
if ($clubTable) {
    $idCol = api_first_existing_column($conn, $clubTable, ['id', 'campo_id', 'club_id']);
    $nameCol = api_first_existing_column($conn, $clubTable, ['nombre', 'campo', 'name']);
    $logoCol = api_first_existing_column($conn, $clubTable, ['logo', 'logotipo']);
    $cityCol = api_first_existing_column($conn, $clubTable, ['ciudad']);
    $stateCol = api_first_existing_column($conn, $clubTable, ['estado']);
    if ($idCol && $nameCol) {
        $cid = esc($conn, $torneo['club_id']);
        $parts = ["`$nameCol` AS nombre"];
        if ($logoCol)  $parts[] = "`$logoCol` AS logo";
        if ($cityCol)  $parts[] = "`$cityCol` AS ciudad";
        if ($stateCol) $parts[] = "`$stateCol` AS estado";
        $row = query_one($conn, 'SELECT ' . implode(', ', $parts) . " FROM `$clubTable` WHERE `$idCol` = $cid");
        if ($row) $club = array_merge($club, $row);
    }
    $diag['club_table'] = $clubTable;
} else {
    $diag['notes'][] = 'Sin tabla de clubes/campos o torneo sin club_id';
}

/** Build the proxied logo URL from a stored file name (may be empty). */
function logo_url($value) {
    global $LOGOS_BASE_URL;
    $value = trim((string)$value);
    if ($value === '') return null;
    // Keep only the file name; DB rows sometimes include folder prefixes.
    $value = basename(str_replace('\\', '/', $value));
    return $LOGOS_BASE_URL . rawurlencode($value);
}

// ============= Best-effort statistics =============
$totalHistorical = 0;
$yearsHistory = 0;
$maxCategorias = 0;
if (!empty($torneo['club_id']) && api_column_exists($conn, 'torneo', 'club_id')) {
    $row = query_one($conn, "SELECT MIN(YEAR(fecha_ini)) AS min_year, MAX(YEAR(fecha_ini)) AS max_year
                             FROM torneo WHERE club_id = (SELECT club_id FROM torneo WHERE torneo_id = $tid)");
    if ($row && $row['min_year'] && $row['max_year']) {
        $yearsHistory = (int)$row['max_year'] - (int)$row['min_year'];
    }
}
if (tbl_exists($conn, 'categorias') && api_column_exists($conn, 'categorias', 'torneo_id')) {
    $row = query_one($conn, "SELECT COUNT(*) AS total FROM categorias WHERE torneo_id = $tid");
    $maxCategorias = (int)($row['total'] ?? 0);
}
if (tbl_exists($conn, 'jugadores') && api_column_exists($conn, 'jugadores', 'torneoid')) {
    $row = query_one($conn, "SELECT COUNT(*) AS total FROM jugadores WHERE torneoid = $tid");
    $totalHistorical = (int)($row['total'] ?? 0);
}

$payload = [
    'id'          => $torneo['torneo_id'],
    'name'        => $torneo['nombre'] ?? '',
    'club'        => $club['nombre'] ?? '',
    'clubLogo'    => logo_url($club['logo'] ?? ''),
    'logo'        => logo_url($torneo['logo'] ?? ''),
    'startDate'   => $torneo['fecha_ini'] ?? null,
    'endDate'     => $torneo['fecha_fin'] ?? null,
    'status'      => $torneo['status'] ?? '',
    'format'      => $torneo['formato'] ?? '',
    'style'       => $torneo['estilo'] ?? '',
    'system'      => $torneo['sistemajuego'] ?? '',
    'type'        => $torneo['tipotorneo'] ?? '',
    'ribbonColor' => $torneo['color_cinta'] ?? '',
    'heroImage'   => logo_url($torneo['logo_fondo'] ?? '') ?: logo_url($torneo['imagen_gif'] ?? ''),
    'logoHeader'  => logo_url($torneo['logo_header'] ?? ''),
    'phone'       => $torneo['telefono'] ?? '',
    'email'       => $torneo['correotorne'] ?? '',
    'city'        => $club['ciudad'] ?? '',
    'state'       => $club['estado'] ?? '',
    'stats' => [
        'totalHistoricalPlayers' => $totalHistorical,
        'yearsHistory'           => $yearsHistory,
        'yearsHistoryRounded'    => (int)(floor($yearsHistory / 2) * 2),
        'maxCategories'          => $maxCategorias,
    ],
];

if ($debug) {
    $diag['raw_logo_fondo']  = $torneo['logo_fondo'] ?? null;
    $diag['raw_logo_header'] = $torneo['logo_header'] ?? null;
    $payload['_debug'] = $diag;
}

json_response($payload);
