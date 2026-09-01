<?php
/**
 * Categories Endpoint
 * GET /api/categories.php?torneoid=XXX
 * Returns all active categories with player counts
 */
require_once 'config.php';

$torneoid = require_torneoid($conn);
$tid = esc($conn, $torneoid);

/**
 * Check whether a table exists without throwing SQL output into the JSON body.
 * Used here because some deployed tournament databases lag behind optional
 * Pre-Registro schema additions.
 */
function categories_table_exists($conn, $table) {
    $table = esc($conn, $table);
    $r = @$conn->query("SHOW TABLES LIKE '$table'");
    $exists = $r && $r->num_rows > 0;
    if ($r) $r->free();
    return $exists;
}

/**
 * Check whether a column exists in a table. Keeps categories.php backwards
 * compatible with older IONOS MySQL schemas where registro columns may differ.
 */
function categories_column_exists($conn, $table, $column) {
    $table = esc($conn, $table);
    $column = esc($conn, $column);
    $r = @$conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    $exists = $r && $r->num_rows > 0;
    if ($r) $r->free();
    return $exists;
}

/**
 * Return the first existing column from a list of legacy-compatible names.
 */
function categories_first_existing_column($conn, $table, $columns) {
    foreach ($columns as $column) {
        if (categories_column_exists($conn, $table, $column)) return $column;
    }
    return null;
}

/**
 * Optional `?skin=1` flag.
 * When set, the endpoint returns only categories that have at least one
 * player enrolled in the SKIN GAME (jugadores.Skeenjuga = 1), and the
 * `playerCount` reflects only those skin-enrolled players. Also filters
 * out related sub-categories (categorias.catrel <> 0) so the /skinplayers
 * grid mirrors the legacy `jugadores_skin.php` view.
 */
$skinOnly = isset($_GET['skin']) && $_GET['skin'] === '1';
/** Las columnas de skin no existen en todas las bases (esquema golftour). */
$hasSkeenjuga = categories_column_exists($conn, 'jugadores', 'Skeenjuga');
$hasCatrel    = categories_column_exists($conn, 'categorias', 'catrel');
$playerJoinCond = ($skinOnly && $hasSkeenjuga)
    ? "(a.categoria_id = b.categoriaid AND b.Skeenjuga = 1)"
    : "(a.categoria_id = b.categoriaid)";
$skinCatFilter = ($skinOnly && $hasCatrel) ? " AND a.catrel = 0 " : '';


/**
 * Optional `?withplayers=1` flag (usado por /jugadores).
 * Replica el query legacy:
 *   SELECT a.categoria_id, a.torneo_id, a.categoria, count(*) as tot
 *   FROM categorias a JOIN jugadores b ON (a.categoria_id = b.categoriaid)
 *   WHERE a.estatus > 0 AND a.torneo_id = $torneoid
 *   GROUP BY categoria_id, a.torneo_id, a.categoria
 * Es decir: solo categorías con al menos un jugador inscrito (INNER JOIN),
 * de modo que el total de la página coincida con el conteo real del torneo.
 */
$onlyWithPlayers = isset($_GET['withplayers']) && $_GET['withplayers'] === '1';


/** Detect new optional age-range columns added for the Pre-Registro feature. */
$ageMinExists = $conn->query("SHOW COLUMNS FROM categorias LIKE 'age_range_min'");
$ageMinExists = $ageMinExists && $ageMinExists->num_rows > 0;
$ageMaxExists = $conn->query("SHOW COLUMNS FROM categorias LIKE 'age_range_max'");
$ageMaxExists = $ageMaxExists && $ageMaxExists->num_rows > 0;
$ageMinSel = $ageMinExists ? ', a.age_range_min' : '';
$ageMaxSel = $ageMaxExists ? ', a.age_range_max' : '';

/**
 * registeredCount source for Pre-Registro availability.
 *
 * Recent behavior: count existing rows in `registro` for the same tournament
 * and category so the public form shows the real queue/lista de espera. This
 * block is defensive: if an older production DB is missing `registro` or uses
 * a legacy tournament/category column name, the endpoint still returns
 * categories with registeredCount=0 instead of failing the whole dropdown.
 */
$registeredCountSelect = '0 AS registeredCount';
if (categories_table_exists($conn, 'registro')) {
    $registroTorneoCol = categories_first_existing_column($conn, 'registro', [
        'reg_id_torneo', 'torneo_id', 'id_torneo', 'idtorneo', 'reg_torneoid', 'reg_torneo_id', 'torneoid'
    ]);
    $registroCategoriaCol = categories_first_existing_column($conn, 'registro', [
        'reg_categoria', 'categoriaid', 'categoria_id', 'catid'
    ]);
    if ($registroTorneoCol && $registroCategoriaCol) {
        $statusFilter = categories_column_exists($conn, 'registro', 'status_pago')
            ? ' AND (r.`status_pago` IS NULL OR r.`status_pago` <> 99)'
            : '';
        $registeredCountSelect = "(SELECT COUNT(*) FROM registro r
                   WHERE r.`$registroTorneoCol` = a.torneo_id
                     AND r.`$registroCategoriaCol` = a.categoria_id$statusFilter) AS registeredCount";
    }
}

/**
 * Query: categorías con conteo de jugadores, tee, rating y slope.
 *
 * IMPORTANTE (esquema golftour): varias columnas opcionales y los nombres de
 * `campo_tee` cambian entre bases. Se construye la lista de columnas de forma
 * dinámica para no romper el endpoint con "Unknown column" (HTTP 500).
 */
$optionalCats = [
    'abreviatura', 'sistema', 'formato', 'estilo', 'hcpIdxMin', 'hcpIdxMax',
    'porcentaje', 'hoyosajugar', 'hoyosacorte', 'salida', 'gross', 'catrel',
    'sexo', 'corte', 'maxjugadores', 'hoyosxronda', 'Skin_grupo_id', 'Skeenporcent',
];
if ($ageMinExists) $optionalCats[] = 'age_range_min';
if ($ageMaxExists) $optionalCats[] = 'age_range_max';

$catCols = ['a.categoria_id', 'a.torneo_id', 'a.categoria'];
foreach ($optionalCats as $c) {
    if (categories_column_exists($conn, 'categorias', $c)) $catCols[] = "a.`$c`";
}
$catColsSql = implode(', ', $catCols);

/** Tee (salidas) — opcional: requiere categorias.salida. */
$hasSalidaCol = categories_column_exists($conn, 'categorias', 'salida');
$teeSel  = '';
$teeJoin = '';
$teeGrp  = '';
if ($hasSalidaCol && categories_table_exists($conn, 'salidas')) {
    $teeSel  = ", s.tee AS teeName" . (categories_column_exists($conn, 'salidas', 'color') ? ", s.color AS teeColorName" : ", '' AS teeColorName");
    $teeJoin = " LEFT JOIN salidas s ON (a.salida = s.id) ";
    $teeGrp  = ", s.tee" . (categories_column_exists($conn, 'salidas', 'color') ? ", s.color" : '');
}

/** campo_tee: esquema nuevo (campoid/salidaid) o golftour (id_campo/id_tee). */
$ctSel = ", NULL AS rating, NULL AS slope, NULL AS parcampo";
$ctJoin = '';
$ctGrp = '';
if ($hasSalidaCol && categories_table_exists($conn, 'campo_tee')) {
    $ctCampoCol = categories_first_existing_column($conn, 'campo_tee', ['campoid', 'id_campo']);
    $ctTeeCol   = categories_first_existing_column($conn, 'campo_tee', ['salidaid', 'id_tee']);
    if ($ctCampoCol && $ctTeeCol) {
        $ctSel  = ", ct.rating, ct.slope, ct.parcampo";
        $ctJoin = " LEFT JOIN campo_tee ct ON (ct.`$ctTeeCol` = a.salida AND ct.`$ctCampoCol` = (
                        SELECT campo FROM caljuego WHERE categoriaid = a.categoria_id LIMIT 1
                    )) ";
        $ctGrp  = ", ct.rating, ct.slope, ct.parcampo";
    }
}

$sql = "SELECT $catColsSql,
               COUNT(b.id) as playerCount,
               $registeredCountSelect$teeSel$ctSel
        FROM categorias a
        LEFT JOIN jugadores b ON $playerJoinCond
        $teeJoin
        $ctJoin
        WHERE a.estatus > 0 AND a.torneo_id = $tid $skinCatFilter
        GROUP BY $catColsSql$teeGrp$ctGrp
        " . (($skinOnly || $onlyWithPlayers) ? " HAVING playerCount > 0 " : "") . "
        ORDER BY a.categoria_id ASC";


$rows = query_all($conn, $sql);

/** Map DB rows to JSON response format */
$categories = array_map(function($row) {
    return [
        'id'          => $row['categoria_id'],
        'name'        => $row['categoria'],
        'shortName'   => $row['abreviatura'] ?? ($row['categoria'] ?? ''),
        'system'      => $row['sistema'] ?? '',
        'format'      => $row['formato'] ?? '',
        'style'       => $row['estilo'] ?? '',
        'hcpMin'      => (float)($row['hcpIdxMin'] ?? 0),
        'hcpMax'      => (float)($row['hcpIdxMax'] ?? 0),
        'percentage'  => (float)($row['porcentaje'] ?? 0),
        /** Valor textual EXACTO de categorias.porcentaje tal como está en la BD
         *  (p.ej. "80.00", "87.5"). El frontend lo usa en la columna VENTAJAS
         *  de /convocatoria para respetar el redondeo/decimales originales. */
        'percentageRaw' => isset($row['porcentaje']) && $row['porcentaje'] !== null
                            ? (string)$row['porcentaje']
                            : null,
        'holes'       => (int)($row['hoyosajugar'] ?? 0),
        'cutHoles'    => (int)($row['hoyosacorte'] ?? 0),
        // Final cut count (categorias.corte) — number of players advancing to the final round.
        'finalCut'    => isset($row['corte']) ? (int)$row['corte'] : 0,
        'teeId'       => $row['salida'] ?? '',
        'gross'       => (int)($row['gross'] ?? 0),
        'relatedCat'  => $row['catrel'] ?? 0,
        'gender'      => $row['sexo'] ?? '',

        'playerCount' => (int)$row['playerCount'],
        /** True cuando la categoría es de parejas (formato='PAREJAS'). El frontend
         *  usa esto en /jugadores y /resultados para activar agrupación por grupoid
         *  y tarjetas de parejas (Go Go, Bola Baja, Suma Scores).
         */
        'isParejas'   => (strtoupper($row['formato'] ?? '') === 'PAREJAS'),
        // Number of players actively registered in this category via the
        // Pre-Registro flow (jugadores.tipoinsc=1 AND tipoinsc2=3). Used by
        // the public registration form to display "spots available" next to
        // each category in the dropdown.
        'registeredCount' => isset($row['registeredCount']) ? (int)$row['registeredCount'] : 0,
        'maxPlayers'  => isset($row['maxjugadores']) ? (int)$row['maxjugadores'] : 0,
        'holesPerRound'=> isset($row['hoyosxronda']) ? (int)$row['hoyosxronda'] : 18,
        'teeName'     => $row['teeName'] ?? '',
        'teeColorName'=> $row['teeColorName'] ?? '',
        'rating'      => $row['rating'] !== null ? (float)$row['rating'] : null,
        'slope'       => $row['slope'] !== null ? (int)$row['slope'] : null,
        'par'         => $row['parcampo'] !== null ? (int)$row['parcampo'] : null,
        // Age-range bounds for senior/age-restricted categories. NULL when
        // the column is absent or the value is not configured.
        'ageMin'      => isset($row['age_range_min']) && $row['age_range_min'] !== null ? (int)$row['age_range_min'] : null,
        'ageMax'      => isset($row['age_range_max']) && $row['age_range_max'] !== null ? (int)$row['age_range_max'] : null,
        /** Skin game grouping identifier (categorias.Skin_grupo_id).
         *  Categorías con el mismo Skin_grupo_id comparten bolsa de skins. */
        'skinGroupId' => isset($row['Skin_grupo_id']) ? (string)$row['Skin_grupo_id'] : '',
        /** Porcentaje de handicap aplicado específicamente para el Skin Game
         *  (categorias.Skeenporcent). Distinto de `percentage` regular. */
        'skinPercent' => isset($row['Skeenporcent']) ? (float)$row['Skeenporcent'] : 0,
    ];
}, $rows);

json_response($categories);
