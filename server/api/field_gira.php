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
 * Siempre se acota por `giraid` cuando llega desde el frontend, para no mezclar
 * jugadores_seed de todas las giras/copas históricas.
 *
 * Modos:
 *   GET /api/field_gira.php?giraid=NN              -> lista de categorías con conteo
 *   GET /api/field_gira.php?giraid=NN&catid=NN     -> categoría + jugadores
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

/** Verifica existencia de tabla sin romper instalaciones legacy. */
function fg_table_exists($conn, $table) {
    $t = esc($conn, $table);
    $r = @$conn->query("SHOW TABLES LIKE '$t'");
    $exists = $r && $r->num_rows > 0;
    if ($r) $r->free();
    return $exists;
}

/** Devuelve la primera columna existente de una lista de nombres legacy. */
function fg_first_existing_column($conn, $table, $columns) {
    foreach ($columns as $column) {
        if (fg_column_exists($conn, $table, $column)) return $column;
    }
    return null;
}

/** Sanitiza una lista de ids enteros para usar en un IN (...). */
function fg_int_list_sql($values) {
    $ids = [];
    foreach ($values as $value) {
        $id = (int)$value;
        if ($id > 0) $ids[$id] = true;
    }
    return implode(',', array_keys($ids));
}

/** Copas pertenecientes a una gira, incluyendo los ids listados en grupocopas. */
function fg_copa_ids_for_gira($conn, $giraid) {
    if (!fg_table_exists($conn, 'copas') || !fg_column_exists($conn, 'copas', 'giraid')) return [];
    $idCol = fg_first_existing_column($conn, 'copas', ['copasid', 'copa_id', 'id_copa', 'copaid']);
    if (!$idCol) return [];

    $gid = (int)$giraid;
    $selectGroup = fg_column_exists($conn, 'copas', 'grupocopas') ? ', grupocopas' : '';
    $rows = query_all($conn, "SELECT `$idCol` AS id$selectGroup FROM copas WHERE giraid = $gid");
    $ids = [];
    foreach ($rows as $row) {
        $ids[] = $row['id'];
        if (isset($row['grupocopas'])) {
            foreach (explode(',', (string)$row['grupocopas']) as $piece) {
                $piece = trim($piece);
                if ($piece !== '') $ids[] = $piece;
            }
        }
    }
    return $ids;
}

/** Torneos pertenecientes a una gira. */
function fg_torneo_ids_for_gira($conn, $giraid) {
    if (!fg_table_exists($conn, 'torneo') || !fg_column_exists($conn, 'torneo', 'giraid')) return [];
    $idCol = fg_first_existing_column($conn, 'torneo', ['torneo_id', 'torneoid', 'id_torneo']);
    if (!$idCol) return [];
    $gid = (int)$giraid;
    $rows = query_all($conn, "SELECT `$idCol` AS id FROM torneo WHERE giraid = $gid");
    return array_map(function ($row) { return $row['id']; }, $rows);
}

/**
 * Condición de alcance para una tabla seed. Prioridad:
 * 1) columna giraid directa
 * 2) columna de copa contra copas.giraid / copas.grupocopas
 * 3) columna de torneo contra torneo.giraid
 */
function fg_scope_condition_for_table($conn, $table, $alias, $giraid) {
    if ($giraid === '' || !ctype_digit((string)$giraid)) return '';
    $gid = (int)$giraid;
    $safeAlias = preg_replace('/[^A-Za-z0-9_]/', '', (string)$alias);

    if (fg_column_exists($conn, $table, 'giraid')) {
        return " AND $safeAlias.`giraid` = $gid ";
    }

    $copaCol = fg_first_existing_column($conn, $table, ['copasid', 'copa_id', 'id_copa', 'copaid']);
    if ($copaCol) {
        $copaIds = fg_int_list_sql(fg_copa_ids_for_gira($conn, $gid));
        return $copaIds !== '' ? " AND $safeAlias.`$copaCol` IN ($copaIds) " : ' AND 1=0 ';
    }

    $torneoCol = fg_first_existing_column($conn, $table, ['torneoid', 'torneo_id', 'id_torneo', 'torneo']);
    if ($torneoCol) {
        $torneoIds = fg_int_list_sql(fg_torneo_ids_for_gira($conn, $gid));
        return $torneoIds !== '' ? " AND $safeAlias.`$torneoCol` IN ($torneoIds) " : ' AND 1=0 ';
    }

    return '';
}

/** Acota el JOIN categorias_tmp + jugadores_seed a la gira activa. */
function fg_join_scope_condition($conn, $giraid) {
    if ($giraid === '' || !ctype_digit((string)$giraid)) return '';
    $catScope = fg_scope_condition_for_table($conn, 'categorias_tmp', 'a', $giraid);
    $playerScope = fg_scope_condition_for_table($conn, 'jugadores_seed', 'b', $giraid);
    if ($catScope === '' && $playerScope === '') {
        // Si llega giraid pero no existe forma de acotar, es más seguro no mezclar
        // todas las giras históricas.
        return ' AND 1=0 ';
    }
    return $catScope . $playerScope;
}

$catid = isset($_GET['catid']) ? trim((string)$_GET['catid']) : '';

$giraid = isset($_GET['giraid']) ? trim((string)$_GET['giraid']) : '';
$joinGiraFilter = fg_join_scope_condition($conn, $giraid);
$playersGiraFilter = fg_scope_condition_for_table($conn, 'jugadores_seed', 'a', $giraid);

// ============= Modo lista de categorías =============
if ($catid === '') {
    $sql  = "SELECT a.categoriasTmp_id, a.categoria, COUNT(DISTINCT b.id) AS tot ";
    $sql .= "FROM categorias_tmp AS a JOIN jugadores_seed AS b ON (a.categoriasTmp_id = b.categoriaid) ";
    $sql .= "WHERE 1=1 $joinGiraFilter ";
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
$catSql  = "SELECT a.categoriasTmp_id, a.categoria, COUNT(DISTINCT b.id) AS tot ";
$catSql .= "FROM categorias_tmp AS a JOIN jugadores_seed AS b ON (a.categoriasTmp_id = b.categoriaid) ";
$catSql .= "WHERE a.categoriasTmp_id = $cid $joinGiraFilter ";
$catSql .= "GROUP BY a.categoriasTmp_id, a.categoria";
$catRow = query_one($conn, $catSql);

$plSql  = "SELECT a.id, a.numjugador, CONCAT(a.nombre, ' ', a.apellido) AS jugador, b.logo, a.fechanac ";
$plSql .= "FROM jugadores_seed AS a JOIN clubs AS b ON (a.id_club = b.id) ";
$plSql .= "WHERE a.categoriaid = $cid $playersGiraFilter ";
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
