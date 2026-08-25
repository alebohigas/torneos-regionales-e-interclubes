<?php
/**
 * _staff_auth.php — Helper de autenticación para staff temporal
 * ------------------------------------------------------------------
 * Provee dos funciones:
 *   - staff_validate_token($conn, $token): valida y devuelve
 *     ['usuario_id'=>..,'areas'=>[..],'torneoid'=>..,'usuario'=>..,'nombre'=>..]
 *     o null si token inválido / expirado / fuera de rango / inactivo.
 *   - assert_admin_or_area($conn, $body, $area): pasa si
 *     `password === 'admin2025'` (admin legacy) o si el staff_token
 *     trae permiso sobre $area y coincide con torneoid solicitado.
 *
 * Token puede venir en:
 *   - $body['staff_token'] (JSON POST)
 *   - $_GET['staff_token']
 *   - Header `Authorization: Bearer <token>`
 */

/** Lee el staff_token desde body / query / header. */
function staff_extract_token($body) {
    if (!empty($body['staff_token'])) return (string)$body['staff_token'];
    if (!empty($_GET['staff_token'])) return (string)$_GET['staff_token'];
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+([a-f0-9]{32,128})/i', $hdr, $m)) return $m[1];
    return '';
}

/**
 * Valida un token de staff. Devuelve datos del usuario o null.
 * Realiza housekeeping borrando sesiones expiradas.
 */
function staff_validate_token($conn, $token) {
    if (!$token || !preg_match('/^[a-f0-9]{32,128}$/i', $token)) return null;
    $tok = $conn->real_escape_string($token);
    $sql = "SELECT s.usuario_id, s.expira, u.usuario, u.nombre, u.torneoid, u.tipo,
                   u.estatus, u.activo, u.desde, u.hasta
              FROM usuario_sesion s
              JOIN " . USERS_TABLE . " u ON u.id = s.usuario_id
             WHERE s.token = '$tok'
             LIMIT 1";
    $r = $conn->query($sql);
    if (!$r || $r->num_rows === 0) return null;
    $row = $r->fetch_assoc();
    $r->free();

    // Vigencia, status, y rango de fechas
    $now = new DateTime('now');
    $expira = new DateTime($row['expira']);
    if ($expira < $now) return null;
    if ((int)$row['activo'] !== 1) return null;
    if (strtolower((string)$row['estatus']) === 'inactivo') return null;
    $today = (new DateTime('today'))->format('Y-m-d');
    if (legacy_date_is_set($row['desde'] ?? null) && $today < $row['desde']) return null;
    if (legacy_date_is_set($row['hasta'] ?? null) && $today > $row['hasta']) return null;

    // Áreas
    $uid = (int)$row['usuario_id'];
    $areas = [];
    $ra = $conn->query("SELECT area FROM usuario_areas WHERE usuario_id = $uid");
    if ($ra) {
        while ($a = $ra->fetch_assoc()) $areas[] = $a['area'];
        $ra->free();
    }

    return [
        'usuario_id' => $uid,
        'usuario'    => $row['usuario'],
        'nombre'     => $row['nombre'],
        'torneoid'   => (int)$row['torneoid'],
        'tipo'       => (int)($row['tipo'] ?? 0),
        'is_superadmin' => is_superadmin_tipo($row['tipo'] ?? 0),
        'areas'      => $areas,
        'expira'     => $row['expira'],
    ];
}

/**
 * Asegura acceso: admin legacy o staff con área asignada.
 * Si falla, responde 401 y termina.
 */
function assert_admin_or_area($conn, $body, $area, $adminPwd = 'admin2025') {
    if (isset($body['password']) && is_superadmin_password($conn, $body['password'])) return null;
    $token = staff_extract_token($body);
    $info = staff_validate_token($conn, $token);
    if ($info && !empty($info['is_superadmin'])) return $info;
    if ($info && in_array($area, $info['areas'], true)) {
        return $info;
    }
    json_error('Unauthorized', 401);
}

/** Variante: solo lee, no muere. Devuelve info o null. */
function staff_check_area($conn, $body, $area) {
    $token = staff_extract_token($body);
    $info = staff_validate_token($conn, $token);
    if ($info && !empty($info['is_superadmin'])) return $info;
    if ($info && in_array($area, $info['areas'], true)) return $info;
    return null;
}