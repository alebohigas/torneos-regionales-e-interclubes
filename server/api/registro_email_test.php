<?php
/**
 * Registro Email — Diagnostic Endpoint
 * ----------------------------------------------------------------------
 * GET  /api/registro_email_test.php?password=registros2025
 *      → JSON con 4 chequeos secuenciales para depurar el flujo de
 *        envío de correos de pre-registro cuando "no llegan" y no hay
 *        error visible en el navegador.
 *
 * POST /api/registro_email_test.php   (JSON body:
 *      { password:"registros2025", to:"tucorreo@dominio.com" })
 *      → Realiza un envío REAL de prueba (SMTP + PHPMailer) usando la
 *        misma función smtp_send() del flujo de pre-registro y devuelve
 *        el resultado + la cuenta remitente rotada.
 *
 * Uso típico desde el dominio nuevo:
 *   curl "https://dgo.speitour.com.mx/api/registro_email_test.php?password=registros2025"
 *   curl -X POST "https://dgo.speitour.com.mx/api/registro_email_test.php" \
 *        -H "Content-Type: application/json" \
 *        -d '{"password":"registros2025","to":"tucorreo@gmail.com"}'
 *
 * NOTA: Sólo se usa para troubleshoot; no modifica la BD (salvo por el
 * incremento normal de contador en cuentas_correo si el envío ocurre).
 */
require_once 'config.php';
require_once '_smtp.php';
require_once '_registro_emails.php';

const REGISTROS_PASSWORD = 'registros2025';

$password = $_GET['password'] ?? ($_POST['password'] ?? '');
$isPost   = $_SERVER['REQUEST_METHOD'] === 'POST';
$body     = [];
if ($isPost) {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true) ?: [];
    if (!$password) $password = $body['password'] ?? '';
}
if ($password !== REGISTROS_PASSWORD) {
    json_error('Unauthorized (usa ?password=registros2025)', 401);
}

$report = [
    'host'      => $_SERVER['HTTP_HOST'] ?? null,
    'php'       => PHP_VERSION,
    'timestamp' => date('c'),
];

// -------- 1) Credenciales SMTP cargadas --------
global $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_FROM_NAME, $SMTP_REPLY_TO;
$report['credentials'] = [
    'SMTP_HOST'       => $SMTP_HOST ?? null,
    'SMTP_PORT'       => $SMTP_PORT ?? null,
    'SMTP_USER'       => $SMTP_USER ?? null,   // fallback si cuentas_correo falla
    'SMTP_PASS_len'   => isset($SMTP_PASS) ? strlen((string)$SMTP_PASS) : 0,  // NO expone el valor
    'SMTP_FROM_NAME'  => $SMTP_FROM_NAME ?? null,
    'SMTP_REPLY_TO'   => $SMTP_REPLY_TO ?? null,
    'ok'              => !empty($SMTP_HOST) && !empty($SMTP_PASS),
];

// -------- 2) PHPMailer detectable --------
$phpmailerLoaded = smtp_load_phpmailer();
$report['phpmailer'] = [
    'loaded' => $phpmailerLoaded,
    'looked_in' => [__DIR__ . '/PHPMailer', __DIR__ . '/PHPMailer/src'],
    'files_found' => [
        'PHPMailer.php'  => file_exists(__DIR__ . '/PHPMailer/PHPMailer.php')
                           || file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php'),
        'SMTP.php'       => file_exists(__DIR__ . '/PHPMailer/SMTP.php')
                           || file_exists(__DIR__ . '/PHPMailer/src/SMTP.php'),
        'Exception.php'  => file_exists(__DIR__ . '/PHPMailer/Exception.php')
                           || file_exists(__DIR__ . '/PHPMailer/src/Exception.php'),
    ],
];

// -------- 3) cuentas_correo — cuentas disponibles hoy --------
// El esquema se detecta en runtime (torneos: id/cuenta_correo/numcorreos/fecha;
// golftour: idcuentas_correo/cuenta/pwd/acum).
$cuentas = [];
$schema = $conn ? smtp_accounts_schema($conn) : null;
$report['cuentas_correo_schema'] = $schema
    ? array_map(fn($v) => $v ?: null, $schema)
    : null;
if ($conn && $schema) {
    $emailCol = "`{$schema['email']}`";
    $cntCol   = $schema['count'] ? "`{$schema['count']}`" : 'NULL';
    $idCol    = $schema['id'] ? "`{$schema['id']}`" : 'NULL';
    $dateCol  = $schema['date'] ? "`{$schema['date']}`" : 'NULL';
    $order    = $schema['count'] ? "$cntCol ASC" : ($schema['id'] ? "$idCol ASC" : '1');
    $r = @$conn->query(
        "SELECT $idCol AS _id, $emailCol AS _email, $cntCol AS _cnt, $dateCol AS _fecha, "
        . ($schema['pwd'] ? "CHAR_LENGTH(`{$schema['pwd']}`)" : '0') . " AS _pwdlen "
        . "FROM `{$schema['table']}` ORDER BY $order"
    );
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $cnt = $row['_cnt'] === null ? null : (int)$row['_cnt'];
            $cuentas[] = [
                'id'         => $row['_id'] === null ? null : (int)$row['_id'],
                'cuenta'     => $row['_email'],
                'numcorreos' => $cnt,
                'fecha'      => $row['_fecha'],
                'pwd_len'    => (int)$row['_pwdlen'],   // NO expone el valor
                'disponible_hoy_normal'     => $cnt === null ? true : $cnt < 250,
                'disponible_hoy_emergencia' => $cnt === null ? true : $cnt < 500,
            ];
        }
        $r->free();
    } else {
        $report['cuentas_correo_error'] = $conn->error;
    }
}
$report['cuentas_correo'] = [
    'total'                 => count($cuentas),
    'disponibles_hoy'       => count(array_filter($cuentas, fn($c) => $c['disponible_hoy_normal'])),
    'agotadas'              => count(array_filter($cuentas, fn($c) => !$c['disponible_hoy_emergencia'])),
    'detalle'               => $cuentas,
];

// -------- 4) Conectividad SMTP real (sin enviar correo) --------
// Prueba que el hosting nuevo pueda abrir el puerto SMTP de IONOS.
// Los IONOS shared bloquean puertos salientes en algunos planes → aquí lo detectamos.
$connTest = ['attempted' => false];
if (!empty($SMTP_HOST)) {
    $port = (int)($SMTP_PORT ?? 587);
    $connTest['attempted'] = true;
    $connTest['host']      = $SMTP_HOST;
    $connTest['port']      = $port;
    $errno = 0; $errstr = '';
    $t0 = microtime(true);
    $fp = @fsockopen($SMTP_HOST, $port, $errno, $errstr, 8);
    $connTest['elapsed_ms'] = (int)((microtime(true) - $t0) * 1000);
    if ($fp) {
        $connTest['tcp_open']   = true;
        stream_set_timeout($fp, 5);
        $connTest['banner']     = trim((string)fgets($fp, 512));
        fclose($fp);
    } else {
        $connTest['tcp_open']   = false;
        $connTest['errno']      = $errno;
        $connTest['errstr']     = $errstr;
        $connTest['hint']       = 'Si errno=110/113 → hosting nuevo BLOQUEA salida SMTP. Contacta IONOS para habilitar SMTP autenticado saliente en este plan.';
    }
}
$report['smtp_connect'] = $connTest;

// -------- 4.5) Diagnóstico del flujo de pre-registro real --------
// Comprueba metadata de `registro` (columnas) y, si pasas ?regid=NN,
// invoca send_registration_ack_email() con captura de error_log para
// ver EXACTAMENTE por qué el correo no se manda desde el formulario.
$regDiag = ['registro_table' => null];
if ($conn) {
    $cr = @$conn->query("SHOW COLUMNS FROM registro");
    if ($cr) {
        $cols = [];
        while ($rr = $cr->fetch_assoc()) $cols[] = $rr['Field'];
        $cr->free();
        $regDiag['registro_table'] = [
            'has_id'         => in_array('id', $cols, true),
            'has_reg_id'     => in_array('reg_id', $cols, true),
            'has_reg_correo' => in_array('reg_correo', $cols, true),
            'torneo_col'     => (function($cols) {
                foreach (['reg_id_torneo','torneo_id','id_torneo','idtorneo','torneoid'] as $c) {
                    if (in_array($c, $cols, true)) return $c;
                }
                return null;
            })($cols),
        ];
    } else {
        $regDiag['registro_table_error'] = $conn->error;
    }
}
$regId = (int)($_GET['regid'] ?? ($body['regid'] ?? 0));
if ($regId > 0) {
    // Capturar error_log en memoria durante la llamada.
    $tmp = tempnam(sys_get_temp_dir(), 'ack');
    $prevErr = ini_set('error_log', $tmp);
    $prevDisp = ini_set('display_errors', '0');
    // Verificar el row antes de llamar
    $pkCol = $regDiag['registro_table']['has_id'] ? 'id' : 'reg_id';
    $rr = @$conn->query("SELECT $pkCol AS id, reg_correo, reg_nombre, reg_apellido FROM registro WHERE $pkCol = $regId LIMIT 1");
    $rowInfo = $rr ? $rr->fetch_assoc() : null;
    if ($rr) $rr->free();
    $regDiag['row_lookup'] = $rowInfo ?: '(row not found)';
    // Contar cuentas antes/después para confirmar que sí intentó enviar
    $sch = smtp_accounts_schema($conn);
    $sumSql = ($sch && $sch['count'])
        ? "SELECT SUM(`{$sch['count']}`) s FROM `{$sch['table']}`"
        : null;
    $sumFn = function () use ($conn, $sumSql) {
        if (!$sumSql) return 0;
        $r = @$conn->query($sumSql);
        return (int)($r ? ($r->fetch_assoc()['s'] ?? 0) : 0);
    };
    $before = $sumFn();
    send_registration_ack_email($conn, $regId);
    $after = $sumFn();
    $regDiag['numcorreos_delta'] = $after - $before;
    ini_set('error_log', $prevErr);
    ini_set('display_errors', $prevDisp);
    $regDiag['error_log_captured'] = @file_get_contents($tmp) ?: '(sin errores)';
    @unlink($tmp);
}
$report['registro_flow'] = $regDiag;

// -------- 5) Envío real (sólo POST con "to") --------
// Ahora también soporta GET: ?password=...&to=tucorreo@gmail.com
$toParam = trim((string)($body['to'] ?? $_GET['to'] ?? ''));
if ($toParam !== '') {
    $to = $toParam;
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $report['send_test'] = ['ok' => false, 'error' => 'Falta "to" (correo destino válido) en el body JSON'];
    } else {
        $picked = smtp_pick_sender($conn);
        $report['send_test_sender_picked'] = $picked ?: '(null → usará SMTP_USER fallback)';
        // Rollback del +1 que hizo smtp_pick_sender, porque smtp_send() lo volverá a hacer.
        if ($picked) {
            $s = $conn->real_escape_string($picked);
            @$conn->query("UPDATE cuentas_correo SET numcorreos = numcorreos - 1 WHERE cuenta_correo = '$s' LIMIT 1");
        }
        $html = '<p>Prueba de envío desde <b>' . htmlspecialchars($_SERVER['HTTP_HOST'] ?? '?') . '</b></p>'
              . '<p>Timestamp: ' . date('c') . '</p>';
        $res = smtp_send($to, 'Prueba Diagnóstico', 'Prueba SMTP · ' . ($_SERVER['HTTP_HOST'] ?? ''), $html, 'Prueba SMTP');
        $report['send_test'] = $res;
    }
}

json_response($report);