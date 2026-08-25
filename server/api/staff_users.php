<?php
/**
 * staff_users.php — CRUD de usuarios staff temporal (solo admin).
 *
 * Requiere `password=admin2025` (header `Authorization: Bearer admin2025`
 * o `?password=` / body `password`).
 *
 * GET  ?torneoid=X                 → lista de usuarios staff (tipo=99)
 * POST action=create               → crea usuario
 *      body: { usuario, nombre, password, desde, hasta, torneoid, areas:[..] }
 * POST action=update               → actualiza (password opcional)
 *      body: { id, nombre, password?, desde, hasta, activo, areas:[..] }
 * POST action=delete               → elimina (hard delete + sesiones)
 *      body: { id }
 */
require_once 'config.php';
require_once '_staff_auth.php';

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

const ADMIN_PWD = 'admin2025';

function require_admin_pwd($body) {
    global $conn;
    $pwd = $body['password'] ?? $_GET['password'] ?? '';
    if (!$pwd) {
        $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)/i', $hdr, $m)) $pwd = trim($m[1]);
    }
    if (!is_superadmin_password($conn, $pwd)) json_error('Unauthorized', 401);
}

/** Áreas válidas (whitelist sincronizada con el frontend). */
$VALID_AREAS = [
    'preregistros','brackets','banderas','pop','eventos','avisos','menus',
    'premios','convocatoria','reglas','uploads','stats','hoteles','matchplay','live',
];

function sync_areas($conn, $uid, $areas, $valid) {
    $conn->query("DELETE FROM usuario_areas WHERE usuario_id = $uid");
    foreach ((array)$areas as $a) {
        if (!in_array($a, $valid, true)) continue;
        $ae = $conn->real_escape_string($a);
        $conn->query("INSERT IGNORE INTO usuario_areas (usuario_id, area) VALUES ($uid, '$ae')");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_admin_pwd([]);
    $torneoid = (int)($_GET['torneoid'] ?? 0);
    $where = "tipo = 99";
    if ($torneoid > 0) $where .= " AND torneoid = $torneoid";
    $rows = query_all($conn, "SELECT id, usuario, nombre, torneoid, desde, hasta, activo, estatus
                                FROM " . USERS_TABLE . " WHERE $where ORDER BY id DESC");
    foreach ($rows as &$r) {
        $uid = (int)$r['id'];
        $areas = [];
        $ra = $conn->query("SELECT area FROM usuario_areas WHERE usuario_id = $uid");
        if ($ra) { while ($a = $ra->fetch_assoc()) $areas[] = $a['area']; $ra->free(); }
        $r['areas'] = $areas;
    }
    json_response(['users' => $rows]);
}

// POST
$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];
require_admin_pwd($body);
$action = $_GET['action'] ?? $body['action'] ?? '';

if ($action === 'create') {
    $usuario = trim((string)($body['usuario'] ?? ''));
    $nombre  = trim((string)($body['nombre'] ?? ''));
    $password = (string)($body['password_user'] ?? $body['pwd'] ?? '');
    $desde   = (string)($body['desde'] ?? '');
    $hasta   = (string)($body['hasta'] ?? '');
    $torneoid = (int)($body['torneoid'] ?? 0);
    $areas = $body['areas'] ?? [];

    if ($usuario === '' || $password === '' || $desde === '' || $hasta === '') {
        json_error('Faltan campos requeridos', 400);
    }
    // Único
    $ue = esc($conn, $usuario);
    $ex = query_one($conn, "SELECT id FROM " . USERS_TABLE . " WHERE usuario = '$ue' LIMIT 1");
    if ($ex) json_error('Usuario ya existe', 409);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ne = esc($conn, $nombre);
    $he = esc($conn, $hash);
    $de = esc($conn, $desde);
    $ha = esc($conn, $hasta);
    $sql = "INSERT INTO " . USERS_TABLE . " (usuario, nombre, pwd, torneoid, desde, hasta, activo, estatus, tipo)
            VALUES ('$ue','$ne','$he',$torneoid,'$de','$ha',1,'activo',99)";
    if (!$conn->query($sql)) json_error('Insert failed: ' . $conn->error, 500);
    $uid = $conn->insert_id;
    sync_areas($conn, $uid, $areas, $VALID_AREAS);
    json_response(['ok' => true, 'id' => $uid]);
}

if ($action === 'update') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) json_error('Missing id', 400);
    $sets = [];
    if (isset($body['nombre']))   $sets[] = "nombre = '"  . esc($conn, $body['nombre']) . "'";
    if (isset($body['desde']))    $sets[] = "desde = '"   . esc($conn, $body['desde'])  . "'";
    if (isset($body['hasta']))    $sets[] = "hasta = '"   . esc($conn, $body['hasta'])  . "'";
    if (isset($body['activo']))   $sets[] = "activo = "   . ((int)$body['activo'] ? 1 : 0);
    if (isset($body['torneoid'])) $sets[] = "torneoid = " . (int)$body['torneoid'];
    if (!empty($body['password_user'])) {
        $h = password_hash((string)$body['password_user'], PASSWORD_DEFAULT);
        $sets[] = "pwd = '" . esc($conn, $h) . "'";
    }
    if ($sets) {
        $sql = "UPDATE " . USERS_TABLE . " SET " . implode(', ', $sets) . " WHERE id = $id AND tipo = 99";
        if (!$conn->query($sql)) json_error('Update failed: ' . $conn->error, 500);
    }
    if (isset($body['areas'])) sync_areas($conn, $id, $body['areas'], $VALID_AREAS);
    // Invalidar sesiones activas si se cambia contraseña o se desactiva
    if (!empty($body['password_user']) || (isset($body['activo']) && !$body['activo'])) {
        $conn->query("DELETE FROM usuario_sesion WHERE usuario_id = $id");
    }
    json_response(['ok' => true]);
}

if ($action === 'delete') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) json_error('Missing id', 400);
    $conn->query("DELETE FROM usuario_sesion WHERE usuario_id = $id");
    $conn->query("DELETE FROM usuario_areas WHERE usuario_id = $id");
    $conn->query("DELETE FROM " . USERS_TABLE . " WHERE id = $id AND tipo = 99");
    json_response(['ok' => true]);
}

json_error('Unknown action', 400);