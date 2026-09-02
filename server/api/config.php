<?php
/**
 * API Configuration
 * Shared database connection and JSON response helpers
 * All API wrappers require this file
 */

// ============= CORS Headers =============
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Superadmin-Password');

// ============= PHP Error Output Guard =============
// API endpoints must never emit PHP warning/fatal HTML around JSON responses.
// Errors are handled by json_error() or logged server-side instead.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
mysqli_report(MYSQLI_REPORT_OFF);

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============= Database Configuration =============
// Credentials are loaded from /api/credentials.php (gitignored in production).
// As a production-safe fallback, the same values can be supplied by environment
// variables; this avoids ever reading credentials.example.php at runtime.
$credentialsFile = __DIR__ . '/credentials.php';
if (file_exists($credentialsFile)) {
    require_once $credentialsFile;
} else {
    $DB_HOST = getenv('DB_HOST') ?: null;
    $DB_USER = getenv('DB_USER') ?: null;
    $DB_PASS = getenv('DB_PASS') ?: null;
    $DB_NAME = getenv('DB_NAME') ?: null;
    $DB_PORT = (int)(getenv('DB_PORT') ?: 3306);
}

if (empty($DB_HOST) || empty($DB_USER) || empty($DB_NAME)) {
    http_response_code(500);
    echo json_encode(['error' => 'Missing /api/credentials.php on this production domain']);
    exit;
}

// ============= Logos Base URL =============
// Proxied through logo.php to avoid cross-origin/ad-blocker issues
$LOGOS_BASE_URL = '/api/logo.php?file=';

// ============= Database Connection =============
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);

if ($conn->connect_error) {
    // Se incluye el detalle real (host/puerto/errno) SIN la contraseña para
    // poder diagnosticar credenciales, IP no autorizada o puerto cerrado.
    http_response_code(500);
    echo json_encode([
        'error'  => 'Database connection failed',
        'detail' => $conn->connect_error,
        'errno'  => $conn->connect_errno,
        'host'   => $DB_HOST,
        'port'   => (int)$DB_PORT,
        'db'     => $DB_NAME,
        'user'   => $DB_USER,
    ]);
    exit;
}

// utf8mb4 evita que los acentos y la "ñ" lleguen como caracteres partidos
// (mojibake tipo "podrÃ¡n"). Si el servidor no soporta utf8mb4, cae a utf8.
if (!@$conn->set_charset('utf8mb4')) {
    @$conn->set_charset('utf8');
}

// ============= Debug Mode =============
/** Check if debug mode is enabled via ?debug=1 query param */
$DEBUG_MODE = isset($_GET['debug']) && $_GET['debug'] === '1';

/** Collected SQL queries for debug output */
$DEBUG_QUERIES = [];

/** Track latest SQL query executed (for error diagnostics) */
$LAST_SQL = null;

/**
 * Log a SQL query for debug output
 * @param string $label - Description of the query
 * @param string $sql - The SQL query string
 */
function debug_log_query($label, $sql) {
    global $DEBUG_MODE, $DEBUG_QUERIES, $LAST_SQL;
    // Keep latest SQL for failure context regardless of mode
    $LAST_SQL = $sql;

    // Keep full query log only in debug mode
    if ($DEBUG_MODE) {
        $DEBUG_QUERIES[] = ['label' => $label, 'sql' => $sql];
    }
}

/**
 * Build standardized debug context payload
 * @param array $extra Optional extra debug values
 * @return array Debug context information
 */
function debug_context($extra = []) {
    global $DEBUG_QUERIES, $LAST_SQL;
    return array_merge([
        'query_count' => count($DEBUG_QUERIES),
        'queries' => $DEBUG_QUERIES,
        'last_sql' => $LAST_SQL,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? ''
    ], $extra);
}

// ============= Helper Functions =============

/**
 * API_BUILD
 * ---------------------------------------------------------------------
 * Marca de versión del código PHP subido al servidor. Sirve para VERIFICAR
 * DESPLIEGUES: `GET /api/health.php` devuelve este valor, así se puede
 * confirmar desde el navegador/curl que el `config.php` con `fix_mojibake`
 * ya está en el hosting (IONOS se actualiza por SFTP manual).
 * Súbelo/increméntalo cada vez que cambie algo crítico de la API.
 */
if (!defined('API_BUILD')) {
    define('API_BUILD', '2026-08-25.field-gira-giraid-1');
}

/**
 * fix_mojibake
 * ---------------------------------------------------------------------
 * Repara texto en ESPAÑOL que fue guardado/leído como UTF-8 interpretado
 * en Latin-1 (doble codificación). Ejemplos:
 *   "podrÃ¡n"  -> "podrán"
 *   "EdiciÃ³n" -> "Edición"
 *   "aÃ±o"     -> "año"
 * Solo actúa cuando detecta la firma típica (Ã / Â / â€) y cuando la
 * reinterpretación produce UTF-8 válido; en caso contrario devuelve el
 * texto original intacto.
 *
 * @param string $text Texto posiblemente mal codificado
 * @return string Texto con acentos correctos
 */
function fix_mojibake($text) {
    if (!is_string($text) || $text === '') return $text;
    // Sin la firma de doble codificación no hay nada que reparar.
    if (!preg_match('/[ÃÂ]|â€/u', $text) && !preg_match('/\xC3[\x80-\xBF]/', $text)) {
        return $text;
    }
    $candidate = $text;
    // Hasta 2 pasadas: algunos textos legacy están doblemente codificados.
    for ($i = 0; $i < 2; $i++) {
        $decoded = @mb_convert_encoding($candidate, 'ISO-8859-1', 'UTF-8');
        if ($decoded === false || $decoded === '' || $decoded === $candidate) break;
        // Solo se acepta si el resultado sigue siendo UTF-8 válido.
        if (!mb_check_encoding($decoded, 'UTF-8')) break;
        $candidate = $decoded;
        if (!preg_match('/\xC3[\x80-\xBF]/', $candidate)) break;
    }
    return mb_check_encoding($candidate, 'UTF-8') ? $candidate : $text;
}

/**
 * fix_mojibake_deep
 * Aplica fix_mojibake() de forma recursiva a arrays/objetos antes de
 * serializar la respuesta JSON (claves y valores).
 *
 * @param mixed $data Estructura de datos a limpiar
 * @return mixed Estructura con textos normalizados
 */
function fix_mojibake_deep($data) {
    if (is_string($data)) return fix_mojibake($data);
    if (is_array($data)) {
        $out = [];
        foreach ($data as $k => $v) {
            $out[is_string($k) ? fix_mojibake($k) : $k] = fix_mojibake_deep($v);
        }
        return $out;
    }
    if (is_object($data)) {
        foreach ($data as $k => $v) { $data->$k = fix_mojibake_deep($v); }
        return $data;
    }
    return $data;
}

/**
 * Send JSON response and exit
 * @param mixed $data - Data to encode as JSON
 * @param int $status - HTTP status code (default 200)
 */
function json_response($data, $status = 200) {
    global $DEBUG_MODE, $DEBUG_QUERIES;
    http_response_code($status);
    // Normaliza acentos/ñ antes de serializar (español correcto siempre).
    $data = fix_mojibake_deep($data);
    $jsonOptions = JSON_UNESCAPED_UNICODE | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0);
    // In debug mode, wrap response with query info
    if ($DEBUG_MODE) {
        $encoded = json_encode([
            '_debug_queries' => $DEBUG_QUERIES,
            '_debug_query_count' => count($DEBUG_QUERIES),
            'data' => $data
        ], $jsonOptions | JSON_PRETTY_PRINT);
    } else {
        $encoded = json_encode($data, $jsonOptions);
    }

    if ($encoded === false) {
        http_response_code(500);
        echo '{"error":"JSON encoding failed"}';
    } else {
        echo $encoded;
    }
    exit;
}

/**
 * Send error JSON response and exit
 * @param string $message - Error message
 * @param int $status - HTTP status code (default 500)
 * @param array $extraDebug Optional extra debug values
 */
function json_error($message, $status = 500, $extraDebug = []) {
    global $DEBUG_MODE;
    http_response_code($status);
    $jsonOptions = JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0);

    $payload = ['error' => $message];
    if ($DEBUG_MODE) {
        $payload['_debug'] = debug_context($extraDebug);
    }

    echo json_encode($payload, $jsonOptions);
    exit;
}

/**
 * Get required GET parameter or return error
 * @param string $name - Parameter name
 * @return string - Parameter value
 */
function require_param($name) {
    if (!isset($_GET[$name]) || $_GET[$name] === '') {
        json_error("Missing required parameter: $name", 400);
    }
    return $_GET[$name];
}

/**
 * Get optional GET parameter with default
 * @param string $name - Parameter name
 * @param mixed $default - Default value
 * @return mixed - Parameter value or default
 */
function optional_param($name, $default = null) {
    return isset($_GET[$name]) && $_GET[$name] !== '' ? $_GET[$name] : $default;
}

// ============= Gira -> Torneo resolution =============
// Esta instalación se configura por `giraid` (site_config.giraid) y ya NO
// guarda un torneoid por dominio. Los endpoints legacy que siguen razonando
// por torneo (categories.php, players.php, resultados, etc.) resuelven el
// torneo activo de la gira con estos helpers, en lugar de fallar con
// "Missing required parameter: torneoid".

/** ¿Existe la columna en la tabla? (silencioso, sin romper el JSON) */
function api_column_exists($conn, $table, $column) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) return false;
    $column = $conn->real_escape_string($column);
    $r = @$conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    $exists = $r && $r->num_rows > 0;
    if ($r) $r->free();
    return $exists;
}

/** Primera columna existente de una lista de nombres legacy equivalentes. */
function api_first_existing_column($conn, $table, $columns) {
    foreach ($columns as $c) {
        if (api_column_exists($conn, $table, $c)) return $c;
    }
    return null;
}

/**
 * Torneo activo de una gira.
 * Prioridad: `torneo.status = 'A'` (marcado activo por el staff) > torneo en
 * curso por fechas > último ya iniciado > próximo por comenzar.
 */
function gira_active_torneoid($conn, $giraid) {
    if ($giraid === null || !ctype_digit((string)$giraid)) return null;
    $gid = (int)$giraid;
    if (!api_column_exists($conn, 'torneo', 'giraid')) return null;
    $idCol = api_first_existing_column($conn, 'torneo', ['torneo_id', 'torneoid', 'id_torneo']);
    if (!$idCol) return null;

    /** 1) status = 'A' manda sobre cualquier heurística de fechas. */
    if (api_column_exists($conn, 'torneo', 'status')) {
        $r = @$conn->query("SELECT `$idCol` AS id FROM `torneo`
                            WHERE `giraid` = $gid AND UPPER(TRIM(`status`)) = 'A'
                            ORDER BY `$idCol` DESC LIMIT 1");
        if ($r) {
            $row = $r->fetch_assoc();
            $r->free();
            if ($row && $row['id'] !== null) return (string)$row['id'];
        }
    }

    $hasIni = api_column_exists($conn, 'torneo', 'fecha_ini');
    $hasFin = api_column_exists($conn, 'torneo', 'fecha_fin');
    $order = "`$idCol` DESC";
    if ($hasIni && $hasFin) {
        $order = "(CASE WHEN CURDATE() BETWEEN `fecha_ini` AND `fecha_fin` THEN 0
                        WHEN `fecha_ini` <= CURDATE() THEN 1 ELSE 2 END) ASC,
                  ABS(DATEDIFF(`fecha_ini`, CURDATE())) ASC, `$idCol` DESC";
    } elseif ($hasIni) {
        $order = "`fecha_ini` DESC, `$idCol` DESC";
    }
    $r = @$conn->query("SELECT `$idCol` AS id FROM `torneo` WHERE `giraid` = $gid ORDER BY $order LIMIT 1");
    if (!$r) return null;
    $row = $r->fetch_assoc();
    $r->free();
    return $row && $row['id'] !== null ? (string)$row['id'] : null;
}


/**
 * torneoid requerido, tolerante al esquema de giras.
 * 1) ?torneoid=NN explícito
 * 2) ?giraid=NN -> torneo activo de la gira
 * Si no se puede resolver, devuelve un error claro (no un 400 opaco).
 */
function require_torneoid($conn) {
    $tid = optional_param('torneoid');
    if ($tid !== null && $tid !== '') return $tid;
    $gid = optional_param('giraid');
    if ($gid !== null && $gid !== '') {
        $resolved = gira_active_torneoid($conn, $gid);
        if ($resolved !== null) return $resolved;
        json_error("La gira $gid no tiene torneos registrados (torneo.giraid)", 400);
    }
    json_error('Missing required parameter: torneoid (ni giraid para resolverlo)', 400);
}



/**
 * Execute query and return all rows as associative array
 * @param mysqli $conn - Database connection
 * @param string $sql - SQL query
 * @return array - Result rows
 */
function query_all($conn, $sql) {
    debug_log_query('query_all', $sql);
    $result = $conn->query($sql);
    if (!$result) {
        json_error('Query failed: ' . $conn->error, 500, ['failed_sql' => $sql]);
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free();
    return $rows;
}

/**
 * Execute query and return single row
 * @param mysqli $conn - Database connection
 * @param string $sql - SQL query
 * @return array|null - Single row or null
 */
function query_one($conn, $sql) {
    debug_log_query('query_one', $sql);
    $result = $conn->query($sql);
    if (!$result) {
        json_error('Query failed: ' . $conn->error, 500, ['failed_sql' => $sql]);
    }
    $row = $result->fetch_assoc();
    $result->free();
    return $row;
}

/**
 * Escape string for SQL query
 * @param mysqli $conn - Database connection
 * @param string $value - Value to escape
 * @return string - Escaped value
 */
function esc($conn, $value) {
    return $conn->real_escape_string($value);
}

// ============= Superadmin Password Helpers =============
// En golftour, `usuarios.tipo = 1` es el nivel más alto. Esos usuarios legacy
// (admin/root/ROOT GT/etc.) son superadmin reales de la app.

// Tabla de usuarios de la app (staff + superadmin).
// Por defecto `usuarios`: los datos de `usuarios2` se copiaron ahí con la
// migración 2026_08_25_copy_usuarios2_into_usuarios.sql. Se puede sobreescribir
// con $USERS_TABLE en credentials.php si hiciera falta.
if (!defined('USERS_TABLE')) {
    $usersTable = isset($USERS_TABLE) ? trim((string)$USERS_TABLE) : '';
    if ($usersTable === '' || !preg_match('/^[A-Za-z0-9_]+$/', $usersTable)) {
        $usersTable = 'usuarios';
    }
    define('USERS_TABLE', $usersTable);
}


const SUPERADMIN_DEFAULT_PASSWORD = 'admin2025';
const SUPERADMIN_USER_KEY = '__superadmin__';
const SUPERADMIN_TIPO = 1;

/** Compatibilidad: tipo=1 es el superadmin real; tipo=100 solo cubre datos creados por migraciones anteriores. */
function is_superadmin_tipo($tipo) {
    $tipo = (int)$tipo;
    return $tipo === SUPERADMIN_TIPO || $tipo === 100;
}

/** SQL para filtrar usuarios con nivel superadmin. */
function superadmin_tipo_where($column = 'tipo') {
    $column = preg_replace('/[^A-Za-z0-9_\.]/', '', (string)$column);
    return $column . ' IN (' . SUPERADMIN_TIPO . ', 100)';
}

/** Verifica columnas opcionales de `usuarios` sin tronar instalaciones legacy. */
function users_table_has_column($conn, $column) {
    static $cache = [];
    $column = (string)$column;
    if (isset($cache[$column])) return $cache[$column];
    $c = esc($conn, $column);
    $t = esc($conn, USERS_TABLE);
    $r = @$conn->query("SELECT 1 FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t'
                           AND COLUMN_NAME = '$c' LIMIT 1");
    $cache[$column] = (bool)($r && $r->num_rows > 0);
    if ($r) $r->free();
    return $cache[$column];
}

/** `0000-00-00` en legacy significa sin límite real, no fecha expirada. */
function legacy_date_is_set($value) {
    $value = trim((string)$value);
    return $value !== '' && !preg_match('/^0{4}-0{2}-0{2}/', $value);
}

/** Soporta bcrypt moderno y passwords planos legacy. */
function password_matches_stored_value($password, $stored) {
    $password = (string)$password;
    $stored = (string)$stored;
    if ($password === '' || $stored === '') return false;
    $looksHashed = preg_match('/^\$2[aby]\$/', $stored);
    if ($looksHashed) return password_verify($password, $stored);
    return hash_equals($stored, $password);
}

/** Lee el hash del superadmin desde `usuarios` (row reservado). */
function superadmin_password_hash_from_db($conn) {
    static $hash = false;
    if ($hash !== false) return $hash;
    $hash = null;

    $key = SUPERADMIN_USER_KEY;
    $r = @$conn->query("SELECT pwd FROM " . USERS_TABLE . " WHERE usuario='$key' LIMIT 1");
    if ($r && $r->num_rows > 0) {
        $row = $r->fetch_assoc();
        $stored = (string)($row['pwd'] ?? '');
        if ($stored !== '') $hash = $stored;
    }
    if ($r) $r->free();
    return $hash;
}

/** Cuenta usuarios reales marcados como superadmin (`tipo = 1`). */
function superadmin_user_count($conn) {
    static $count = null;
    if ($count !== null) return $count;
    $count = 0;
    $r = @$conn->query("SELECT COUNT(*) c FROM " . USERS_TABLE . " WHERE " . superadmin_tipo_where() . " AND usuario <> '" . SUPERADMIN_USER_KEY . "'");
    if ($r && ($row = $r->fetch_assoc())) $count = (int)$row['c'];
    if ($r) $r->free();
    return $count;
}

/** Hay identidad superadmin en BD si existe `__superadmin__` o algún `tipo=1`. */
function superadmin_has_db_identity($conn) {
    return (bool)superadmin_password_hash_from_db($conn) || superadmin_user_count($conn) > 0;
}

/** Valida contraseña contra cualquier usuario activo con `tipo = 1` (ej. admin/root/ROOT GT). */
function superadmin_user_password_matches($conn, $password) {
    $password = (string)$password;
    if ($password === '') return false;

    $pwd2Select = users_table_has_column($conn, 'pwd2') ? ', pwd2' : '';
    $sql = "SELECT pwd$pwd2Select, activo, estatus FROM " . USERS_TABLE . " WHERE " . superadmin_tipo_where() . " LIMIT 50";
    $r = @$conn->query($sql);
    if (!$r) return false;

    while ($row = $r->fetch_assoc()) {
        if (array_key_exists('activo', $row) && (int)$row['activo'] !== 1) continue;
        if (strtolower((string)($row['estatus'] ?? '')) === 'inactivo') continue;

        if (password_matches_stored_value($password, $row['pwd'] ?? '')) {
            $r->free();
            return true;
        }
        if (array_key_exists('pwd2', $row) && password_matches_stored_value($password, $row['pwd2'] ?? '')) {
            $r->free();
            return true;
        }
    }
    $r->free();
    return false;
}



/**
 * superadmin_password_candidates
 * ------------------------------------------------------------------
 * Builds the ordered list of possible superadmin password values for the
 * current request. This lets legacy admin screens that still send the old
 * `admin2025` body value be rescued by the current password sent in the
 * `X-Superadmin-Password` header by the frontend compatibility layer.
 */
function superadmin_password_candidates($password) {
    $candidates = [];
    $add = function ($value) use (&$candidates) {
        $value = (string)$value;
        if ($value !== '' && !in_array($value, $candidates, true)) {
            $candidates[] = $value;
        }
    };

    $add($password);
    if (!empty($_SERVER['HTTP_X_SUPERADMIN_PASSWORD'])) {
        $add($_SERVER['HTTP_X_SUPERADMIN_PASSWORD']);
    }
    if (!empty($_POST['password'])) {
        $add($_POST['password']);
    }
    if (!empty($_GET['password'])) {
        $add($_GET['password']);
    }

    return $candidates;
}

/** Valida una contraseña candidata del superadmin contra DB/env/fallback. */
function superadmin_password_matches($conn, $password) {
    global $SUPERADMIN_PASSWORD, $SUPERADMIN_PASSWORD_HASH;
    $password = (string)$password;
    if ($password === '') return false;

    $dbHash = superadmin_password_hash_from_db($conn);
    if ($dbHash) {
        if (password_matches_stored_value($password, $dbHash)) return true;
    }

    if (superadmin_user_password_matches($conn, $password)) return true;

    if (!empty($SUPERADMIN_PASSWORD_HASH) && password_verify($password, $SUPERADMIN_PASSWORD_HASH)) return true;
    if (!empty($SUPERADMIN_PASSWORD) && hash_equals((string)$SUPERADMIN_PASSWORD, $password)) return true;

    // Fallback histórico mientras no exista un hash configurado.
    if (!superadmin_has_db_identity($conn) && empty($SUPERADMIN_PASSWORD_HASH) && empty($SUPERADMIN_PASSWORD)) {
        return hash_equals(SUPERADMIN_DEFAULT_PASSWORD, $password);
    }
    return false;
}

/** Valida la contraseña del superadmin (sin usuario). */
function is_superadmin_password($conn, $password) {
    foreach (superadmin_password_candidates($password) as $candidate) {
        if (superadmin_password_matches($conn, $candidate)) return true;
    }
    return false;
}

/** Inicia una sesión PHP segura y limitada al mismo sitio. */
function superadmin_session_start() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

/** Registra una autenticación de superadmin válida para esta pestaña/sesión. */
function establish_superadmin_session() {
    superadmin_session_start();
    session_regenerate_id(true);
    $_SESSION['superadmin_authenticated_at'] = time();
}

/** La sesión administrativa expira tras 8 horas de inactividad. */
function is_superadmin_session() {
    superadmin_session_start();
    $authenticatedAt = (int)($_SESSION['superadmin_authenticated_at'] ?? 0);
    if ($authenticatedAt <= 0 || time() - $authenticatedAt > 28800) {
        unset($_SESSION['superadmin_authenticated_at']);
        return false;
    }
    $_SESSION['superadmin_authenticated_at'] = time();
    return true;
}

/** Persiste el hash del superadmin en `usuarios` (row reservado, upsert). */
function set_superadmin_password_hash($conn, $hash) {
    $h = esc($conn, $hash);
    $key = SUPERADMIN_USER_KEY;
    $tipo = SUPERADMIN_TIPO;

    // Verifica que la columna pwd admita el hash completo (bcrypt = 60 chars).
    $r = @$conn->query("SELECT CHARACTER_MAXIMUM_LENGTH len FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . USERS_TABLE . "'
                            AND COLUMN_NAME = 'pwd' LIMIT 1");
    $len = ($r && $r->num_rows > 0) ? (int)$r->fetch_assoc()['len'] : 255;
    if ($r) $r->free();
    if ($len > 0 && $len < strlen($hash)) {
        json_error('La columna ' . USERS_TABLE . '.pwd es muy corta (' . $len . '). Ejecuta la migración 2026_08_25_copy_usuarios2_into_usuarios.sql', 500);
    }

    $sql = "INSERT INTO " . USERS_TABLE . " (usuario, pwd, clubid, tipo, torneoid, estatus, nombre, ultent)
              VALUES ('$key', '$h', 0, $tipo, 0, 'ACTIVO', 'Superadmin', NOW())
              ON DUPLICATE KEY UPDATE pwd=VALUES(pwd), tipo=VALUES(tipo), estatus='ACTIVO'";
    if (!$conn->query($sql)) json_error('No se pudo guardar la contraseña: ' . $conn->error, 500);
}


/**
 * ¿Existe la columna en la tabla/vista de la base activa?
 * Usada para soportar el esquema legacy `golftour` (id_campo/salida) junto al
 * esquema nuevo (campoid/salidaid) sin duplicar endpoints.
 */
if (!function_exists('api_column_exists')) {
    function api_column_exists($conn, $table, $column) {
        $t = esc($conn, $table);
        $c = esc($conn, $column);
        $res = @$conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t'
                                AND COLUMN_NAME = '$c' LIMIT 1");
        if (!$res) return false;
        $ok = $res->num_rows > 0;
        $res->free();
        return $ok;
    }
}
