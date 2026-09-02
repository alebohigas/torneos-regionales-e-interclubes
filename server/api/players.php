<?php
/**
 * Players Endpoint
 * GET /api/players.php?torneoid=XXX&catid=XXX
 * Returns players for a specific category
 * Uses: jugadores table joined with clubs for logo
 */
require_once 'config.php';

$torneoid = require_torneoid($conn);
$catid = require_param('catid');
$cid = esc($conn, $catid);
$tid = esc($conn, $torneoid);

/**
 * Optional `?skin=1` flag — restrict results to players enrolled in the
 * SKIN GAME (jugadores.Skeenjuga = 1). Used by the /skinplayers page.
 */
$skinOnly = isset($_GET['skin']) && $_GET['skin'] === '1';
$skinPlayerFilter = ($skinOnly && api_column_exists($conn, 'jugadores', 'Skeenjuga'))
    ? " AND p.Skeenjuga = 1 " : '';

/**
 * Detectar si la categoría es de parejas (formato='PAREJAS'). El frontend lo
 * usa para agrupar jugadores por grupoid (cada grupo = una pareja).
 */
$isParejas = false;
if (api_column_exists($conn, 'categorias', 'formato')) {
    $catInfoRow = query_one($conn, "SELECT formato FROM categorias WHERE categoria_id = '$cid' LIMIT 1");
    $isParejas = $catInfoRow && strtoupper($catInfoRow['formato'] ?? '') === 'PAREJAS';
}


/**
 * Construcción dinámica de columnas.
 *
 * El esquema `golftour` no tiene algunas columnas del esquema original
 * (`equipo`, `grupoid`, `Skeenjuga`, ...). Si se referencian directamente el
 * query falla con "Unknown column" (HTTP 500) y la tabla de /jugadores queda
 * vacía aunque los conteos por categoría sí se calculen.
 */
$optionalPlayerCols = ['numjugador', 'indexjgo', 'teesalidaid', 'club', 'sexo', 'estatus', 'equipo', 'grupoid', 'fechanac'];
$selCols = ['p.id', "CONCAT(p.nombre, ' ', p.apellido) as jugador"];
$has = [];
foreach ($optionalPlayerCols as $c) {
    $has[$c] = api_column_exists($conn, 'jugadores', $c);
    if ($has[$c]) $selCols[] = "p.`$c`" . ($c === 'indexjgo' ? ' as hi' : '');
}

/** Logo del club (opcional: requiere jugadores.clubid + tabla clubs). */
$hasClubId = api_column_exists($conn, 'jugadores', 'clubid');
$logoJoin = '';
if ($hasClubId) {
    $selCols[] = 'c.logo';
    $logoJoin = ' LEFT JOIN clubs c ON (p.clubid = c.id) ';
}

/** Handicaps calculados por funciones legacy (requieren indexjgo/teesalidaid). */
$catJoin = '';
if ($has['indexjgo'] && $has['teesalidaid'] && api_column_exists($conn, 'caljuego', 'campo')) {
    $pctCol = api_column_exists($conn, 'categorias', $skinOnly ? 'Skeenporcent' : 'porcentaje')
        ? ($skinOnly ? 'cat.skeenporcent' : 'cat.porcentaje')
        : 'NULL';
    $pctSelect = api_column_exists($conn, 'categorias', 'porcentaje') ? 'cat.porcentaje' : 'NULL AS porcentaje';
    $skeenSelect = api_column_exists($conn, 'categorias', 'Skeenporcent')
        ? 'cat.Skeenporcent AS skeenporcent' : 'NULL AS skeenporcent';
    $selCols[] = "f_hdccampo(p.indexjgo, p.teesalidaid, cat.campoid) as hj";
    $selCols[] = "f_hdccamponeto(p.indexjgo, p.teesalidaid, cat.campoid, $pctCol) as hn";
    $catJoin = " LEFT JOIN (
            SELECT cat.categoria_id, cj.campo as campoid, $pctSelect, $skeenSelect
            FROM categorias cat
            JOIN caljuego cj ON (cat.categoria_id = cj.categoriaid)
            WHERE cat.categoria_id = '$cid' and cj.campo > 0
            LIMIT 1
        ) cat ON (p.categoriaid = cat.categoria_id) ";
}

$orderBy = api_column_exists($conn, 'jugadores', 'apellido') ? 'p.apellido, p.nombre ASC' : 'p.nombre ASC';

$sql = "SELECT " . implode(', ', $selCols) . "
        FROM jugadores p
        $logoJoin
        $catJoin
        WHERE p.categoriaid = '$cid' AND p.torneoid = $tid $skinPlayerFilter
        ORDER BY $orderBy";


$result = $conn->query($sql);
if (!$result) {
    json_error('Query failed: ' . $conn->error);
}

$players = [];
/**
 * fechaHandicap: Tournament-wide handicap effective date.
 * Source: categorias.fechaHandicap (PK: categoria_id), looked up by the
 * current $catid. Per requirement, the handicap effective date is now stored
 * per category instead of at the tournament level (torneo.fecha_hand) or
 * per player (jugadores.fechahandicap).
 */
$fechaHandicap = '';
while ($row = $result->fetch_assoc()) {
    $players[] = [
        'id'         => $row['id'],
        'numjugador' => $row['numjugador'] ?? '',
        'jugador'    => $row['jugador'],
        'logo'       => !empty($row['logo']) ? $LOGOS_BASE_URL . $row['logo'] : '',
        'hi'         => $row['hi'] ?? '0',
        'hj'         => $row['hj'] ?? '0',
        'hn'         => $row['hn'] ?? '0',
        'club'       => $row['club'] ?? '',
        'sexo'       => $row['sexo'] ?? '',
        'estatus'    => $row['estatus'] ?? 'NORMAL',
        /** grupoid: agrupador de parejas (ej. "C24"). El frontend usa este
         *  campo cuando isParejas=true para mostrar "Grupo C24". */
        'grupoid'    => $row['grupoid'] ?? '',
        /** fechanac: fecha de nacimiento mostrada en la tabla de /jugadores. */
        'fechanac'   => $row['fechanac'] ?? ''
    ];
}
$result->free();

/**
 * Fetch fechaHandicap from categorias table (PK: categoria_id).
 * Uses escaped category id; safely returns empty string if not found,
 * if the value is empty, or if it equals known placeholder dates
 * ('0000-00-00' or the legacy default '1900-01-01').
 */
$catSql = "SELECT fechaHandicap FROM categorias WHERE categoria_id = '$cid' LIMIT 1";
$catRes = api_column_exists($conn, 'categorias', 'fechaHandicap') ? $conn->query($catSql) : false;

if ($catRes) {
    if ($catRow = $catRes->fetch_assoc()) {
        $val = $catRow['fechaHandicap'] ?? '';
        if (!empty($val) && $val !== '0000-00-00' && $val !== '1900-01-01') {
            $fechaHandicap = $val;
        }
    }
    $catRes->free();
}

json_response([
    'players'       => $players,
    'fechaHandicap' => $fechaHandicap,
    'isParejas'     => $isParejas,
]);