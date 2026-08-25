<?php
/**
 * admin_auth.php — username-less superadmin auth.
 * ------------------------------------------------------------
 * Keeps the legacy superadmin separate from `usuarios` staff temporal.
 * POST ?action=login           Body: { password }
 * POST ?action=change_password Body: { current_password, new_password }
 */
require_once 'config.php';

header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) json_error('Invalid JSON body', 400);

$action = $_GET['action'] ?? $body['action'] ?? 'login';

if ($action === 'login') {
    $password = (string)($body['password'] ?? '');
    if (!is_superadmin_password($conn, $password)) json_error('Unauthorized', 401);
    establish_superadmin_session();
    json_response(['ok' => true]);
}

if ($action === 'change_password') {
    $current = (string)($body['current_password'] ?? '');
    $new = (string)($body['new_password'] ?? '');

    if (!is_superadmin_password($conn, $current)) json_error('Contraseña actual incorrecta', 401);
    if (strlen($new) < 8) json_error('La nueva contraseña debe tener mínimo 8 caracteres', 400);
    if (hash_equals($current, $new)) json_error('La nueva contraseña debe ser distinta', 400);

    set_superadmin_password_hash($conn, password_hash($new, PASSWORD_DEFAULT));
    establish_superadmin_session();
    json_response(['ok' => true, 'changed' => true]);
}

json_error('Unknown action', 400);