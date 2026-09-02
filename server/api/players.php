<?php
/**
 * Players Endpoint
 * GET /api/players.php?giraid=XXX&catid=XXX[&torneoid=XXX][&debug=1]
 *
 * Devuelve los jugadores de una categoría con el mismo formato que /field-gira
 * (Club/logo, Jugador, Fecha de nacimiento), filtrado por categoría y por el
 * torneo activo de la gira.
 *
 * Todo el esquema se detecta en runtime: el esquema `golftour` renombra varias
 * columnas respecto al esquema original (`categoriaid` vs `id_categoria`,
 * `torneoid` vs `id_torneo`, `fechanac` vs `il`, ...). Si alguna no existe se
 * omite del query en lugar de provocar un 500 opaco.
 *
 * Añade `&debug=1` para obtener el SQL ejecutado y el esquema detectado.
 */
require_once 'config.php';

$torneoid = require_torneoid($conn);
$catid = require_param('catid');
$cid = esc($conn, $catid);
$tid = esc($conn, $torneoid);

/** Tabla de jugadores: `jugadores` (por torneo) o `jugadores_seed` (gira). */
$playersTable = null;
foreach (['jugadores', 'jugadores_seed'] as $t) {
    if (@$conn->query("SHOW TABLES LIKE '$t'")->num_rows > 0) { $playersTable = $t; break; }
}
if (!$playersTable) {
    json_error('No existe la tabla de jugadores (jugadores / jugadores_seed)');
}

/** Columnas clave, con alias legacy. */
$colCat   = api_first_existing_column($conn, $playersTable, ['categoriaid', 'id_categoria', 'categoria_id', 'categoriasid', 'catid']);
$colTor   = api_first_existing_column($conn, $playersTable, ['torneoid', 'id_torneo', 'torneo_id']);
$colId    = api_first_existing_column($conn, $playersTable, ['id', 'jugador_id', 'jugadorid']);
$colNom   = api_first_existing_column($conn, $playersTable, ['nombre', 'nombres']);
$colApe   = api_first_existing_column($conn, $playersTable, ['apellido', 'apellidos']);
$colBirth = api_first_existing_column($conn, $playersTable, ['fechanac', 'fecha_nac', 'fechanacimiento', 'il']);
$colClub  = api_first_existing_column($conn, $playersTable, ['clubid', 'id_club', 'club_id']);
$colNum   = api_first_existing_column($conn, $playersTable, ['numjugador', 'numero']);
$colSexo  = api_first_existing_column($conn, $playersTable, ['sexo']);
$colEst   = api_first_existing_column($conn, $playersTable, ['estatus', 'status']);
$colGrupo = api_first_existing_column($conn, $playersTable, ['grupoid', 'grupo']);
$colClubT = api_first_existing_column($conn, $playersTable, ['club']);

$debugInfo = [
    'players_table' => $playersTable,
    'torneoid'      => $torneoid,
    'catid'         => $catid,
    'columns'       => compact('colCat', 'colTor', 'colId', 'colNom', 'colApe', 'colBirth', 'colClub', 'colNum', 'colSexo', 'colEst', 'colGrupo', 'colClubT'),
];

if (!$colCat) {
    json_error("La tabla `$playersTable` no tiene columna de categoría", 500, $debugInfo);
}
if (!$colNom) {
    json_error("La tabla `$playersTable` no tiene columna de nombre", 500, $debugInfo);
}

/** ?skin=1 -> solo jugadores inscritos al SKIN GAME. */
$skinOnly = isset($_GET['skin']) && $_GET['skin'] === '1';
$skinFilter = ($skinOnly && api_column_exists($conn, $playersTable, 'Skeenjuga')) ? ' AND p.Skeenjuga = 1 ' : '';

/** ¿Categoría de parejas? El frontend agrupa por grupoid. */
$isParejas = false;
$catPk = api_first_existing_column($conn, 'categorias', ['categoria_id', 'categoriaid', 'categoriasid', 'id']);
if ($catPk && api_column_exists($conn, 'categorias', 'formato')) {
    $row = query_one($conn, "SELECT `formato` FROM `categorias` WHERE `$catPk` = '$cid' LIMIT 1");
    $isParejas = $row && strtoupper($row['formato'] ?? '') === 'PAREJAS';
}

/** SELECT dinámico. */
$sel = [];
$sel[] = ($colId ? "p.`$colId`" : "0") . ' AS id';
$sel[] = $colApe ? "TRIM(CONCAT(p.`$colNom`, ' ', p.`$colApe`)) AS jugador" : "p.`$colNom` AS jugador";
if ($colBirth) $sel[] = "p.`$colBirth` AS fechanac";
if ($colNum)   $sel[] = "p.`$colNum` AS numjugador";
if ($colSexo)  $sel[] = "p.`$colSexo` AS sexo";
if ($colEst)   $sel[] = "p.`$colEst` AS estatus";
if ($colGrupo) $sel[] = "p.`$colGrupo` AS grupoid";
if ($colClubT) $sel[] = "p.`club` AS club";

/** Logo del club. */
$logoJoin = '';
$clubsIdCol = api_first_existing_column($conn, 'clubs', ['id', 'club_id', 'clubid']);
if ($colClub && $clubsIdCol && api_column_exists($conn, 'clubs', 'logo')) {
    $sel[] = 'c.logo AS logo';
    if (!$colClubT && api_column_exists($conn, 'clubs', 'club')) $sel[] = 'c.club AS club';
    $logoJoin = " LEFT JOIN `clubs` c ON (p.`$colClub` = c.`$clubsIdCol`) ";
}

/** Filtros: categoría (+ torneo de la etapa activa cuando la columna existe). */
$where = ["p.`$colCat` = '$cid'"];
if ($colTor && $tid !== '') $where[] = "p.`$colTor` = '$tid'";

$orderBy = $colApe ? "p.`$colApe`, p.`$colNom` ASC" : "p.`$colNom` ASC";

$sql = "SELECT " . implode(', ', $sel) . "
        FROM `$playersTable` p
        $logoJoin
        WHERE " . implode(' AND ', $where) . " $skinFilter
        ORDER BY $orderBy";
debug_log_query('players', $sql);

$result = $conn->query($sql);
if (!$result) {
    json_error('Query failed: ' . $conn->error, 500, array_merge($debugInfo, ['sql' => $sql]));
}

$cleanDate = function ($v) {
    $v = trim((string)$v);
    if ($v === '' || strpos($v, '0000-00-00') === 0 || strpos($v, '1900-01-01') === 0) return '';
    return $v;
};

$players = [];
while ($row = $result->fetch_assoc()) {
    $players[] = [
        'id'         => $row['id'] ?? '',
        'numjugador' => $row['numjugador'] ?? '',
        'jugador'    => $row['jugador'] ?? '',
        'logo'       => !empty($row['logo']) ? $LOGOS_BASE_URL . $row['logo'] : '',
        'club'       => $row['club'] ?? '',
        'sexo'       => $row['sexo'] ?? '',
        'estatus'    => $row['estatus'] ?? 'NORMAL',
        'grupoid'    => $row['grupoid'] ?? '',
        'fechanac'   => $cleanDate($row['fechanac'] ?? ''),
        'hi'         => '0',
        'hj'         => '0',
        'hn'         => '0',
    ];
}
$result->free();

json_response([
    'players'       => $players,
    'fechaHandicap' => '',
    'isParejas'     => $isParejas,
    '_schema'       => $DEBUG_MODE ? $debugInfo : null,
]);
