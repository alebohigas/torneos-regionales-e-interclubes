<?php
/**
 * Registro Precios Endpoint
 * -----------------------------------------------------------------------
 * Reglas de precio para el formulario público de Pre-Registro.
 *
 * GET  /api/registro_precios.php?torneoid=XXX
 *      → Lista TODAS las reglas del torneo (para admin / display público).
 *      Respuesta: { rules: [...] }
 *
 * GET  /api/registro_precios.php?torneoid=XXX&action=match
 *           &tipo_socio=...
 *      → Devuelve la regla que mejor matchea (o null).
 *      Respuesta: { match: { id, precio, moneda, etiqueta, incluye } | null }
 *
 * POST /api/registro_precios.php?torneoid=XXX  (JSON body)
 *      → Admin: reemplaza el set completo de reglas del torneo.
 *      Body: { password, torneoid, rules: [...] }
 *
 * Tabla backend: `registro_precios` — ver
 * server/migrations/2026_05_19_registro_precios.sql
 *
 * IMPORTANTE (2026-05-22): Los filtros de elegibilidad de categoría
 * (edad/género/hcp) se mueven a la tabla `categorias_reglas`. Este
 * endpoint sigue aceptando las columnas legacy para no romper datos
 * existentes, pero el matching nuevo SÓLO usa `tipo_socio`.
 *
 * Patrón: si la tabla no existe, GET devuelve { rules: [] } y POST devuelve
 * 500 con instrucción de correr la migración.
 */
require_once 'config.php';
require_once '_staff_auth.php';

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

/**
 * ¿Existe la tabla en este esquema? Cacheado por request.
 * Self-healing: si falta (base nueva sin la migración 2026_05_19) se crea
 * automáticamente para que el admin pueda guardar sin intervención manual.
 */
function precios_table_exists($conn) {
    static $exists = null;
    if ($exists !== null) return $exists;
    $r = $conn->query("SHOW TABLES LIKE 'registro_precios'");
    $exists = $r && $r->num_rows > 0;
    if (!$exists) {
        $ddl = "CREATE TABLE IF NOT EXISTS `registro_precios` (
                  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
                  `torneo_id`     INT(11)      NOT NULL,
                  `etiqueta`      VARCHAR(120) NULL,
                  `categoria`     VARCHAR(120) NULL,
                  `tipo_socio`    VARCHAR(20)  NULL,
                  `genero`        VARCHAR(8)   NULL,
                  `edad_min`      INT(11)      NULL,
                  `edad_max`      INT(11)      NULL,
                  `hcp_min`       DECIMAL(4,1) NULL,
                  `hcp_max`       DECIMAL(4,1) NULL,
                  `precio`        DECIMAL(10,2) NOT NULL DEFAULT 0,
                  `moneda`        VARCHAR(3)   NOT NULL DEFAULT 'MXN',
                  `incluye`       TEXT         NULL,
                  `prioridad`     INT(11)      NOT NULL DEFAULT 0,
                  `display_order` INT(11)      NOT NULL DEFAULT 0,
                  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
                  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `idx_torneo` (`torneo_id`),
                  KEY `idx_match` (`torneo_id`, `is_active`, `prioridad`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        if (@$conn->query($ddl)) {
            $exists = true;
        } else {
            error_log('registro_precios auto-create failed: ' . $conn->error);
        }
    }
    return $exists;
}

/**
 * Garantiza que las columnas hcp_min / hcp_max existan en registro_precios.
 * Las añade silenciosamente si faltan; cachea el resultado por request.
 * (Patrón usado también en registro_fields.php → ensure_section_column).
 */
function ensure_hcp_columns($conn) {
    static $checked = null;
    if ($checked !== null) return $checked;
    if (!precios_table_exists($conn)) return $checked = false;
    foreach (['hcp_min', 'hcp_max'] as $col) {
        $r = $conn->query("SHOW COLUMNS FROM registro_precios LIKE '$col'");
        if (!$r || $r->num_rows === 0) {
            @$conn->query("ALTER TABLE registro_precios ADD COLUMN `$col` DECIMAL(4,1) NULL");
        }
    }
    return $checked = true;
}

/**
 * Normaliza una regla cruda de la BD a la forma que consume el cliente.
 * Castea numéricos y devuelve NULL (no string vacío) en los filtros opcionales.
 */
function normalize_rule($r) {
    return [
        'id'            => (int)$r['id'],
        'etiqueta'      => $r['etiqueta'] ?? '',
        'categoria'     => $r['categoria'] !== null && $r['categoria'] !== '' ? $r['categoria'] : null,
        'tipo_socio'    => $r['tipo_socio'] !== null && $r['tipo_socio'] !== '' ? $r['tipo_socio'] : null,
        'genero'        => $r['genero'] !== null && $r['genero'] !== '' ? $r['genero'] : null,
        'edad_min'      => $r['edad_min'] !== null ? (int)$r['edad_min'] : null,
        'edad_max'      => $r['edad_max'] !== null ? (int)$r['edad_max'] : null,
        'hcp_min'       => isset($r['hcp_min']) && $r['hcp_min'] !== null ? (float)$r['hcp_min'] : null,
        'hcp_max'       => isset($r['hcp_max']) && $r['hcp_max'] !== null ? (float)$r['hcp_max'] : null,
        'precio'        => (float)$r['precio'],
        'moneda'        => $r['moneda'] ?: 'MXN',
        'incluye'       => $r['incluye'] ?? '',
        'prioridad'     => (int)$r['prioridad'],
        'display_order' => (int)$r['display_order'],
        'is_active'     => (int)$r['is_active'] ? 1 : 0,
    ];
}

/**
 * Decide si una regla aplica para el (categoria/tipo_socio/genero/edad) dados.
 * NULL en la regla = "comodín". Match exacto en valores presentes.
 *
 * Para tipo_socio: si la regla pide 'SOCIO' aceptamos cualquier subtipo
 * (TITULAR/EMERITO/DEPENDIENTE). Si pide un subtipo específico, debe coincidir.
 */
function rule_matches($rule, $tipoSocio, $genero = null, $edad = null) {
    if ($rule['tipo_socio'] !== null) {
        $rt = $rule['tipo_socio'];
        $ut = (string)$tipoSocio;
        if ($rt === 'SOCIO') {
            if (!in_array($ut, ['SOCIO','TITULAR','EMERITO','DEPENDIENTE'], true)) return false;
        } elseif ($rt === 'NO_SOCIO') {
            if ($ut !== 'NO_SOCIO' && $ut !== 'INVITADO' && $ut !== '') return false;
            // Acepta vacío como "no socio" porque el form puede no marcarlo.
        } else {
            if (strcasecmp($rt, $ut) !== 0) return false;
        }
    }
    // Filtro por género: si la regla define M/F y el jugador tiene un valor
    // declarado, debe coincidir. Si el jugador no declara género, se omite
    // la regla específica (no se asume).
    if ($rule['genero'] !== null) {
        $rg = strtoupper((string)$rule['genero']);
        $ug = strtoupper((string)($genero ?? ''));
        if ($ug === '' || $ug !== $rg) return false;
    }
    // Filtro por edad: si la regla define un rango y conocemos la edad,
    // debe caer dentro. Si no conocemos la edad pero la regla la exige,
    // descartamos para no mostrar precios incorrectos.
    if ($rule['edad_min'] !== null || $rule['edad_max'] !== null) {
        if ($edad === null || $edad === '') return false;
        $ageNum = (int)$edad;
        if ($rule['edad_min'] !== null && $ageNum < (int)$rule['edad_min']) return false;
        if ($rule['edad_max'] !== null && $ageNum > (int)$rule['edad_max']) return false;
    }
    return true;
}

/**
 * Especificidad: cuantos más filtros explícitos tenga la regla, más
 * específica es. Se suma 1 por cada filtro definido (tipo_socio, genero,
 * edad_min, edad_max). Después decide `prioridad` y luego id.
 */
function rule_specificity($rule) {
    $s = 0;
    if ($rule['tipo_socio'] !== null) $s++;
    if ($rule['genero']     !== null) $s++;
    if ($rule['edad_min']   !== null) $s++;
    if ($rule['edad_max']   !== null) $s++;
    return $s;
}

// ===========================================================================
// GET — lista o match
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $torneoid = (int) require_torneoid($conn);
    $action   = optional_param('action', 'list');

    if (!precios_table_exists($conn)) {
        if ($action === 'match') json_response(['match' => null, 'source' => 'no_table']);
        json_response(['rules' => [], 'source' => 'no_table']);
    }
    ensure_hcp_columns($conn);

    $sql = "SELECT id, etiqueta, categoria, tipo_socio, genero, edad_min, edad_max,
                   hcp_min, hcp_max,
                   precio, moneda, incluye, prioridad, display_order, is_active
            FROM registro_precios
            WHERE torneo_id = $torneoid
            ORDER BY display_order ASC, id ASC";
    $rows  = array_map('normalize_rule', query_all($conn, $sql));

    if ($action === 'match') {
        // Matching por tipo_socio + genero + edad. categoria/handicap se
        // siguen aceptando para compatibilidad pero NO entran al match
        // (las restricciones de categoría viven en categorias_reglas).
        $tipo   = optional_param('tipo_socio', null);
        $genero = optional_param('genero', null);
        $edadP  = optional_param('edad', null);
        $edad   = ($edadP === null || $edadP === '') ? null : (int)$edadP;

        $candidates = array_filter($rows, function($r) use ($tipo, $genero, $edad) {
            if (!$r['is_active']) return false;
            return rule_matches($r, $tipo, $genero, $edad);
        });

        if (count($candidates) === 0) {
            json_response(['match' => null]);
        }

        // Mejor regla: mayor especificidad, luego mayor prioridad, luego menor id
        usort($candidates, function($a, $b) {
            $sa = rule_specificity($a);
            $sb = rule_specificity($b);
            if ($sa !== $sb) return $sb - $sa;
            if ($a['prioridad'] !== $b['prioridad']) return $b['prioridad'] - $a['prioridad'];
            return $a['id'] - $b['id'];
        });
        json_response(['match' => $candidates[0]]);
    }

    json_response(['rules' => $rows]);
}

// ===========================================================================
// POST — admin: reemplaza el set de reglas para el torneo
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

    if (!precios_table_exists($conn)) {
        json_error('Table registro_precios not found. Corre la migración 2026_05_19_registro_precios.sql.', 500);
    }
    ensure_hcp_columns($conn);

    $rules = $body['rules'] ?? [];
    if (!is_array($rules)) json_error('rules must be an array', 400);

    $conn->query("DELETE FROM registro_precios WHERE torneo_id = $torneoid");

    /** Acepta nulls reales para los filtros opcionales. */
    $nullable = function($v) use ($conn) {
        if ($v === null || $v === '' || $v === 'ANY') return 'NULL';
        return "'" . esc($conn, (string)$v) . "'";
    };
    $nullableInt = function($v) {
        if ($v === null || $v === '' || $v === 'ANY') return 'NULL';
        return (int)$v;
    };
    /** Floats nullables para hcp_min/hcp_max (acepta decimales). */
    $nullableFloat = function($v) {
        if ($v === null || $v === '' || $v === 'ANY') return 'NULL';
        return (float)$v;
    };

    $count = 0;
    foreach ($rules as $r) {
        $etiqueta   = esc($conn, (string)($r['etiqueta'] ?? ''));
        $categoria  = $nullable($r['categoria'] ?? null);
        $tipoSocio  = $nullable($r['tipo_socio'] ?? null);
        $genero     = $nullable($r['genero'] ?? null);
        $edadMin    = $nullableInt($r['edad_min'] ?? null);
        $edadMax    = $nullableInt($r['edad_max'] ?? null);
        $hcpMin     = $nullableFloat($r['hcp_min'] ?? null);
        $hcpMax     = $nullableFloat($r['hcp_max'] ?? null);
        $precio     = (float)($r['precio'] ?? 0);
        $moneda     = esc($conn, (string)($r['moneda'] ?? 'MXN'));
        $incluye    = esc($conn, (string)($r['incluye'] ?? ''));
        $prioridad  = (int)($r['prioridad'] ?? 0);
        $ord        = (int)($r['display_order'] ?? 0);
        $active     = !empty($r['is_active']) ? 1 : 0;

        $sql = "INSERT INTO registro_precios
                  (torneo_id, etiqueta, categoria, tipo_socio, genero, edad_min, edad_max,
                   hcp_min, hcp_max, precio, moneda, incluye, prioridad, display_order, is_active)
                VALUES
                  ($torneoid, '$etiqueta', $categoria, $tipoSocio, $genero, $edadMin, $edadMax,
                   $hcpMin, $hcpMax, $precio, '$moneda', '$incluye', $prioridad, $ord, $active)";
        if ($conn->query($sql)) $count++;
    }

    json_response(['saved' => true, 'count' => $count]);
}

json_error('Method not allowed', 405);
