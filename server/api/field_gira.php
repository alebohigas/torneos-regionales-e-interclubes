<?php
/**
 * FIELD-GIRA Endpoint
 *
 * Duplicado funcional de players.php / categories.php pero leyendo las tablas
 * "seed" de la gira:
 *   - categorias_tmp (categoriasTmp_id, categoria)
 *   - jugadores_seed (id, numjugador, nombre, apellido, id_club, categoriaid, fechanac)
 *   - clubs (id, logo)
 *
 * Modos:
 *   GET /api/field_gira.php                -> lista de categorías con conteo
 *   GET /api/field_gira.php?catid=NN       -> categoría + jugadores de esa categoría
 */
require_once 'config.php';

/** Verifica existencia de columna sin romper instalaciones legacy. */
function fg_column_exists($conn, $table, $column) {
    $t = esc($conn, $table);
    $c = esc($conn, $column);
    $r = @$conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    $exists = $r && $r->num_rows > 0;
    if ($r) $r->free();
    return $exists;
}

$catid = isset($_GET['catid']) ? trim((string)$_GET['catid']) : '';

/** Filtro opcional por gira: solo si la tabla lo soporta y llega el parámetro. */
$giraFilter = '';
$giraid = isset($_GET['giraid']) ? trim((string)$_GET['giraid']) : '';
if ($giraid !== '' && ctype_digit($giraid) && fg_column_exists($conn, 'categorias_tmp', 'giraid')) {
    $giraFilter = ' AND a.giraid = ' . (int)$giraid . ' ';
}

// ============= Modo lista de categorías =============
if ($catid === '') {
    $sql  = "SELECT a.categoriasTmp_id, a.categoria, COUNT(*) AS tot ";
    $sql .= "FROM categorias_tmp AS a JOIN jugadores_seed AS b ON (a.categoriasTmp_id = b.categoriaid) ";
    $sql .= "WHERE 1=1 $giraFilter ";
    $sql .= "GROUP BY a.categoriasTmp_id, a.categoria ";
    $sql .= "ORDER BY a.categoriasTmp_id ASC";

    $rows = query_all($conn, $sql);
    $categories = array_map(function ($row) {
        return [
            'id'          => (string)$row['categoriasTmp_id'],
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

// ============= Modo detalle: categoría + jugadores =============
$catSql  = "SELECT a.categoriasTmp_id, a.categoria, COUNT(*) AS tot ";
$catSql .= "FROM categorias_tmp AS a JOIN jugadores_seed AS b ON (a.categoriasTmp_id = b.categoriaid) ";
$catSql .= "WHERE a.categoriasTmp_id = $cid ";
$catSql .= "GROUP BY a.categoriasTmp_id, a.categoria";
$catRow = query_one($conn, $catSql);

$plSql  = "SELECT a.id, a.numjugador, CONCAT(a.nombre, ' ', a.apellido) AS jugador, b.logo, a.fechanac ";
$plSql .= "FROM jugadores_seed AS a JOIN clubs AS b ON (a.id_club = b.id) ";
$plSql .= "WHERE a.categoriaid = $cid ";
$plSql .= "ORDER BY a.nombre, a.apellido";
$plRows = query_all($conn, $plSql);

$players = array_map(function ($row) use ($LOGOS_BASE_URL) {
    $fecha = trim((string)($row['fechanac'] ?? ''));
    if ($fecha === '' || strpos($fecha, '0000-00-00') === 0 || strpos($fecha, '1900-01-01') === 0) {
        $fecha = '';
    }
    return [
        'id'         => (string)$row['id'],
        'numjugador' => (string)($row['numjugador'] ?? ''),
        'jugador'    => $row['jugador'] ?? '',
        'logo'       => !empty($row['logo']) ? $LOGOS_BASE_URL . $row['logo'] : '',
        'fechanac'   => $fecha,
    ];
}, $plRows);

json_response([
    'category' => [
        'id'          => (string)($catRow['categoriasTmp_id'] ?? $cid),
        'name'        => $catRow['categoria'] ?? '',
        'playerCount' => isset($catRow['tot']) ? (int)$catRow['tot'] : count($players),
    ],
    'players' => $players,
]);
