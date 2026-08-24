<?php
/**
 * db_diag.php
 * ---------------------------------------------------------------------
 * Diagnóstico de conexión MySQL desde el HOSTING (IONOS) hacia la BD
 * remota (golftour @ 70.35.203.117). NO usa config.php porque config.php
 * aborta el request cuando la conexión falla.
 *
 * Uso:  GET /api/db_diag.php
 *
 * Devuelve:
 *  - outbound_ip  : IP pública desde la que sale IONOS (la que MySQL ve).
 *                   Ésta es la IP que hay que autorizar en el GRANT.
 *  - reverse_dns  : nombre del host que MySQL reporta en el error 1045.
 *  - tcp          : si el puerto 3306 responde (socket abierto).
 *  - mysql        : resultado del handshake (errno + mensaje real).
 *  - server_info  : versión del servidor y usuario efectivo si conecta.
 *
 * No expone la contraseña en ningún caso.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
ini_set('display_errors', '0');
mysqli_report(MYSQLI_REPORT_OFF);

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
$DB_PORT = (int)($DB_PORT ?: 3306);

$out = [
    'credentials_file' => file_exists($credentialsFile),
    'target' => [
        'host' => $DB_HOST,
        'port' => $DB_PORT,
        'db'   => $DB_NAME,
        'user' => $DB_USER,
        'pass_length' => is_string($DB_PASS) ? strlen($DB_PASS) : 0,
        'pass_has_spaces' => is_string($DB_PASS) ? (trim($DB_PASS) !== $DB_PASS) : false,
    ],
];

// ---- 1) IP pública de salida del hosting (la que ve MySQL) --------------
$ip = @file_get_contents('https://api.ipify.org');
$out['outbound_ip'] = $ip ?: 'no se pudo determinar (egress HTTP bloqueado)';
$out['server_addr'] = $_SERVER['SERVER_ADDR'] ?? null;
$out['reverse_dns'] = $out['outbound_ip'] && filter_var($out['outbound_ip'], FILTER_VALIDATE_IP)
    ? gethostbyaddr($out['outbound_ip'])
    : null;

// ---- 2) ¿El puerto 3306 acepta TCP? -----------------------------------
$t0 = microtime(true);
$errno = 0; $errstr = '';
$sock = @fsockopen($DB_HOST, $DB_PORT, $errno, $errstr, 5);
$out['tcp'] = [
    'open' => (bool)$sock,
    'ms'   => (int)round((microtime(true) - $t0) * 1000),
    'error' => $sock ? null : "$errno $errstr",
];
if ($sock) {
    // El primer paquete del handshake incluye la versión del servidor.
    $banner = @fread($sock, 64);
    if (is_string($banner) && $banner !== '') {
        $out['tcp']['banner'] = preg_replace('/[^\x20-\x7E]/', '.', substr($banner, 0, 64));
    }
    fclose($sock);
}

// ---- 3) Handshake MySQL real ------------------------------------------
$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
if ($conn->connect_errno) {
    $out['mysql'] = [
        'connected' => false,
        'errno'     => $conn->connect_errno,
        'error'     => $conn->connect_error,
    ];
    // Segundo intento SIN base de datos: distingue "usuario rechazado"
    // (1045) de "usuario OK pero sin permiso sobre golftour" (1044).
    $conn2 = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, '', $DB_PORT);
    $out['mysql']['without_db'] = $conn2->connect_errno
        ? ['connected' => false, 'errno' => $conn2->connect_errno, 'error' => $conn2->connect_error]
        : ['connected' => true, 'note' => 'El usuario SÍ es válido; falta permiso sobre la base ' . $DB_NAME];
    if (!$conn2->connect_errno) $conn2->close();
} else {
    $row = @$conn->query("SELECT CURRENT_USER() cu, USER() u, VERSION() v, DATABASE() d");
    $info = $row ? $row->fetch_assoc() : null;
    $out['mysql'] = [
        'connected'      => true,
        'current_user'   => $info['cu'] ?? null,
        'connection_user'=> $info['u'] ?? null,
        'version'        => $info['v'] ?? null,
        'database'       => $info['d'] ?? null,
    ];
    $t = @$conn->query("SHOW TABLES");
    $out['mysql']['table_count'] = $t ? $t->num_rows : 0;
    $conn->close();
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
