<?php
/**
 * COPA Endpoint (gira)
 *
 * Réplica JSON de los legacy `copas.php` + `lista_copas.php` + `copas_gira.php`:
 * cada jugador aporta sus puntos al club al que pertenece dentro de la gira
 * (`jugadores_seed.id_club`) y la copa muestra la sumatoria por club.
 *
 * Las copas de la gira viven en `copas(copasid, giraid, nombre, grupocopas)`:
 *   - nombre con "varonil/caballeros" -> sólo jugadores sexo M
 *   - nombre con "femenil/damas"      -> sólo jugadores sexo F
 *   - `grupocopas` con otros copasid  -> copa conjunta (sin filtro de sexo)
 *
 * Modos:
 *   GET /api/copa.php?giraid=NN                          -> copas de la gira
 *   GET /api/copa.php?giraid=NN&copasid=NN               -> ranking de clubes
 *   GET /api/copa.php?giraid=NN&copasid=NN&clubid=NN     -> jugadores del club
 */
require_once 'config.php';

$giraid = isset($_GET['giraid']) ? trim((string)$_GET['giraid']) : '';
if (!ctype_digit($giraid) || (int)$giraid <= 0) {
    json_error('giraid requerido');
}
$gid = (int)$giraid;

// ---------- Helpers ----------

/** Deduce el filtro de sexo de una copa a partir de su nombre / grupocopas. */
function copa_sexo_filter($nombre, $grupoRaw) {
    $n = strtolower((string)$nombre);
    $n = strtr($n, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
    $hasGroup = trim((string)$grupoRaw) !== '';

    // Una copa que consolida otras copas siempre es conjunta.
    if (!$hasGroup) {
        if (preg_match('/(varonil|caballer|masculin|varon)/', $n)) return 'M';
        if (preg_match('/(femenil|damas|femenin|mujer)/', $n))     return 'F';
    }
    return '';
}

/** Torneos que pertenecen a la gira (para no sumar puntos de otras giras). */
function gira_torneo_ids($conn, $gid) {
    $pk = api_first_existing_column($conn, 'torneo', ['torneo_id', 'torneoid', 'id']) ?: 'torneo_id';
    $rows = query_all($conn, "SELECT `$pk` AS id FROM torneo WHERE giraid = $gid");
    $ids = [];
    foreach ($rows as $r) {
        $ids[] = (int)$r['id'];
    }
    return $ids;
}

/** Columna con el sexo del jugador: preferimos jugadores_seed, luego jugadores. */
function sexo_source($conn) {
    if (api_column_exists($conn, 'jugadores_seed', 'sexo')) return 's.sexo';
    if (api_column_exists($conn, 'jugadores', 'sexo'))      return 'b.sexo';
    return '';
}

$copasRows = query_all(
    $conn,
    "SELECT copasid, nombre, grupocopas FROM copas WHERE giraid = $gid ORDER BY copasid ASC"
);

$copasParam = isset($_GET['copasid']) ? trim((string)$_GET['copasid']) : '';

// ============= Modo lista: copas de la gira =============
if ($copasParam === '') {
    $copas = [];
    foreach ($copasRows as $r) {
        $sexo = copa_sexo_filter($r['nombre'] ?? '', $r['grupocopas'] ?? '');
        $copas[] = [
            'copasid'      => (string)(int)$r['copasid'],
            'name'         => $r['nombre'] ?? '',
            'sexo'         => $sexo,
            'consolidated' => trim((string)($r['grupocopas'] ?? '')) !== '',
        ];
    }
    json_response(['copas' => $copas]);
}

if (!ctype_digit($copasParam)) {
    json_error('copasid inválido');
}
$copasid = (int)$copasParam;

$copa = null;
foreach ($copasRows as $r) {
    if ((int)$r['copasid'] === $copasid) $copa = $r;
}
if (!$copa) {
    json_error('Copa no encontrada en esta gira', 404);
}

$sexo = copa_sexo_filter($copa['nombre'] ?? '', $copa['grupocopas'] ?? '');
$sexoCol = $sexo !== '' ? sexo_source($conn) : '';

$torneoIds = gira_torneo_ids($conn, $gid);
$torneoWhere = count($torneoIds) > 0 ? " AND b.torneoid IN (" . implode(',', $torneoIds) . ") " : '';
$sexoWhere = ($sexo !== '' && $sexoCol !== '') ? " AND $sexoCol = '" . esc($conn, $sexo) . "' " : '';

$clubidParam = isset($_GET['clubid']) ? trim((string)$_GET['clubid']) : '';

// ============= Modo detalle: jugadores de un club =============
if ($clubidParam !== '') {
    if (!ctype_digit($clubidParam)) {
        json_error('clubid inválido');
    }
    $clubid = (int)$clubidParam;

    $sql  = "SELECT b.numjugador, CONCAT(b.nombre, ' ', b.apellido) AS jugador, ";
    $sql .= "SUM(ROUND(b.puntos, 1)) AS puntos, COUNT(DISTINCT b.torneoid) AS etapas ";
    $sql .= "FROM jugadores AS b ";
    $sql .= "JOIN jugadores_seed AS s ON (b.numjugador = s.numjugador AND s.giraid = $gid) ";
    $sql .= "WHERE s.id_club = $clubid $torneoWhere $sexoWhere ";
    $sql .= "GROUP BY b.numjugador, jugador ";
    $sql .= "ORDER BY puntos DESC";

    $rows = query_all($conn, $sql);
    $players = [];
    $pos = 0;
    foreach ($rows as $row) {
        $pos++;
        $players[] = [
            'position'   => $pos,
            'numjugador' => (string)($row['numjugador'] ?? ''),
            'jugador'    => $row['jugador'] ?? '',
            'puntos'     => isset($row['puntos']) ? (float)$row['puntos'] : 0,
            'etapas'     => (int)($row['etapas'] ?? 0),
        ];
    }

    json_response([
        'copasid' => (string)$copasid,
        'clubid'  => (string)$clubid,
        'players' => $players,
    ]);
}

// ============= Modo ranking de clubes =============
$sql  = "SELECT SUM(ROUND(b.puntos, 1)) AS puntos, c.nombre AS club, c.logo, s.id_club, ";
$sql .= "COUNT(DISTINCT b.numjugador) AS jugadores ";
$sql .= "FROM jugadores AS b ";
$sql .= "JOIN jugadores_seed AS s ON (b.numjugador = s.numjugador AND s.giraid = $gid) ";
$sql .= "JOIN clubs AS c ON (s.id_club = c.id) ";
$sql .= "WHERE 1 $torneoWhere $sexoWhere ";
$sql .= "GROUP BY c.nombre, c.logo, s.id_club ";
$sql .= "ORDER BY puntos DESC";

$rows = query_all($conn, $sql);

$clubs = [];
$pos = 0;
foreach ($rows as $row) {
    $pos++;
    $clubs[] = [
        'position'  => $pos,
        'clubid'    => (string)(int)($row['id_club'] ?? 0),
        'club'      => $row['club'] ?? '',
        'logo'      => !empty($row['logo']) ? $LOGOS_BASE_URL . $row['logo'] : '',
        'puntos'    => isset($row['puntos']) ? (float)$row['puntos'] : 0,
        'jugadores' => (int)($row['jugadores'] ?? 0),
    ];
}

json_response([
    'copa' => [
        'copasid'      => (string)$copasid,
        'name'         => $copa['nombre'] ?? '',
        'sexo'         => $sexo,
        'consolidated' => trim((string)($copa['grupocopas'] ?? '')) !== '',
    ],
    'clubs' => $clubs,
]);
