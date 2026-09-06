<?php
/**
 * RANKING FINAL Endpoint (gira, top 5 etapas)
 *
 * Unifica en un solo endpoint lo que en legacy eran 3 archivos
 * (desk_Web_Ranking_21_gira5max.php, Web_ranking_21_gira_det5max.php,
 *  Web_ranking_21_gira_det5max_desk.php + popup_jugador5top.php).
 *
 * Modos:
 *   GET ?giraid=NN                      -> categorías de la gira (categorias_tmp)
 *   GET ?giraid=NN&catid=NN             -> ranking de la categoría (suma top5)
 *   GET ?giraid=NN&numjug=XXX           -> desglose por etapa de un jugador
 *
 * Reglas legacy respetadas:
 *   - Puntos del ranking = SUM(puntos) solo de los torneos marcados top5 = 1
 *   - `puntosx` = suma de todos los puntos (referencia)
 *   - Se excluyen jugadores con estatus 'PENALTY' y con 0 puntos
 *   - Los empates comparten posición
 */
require_once 'config.php';

$giraid = isset($_GET['giraid']) ? trim((string)$_GET['giraid']) : '';
if (!ctype_digit($giraid) || (int)$giraid <= 0) {
    json_error('giraid requerido');
}
$gid = (int)$giraid;

/** Columnas clave de categorias_tmp / categorias / clubs (esquemas legacy). */
$tmpIdCol  = api_first_existing_column($conn, 'categorias_tmp', ['categoriasTmp_id', 'categoriastmp_id', 'categoria_id', 'id']);
$tmpNomCol = api_first_existing_column($conn, 'categorias_tmp', ['categoria', 'nombre']);
$catPkCol  = api_first_existing_column($conn, 'categorias', ['categoria_id', 'categoriaid', 'id']);
$clubIdCol = api_first_existing_column($conn, 'clubs', ['id', 'club_id', 'clubid']);
$clubNomCol = api_first_existing_column($conn, 'clubs', ['nombre', 'club']);

if (!$tmpIdCol || !$tmpNomCol || !$catPkCol) {
    json_error('Esquema incompatible: falta categorias_tmp / categorias', 500, [
        'tmpIdCol' => $tmpIdCol, 'tmpNomCol' => $tmpNomCol, 'catPkCol' => $catPkCol,
    ]);
}

/** Torneos (etapas) de la gira, ordenados como el legacy: LEFT(nombre, 7). */
function rf_etapas($conn, $gid) {
    $torneoPk = api_first_existing_column($conn, 'torneo', ['torneo_id', 'torneoid', 'id']);
    if (!$torneoPk) return [];
    $rows = query_all($conn, "SELECT `$torneoPk` AS id, nombre, fecha_ini
                              FROM torneo WHERE giraid = $gid
                              ORDER BY LEFT(TRIM(nombre), 7) ASC, `$torneoPk` ASC");
    $etapas = [];
    $seq = 0;
    foreach ($rows as $row) {
        $seq++;
        $clean = trim(preg_replace('/\s+/u', ' ', (string)($row['nombre'] ?? '')));
        // Etiqueta "ETAPA-[n]": toma el número que acompañe a la palabra etapa;
        // si el nombre no lo trae, usa el orden de la gira.
        if (preg_match('/etapa[\s\-_.]*(\d+)/iu', $clean, $m)) {
            $label = 'ETAPA-' . (int)$m[1];
        } else {
            $first = $clean === '' ? '' : explode(' ', $clean)[0];
            $label = preg_match('/\d/', $first) ? strtoupper($first) : 'ETAPA-' . $seq;
        }
        $etapas[] = [
            'torneoid' => (string)$row['id'],
            'label'    => $label,
            'nombre'   => $row['nombre'] ?? '',
            'fecha'    => $row['fecha_ini'] ?? '',
        ];
    }
    return $etapas;
}

$etapas = rf_etapas($conn, $gid);
$etapaIds = array_map(function ($e) { return (int)$e['torneoid']; }, $etapas);
$inTorneos = count($etapaIds) > 0 ? implode(',', $etapaIds) : '0';

// ================= Modo desglose por jugador =================
$numjug = isset($_GET['numjug']) ? trim((string)$_GET['numjug']) : '';
if ($numjug !== '') {
    $nj = esc($conn, $numjug);
    $hasTop5 = api_column_exists($conn, 'jugadores', 'top5');
    $hasPos  = api_column_exists($conn, 'jugadores', 'posptos');
    $jugIdCol = api_first_existing_column($conn, 'jugadores', ['id', 'jugador_id', 'jugadorid']) ?: 'id';

    $name = '';
    $rows = [];
    $totalTop5 = 0.0;
    $totalAll = 0.0;

    foreach ($etapas as $etapa) {
        $tid = (int)$etapa['torneoid'];
        $sel = "SELECT j.`$jugIdCol` AS jid, j.numjugador, j.nombre, j.apellido, j.puntos,
                       LEFT(COALESCE(j.estatus, 'NORMAL'), 1) AS est"
             . ($hasTop5 ? ", j.top5" : ", 0 AS top5")
             . ($hasPos ? ", j.posptos" : ", NULL AS posptos") . "
                FROM jugadores j
                WHERE j.numjugador = '$nj' AND j.torneoid = $tid LIMIT 1";
        $jug = query_one($conn, $sel);

        $entry = [
            'torneoid'  => (string)$tid,
            'etapa'     => $etapa['label'],
            'nombre'    => $etapa['nombre'],
            'played'    => false,
            'puntos'    => 0,
            'counted'   => false,
            'estatus'   => '',
            'rounds'    => [],
            'total'     => null,
            'lugar'     => null,
        ];

        if ($jug) {
            $entry['played'] = true;
            $entry['puntos'] = isset($jug['puntos']) ? round((float)$jug['puntos'], 1) : 0;
            $entry['counted'] = (int)($jug['top5'] ?? 0) === 1;
            $entry['estatus'] = strtoupper((string)($jug['est'] ?? ''));
            $entry['lugar'] = isset($jug['posptos']) && $jug['posptos'] !== null && $jug['posptos'] !== ''
                ? (int)$jug['posptos'] : null;
            if ($name === '') {
                $name = trim(((string)($jug['nombre'] ?? '')) . ' ' . ((string)($jug['apellido'] ?? '')));
            }
            $totalAll += (float)$entry['puntos'];
            if ($entry['counted']) $totalTop5 += (float)$entry['puntos'];

            /** Rondas del jugador en la etapa (tarjetas.so = score del día). */
            $jid = (int)($jug['jid'] ?? 0);
            $cards = query_all($conn, "SELECT t.so, t.fecha_juego
                                       FROM tarjetas t
                                       JOIN jugadores b ON (t.jugadorid = b.`$jugIdCol`)
                                       WHERE b.numjugador = '$nj' AND t.torneoid = $tid
                                         AND COALESCE(b.estatus, '') <> 'PENALTY'
                                       ORDER BY t.fecha_juego ASC");
            $sum = 0; $any = false;
            foreach ($cards as $card) {
                $so = $card['so'];
                if ($so === null || $so === '') { $entry['rounds'][] = null; continue; }
                $entry['rounds'][] = (int)$so;
                $sum += (int)$so; $any = true;
            }
            if ($any) $entry['total'] = $sum;
        }

        $rows[] = $entry;
    }

    json_response([
        'numjugador'  => $numjug,
        'jugador'     => $name,
        'etapas'      => $rows,
        'totalPuntos' => round($totalTop5, 1),
        'totalTodos'  => round($totalAll, 1),
    ]);
}

// ================= Modo lista de categorías =================
$catid = isset($_GET['catid']) ? trim((string)$_GET['catid']) : '';
if ($catid === '') {
    $sql = "SELECT tmp.`$tmpIdCol` AS id, MIN(tmp.`$tmpNomCol`) AS categoria,
                   COUNT(DISTINCT j.numjugador) AS tot
            FROM categorias_tmp tmp
            JOIN categorias c ON (c.catidoriginal = tmp.`$tmpIdCol`)
            JOIN jugadores j ON (j.categoriaid = c.`$catPkCol` AND j.torneoid IN ($inTorneos))
            WHERE COALESCE(j.estatus, '') <> 'PENALTY'
            GROUP BY tmp.`$tmpIdCol`
            HAVING tot > 0
            ORDER BY tmp.`$tmpIdCol` ASC";
    debug_log_query('ranking_final_cats', $sql);
    $rows = query_all($conn, $sql);

    $categories = array_map(function ($row) {
        return [
            'id'          => (string)$row['id'],
            'name'        => $row['categoria'] ?? '',
            'shortName'   => $row['categoria'] ?? '',
            'playerCount' => (int)$row['tot'],
        ];
    }, $rows);

    json_response($categories);
}

// ================= Modo ranking de la categoría =================
if (!ctype_digit($catid)) {
    json_error('catid inválido');
}
$cid = (int)$catid;
$hasTop5 = api_column_exists($conn, 'jugadores', 'top5');
$top5Expr = $hasTop5 ? "SUM(ROUND(IF(j.top5 = 1, j.puntos, 0), 1))" : "SUM(ROUND(j.puntos, 1))";

$clubSel = ($clubIdCol && $clubNomCol) ? "cl.`$clubNomCol` AS club, cl.logo AS logo" : "'' AS club, '' AS logo";
$clubJoin = ($clubIdCol && api_column_exists($conn, 'jugadores', 'clubid'))
    ? " LEFT JOIN clubs cl ON (j.clubid = cl.`$clubIdCol`) " : '';
if ($clubJoin === '') $clubSel = "'' AS club, '' AS logo";

$sql = "SELECT MIN(tmp.`$tmpNomCol`) AS categoria, j.numjugador,
               TRIM(CONCAT(j.nombre, ' ', j.apellido)) AS jugador,
               $top5Expr AS puntos,
               SUM(ROUND(j.puntos, 1)) AS puntosx,
               COUNT(DISTINCT j.torneoid) AS etapas,
               $clubSel
        FROM categorias_tmp tmp
        JOIN categorias c ON (c.catidoriginal = tmp.`$tmpIdCol`)
        JOIN jugadores j ON (j.categoriaid = c.`$catPkCol` AND j.torneoid IN ($inTorneos))
        $clubJoin
        WHERE tmp.`$tmpIdCol` = $cid AND COALESCE(j.estatus, '') <> 'PENALTY'
        GROUP BY j.numjugador, jugador, club, logo
        HAVING puntos > 0
        ORDER BY puntos DESC";
debug_log_query('ranking_final_players', $sql);
$rows = query_all($conn, $sql);

$players = [];
$pos = 0;
$prevPuntos = null;
$prevPos = 0;
foreach ($rows as $index => $row) {
    $pos = $index + 1;
    $puntos = isset($row['puntos']) ? (float)$row['puntos'] : 0;
    if ($prevPuntos !== null && abs($puntos - $prevPuntos) < 0.0001) {
        $pos = $prevPos; // empate: comparten posición (legacy)
    }
    $prevPuntos = $puntos;
    $prevPos = $pos;

    $players[] = [
        'position'   => $pos,
        'numjugador' => (string)($row['numjugador'] ?? ''),
        'jugador'    => $row['jugador'] ?? '',
        'club'       => $row['club'] ?? '',
        'logo'       => !empty($row['logo']) ? $LOGOS_BASE_URL . $row['logo'] : '',
        'puntos'     => $puntos,
        'puntosx'    => isset($row['puntosx']) ? (float)$row['puntosx'] : 0,
        'etapas'     => (int)($row['etapas'] ?? 0),
    ];
}

$categoryName = count($rows) > 0 ? ($rows[0]['categoria'] ?? '') : '';
if ($categoryName === '') {
    $row = query_one($conn, "SELECT `$tmpNomCol` AS categoria FROM categorias_tmp WHERE `$tmpIdCol` = $cid LIMIT 1");
    $categoryName = $row['categoria'] ?? '';
}

json_response([
    'category' => [
        'id'          => (string)$cid,
        'name'        => $categoryName,
        'playerCount' => count($players),
    ],
    'etapas'  => array_map(function ($e) { return ['torneoid' => $e['torneoid'], 'label' => $e['label']]; }, $etapas),
    'players' => $players,
]);
