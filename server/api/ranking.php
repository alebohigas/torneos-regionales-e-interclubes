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
