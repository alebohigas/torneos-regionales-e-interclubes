<?php
/**
 * ALIEN SYSTEM — tarjetas para impresión.
 * GET /api/alein_tarjetas.php?torneoid=NN[&fecha=YYYY-MM-DD&campoid=NN&catid=NN&sistema=...]
 *
 * Sin filtros devuelve catálogo de fechas/campos/categorías. Con fecha,
 * campo y categoría devuelve todas las tarjetas listas para imprimir.
 */
require_once 'config.php';
require_once '_staff_auth.php';

// La información de salidas y folios requiere superadmin o el área ALIEN SYSTEM.
$alienStaff = staff_check_area($conn, [], 'alien-system');
if (!is_superadmin_session() && !$alienStaff) json_error('Unauthorized', 401);

$torneoid = require_torneoid($conn);
$tid = esc($conn, $torneoid);
$fecha = trim(optional_param('fecha', ''));
$hasta = trim(optional_param('hasta', ''));
$campoid = trim(optional_param('campoid', ''));
$catid = trim(optional_param('catid', ''));
$sistemaFiltro = trim(optional_param('sistema', ''));

/** Devuelve una URL segura para logos almacenados como nombre de archivo. */
function alein_logo_url($value) {
    global $LOGOS_BASE_URL;
    $file = basename(str_replace('\\', '/', trim((string)$value)));
    return $file === '' ? '' : $LOGOS_BASE_URL . rawurlencode($file);
}

/** Convierte CSV numérico a enteros. */
function alein_csv_ints($value) {
    $out = [];
    foreach (preg_split('/[,;|\s]+/', (string)$value) as $part) {
        if ($part !== '' && is_numeric($part)) $out[] = (int)$part;
    }
    return $out;
}

/** HH:MM desde DATETIME/TIME. */
function alein_hhmm($value) {
    if (preg_match('/(\d{1,2}):(\d{2})(?::\d{2})?\s*$/', trim((string)$value), $m)) {
        return str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
    }
    return '';
}

// ============= Torneo =============
$torneoCols = ['nombre'];
foreach (['logo_header', 'logo', 'fecha_ini', 'fecha_fin'] as $col) {
    if (api_column_exists($conn, 'torneo', $col)) $torneoCols[] = $col;
}
$torneo = query_one($conn, 'SELECT ' . implode(', ', $torneoCols) . " FROM torneo WHERE torneo_id = $tid LIMIT 1") ?: [];

// ============= Catálogo de filtros =============
$hasAbrev = api_column_exists($conn, 'categorias', 'abreviatura');
$hasSistema = api_column_exists($conn, 'categorias', 'sistema');
$catalogSql = "SELECT cj.fecha, cj.campo AS campoid, COALESCE(ca.campo, '') AS campo,
                      cat.categoria_id AS categoryId, cat.categoria AS categoryName"
            . ($hasAbrev ? ", cat.abreviatura AS shortName" : ", cat.categoria AS shortName")
            . ($hasSistema ? ", cat.sistema" : ", '' AS sistema") . "
               FROM caljuego cj
               JOIN categorias cat ON cat.categoria_id = cj.categoriaid
               LEFT JOIN campos ca ON ca.id = cj.campo
               WHERE cj.torneoid = $tid AND cj.campo > 0
               ORDER BY cj.fecha, ca.campo, cat.categoria";
$catalogRows = query_all($conn, $catalogSql);
$filters = [];
foreach ($catalogRows as $row) {
    $filters[] = [
        'date' => $row['fecha'],
        'courseId' => (string)$row['campoid'],
        'course' => $row['campo'],
        'categoryId' => (string)$row['categoryId'],
        'category' => $row['categoryName'],
        'shortName' => $row['shortName'] ?: $row['categoryName'],
        'system' => $row['sistema'] ?? '',
    ];
}

$basePayload = [
    'tournament' => [
        'id' => (string)$torneoid,
        'name' => $torneo['nombre'] ?? '',
        'logo' => alein_logo_url($torneo['logo_header'] ?? ($torneo['logo'] ?? '')),
    ],
    'filters' => $filters,
    'cards' => [],
];

if ($fecha === '' || $campoid === '' || $catid === '') json_response($basePayload);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)
    || ($hasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta))
    || ($hasta !== '' && $hasta < $fecha)
    || !ctype_digit($campoid) || !ctype_digit($catid)) {
    json_error('Filtros inválidos', 400);
}

$fec = esc($conn, $fecha);
$fecHasta = esc($conn, $hasta !== '' ? $hasta : $fecha);
$campo = esc($conn, $campoid);
$cat = esc($conn, $catid);

// ============= Columnas tolerantes del view =============
$vTable = 'v_sal_jug';
$vCampo = api_first_existing_column($conn, $vTable, ['id_campo', 'campoid']);
if (!$vCampo) json_error('La vista de salidas no contiene campo', 500);

$select = [
    'v.jugadorid', 'v.numjugador', 'v.nombre', 'v.apellido',
    'v.horainicio1a', 'v.teesal', 'v.categoriaid', 'v.fecha_juego',
    "v.`$vCampo` AS campoid",
];
foreach (['tarjetaid', 'teesalidaid', 'tee_salida', 'tee', 'clubjug', 'club', 'sistema', 'salidagrupoid'] as $col) {
    if (api_column_exists($conn, $vTable, $col)) $select[] = "v.`$col`";
}

$where = "v.torneoid = $tid AND v.fecha_juego BETWEEN '$fec' AND '$fecHasta' AND v.`$vCampo` = $campo AND v.categoriaid = $cat";
if ($sistemaFiltro !== '' && api_column_exists($conn, $vTable, 'sistema')) {
    $where .= " AND UPPER(v.sistema) = UPPER('" . esc($conn, $sistemaFiltro) . "')";
}
$order = [];
if (api_column_exists($conn, $vTable, 'horainicio1a')) $order[] = 'v.horainicio1a';
if (api_column_exists($conn, $vTable, 'salidagrupoid')) $order[] = 'v.salidagrupoid';
$order[] = 'v.apellido';
$sql = 'SELECT ' . implode(', ', array_unique($select)) . " FROM $vTable v WHERE $where ORDER BY " . implode(', ', $order);
debug_log_query('ALEIN printable players', $sql);
$players = query_all($conn, $sql);

$category = query_one($conn, "SELECT * FROM categorias WHERE categoria_id = $cat LIMIT 1") ?: [];
$course = query_one($conn, "SELECT campo FROM campos WHERE id = $campo LIMIT 1") ?: [];
$teeRows = query_all($conn, 'SELECT id, tee, color, bgcolor FROM salidas');
$teeById = [];
$teeByName = [];
foreach ($teeRows as $tee) {
    $teeById[(string)$tee['id']] = $tee;
    $teeByName[strtoupper(trim((string)$tee['tee']))] = $tee;
    $teeByName[strtoupper(trim((string)$tee['color']))] = $tee;
}

// Duración configurada de cada hoyo para calcular PAR TIME.
$minutes = array_fill(0, 18, 0);
if (api_column_exists($conn, 'hoyos', 'minutos')) {
    foreach (query_all($conn, "SELECT numero, minutos FROM hoyos WHERE id_campo = $campo ORDER BY numero") as $h) {
        $n = (int)$h['numero'];
        if ($n >= 1 && $n <= 18) $minutes[$n - 1] = (int)$h['minutos'];
    }
}

$holeCampo = api_first_existing_column($conn, 'hoyosxsalida', ['id_campo', 'campoid']);
$holeTee = api_first_existing_column($conn, 'hoyosxsalida', ['salida', 'salidaid']);
$ctCampo = api_first_existing_column($conn, 'campo_tee', ['id_campo', 'campoid']);
$ctTee = api_first_existing_column($conn, 'campo_tee', ['id_tee', 'salidaid']);
$holeCache = [];
$cards = [];

foreach ($players as $player) {
    $teeRaw = trim((string)($player['teesalidaid'] ?? $player['tee_salida'] ?? $category['salida'] ?? ''));
    $tee = isset($teeById[$teeRaw]) ? $teeById[$teeRaw] : ($teeByName[strtoupper($teeRaw)] ?? null);
    $teeId = $tee ? (string)$tee['id'] : (ctype_digit($teeRaw) ? $teeRaw : '0');
    $cacheKey = $campoid . ':' . $teeId;

    if (!isset($holeCache[$cacheKey])) {
        $holes = [];
        if ($holeCampo && $holeTee && $teeId !== '0') {
            $holeRows = query_all($conn, "SELECT numero, par, yardaje FROM hoyosxsalida
                                         WHERE `$holeCampo` = $campo AND `$holeTee` = " . esc($conn, $teeId) . '
                                         ORDER BY numero');
            foreach ($holeRows as $h) {
                $holes[(int)$h['numero']] = ['par' => (int)$h['par'], 'yards' => (int)$h['yardaje']];
            }
        }
        $needsFallback = count($holes) < 18 || array_sum(array_column($holes, 'yards')) <= 0;
        if ($needsFallback && $ctCampo && $ctTee && $teeId !== '0') {
            $ct = query_one($conn, "SELECT parcampohoyo, yardaje FROM campo_tee
                                    WHERE `$ctCampo` = $campo AND `$ctTee` = " . esc($conn, $teeId) . ' LIMIT 1');
            $pars = alein_csv_ints($ct['parcampohoyo'] ?? '');
            $yards = alein_csv_ints($ct['yardaje'] ?? '');
            for ($i = 1; $i <= 18; $i++) {
                if (!isset($holes[$i])) $holes[$i] = ['par' => 0, 'yards' => 0];
                if ($holes[$i]['par'] <= 0) $holes[$i]['par'] = $pars[$i - 1] ?? 0;
                if ($holes[$i]['yards'] <= 0) $holes[$i]['yards'] = $yards[$i - 1] ?? 0;
            }
        }
        ksort($holes);
        $holeCache[$cacheKey] = $holes;
    }

    $startHoleRaw = strtoupper(trim((string)($player['teesal'] ?? '1')));
    preg_match('/(\d{1,2})/', $startHoleRaw, $startMatch);
    $startHole = max(1, min(18, (int)($startMatch[1] ?? 1)));
    $startTime = alein_hhmm($player['horainicio1a'] ?? '');
    $clock = $startTime !== '' ? DateTime::createFromFormat('H:i', $startTime) : false;
    $parTimes = array_fill(0, 18, '');
    if ($clock) {
        for ($offset = 0; $offset < 18; $offset++) {
            $holeNumber = (($startHole - 1 + $offset) % 18) + 1;
            $clock->modify('+' . max(0, $minutes[$holeNumber - 1]) . ' minutes');
            $parTimes[$holeNumber - 1] = $clock->format('H:i');
        }
    }

    $holes = [];
    for ($i = 1; $i <= 18; $i++) {
        $info = $holeCache[$cacheKey][$i] ?? ['par' => 0, 'yards' => 0];
        $holes[] = [
            'number' => $i,
            'yards' => (int)$info['yards'],
            'par' => (int)$info['par'],
            'parTime' => $parTimes[$i - 1],
        ];
    }

    $club = trim((string)($player['clubjug'] ?? $player['club'] ?? ''));
    $system = trim((string)($player['sistema'] ?? $category['sistema'] ?? ''));
    $cards[] = [
        'id' => (string)($player['tarjetaid'] ?? $player['jugadorid']),
        'folio' => (string)($player['tarjetaid'] ?? $player['numjugador'] ?? $player['jugadorid']),
        'playerNumber' => (string)($player['jugadorid'] ?? ''),
        'playerName' => trim(($player['nombre'] ?? '') . ' ' . ($player['apellido'] ?? '')),
        'club' => $club,
        'category' => $category['categoria'] ?? '',
        'tee' => trim((string)($player['tee'] ?? ($tee['tee'] ?? $teeRaw))),
        'teeColor' => trim((string)($tee['color'] ?? $tee['bgcolor'] ?? '')),
        'startHole' => $startHole,
        'startTime' => $startTime,
        'system' => $system,
        'course' => $course['campo'] ?? '',
        'date' => (string)($player['fecha_juego'] ?? $fecha),
        'holes' => $holes,
    ];
}

$basePayload['cards'] = $cards;
json_response($basePayload);