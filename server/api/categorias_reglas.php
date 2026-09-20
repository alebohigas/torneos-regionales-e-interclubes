<?php
/**
 * Categorias Reglas Endpoint
 * -----------------------------------------------------------------------
 * Reglas de ELEGIBILIDAD de categoría para el formulario de Pre-Registro.
 * Define qué categorías puede ver un jugador según su edad, género y
 * hándicap. NO contiene precios — esos viven en `registro_precios.php`.
 *
 * GET  /api/categorias_reglas.php?torneoid=XXX
 *      → Lista todas las reglas. Si la tabla está vacía y hay reglas
 *        legacy con restricciones en `registro_precios`, las migra
 *        automáticamente (idempotente).
 *
 * POST /api/categorias_reglas.php
 *      → Admin replace-all. Body: { password, torneoid, rules: [...] }.
 *
 * Tabla: `categorias_reglas` (migración 2026_05_22).
 */
require_once 'config.php';
require_once '_staff_auth.php';

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

/**
 * ¿Existe la tabla? Cacheado.
 * Self-healing: si falta (base nueva sin la migración 2026_05_22) se crea
 * automáticamente para que el admin pueda guardar sin intervención manual.
 */
function reglas_table_exists($conn) {
    static $exists = null;
    if ($exists !== null) return $exists;
    $r = $conn->query("SHOW TABLES LIKE 'categorias_reglas'");
    $exists = $r && $r->num_rows > 0;
    if (!$exists) {
        $ddl = "CREATE TABLE IF NOT EXISTS `categorias_reglas` (
                  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
                  `torneo_id`     INT(11)      NOT NULL,
                  `categoria`     VARCHAR(255) NOT NULL,
                  `genero`        VARCHAR(8)   NULL,
                  `edad_min`      INT(11)      NULL,
                  `edad_max`      INT(11)      NULL,
                  `hcp_min`       DECIMAL(4,1) NULL,
                  `hcp_max`       DECIMAL(4,1) NULL,
                  `display_order` INT(11)      NOT NULL DEFAULT 0,
                  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
                  PRIMARY KEY (`id`),
                  KEY `idx_torneo` (`torneo_id`),
                  KEY `idx_torneo_cat` (`torneo_id`, `categoria`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (@$conn->query($ddl)) {
            $exists = true;
        } else {
            error_log('categorias_reglas auto-create failed: ' . $conn->error);
        }
    }
    return $exists;
}

/** ¿Existe registro_precios? Para detectar legacy data a migrar. */
function precios_legacy_exists($conn) {
    static $e = null;
    if ($e !== null) return $e;
    $r = $conn->query("SHOW TABLES LIKE 'registro_precios'");
    $e = $r && $r->num_rows > 0;
    return $e;
}

/** Normaliza fila de BD a forma JSON consumida por el cliente. */
function normalize_regla($r) {
    return [
        'id'            => (int)$r['id'],
        'categoria'     => $r['categoria'] ?? '',
        'genero'        => $r['genero'] !== null && $r['genero'] !== '' ? $r['genero'] : null,
        'edad_min'      => $r['edad_min'] !== null ? (int)$r['edad_min'] : null,
        'edad_max'      => $r['edad_max'] !== null ? (int)$r['edad_max'] : null,
        'hcp_min'       => $r['hcp_min'] !== null ? (float)$r['hcp_min'] : null,
        'hcp_max'       => $r['hcp_max'] !== null ? (float)$r['hcp_max'] : null,
        'display_order' => (int)$r['display_order'],
        'is_active'     => (int)$r['is_active'] ? 1 : 0,
    ];
}

/**
 * Auto-migración: si `categorias_reglas` está vacía para este torneo y
 * `registro_precios` tiene reglas con cualquier restricción de
 * edad/género/hcp/categoría, las copiamos agrupadas por combinación
 * única. Idempotente: una vez que existen reglas para el torneo, no se
 * vuelve a ejecutar.
 */
function auto_seed_from_precios($conn, $torneoid) {
    if (!precios_legacy_exists($conn)) return 0;
    // ¿Ya hay reglas? Si sí, no tocar nada.
    $r = $conn->query("SELECT COUNT(*) AS n FROM categorias_reglas WHERE torneo_id = $torneoid");
    if ($r && (int)$r->fetch_assoc()['n'] > 0) return 0;

    // Extraer combinaciones únicas de filtros de elegibilidad en registro_precios.
    // Sólo reglas con `categoria` definida (las wildcard no aplican a
    // ninguna categoría específica). Si todos los filtros de elegibilidad
    // son NULL no aporta — se omite.
    $sql = "SELECT DISTINCT categoria, genero, edad_min, edad_max,
                            hcp_min, hcp_max
              FROM registro_precios
             WHERE torneo_id = $torneoid
               AND categoria IS NOT NULL AND categoria <> ''
               AND is_active = 1
               AND (genero IS NOT NULL
                    OR edad_min IS NOT NULL OR edad_max IS NOT NULL
                    OR hcp_min  IS NOT NULL OR hcp_max  IS NOT NULL)";
    $rs = $conn->query($sql);
    if (!$rs || $rs->num_rows === 0) return 0;

    $count = 0;
    $order = 10;
    while ($row = $rs->fetch_assoc()) {
        $cat   = "'" . esc($conn, $row['categoria']) . "'";
        $gen   = $row['genero']   !== null && $row['genero'] !== ''
                    ? "'" . esc($conn, $row['genero']) . "'" : 'NULL';
        $emin  = $row['edad_min'] !== null ? (int)$row['edad_min'] : 'NULL';
        $emax  = $row['edad_max'] !== null ? (int)$row['edad_max'] : 'NULL';
        $hmin  = $row['hcp_min']  !== null ? (float)$row['hcp_min']  : 'NULL';
        $hmax  = $row['hcp_max']  !== null ? (float)$row['hcp_max']  : 'NULL';
        $ins = "INSERT INTO categorias_reglas
                  (torneo_id, categoria, genero, edad_min, edad_max,
                   hcp_min, hcp_max, display_order, is_active)
                VALUES
                  ($torneoid, $cat, $gen, $emin, $emax,
                   $hmin, $hmax, $order, 1)";
        if ($conn->query($ins)) { $count++; $order += 10; }
    }
    return $count;
}

// ===========================================================================
// GET — lista (con auto-seed)
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $torneoid = (int) require_torneoid($conn);

    if (!reglas_table_exists($conn)) {
        json_response(['rules' => [], 'source' => 'no_table']);
    }

    $seeded = auto_seed_from_precios($conn, $torneoid);

    $sql = "SELECT id, categoria, genero, edad_min, edad_max,
                   hcp_min, hcp_max, display_order, is_active
              FROM categorias_reglas
             WHERE torneo_id = $torneoid
             ORDER BY display_order ASC, id ASC";
    $rows = array_map('normalize_regla', query_all($conn, $sql));
    json_response(['rules' => $rows, 'seeded' => $seeded]);
}

// ===========================================================================
// POST — admin replace-all
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) json_error('Invalid JSON body', 400);

    $password = $body['password'] ?? '';
    if (!is_superadmin_password($conn, $password)) {
        $staff = staff_check_area($conn, $body, 'preregistros');
        if (!$staff) json_error('Unauthorized', 401);
    }

    $torneoid = isset($body['torneoid']) ? (int)$body['torneoid'] : 0;
    if ($torneoid <= 0) json_error('Missing torneoid', 400);

    if (!reglas_table_exists($conn)) {
        json_error('Tabla categorias_reglas no existe. Corre la migración 2026_05_22_categorias_reglas.sql.', 500);
    }

    $rules = $body['rules'] ?? [];
    if (!is_array($rules)) json_error('rules must be an array', 400);

    $conn->query("DELETE FROM categorias_reglas WHERE torneo_id = $torneoid");

    $nullable = function($v) use ($conn) {
        if ($v === null || $v === '' || $v === 'ANY') return 'NULL';
        return "'" . esc($conn, (string)$v) . "'";
    };
    $nullableInt = function($v) {
        if ($v === null || $v === '' || $v === 'ANY') return 'NULL';
        return (int)$v;
    };
    $nullableFloat = function($v) {
        if ($v === null || $v === '' || $v === 'ANY') return 'NULL';
        return (float)$v;
    };

    $count = 0;
    foreach ($rules as $r) {
        $cat   = (string)($r['categoria'] ?? '');
        if ($cat === '') continue; // skip filas sin categoría — son inútiles
        $catSql = "'" . esc($conn, $cat) . "'";
        $gen   = $nullable($r['genero'] ?? null);
        $emin  = $nullableInt($r['edad_min'] ?? null);
        $emax  = $nullableInt($r['edad_max'] ?? null);
        $hmin  = $nullableFloat($r['hcp_min'] ?? null);
        $hmax  = $nullableFloat($r['hcp_max'] ?? null);
        $ord   = (int)($r['display_order'] ?? 0);
        $act   = !empty($r['is_active']) ? 1 : 0;

        $sql = "INSERT INTO categorias_reglas
                  (torneo_id, categoria, genero, edad_min, edad_max,
                   hcp_min, hcp_max, display_order, is_active)
                VALUES
                  ($torneoid, $catSql, $gen, $emin, $emax,
                   $hmin, $hmax, $ord, $act)";
        if ($conn->query($sql)) $count++;
    }
    json_response(['saved' => true, 'count' => $count]);
}

json_error('Method not allowed', 405);