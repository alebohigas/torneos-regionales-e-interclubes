<?php
/**
 * staff_login.php — Login para usuarios staff temporales.
 *
 * POST JSON { usuario, password }
 *   → { token, expira, usuario, nombre, areas, torneoid }
 *
 * Valida contra `usuarios.pwd`. Soporta tanto hashes bcrypt como
 * valores planos legacy; si encuentra un valor plano correcto lo
 * migra a hash automáticamente. Respeta `desde`/`hasta` y `activo=1`.
 */
require_once 'config.php';
require_once '_staff_auth.php';

header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) json_error('Invalid body', 400);

$usuario = trim((string)($body['usuario'] ?? ''));
$password = (string)($body['password'] ?? '');
if ($usuario === '' || $password === '') json_error('Missing credentials', 400);

$u = esc($conn, $usuario);
$pwd2Select = users_table_has_column($conn, 'pwd2') ? ', pwd2' : '';
$row = query_one($conn, "SELECT id, usuario, nombre, torneoid, pwd$pwd2Select, activo, estatus, desde, hasta, tipo
                           FROM " . USERS_TABLE . " WHERE usuario = '$u' LIMIT 1");
if (!$row) json_error('Credenciales inválidas', 401);

// Validación de password
$ok = false;
$pwd = (string)($row['pwd'] ?? '');
$looksHashed = $pwd !== '' && preg_match('/^\$2[aby]\$/', $pwd);
if (password_matches_stored_value($password, $pwd)) {
    $ok = true;
    if (!$looksHashed && $pwd !== '') {
        // Migrar plano → hash en la misma columna pwd
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $h = esc($conn, $hash);
        $id = (int)$row['id'];
        $conn->query("UPDATE " . USERS_TABLE . " SET pwd='$h' WHERE id=$id");
    }
} elseif (array_key_exists('pwd2', $row) && password_matches_stored_value($password, $row['pwd2'] ?? '')) {
    // `pwd2` existe en golftour como contraseña legacy visible; se acepta pero
    // no se modifica para no romper otras pantallas legacy que aún la consulten.
    $ok = true;
}
if (!$ok) json_error('Credenciales inválidas', 401);

// Verificar estatus + rango fechas. En legacy, tipo=1 es superadmin y no se
// bloquea por `desde/hasta`; varios roots históricos traen fechas vencidas.
$isSuperadmin = is_superadmin_tipo($row['tipo'] ?? 0);
if ((int)$row['activo'] !== 1) json_error('Usuario inactivo', 403);
if (strtolower((string)$row['estatus']) === 'inactivo') json_error('Usuario inactivo', 403);
$today = (new DateTime('today'))->format('Y-m-d');
if (!$isSuperadmin && legacy_date_is_set($row['desde'] ?? null) && $today < $row['desde']) json_error('Acceso aún no inicia (' . $row['desde'] . ')', 403);
if (!$isSuperadmin && legacy_date_is_set($row['hasta'] ?? null) && $today > $row['hasta']) json_error('Acceso expirado (' . $row['hasta'] . ')', 403);

// Generar token
$token = bin2hex(random_bytes(32));
$uid = (int)$row['id'];
// La sesión expira con `hasta` (fin de día) o en 12h si no hay hasta real.
// Para superadmin tipo=1, ignoramos `hasta` legacy aunque esté vencido.
if (!$isSuperadmin && legacy_date_is_set($row['hasta'] ?? null)) {
    $expira = $row['hasta'] . ' 23:59:59';
} else {
    $expira = (new DateTime('+12 hours'))->format('Y-m-d H:i:s');
}
$te = esc($conn, $token);
$ee = esc($conn, $expira);
$conn->query("INSERT INTO usuario_sesion (usuario_id, token, expira) VALUES ($uid, '$te', '$ee')");

if ($isSuperadmin) {
    establish_superadmin_session();
}

// Housekeeping: borrar sesiones expiradas
$conn->query("DELETE FROM usuario_sesion WHERE expira < NOW()");

// Áreas
$areas = [];
$ra = $conn->query("SELECT area FROM usuario_areas WHERE usuario_id = $uid");
if ($ra) { while ($a = $ra->fetch_assoc()) $areas[] = $a['area']; $ra->free(); }

json_response([
    'token'   => $token,
    'expira'  => $expira,
    'usuario' => $row['usuario'],
    'nombre'  => $row['nombre'],
    'torneoid'=> (int)$row['torneoid'],
    'tipo'    => (int)($row['tipo'] ?? 0),
    'is_superadmin' => $isSuperadmin,
    'areas'   => $areas,
]);