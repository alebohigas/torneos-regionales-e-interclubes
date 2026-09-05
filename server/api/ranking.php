<?php
/**
 * RANKING Endpoint (gira)
 *
 * Réplica JSON de los legacy `ranking.php` + `lista_ranking.php`:
 *   - Categorías: jugadores_seed + jugadores + categorias, agrupadas por catidoriginal
 *   - Jugadores:  suma de `puntos` por jugador dentro del catidoriginal, con club/logo
 *
 * Modos:
 *   GET /api/ranking.php?giraid=NN            -> lista de categorías (catidoriginal)
 *   GET /api/ranking.php?giraid=NN&catid=NN   -> categoría + ranking de jugadores
 */
require_once 'config.php';

$giraid = isset($_GET['giraid']) ? trim((string)$_GET['giraid']) : '';
if (!ctype_digit($giraid) || (int)$giraid <= 0) {
    json_error('giraid requerido');
}
$gid = (int)$giraid;

$catid = isset($_GET['catid']) ? trim((string)$_GET['catid']) : '';

// ============= Modo detalle por jugador (numjug) =============
// Desglose de las etapas de la gira: score, lugar y puntos por etapa,
// resaltando las etapas cuyos puntos cuentan (top5 = 1).
$numjug = isset($_GET['numjug']) ? trim((string)$_GET['numjug']) : '';
if ($numjug !== '') {
    $nj = esc($conn, $numjug);
    $torneoPk = api_first_existing_column($conn, 'torneo', ['torneo_id', 'torneoid', 'id']) ?: 'torneo_id';
    $hasTop5  = api_column_exists($conn, 'jugadores', 'top5');
    $hasTotso = api_column_exists($conn, 'jugadores', 'totso');
    $hasPos   = api_column_exists($conn, 'jugadores', 'posptos');
    $jugIdCol = api_first_existing_column($conn, 'jugadores', ['id', 'jugador_id', 'jugadorid']) ?: 'id';

    $etapaRows = query_all($conn, "SELECT `$torneoPk` AS id, nombre
                                   FROM torneo WHERE giraid = $gid
                                   ORDER BY LEFT(TRIM(nombre), 7) ASC, `$torneoPk` ASC");

    $sel = "j.`$jugIdCol` AS jid, j.nombre, j.apellido, j.puntos, j.estatus"
         . ($hasTop5 ? ", j.top5" : ", 0 AS top5")
         . ($hasTotso ? ", j.totso" : ", NULL AS totso")
         . ($hasPos ? ", j.posptos" : ", NULL AS posptos");

    $name = '';
    $etapasOut = [];
    $totalCounted = 0.0;
    $totalAll = 0.0;

    foreach ($etapaRows as $er) {
        $tid = (int)$er['id'];
        $label = trim(preg_replace('/\s+/u', ' ', (string)($er['nombre'] ?? '')));
        $label = $label === '' ? '' : explode(' ', $label)[0];

        $jug = query_one($conn, "SELECT $sel FROM jugadores j
                                 WHERE j.numjugador = '$nj' AND j.torneoid = $tid LIMIT 1");
        if (!$jug) continue;

        if ($name === '') {
            $name = trim(((string)($jug['nombre'] ?? '')) . ' ' . ((string)($jug['apellido'] ?? '')));
        }

        $puntos = isset($jug['puntos']) ? round((float)$jug['puntos'], 1) : 0;
        $counted = (int)($jug['top5'] ?? 0) === 1;
        $totalAll += (float)$puntos;
        if ($counted) $totalCounted += (float)$puntos;

        // Score de la etapa: totso si existe, si no la suma de las tarjetas.
        $score = isset($jug['totso']) && $jug['totso'] !== null ? (int)$jug['totso'] : null;
        if ($score === null || $score === 0) {
            $card = query_one($conn, "SELECT SUM(t.so) AS tot FROM tarjetas t
                                      WHERE t.jugadorid = " . (int)($jug['jid'] ?? 0) . "
                                        AND t.torneoid = $tid");
            if ($card && $card['tot'] !== null) $score = (int)$card['tot'];
        }

        $etapasOut[] = [
            'torneoid' => (string)$tid,
            'etapa'    => $label,
            'nombre'   => $er['nombre'] ?? '',
            'score'    => $score,
            'lugar'    => isset($jug['posptos']) && $jug['posptos'] !== null ? (int)$jug['posptos'] : null,
            'puntos'   => $puntos,
            'counted'  => $counted,
            'estatus'  => strtoupper((string)($jug['estatus'] ?? '')),
        ];
    }

    json_response([
        'numjugador'   => $numjug,
        'jugador'      => $name,
        'etapas'       => $etapasOut,
        'totalPuntos'  => round($totalAll, 1),
        'totalContado' => round($totalCounted, 1),
    ]);
}


// ============= Modo lista de categorías =============
if ($catid === '') {
    $sql  = "SELECT c.catidoriginal, MIN(c.categoria) AS categoria, ";
    $sql .= "COUNT(DISTINCT b.numjugador) AS tot ";
    $sql .= "FROM jugadores_seed AS a ";
    $sql .= "JOIN jugadores AS b ON (a.numjugador = b.numjugador AND a.giraid = $gid) ";
    $sql .= "JOIN categorias AS c ON (b.categoriaid = c.categoria_id) ";
    $sql .= "WHERE c.catidoriginal IS NOT NULL AND c.catidoriginal > 0 ";
    $sql .= "GROUP BY c.catidoriginal ";
    $sql .= "ORDER BY c.catidoriginal ASC";

    $rows = query_all($conn, $sql);
    $categories = array_map(function ($row) {
        return [
            'id'          => (string)$row['catidoriginal'],
            'name'        => $row['categoria'] ?? '',
            'shortName'   => $row['categoria'] ?? '',
            'playerCount' => (int)$row['tot'],
        ];
    }, $rows);

    json_response($categories);
}

if (!ctype_digit($catid)) {
    json_error('catid inválido');
}
$cid = (int)$catid;

// ============= Modo detalle: ranking de la categoría =============
$sql  = "SELECT a.catidoriginal, a.categoria, b.numjugador, ";
$sql .= "CONCAT(b.nombre, ' ', b.apellido) AS jugador, ";
$sql .= "SUM(ROUND(b.puntos, 1)) AS puntos, c.nombre AS club, c.logo ";
$sql .= "FROM categorias AS a ";
$sql .= "JOIN jugadores AS b ON (a.categoria_id = b.categoriaid) ";
$sql .= "JOIN jugadores_seed AS s ON (b.numjugador = s.numjugador AND s.giraid = $gid) ";
$sql .= "JOIN clubs AS c ON (s.id_club = c.id) ";
$sql .= "WHERE a.catidoriginal = $cid ";
$sql .= "GROUP BY a.catidoriginal, a.categoria, b.numjugador, c.nombre, c.logo ";
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
        'club'       => $row['club'] ?? '',
        'logo'       => !empty($row['logo']) ? $LOGOS_BASE_URL . $row['logo'] : '',
        'puntos'     => isset($row['puntos']) ? (float)$row['puntos'] : 0,
    ];
}

$categoryName = count($rows) > 0 ? ($rows[0]['categoria'] ?? '') : '';

json_response([
    'category' => [
        'id'          => (string)$cid,
        'name'        => $categoryName,
        'playerCount' => count($players),
    ],
    'players' => $players,
]);
