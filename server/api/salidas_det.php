<?php
/**
 * Salidas Detail Endpoint (golftour / gira schema)
 * GET /api/salidas_det.php?caljgoid=XXX
 *
 * Réplica del legacy `salidas_detsu.php`:
 *   1. Info del caljuego → campo, fecha, categoría, sistema, tee.
 *   2. Grupos de salida: salidagrupo JOIN categorias JOIN salidas
 *      → hora (HH:MM desde horainicio1a), tee / teesal.
 *   3. Jugadores por grupo desde la vista `v_sal_jug`
 *      → logo, nombre+apellido, acumso, sistema y `catjugador`
 *        (categoría de cada jugador, que es la info nueva a mostrar).
 *
 * Todas las columnas se detectan en runtime: el esquema `golftour` no tiene
 * varias columnas legacy (abreviatura, gross, grossstb, orden, tarjetaid...).
 */
require_once 'config.php';

$caljgoid = require_param('caljgoid');
$cgid = esc($conn, $caljgoid);

$conn->query("SET lc_time_names = 'es_ES'");

// ============= 1. Calendar game + category info =============
$hasAbrev    = api_column_exists($conn, 'categorias', 'abreviatura');
$hasSistema  = api_column_exists($conn, 'categorias', 'sistema');
$hasSalidaFk = api_column_exists($conn, 'categorias', 'salida');

$cols = "a.id, a.torneoid, a.fecha, a.campo, a.categoriaid, b.categoria, c.campo AS campo_nombre";
$cols .= $hasAbrev   ? ", b.abreviatura" : ", b.categoria AS abreviatura";
$cols .= $hasSistema ? ", b.sistema"     : ", '' AS sistema";
$cols .= $hasSalidaFk ? ", s.tee" : ", '' AS tee";
$join  = $hasSalidaFk ? " LEFT JOIN salidas s ON (b.salida = s.id)" : "";

$sql = "SELECT $cols
        FROM caljuego a
        JOIN categorias b ON (a.categoriaid = b.categoria_id)
        LEFT JOIN campos c ON (a.campo = c.id)$join
        WHERE a.id = $cgid";
$calInfo = query_one($conn, $sql);

if (!$calInfo) {
    json_response([
        'caljgoid' => $caljgoid, 'date' => '', 'course' => '',
        'categoryId' => '', 'categoryName' => '', 'shortName' => '',
        'system' => '', 'tee' => '', 'groups' => []
    ]);
    exit;
}

// ============= 2. Mixed tee-time groups for the whole day =============
$hasTeesal = api_column_exists($conn, 'salidagrupo', 'teesal');
$horaCol   = api_first_existing_column($conn, 'salidagrupo', ['horainicio1a', 'horainicio', 'hora']);
$teeSel    = $hasSalidaFk ? "sal.tee" : "'' AS tee";

$gcols = "a.id";
$gcols .= $horaCol ? ", LEFT(RIGHT(a.`$horaCol`, 8), 5) AS hora" : ", '' AS hora";
$gcols .= $hasTeesal ? ", a.teesal" : ", '' AS teesal";
$gcols .= ", $teeSel";

$calDate = esc($conn, $calInfo['fecha']);
$calTournament = (int)$calInfo['torneoid'];
$gjoin = "JOIN caljuego cg ON (a.caljuegoid = cg.id AND cg.torneoid = $calTournament AND cg.fecha = '$calDate')
          JOIN categorias b ON (a.categoriaid = b.categoria_id)";
if ($hasSalidaFk) $gjoin .= " LEFT JOIN salidas sal ON (sal.id = b.salida)";

$sql = "SELECT $gcols FROM salidagrupo a $gjoin ORDER BY a.id";
debug_log_query('salidas_det_grupos', $sql);
$groupRows = query_all($conn, $sql);

// ============= 3. Players per group (v_sal_jug) =============
$vHas = function ($col) use ($conn) { return api_column_exists($conn, 'v_sal_jug', $col); };

$scoreCol = api_first_existing_column($conn, 'v_sal_jug', ['acumso', 'acumsa', 'acumstbgross']);
$hasLogo  = $vHas('logo');
$hasCatJug = $vHas('catjugador');
$hasSist  = $vHas('sistema');
$hasGrupo = $vHas('grupoid');

$pcols = $hasLogo ? "logo" : "'' AS logo";
$pcols .= ", CONCAT(nombre, ' ', apellido) AS jugador";
$pcols .= $scoreCol ? ", `$scoreCol` AS sa" : ", 0 AS sa";
$pcols .= $hasSist ? ", sistema" : ", '' AS sistema";
$pcols .= $hasCatJug ? ", catjugador" : ", '' AS catjugador";
if ($hasGrupo) $pcols .= ", grupoid";

// Legacy ORDER BY: salidagrupoid, acumso, orden DESC, tarjetaid DESC
$order = "salidagrupoid";
if ($scoreCol) $order .= ", `$scoreCol`";
if ($vHas('orden'))     $order .= ", orden DESC";
if ($vHas('tarjetaid')) $order .= ", tarjetaid DESC";

$groups = [];
foreach ($groupRows as $group) {
    $salid = esc($conn, $group['id']);
    $sql = "SELECT $pcols FROM v_sal_jug WHERE salidagrupoid = $salid ORDER BY $order";
    debug_log_query('salidas_det_jugadores', $sql);
    $playerRows = query_all($conn, $sql);

    $players = [];
    foreach ($playerRows as $pr) {
        $player = [
            'name'     => trim($pr['jugador']),
            'clubLogo' => !empty($pr['logo']) ? $LOGOS_BASE_URL . $pr['logo'] : '',
            'score'    => (int)($pr['sa'] ?? 0),
            'system'   => $pr['sistema'] ?? '',
            'category' => trim((string)($pr['catjugador'] ?? '')),
        ];
        if (isset($pr['grupoid'])) $player['groupId'] = $pr['grupoid'];
        $players[] = $player;
    }

    $groups[] = [
        'id'      => $group['id'],
        'tee'     => $group['teesal'] ?: ($group['tee'] ?? ''),
        'time'    => $group['hora'] ?? '',
        'players' => $players
    ];
}

json_response([
    'caljgoid'     => $caljgoid,
    'date'         => $calInfo['fecha'],
    'course'       => $calInfo['campo_nombre'] ?? '',
    'categoryId'   => $calInfo['categoriaid'],
    'categoryName' => $calInfo['categoria'],
    'shortName'    => $calInfo['abreviatura'] ?: $calInfo['categoria'],
    'system'       => $calInfo['sistema'] ?? '',
    'tee'          => $calInfo['tee'] ?? '',
    'groups'       => $groups
]);
