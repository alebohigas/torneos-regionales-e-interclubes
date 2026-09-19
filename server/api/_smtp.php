<?php
/**
 * SMTP helper for registro emails
 * ----------------------------------------------------------------------
 * Sends transactional emails for the pre-registration flow.
 *
 * Uses PHPMailer when present at /api/PHPMailer/ (3 source files dropped
 * in manually — Composer is not available on IONOS shared). Falls back
 * to PHP's native mail() with HTML headers when PHPMailer is missing so
 * deployments without the library still get a (best-effort) send.
 *
 * SMTP credentials are loaded from credentials.php:
 *   $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_FROM_NAME
 */

/**
 * Try to load PHPMailer classes from /api/PHPMailer/.
 * Supports both deployment layouts:
 *   - /api/PHPMailer/{PHPMailer,SMTP,Exception}.php
 *   - /api/PHPMailer/src/{PHPMailer,SMTP,Exception}.php
 * Returns true if all three required files are loaded.
 */
function smtp_load_phpmailer() {
    static $loaded = null;
    if ($loaded !== null) return $loaded;
    $baseCandidates = [
        __DIR__ . '/PHPMailer',
        __DIR__ . '/PHPMailer/src',
    ];
    $files = ['Exception.php', 'PHPMailer.php', 'SMTP.php'];
    $base = null;
    foreach ($baseCandidates as $candidate) {
        $hasAllFiles = true;
        foreach ($files as $f) {
            if (!file_exists("$candidate/$f")) { $hasAllFiles = false; break; }
        }
        if ($hasAllFiles) { $base = $candidate; break; }
    }
    if (!$base) {
        error_log('[smtp] PHPMailer no encontrado. Esperado en /api/PHPMailer/ o /api/PHPMailer/src/');
        $loaded = false;
        return false;
    }
    foreach ($files as $f) {
        require_once "$base/$f";
    }
    $loaded = class_exists('\\PHPMailer\\PHPMailer\\PHPMailer');
    return $loaded;
}

/**
 * Detecta el esquema real de `cuentas_correo` en la base activa.
 *
 * Hay dos variantes en producción:
 *   A) torneos  : id, cuenta_correo, numcorreos, fecha
 *   B) golftour : idcuentas_correo, cuenta, pwd, acum   (contraseña por cuenta)
 *
 * Devuelve ['table','id','email','count','date','pwd'] o null si no existe.
 */
function smtp_accounts_schema($conn) {
    static $schema = null;
    static $checked = false;
    if ($checked) return $schema;
    $checked = true;
    if (!$conn) return null;

    $res = @$conn->query("SHOW COLUMNS FROM cuentas_correo");
    if (!$res) {
        error_log('[smtp] tabla cuentas_correo no disponible; se usará SMTP_USER/SMTP_PASS de credentials.php');
        return null;
    }
    $cols = [];
    while ($r = $res->fetch_assoc()) $cols[strtolower($r['Field'])] = $r['Field'];
    $res->free();

    $pick = function (array $names) use ($cols) {
        foreach ($names as $n) if (isset($cols[$n])) return $cols[$n];
        return null;
    };

    $schema = [
        'table' => 'cuentas_correo',
        'id'    => $pick(['id', 'idcuentas_correo', 'idcuenta', 'idcuentas']),
        'email' => $pick(['cuenta_correo', 'cuenta', 'correo', 'email']),
        'count' => $pick(['numcorreos', 'acum', 'contador']),
        'date'  => $pick(['fecha', 'fecha_envio', 'ultimo_envio']),
        'pwd'   => $pick(['pwd', 'password', 'contrasena', 'clave', 'pass']),
    ];
    if (!$schema['email']) {
        error_log('[smtp] cuentas_correo sin columna de correo reconocible');
        $schema = null;
    }
    return $schema;
}

/**
 * Elige el siguiente buzón remitente de `cuentas_correo`.
 *
 * Política de rotación:
 *   1) Reset diario del contador (solo si la tabla tiene columna de fecha).
 *   2) Modo normal: la cuenta con menor contador y < 250 envíos del día.
 *   3) Modo emergencia: si todas pasaron de 250, rota cada 50 hasta 500
 *      (límite duro de IONOS).
 *   4) Reserva +1 envío para el destinatario principal (los CC se cargan
 *      luego con smtp_bump_counter()).
 *
 * Devuelve ['email'=>..., 'pass'=>string|null] o null si no hay cuenta
 * disponible (en ese caso el caller usa $SMTP_USER/$SMTP_PASS estáticos).
 */
function smtp_pick_sender($conn = null) {
    if (!$conn) { global $conn; }
    if (!$conn) return null;

    $s = smtp_accounts_schema($conn);
    if (!$s) return null;

    $t     = $s['table'];
    $email = "`{$s['email']}`";
    $cnt   = $s['count'] ? "`{$s['count']}`" : null;
    $idc   = $s['id'] ? "`{$s['id']}`" : null;
    $pwdc  = $s['pwd'] ? "`{$s['pwd']}`" : null;
    $order = $cnt ? "$cnt ASC" . ($idc ? ", $idc ASC" : '') : ($idc ? "$idc ASC" : '1');

    // 1) Reset diario del contador cuando la tabla guarda fecha.
    if ($cnt && $s['date']) {
        @$conn->query(
            "UPDATE $t SET $cnt = 0 "
            . "WHERE LEFT(`{$s['date']}`,10) < LEFT(CURDATE(),10)"
        );
    }

    $select = "SELECT $email AS _email" . ($idc ? ", $idc AS _id" : '')
            . ($pwdc ? ", $pwdc AS _pwd" : '') . ($cnt ? ", $cnt AS _cnt" : '');

    // 2) Modo normal: la cuenta con menos envíos del día (< 250).
    $where = $cnt ? "WHERE $cnt < 250 " : '';
    $res = @$conn->query("$select FROM $t $where ORDER BY $order LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) $res->free();

    // 3) Modo emergencia: nadie bajo 250 → rotar cada 50 hasta tope 500.
    if (!$row && $cnt) {
        $order2 = "FLOOR($cnt/50) ASC" . ($idc ? ", $idc ASC" : '');
        $res2 = @$conn->query("$select FROM $t WHERE $cnt < 500 ORDER BY $order2 LIMIT 1");
        $row = $res2 ? $res2->fetch_assoc() : null;
        if ($res2) $res2->free();
        if ($row) {
            error_log('[smtp] cuentas_correo en modo emergencia: todas las cuentas > 250, rotando cada 50.');
        }
    }

    if (!$row) {
        error_log('[smtp] cuentas_correo agotado o vacío: no hay cuenta de envío disponible.');
        return null;
    }

    $picked = trim((string)($row['_email'] ?? ''));
    if ($picked === '') return null;

    // 4) Reservar +1 envío (TO) y refrescar fecha si existe la columna.
    if ($cnt) {
        $sets = "$cnt = $cnt + 1" . ($s['date'] ? ", `{$s['date']}` = NOW()" : '');
        if ($idc && isset($row['_id'])) {
            $idv = (int)$row['_id'];
            @$conn->query("UPDATE $t SET $sets WHERE $idc = $idv LIMIT 1");
        } else {
            $esc = $conn->real_escape_string($picked);
            @$conn->query("UPDATE $t SET $sets WHERE $email = '$esc' LIMIT 1");
        }
    }

    $pass = isset($row['_pwd']) ? trim((string)$row['_pwd']) : '';
    return ['email' => $picked, 'pass' => $pass !== '' ? $pass : null];
}

/**
 * Suma N envíos extra (destinatarios en CC) al contador del buzón usado.
 *
 * @param mysqli $conn   Conexión activa.
 * @param string $sender Correo usado como remitente.
 * @param int    $extra  Destinatarios adicionales a cargar.
 */
function smtp_bump_counter($conn, $sender, $extra) {
    if (!$conn || !$sender || $extra <= 0) return;
    $s = smtp_accounts_schema($conn);
    if (!$s || !$s['count']) return;
    $esc = $conn->real_escape_string($sender);
    $n = (int)$extra;
    @$conn->query(
        "UPDATE `{$s['table']}` SET `{$s['count']}` = `{$s['count']}` + $n "
        . "WHERE `{$s['email']}` = '$esc' LIMIT 1"
    );
}

/**
 * Send an HTML email. Returns ['ok'=>bool, 'error'=>string|null].
 *
 * El remitente (Username/From) se rota por llamada desde `cuentas_correo`
 * para que ningún buzón de IONOS pase de 250 envíos al día. Si la tabla
 * guarda la contraseña de cada cuenta (columna `pwd`), se usa esa; si no,
 * se usa la compartida $SMTP_PASS de credentials.php. Reply-To queda fijo
 * en $SMTP_REPLY_TO.
 *
 * @param string       $to       Primary recipient email
 * @param string       $toName   Recipient display name
 * @param string       $subject  Subject line
 * @param string       $html     HTML body
 * @param string       $textAlt  Plain-text alternative (optional)
 * @param array        $cc       Optional list of CC emails (or [email,name] pairs)
 */
function smtp_send($to, $toName, $subject, $html, $textAlt = '', $cc = []) {
    global $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_FROM_NAME,
           $SMTP_REPLY_TO, $conn;

    // 1) Rotate sender via cuentas_correo. Fall back to static SMTP_USER
    //    if the DB lookup fails so registro_email keeps working.
    $picked   = smtp_pick_sender($conn);
    $fromAddr = $picked['email'] ?? ($SMTP_USER ?? '');
    $fromPass = $picked['pass'] ?? null;
    if (!$fromPass) $fromPass = $SMTP_PASS ?? '';
    $fromName = $SMTP_FROM_NAME ?? 'Pre-Registro';
    $replyTo  = $SMTP_REPLY_TO ?? ($fromAddr ?: 'noreply@speitour.mx');
    if (!$fromAddr) {
        return ['ok' => false, 'error' => 'No hay cuenta de envío disponible (cuentas_correo agotado y SMTP_USER vacío)'];
    }

    // 2) Normalize CC list to [[email, name], ...] for PHPMailer.
    $ccList = [];
    foreach ((array)$cc as $entry) {
        if (is_array($entry)) {
            $addr = trim((string)($entry[0] ?? ''));
            $nm   = trim((string)($entry[1] ?? ''));
        } else {
            $addr = trim((string)$entry);
            $nm   = '';
        }
        if ($addr !== '') $ccList[] = [$addr, $nm];
    }

    // Preferred path: PHPMailer over SMTP with credentials.
    if (smtp_load_phpmailer() && !empty($SMTP_HOST) && !empty($fromPass)) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = $fromAddr;
            $mail->Password   = $fromPass;
            $mail->Port       = (int)($SMTP_PORT ?? 587);
            $mail->SMTPSecure = ((int)$mail->Port === 465)
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom($fromAddr, $fromName);
            $mail->addAddress($to, $toName ?: $to);
            foreach ($ccList as [$ccAddr, $ccName]) {
                $mail->addCC($ccAddr, $ccName ?: $ccAddr);
            }
            $mail->addReplyTo($replyTo, $fromName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            if ($textAlt) $mail->AltBody = $textAlt;
            $mail->send();
            smtp_bump_counter($conn, $fromAddr, count($ccList));
            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            error_log('[smtp] PHPMailer failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // Fallback: PHP mail() with HTML headers (deliverability not guaranteed).
    $boundary = bin2hex(random_bytes(8));
    $headers  = "From: {$fromName} <{$fromAddr}>\r\n";
    $headers .= "Reply-To: <{$replyTo}>\r\n";
    if (!empty($ccList)) {
        $ccHeader = implode(', ', array_map(fn($p) => $p[0], $ccList));
        $headers .= "Cc: {$ccHeader}\r\n";
    }
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $ok = @mail($to, $subject, $html, $headers);
    if (!$ok) error_log("[smtp] mail() fallback failed for $to");
    if ($ok) smtp_bump_counter($conn, $fromAddr, count($ccList));
    return ['ok' => (bool)$ok, 'error' => $ok ? null : 'mail() falló (sin PHPMailer/SMTP)'];
}

/**
 * Build the absolute public URL for the player's "Adjuntar comprobante"
 * page using the active host. Falls back to https if scheme cannot be
 * detected (most IONOS deployments are TLS).
 */
function smtp_public_origin() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
        (($_SERVER['SERVER_PORT'] ?? '') == 443)
    );
    return ($isHttps ? 'https' : 'http') . '://' . $host;
}