<?php
/**
 * Registro Email Helpers
 * ----------------------------------------------------------------------
 * Helpers shared by registro.php (initial pre-registration submit) and
 * registro_publico.php (player uploads comprobante via public token):
 *
 *   send_registration_ack_email($conn, $regId)
 *     "Pre-registro recibido" — sent right after a normal (non-waitlist)
 *     INSERT so the player gets immediate confirmation of their entry.
 *
 *   send_comprobante_received_email($conn, $regId)
 *     "Comprobante recibido" — sent when the player attaches the payment
 *     receipt through the public token page, so they know it arrived.
 *
 * Both are best-effort: errors are logged and never break the flow.
 * Both rely on smtp_send() from _smtp.php (already loaded by callers).
 */

if (!function_exists('send_registration_ack_email')) {

/**
 * Resolve {pkCol, torneoCol, has} for the `registro` table in one pass.
 * Returns null if essential columns are missing.
 */
function _regmail_meta($conn) {
    $cols = query_all($conn, "SHOW COLUMNS FROM registro");
    $set  = [];
    foreach ($cols as $c) $set[$c['Field']] = true;
    $has = fn($c) => isset($set[$c]);
    $pkCol = $has('id') ? 'id' : ($has('reg_id') ? 'reg_id' : null);
    $torneoCol = null;
    foreach (['reg_id_torneo','torneo_id','id_torneo','idtorneo','torneoid'] as $c) {
        if ($has($c)) { $torneoCol = $c; break; }
    }
    if (!$pkCol || !$torneoCol) return null;
    return ['pk' => $pkCol, 'torneo' => $torneoCol, 'has' => $has];
}

/**
 * Lista de CC internos para los correos de pre-registro.
 * ÚNICAMENTE info@speitour.mx; se ignoran los correos adicionales
 * que pudiera traer el torneo para evitar destinatarios no deseados.
 *
 * @param string $torneoMail Valor crudo de torneo.correotorne (ignorado)
 * @return array<int,array{0:string,1:string}> Pares [email, nombre]
 */
function _regmail_admin_cc($torneoMail = '') {
    return [['info@speitour.mx', 'SPEI Tour']];
}

/**
 * Etiquetas de los campos marcados como OBLIGATORIOS en
 * "Pre-Registro · Configuración de campos" (registro_form_fields).
 * Devuelve un mapa field_name => field_label sólo con los campos
 * habilitados + requeridos del torneo. Si la tabla no existe o el
 * torneo no tiene configuración, devuelve un arreglo vacío.
 *
 * @return array<string,string>
 */
function _regmail_required_fields($conn, $torneoid) {
    $torneoid = (int)$torneoid;
    if ($torneoid <= 0) return [];
    $t = @$conn->query("SHOW TABLES LIKE 'registro_form_fields'");
    if (!$t || $t->num_rows === 0) return [];
    $r = @$conn->query(
        "SELECT field_name, field_label FROM registro_form_fields
          WHERE torneo_id = $torneoid AND is_enabled = 1 AND is_required = 1
          ORDER BY display_order ASC"
    );
    $out = [];
    if ($r) {
        while ($f = $r->fetch_assoc()) {
            $out[$f['field_name']] = $f['field_label'] ?: $f['field_name'];
        }
        $r->free();
    }
    return $out;
}

/**
 * Normaliza el valor de un campo del registro para mostrarlo en el correo.
 * Traduce catálogos (categoría, género, socio) y devuelve '' cuando el
 * valor está vacío para poder omitir la fila.
 */
function _regmail_field_value($conn, $row, $field, $catName) {
    switch ($field) {
        case 'reg_categoria':
            return $catName;
        case 'reg_sexo':
        case 'reg_genero':
            $g = strtoupper(trim((string)($row['reg_genero'] ?? $row['reg_sexo'] ?? '')));
            if ($g === '') return '';
            if (in_array($g, ['M','H','MASCULINO','HOMBRE','C','CABALLERO'], true)) return 'Caballero';
            if (in_array($g, ['F','D','FEMENINO','MUJER','DAMA'], true))          return 'Dama';
            return $g;
        case 'reg_telefono':
        case 'reg_celular':
            return trim((string)($row['reg_celular'] ?? $row['reg_telefono'] ?? ''));
        case 'reg_es_socio':
            $s = strtoupper(trim((string)($row['reg_es_socio'] ?? '')));
            if ($s === '') return '';
            return $s === 'SI' ? 'Sí' : 'No';
        case 'reg_ghin':
        case 'numghinspei':
            return trim((string)($row['numghinspei'] ?? $row['reg_ghin'] ?? ''));
        case 'reg_notas':
        case 'reg_mensaje':
            return trim((string)($row['reg_mensaje'] ?? $row['reg_notas'] ?? ''));
        default:
            return trim((string)($row[$field] ?? ''));
    }
}

/** Confirmation email after the initial pre-registration form is saved. */
function send_registration_ack_email($conn, $registroId) {
    $registroId = (int)$registroId;
    if ($registroId <= 0) return;
    $meta = _regmail_meta($conn);
    if (!$meta) return;
    $pkCol = $meta['pk']; $torneoCol = $meta['torneo']; $has = $meta['has'];

    // Se lee la fila completa: el correo debe poder mostrar cualquier campo
    // marcado como obligatorio en la configuración del torneo.
    $row = query_one($conn, "SELECT *, $pkCol AS id, $torneoCol AS torneoid FROM registro WHERE $pkCol = $registroId LIMIT 1");
    if (!$row || empty($row['reg_correo'])) return;

    $folio = ((int)$row['torneoid'] > 0)
        ? ((int)$row['torneoid'] . '-' . (int)$row['id'])
        : ('' . (int)$row['id']);

    $catName = '';
    if (!empty($row['reg_categoria'])) {
        $cr = @$conn->query("SELECT categoria FROM categorias WHERE categoria_id = " . (int)$row['reg_categoria'] . " LIMIT 1");
        if ($cr) { $cc = $cr->fetch_assoc(); $cr->free(); if ($cc) $catName = $cc['categoria']; }
    }
    $torneoName = '';
    $torneoMail = '';
    $tr = @$conn->query("SELECT nombre, correotorne FROM torneo WHERE torneo_id = " . (int)$row['torneoid'] . " LIMIT 1");
    if ($tr) {
        $tt = $tr->fetch_assoc(); $tr->free();
        if ($tt) {
            $torneoName = $tt['nombre'] ?? '';
            $torneoMail = trim((string)($tt['correotorne'] ?? ''));
        }
    }

    $nombre = trim(($row['reg_nombre'] ?? '') . ' ' . ($row['reg_apellido'] ?? ''));
    if ($nombre === '') $nombre = 'Jugador';

    $b = fn($v) => '<strong>' . htmlspecialchars((string)($v ?? '—'), ENT_QUOTES, 'UTF-8') . '</strong>';

    /**
     * Bloque fijo obligatorio del correo (siempre presente):
     * Torneo, Folio, Jugador, Categoría, Club, Handicap Índice, Género,
     * Correo y Celular del jugador.
     */
    $genero  = _regmail_field_value($conn, $row, 'reg_genero',  $catName);
    $celular = _regmail_field_value($conn, $row, 'reg_celular', $catName);
    $entries = [];
    if ($torneoName) $entries[] = ['Torneo', $torneoName];
    $entries[] = ['Folio',            '#' . $folio];
    $entries[] = ['Jugador',          $nombre];
    $entries[] = ['Categoría',        $catName ?: '—'];
    $entries[] = ['Club',             $row['reg_club'] ?: '—'];
    $entries[] = ['Handicap Índice',  ($row['reg_handicap'] !== null && $row['reg_handicap'] !== '') ? $row['reg_handicap'] : '—'];
    $entries[] = ['Género',           $genero ?: '—'];
    $entries[] = ['Correo',           $row['reg_correo']];
    $entries[] = ['Celular',          $celular ?: '—'];

    /**
     * Campos extra marcados como OBLIGATORIOS en Pre-Registro ·
     * Configuración de campos. Los que ya están en el bloque fijo se
     * omiten para no duplicar filas.
     */
    $skip = [
        'reg_nombre' => 1, 'reg_apellido' => 1, 'reg_correo' => 1,
        'reg_categoria' => 1, 'reg_club' => 1, 'reg_handicap' => 1,
        'reg_sexo' => 1, 'reg_genero' => 1, 'reg_telefono' => 1,
        'reg_celular' => 1, 'reg_archivo' => 1,
    ];
    foreach (_regmail_required_fields($conn, (int)$row['torneoid']) as $fname => $flabel) {
        if (isset($skip[$fname])) continue;
        $val = _regmail_field_value($conn, $row, $fname, $catName);
        if ($val === '') continue;
        $entries[] = [$flabel, $val];
    }
    $rowsHtml = '';
    foreach ($entries as [$label, $val]) {
        $rowsHtml .= '<tr>'
            . '<td style="padding:6px 12px;color:#666;font-size:13px;white-space:nowrap;">' . htmlspecialchars($label) . '</td>'
            . '<td style="padding:6px 12px;font-size:14px;">' . $b($val) . '</td>'
            . '</tr>';
    }

    $subject = 'Pre-registro recibido · Folio #' . $folio
        . ($torneoName ? ' · ' . $torneoName : '');

    $html = '<!doctype html><html><body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f4f4f5;padding:24px 0;">'
        . '<tr><td align="center">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;background:#ffffff;border-radius:8px;padding:32px;">'
        . '<tr><td>'
        . '<h1 style="font-size:20px;margin:0 0 8px;color:#0a7d3e;">Pre-registro recibido</h1>'
        . '<p style="font-size:14px;line-height:1.5;margin:0 0 16px;">Hola ' . htmlspecialchars($nombre) . ',</p>'
        . '<p style="font-size:14px;line-height:1.6;margin:0 0 16px;">'
        . 'Hemos recibido tu pre-registro. El comité del torneo lo revisará y te contactaremos para los siguientes pasos.'
        . '</p>'
        . '<h2 style="font-size:13px;margin:24px 0 8px;color:#666;text-transform:uppercase;letter-spacing:0.5px;">Detalles de tu pre-registro</h2>'
        . '<table cellpadding="0" cellspacing="0" style="width:100%;border:1px solid #eee;border-radius:6px;border-collapse:collapse;">'
        . $rowsHtml
        . '</table>'
        . '<p style="font-size:12px;color:#999;margin:32px 0 0;text-align:center;">'
        . ($torneoName ? htmlspecialchars($torneoName) : 'Pre-Registro')
        . '</p>'
        . '<p style="font-size:10px;color:#cccccc;margin:8px 0 0;text-align:center;">Ref: '
        . htmlspecialchars($folio) . ' · ' . date('Y-m-d H:i:s')
        . '</p>'
        . '</td></tr></table>'
        . '</td></tr></table>'
        . '</body></html>';

    $textAlt = "Hola $nombre,\n\n"
        . "Hemos recibido tu pre-registro.\n\n"
        . implode("\n", array_map(function($e) { return $e[0] . ': ' . $e[1]; }, $entries))
        . "\n";

    // CC fijo al buzón de coordinación para tener trazabilidad de cada paso del pre-registro.
    // Se añade también el correo del torneo (torneo.correotorne) cuando existe.
    $ccAdmin = _regmail_admin_cc($torneoMail);
    $res = smtp_send($row['reg_correo'], $nombre, $subject, $html, $textAlt, $ccAdmin);
    if (!$res['ok']) {
        error_log('[registro_ack_email] send failed id=' . $registroId . ' err=' . ($res['error'] ?? ''));
    }
}

/** Confirmation email after the player uploads the payment receipt. */
function send_comprobante_received_email($conn, $registroId) {
    $registroId = (int)$registroId;
    if ($registroId <= 0) return;
    $meta = _regmail_meta($conn);
    if (!$meta) return;
    $pkCol = $meta['pk']; $torneoCol = $meta['torneo']; $has = $meta['has'];

    $sel = ["$pkCol AS id", "$torneoCol AS torneoid"];
    foreach (['reg_nombre','reg_apellido','reg_correo','reg_categoria',
              'reg_precio_estimado','reg_precio_moneda'] as $c) {
        if ($has($c)) $sel[] = $c;
    }
    $row = query_one($conn, "SELECT " . implode(',', $sel) . " FROM registro WHERE $pkCol = $registroId LIMIT 1");
    if (!$row || empty($row['reg_correo'])) return;

    $folio = ((int)$row['torneoid'] > 0)
        ? ((int)$row['torneoid'] . '-' . (int)$row['id'])
        : ('' . (int)$row['id']);

    $torneoName = '';
    $torneoMail = '';
    $tr = @$conn->query("SELECT nombre, correotorne FROM torneo WHERE torneo_id = " . (int)$row['torneoid'] . " LIMIT 1");
    if ($tr) {
        $tt = $tr->fetch_assoc(); $tr->free();
        if ($tt) {
            $torneoName = $tt['nombre'] ?? '';
            $torneoMail = trim((string)($tt['correotorne'] ?? ''));
        }
    }

    $catName = '';
    if (!empty($row['reg_categoria'])) {
        $cr = @$conn->query("SELECT categoria FROM categorias WHERE categoria_id = " . (int)$row['reg_categoria'] . " LIMIT 1");
        if ($cr) { $cc = $cr->fetch_assoc(); $cr->free(); if ($cc) $catName = $cc['categoria']; }
    }

    $nombre = trim(($row['reg_nombre'] ?? '') . ' ' . ($row['reg_apellido'] ?? ''));
    if ($nombre === '') $nombre = 'Jugador';

    $subject = 'Comprobante recibido · Folio #' . $folio
        . ($torneoName ? ' · ' . $torneoName : '');

    $html = '<!doctype html><html><body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f4f4f5;padding:24px 0;">'
        . '<tr><td align="center">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;background:#ffffff;border-radius:8px;padding:32px;">'
        . '<tr><td>'
        . '<h1 style="font-size:20px;margin:0 0 8px;color:#0a7d3e;">¡Comprobante recibido!</h1>'
        . '<p style="font-size:14px;line-height:1.5;margin:0 0 16px;">Hola ' . htmlspecialchars($nombre) . ',</p>'
        . '<p style="font-size:14px;line-height:1.6;margin:0 0 16px;">'
        . 'Recibimos tu comprobante de pago para el folio <strong>#' . htmlspecialchars($folio) . '</strong>'
        . ($catName ? ' (categoría <strong>' . htmlspecialchars($catName) . '</strong>)' : '')
        . '. El comité del torneo validará el pago y te confirmaremos tu registro en breve.'
        . '</p>'
        . '<p style="font-size:12px;color:#999;margin:32px 0 0;text-align:center;">'
        . ($torneoName ? htmlspecialchars($torneoName) : 'Pre-Registro')
        . '</p>'
        . '<p style="font-size:10px;color:#cccccc;margin:8px 0 0;text-align:center;">Ref: '
        . htmlspecialchars($folio) . ' · ' . date('Y-m-d H:i:s')
        . '</p>'
        . '</td></tr></table>'
        . '</td></tr></table>'
        . '</body></html>';

    $textAlt = "Hola $nombre,\n\n"
        . "Recibimos tu comprobante de pago para el folio #$folio.\n"
        . "El comité validará tu pago y te confirmaremos tu registro en breve.\n"
        . ($torneoName ? "Torneo: $torneoName\n" : '');

    // CC fijo al buzón de coordinación para tener trazabilidad de cada paso del pre-registro.
    // Se añade también el correo del torneo (torneo.correotorne) cuando existe.
    $ccAdmin = _regmail_admin_cc($torneoMail);
    $res = smtp_send($row['reg_correo'], $nombre, $subject, $html, $textAlt, $ccAdmin);
    if (!$res['ok']) {
        error_log('[registro_comprobante_email] send failed id=' . $registroId . ' err=' . ($res['error'] ?? ''));
    }
}

} // end function_exists guard