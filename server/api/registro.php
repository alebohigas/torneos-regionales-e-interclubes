<?php
/**
 * Registro Endpoint (Pre-Registro)
 * -----------------------------------------------------------------------
 * POST /api/registro.php?torneoid=NN     (multipart/form-data)
 *      Public submission. Accepts whatever fields are enabled in
 *      registro_form_fields for the tournament, plus optional file
 *      `reg_archivo` (uploaded as binary into the LONGBLOB column).
 *
 * GET  /api/registro.php?torneoid=NN&password=registros2025
 *      Admin: lists all submissions for the tournament (newest first).
 *      Excludes the binary blob — use registro_archivo.php to fetch it.
 *
 * POST /api/registro.php?action=verify&password=registros2025  (JSON body)
 *      Admin: { id, verified: 0|1 } toggles a verification flag.
 *      Requires column `reg_verificado TINYINT(1)` (auto-detected).
 *
 * Resilient: any column the host DB doesn't have is silently skipped.
 */
require_once 'config.php';
require_once '_smtp.php';
require_once '_registro_emails.php';
require_once '_staff_auth.php';

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

/**
 * Autorización unificada para las rutas admin de este endpoint.
 * Acepta cualquiera de:
 *   1. Password legacy REGISTROS_PASSWORD ('registros2025') — retro-compat.
 *   2. Password de superadmin (via is_superadmin_password, admite el default
 *      admin2025 y también el hash guardado en BD si el superadmin lo cambió).
 *   3. Token de staff con área 'registros' en Authorization: Bearer / staff_token.
 * Devuelve true si autoriza, false en caso contrario. NO emite json_error;
 * el caller decide qué hacer.
 */
function registro_admin_authorized($conn, $body) {
    $pwd = (string)($body['password'] ?? optional_param('password', ''));
    if ($pwd !== '' && $pwd === REGISTROS_PASSWORD) return true;
    if ($pwd !== '' && is_superadmin_password($conn, $pwd)) return true;
    if (staff_check_area($conn, $body, 'registros')) return true;
    return false;
}

/**
 * Resolve the email column on the `registro` table. Different deployments
 * may use `reg_correo` (canonical) or `correo` (legacy). Returns null when
 * no email column exists at all (in which case duplicate-email checks are
 * skipped).
 */
function registro_email_col($conn) {
    foreach (['reg_correo', 'correo', 'reg_email', 'email'] as $c) {
        if (registro_has($conn, $c)) return $c;
    }
    return null;
}

/**
 * Verifica si una categoría está llena en función de jugadores reales
 * inscritos (tabla `jugadores`, excluyendo BAJA). Devuelve true cuando
 * `categorias.maxjugadores` está definido (>0 y <>99, donde 99 = ilimitado)
 * y el conteo actual alcanza o supera el máximo. False si no aplica cupo.
 */
function categoria_esta_llena($conn, $torneoid, $categoriaId) {
    $torneoid    = (int)$torneoid;
    $categoriaId = (int)$categoriaId;
    if ($torneoid <= 0 || $categoriaId <= 0) return false;
    $r = @$conn->query(
        "SELECT maxjugadores FROM categorias "
        . "WHERE categoria_id = $categoriaId AND torneo_id = $torneoid LIMIT 1"
    );
    if (!$r) return false;
    $row = $r->fetch_assoc(); $r->free();
    if (!$row) return false;
    $max = (int)$row['maxjugadores'];
    if ($max <= 0 || $max === 99) return false; // ilimitado
    $rc = @$conn->query(
        "SELECT COUNT(*) AS n FROM jugadores "
        . "WHERE torneoid = $torneoid AND categoriaid = $categoriaId "
        . "AND (estatus IS NULL OR estatus <> 'BAJA')"
    );
    if (!$rc) return false;
    $cnt = (int)($rc->fetch_assoc()['n'] ?? 0);
    $rc->free();
    return $cnt >= $max;
}

/**
 * Envía el correo automático para registros en LISTA DE ESPERA
 * (status_pago=67). Misma estructura visual que el correo de bienvenida
 * pero SIN imagen de datos bancarios y SIN CTA para subir comprobante;
 * agrega un encabezado destacado explicando la cola y prioridad por
 * fecha de pre-registro. Best-effort: errores se loguean y no rompen
 * el flujo de inserción.
 */
function send_waitlist_email($conn, $registroId) {
    $registroId = (int)$registroId;
    if ($registroId <= 0) return;

    $cols = query_all($conn, "SHOW COLUMNS FROM registro");
    $set  = [];
    foreach ($cols as $c) $set[$c['Field']] = true;
    $has  = fn($c) => isset($set[$c]);

    $pkCol = $has('id') ? 'id' : ($has('reg_id') ? 'reg_id' : null);
    if (!$pkCol) return;
    $torneoCol = null;
    foreach (['reg_id_torneo','torneo_id','id_torneo','idtorneo','torneoid'] as $c) {
        if ($has($c)) { $torneoCol = $c; break; }
    }
    if (!$torneoCol) return;

    $sel = ["$pkCol AS id", "$torneoCol AS torneoid"];
    foreach (['reg_nombre','reg_apellido','reg_correo','reg_telefono','reg_celular',
              'reg_handicap','reg_categoria','reg_club','reg_es_socio','reg_tipo_socio',
              'reg_precio_estimado','reg_precio_moneda'] as $c) {
        if ($has($c)) $sel[] = $c;
    }
    $row = query_one($conn, "SELECT " . implode(',', $sel) . " FROM registro WHERE $pkCol = $registroId LIMIT 1");
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
    $tr = @$conn->query("SELECT nombre FROM torneo WHERE torneo_id = " . (int)$row['torneoid'] . " LIMIT 1");
    if ($tr) { $tt = $tr->fetch_assoc(); $tr->free(); if ($tt) $torneoName = $tt['nombre'] ?? ''; }

    $nombre = trim(($row['reg_nombre'] ?? '') . ' ' . ($row['reg_apellido'] ?? ''));
    if ($nombre === '') $nombre = 'Jugador';

    $b = fn($v) => '<strong>' . htmlspecialchars((string)($v ?? '—'), ENT_QUOTES, 'UTF-8') . '</strong>';
    $entries = [
        ['Folio',     '#' . $folio],
        ['Jugador',   $nombre],
        ['Categoría', $catName ?: '—'],
        ['Club',      $row['reg_club'] ?? '—'],
        ['Handicap',  $row['reg_handicap'] ?? '—'],
    ];
    if ($torneoName) array_unshift($entries, ['Torneo', $torneoName]);
    $rowsHtml = '';
    foreach ($entries as [$label, $val]) {
        $rowsHtml .= '<tr>'
            . '<td style="padding:6px 12px;color:#666;font-size:13px;white-space:nowrap;">' . htmlspecialchars($label) . '</td>'
            . '<td style="padding:6px 12px;font-size:14px;">' . $b($val) . '</td>'
            . '</tr>';
    }

    $subject = 'Pre-registro en LISTA DE ESPERA · Folio #' . $folio
        . ($torneoName ? ' · ' . $torneoName : '');

    // Encabezado destacado pedido por el cliente (negritas + tamaño mayor).
    $waitHeader = '<div style="background:#fff3cd;border:1px solid #f1c40f;'
        . 'border-radius:6px;padding:16px 18px;margin:0 0 20px;'
        . 'font-size:17px;font-weight:bold;line-height:1.45;color:#5b3a00;">'
        . 'Este jugador está en lista de espera, se enviará un correo para procesar '
        . 'su pago si es que se abre un lugar y su registro lo ocupa automaticamente. '
        . 'La prioridad para entrar al espacio disponible es por orden fecha de '
        . 'solicitud de pre-registro de la categoria seleccionada.'
        . '</div>';

    $html = '<!doctype html><html><body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f4f4f5;padding:24px 0;">'
        . '<tr><td align="center">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;background:#ffffff;border-radius:8px;padding:32px;">'
        . '<tr><td>'
        . $waitHeader
        . '<h1 style="font-size:20px;margin:0 0 8px;color:#0a7d3e;">Pre-registro recibido</h1>'
        . '<p style="font-size:14px;line-height:1.5;margin:0 0 16px;">Hola ' . htmlspecialchars($nombre) . ',</p>'
        . '<p style="font-size:14px;line-height:1.6;margin:0 0 16px;">'
        . 'Hemos recibido tu pre-registro. A continuación los detalles de tu solicitud:'
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
        . "ESTE JUGADOR ESTÁ EN LISTA DE ESPERA. Se enviará un correo para procesar su pago "
        . "si se abre un lugar y su registro lo ocupa automáticamente. La prioridad es por orden "
        . "de fecha de solicitud de pre-registro de la categoría seleccionada.\n\n"
        . "Folio: #$folio\n"
        . ($catName ? "Categoría: $catName\n" : '')
        . ($torneoName ? "Torneo: $torneoName\n" : '');

    $res = smtp_send($row['reg_correo'], $nombre, $subject, $html, $textAlt);
    if (!$res['ok']) {
        error_log('[registro_waitlist_email] send failed id=' . $registroId . ' err=' . ($res['error'] ?? ''));
    }
}

/**
 * Check whether a given (nombre, apellido, correo) triple is already
 * registered for a tournament. Bloqueo SOLO cuando los TRES coinciden
 * exactamente (case-insensitive, trim). Reglas:
 *   - mismo nombre + mismo apellido + mismo correo → existe (BLOQUEAR)
 *   - mismo nombre + DIFERENTE apellido + mismo correo → no existe (permitir)
 *   - DIFERENTE nombre + mismo apellido + mismo correo → no existe (permitir)
 * Devuelve false si falta cualquiera de los tres campos (no podemos
 * decidir aún) o si alguna columna requerida falta en la BD.
 */
function registro_triple_exists($conn, $torneoid, $email, $nombre, $apellido) {
    $email    = trim((string)$email);
    $nombre   = trim((string)$nombre);
    $apellido = trim((string)$apellido);
    if ($email === '' || $nombre === '' || $apellido === '') return false;
    $emailCol  = registro_email_col($conn);
    $torneoCol = registro_torneo_col($conn);
    if (!$emailCol || !$torneoCol) return false;
    if (!registro_has($conn, 'reg_nombre') || !registro_has($conn, 'reg_apellido')) return false;
    $sql = "SELECT 1 FROM registro
              WHERE $torneoCol = " . (int)$torneoid . "
                AND LOWER($emailCol)   = LOWER('" . esc($conn, $email) . "')
                AND LOWER(TRIM(reg_nombre))   = LOWER('" . esc($conn, $nombre) . "')
                AND LOWER(TRIM(reg_apellido)) = LOWER('" . esc($conn, $apellido) . "')
              LIMIT 1";
    $r = @$conn->query($sql);
    $found = ($r && $r->fetch_row());
    if ($r) $r->free();
    return (bool)$found;
}

const REGISTROS_PASSWORD = 'registros2025';
/** Max binary upload accepted into reg_archivo (LONGBLOB). 15 MB. */
const MAX_REG_FILE_BYTES = 15 * 1024 * 1024;

/**
 * Ensure the columns required by the 4-section admin flow exist on
 * `registro`. Idempotent — safe to call on every request.
 *  - `enviado`   TINYINT(1) DEFAULT 0  — set to 1 once the player has
 *    either uploaded a payment receipt or selected "cargo a cuenta".
 *  - `reg_token` VARCHAR(64) NULL UNIQUE — opaque per-row token used
 *    in the public "adjuntar comprobante" link sent by email.
 */
function ensure_registro_flow_cols($conn) {
    $needRefresh = false;
    if (!registro_has($conn, 'enviado')) {
        @$conn->query("ALTER TABLE registro ADD COLUMN enviado TINYINT(1) NOT NULL DEFAULT 0");
        $needRefresh = true;
    }
    if (!registro_has($conn, 'reg_token')) {
        @$conn->query("ALTER TABLE registro ADD COLUMN reg_token VARCHAR(64) NULL");
        @$conn->query("ALTER TABLE registro ADD UNIQUE INDEX uk_registro_token (reg_token)");
        $needRefresh = true;
    }
    if ($needRefresh) registro_columns($conn, true);
}

/** Generate a 256-bit hex token for a registro row's public upload link. */
function gen_registro_token() {
    return bin2hex(random_bytes(32));
}

/**
 * Map UI/legacy field_name -> actual DB column on `registro`.
 * Older code/forms used reg_sexo/reg_telefono/reg_notas/reg_ghin, but the
 * production schema (registro_campos) uses reg_genero/reg_celular/reg_mensaje/
 * numghinspei. We accept both on the wire and write to the canonical column
 * if it exists; otherwise we fall back to the legacy column.
 */
const REGISTRO_COLUMN_ALIASES = [
    'reg_sexo'     => 'reg_genero',
    'reg_telefono' => 'reg_celular',
    'reg_notas'    => 'reg_mensaje',
    'reg_ghin'     => 'numghinspei',
    // La columna real para la edad al momento del registro vive en
    // `akron_edad`. Aceptamos ambos nombres en el POST por compat.
    'reg_edad'     => 'akron_edad',
];

/** Resolve a posted field_name to the column we'll write to (canonical first). */
function resolve_reg_column($conn, $name) {
    if (registro_has($conn, $name)) return $name;
    if (isset(REGISTRO_COLUMN_ALIASES[$name])) {
        $alt = REGISTRO_COLUMN_ALIASES[$name];
        if (registro_has($conn, $alt)) return $alt;
    }
    // Reverse alias: form posted canonical but only legacy exists
    foreach (REGISTRO_COLUMN_ALIASES as $legacy => $canonical) {
        if ($canonical === $name && registro_has($conn, $legacy)) return $legacy;
    }
    return null;
}

/**
 * Compute precise age (in completed years) at a given reference date.
 * Returns null if either date is invalid. Uses day/month precision so a
 * birthday one day after the cutoff stays in the older-age bucket.
 */
function compute_age_at($birth_yyyymmdd, $ref_yyyymmdd) {
    if (!$birth_yyyymmdd || !$ref_yyyymmdd) return null;
    try {
        $b = new DateTime($birth_yyyymmdd);
        $r = new DateTime($ref_yyyymmdd);
    } catch (Exception $e) { return null; }
    if ($b > $r) return null;
    return (int) $b->diff($r)->y;
}

/** Lookup torneo start date (fecha_ini) — used as the cutoff for akron_edad. */
function torneo_fecha_ini($conn, $torneoid) {
    $r = $conn->query("SELECT fecha_ini FROM torneo WHERE torneo_id = " . (int)$torneoid . " LIMIT 1");
    if (!$r) return null;
    $row = $r->fetch_assoc();
    $r->free();
    return $row['fecha_ini'] ?? null;
}

/** Cache: which columns exist in the `jugadores` table. */
function jugadores_cols_reg($conn) {
    static $c = null;
    if ($c !== null) return $c;
    $c = [];
    $r = $conn->query("SHOW COLUMNS FROM jugadores");
    if ($r) { while ($row = $r->fetch_assoc()) $c[$row['Field']] = true; $r->free(); }
    return $c;
}
function jug_has_reg($conn, $col) { $c = jugadores_cols_reg($conn); return isset($c[$col]); }

/**
 * If the pre-registro carries a SPEI or GHIN, sync the `jugadores` row:
 *  - Match by reg_spei or numghinspei (whichever was provided & exists as col).
 *  - On match: UPDATE only the columns that are currently NULL/empty so we
 *    fill gaps without overwriting curated data.
 *  - On no match: do nothing (per product decision — admin merges manually).
 * Silent on errors (non-blocking for the registration save).
 */
function sync_jugadores_from_registro($conn, $posted, $birth_yyyymmdd) {
    $spei = trim((string)($posted['reg_spei'] ?? ''));
    $ghin = trim((string)($posted['numghinspei'] ?? $posted['reg_ghin'] ?? ''));
    if ($spei === '' && $ghin === '') return;

    $where = [];
    if ($spei !== '' && jug_has_reg($conn, 'reg_spei')) {
        $where[] = "reg_spei = '" . esc($conn, $spei) . "'";
    }
    if ($ghin !== '' && jug_has_reg($conn, 'numghinspei')) {
        $where[] = "numghinspei = '" . esc($conn, $ghin) . "'";
    }
    if (!$where) return;

    $sel = "SELECT * FROM jugadores WHERE " . implode(' OR ', $where) . " LIMIT 1";
    $r = @$conn->query($sel);
    if (!$r || !($row = $r->fetch_assoc())) {
        // Player not yet in jugadores; nothing to sync now.
        return;
    }
    $r->free();

    $jugId = (int)($row['id'] ?? 0);
    if ($jugId <= 0) return;

    /**
     * Map of jugadores column -> incoming registro field. Only columns that
     * exist on jugadores AND are currently NULL/empty get filled.
     */
    $fillMap = [
        'nombre'      => $posted['reg_nombre']    ?? '',
        'apellido'    => $posted['reg_apellido']  ?? '',
        'correo'      => $posted['reg_correo']    ?? '',
        'telefono'    => $posted['reg_telefono']  ?? $posted['reg_celular'] ?? '',
        'celular'     => $posted['reg_celular']   ?? $posted['reg_telefono'] ?? '',
        'sexo'        => $posted['reg_sexo']      ?? $posted['reg_genero']  ?? '',
        'genero'      => $posted['reg_genero']    ?? $posted['reg_sexo']    ?? '',
        'club'        => $posted['reg_club']      ?? '',
        'fechanac'    => $birth_yyyymmdd          ?? '',
        'reg_spei'    => $posted['reg_spei']      ?? '',
        'numghinspei' => $posted['numghinspei']   ?? $posted['reg_ghin'] ?? '',
        'handicap'    => $posted['reg_handicap']  ?? '',
    ];
    $sets = [];
    foreach ($fillMap as $col => $val) {
        $val = trim((string)$val);
        if ($val === '') continue;
        if (!jug_has_reg($conn, $col)) continue;
        // Only fill if current value is NULL or empty string
        $current = $row[$col] ?? null;
        if ($current !== null && trim((string)$current) !== '') continue;
        $sets[] = "$col = '" . esc($conn, $val) . "'";
    }
    if ($sets) {
        @$conn->query("UPDATE jugadores SET " . implode(',', $sets) . " WHERE id = $jugId LIMIT 1");
    }
}

/** Cache: which columns exist in the `registro` table. */
function registro_columns($conn, $refresh = false) {
    static $cols = null;
    if ($refresh) $cols = null;
    if ($cols !== null) return $cols;
    $cols = [];
    $r = $conn->query("SHOW COLUMNS FROM registro");
    if ($r) {
        while ($row = $r->fetch_assoc()) $cols[$row['Field']] = true;
        $r->free();
    }
    return $cols;
}

function registro_has($conn, $col) {
    $cols = registro_columns($conn);
    return isset($cols[$col]);
}


/**
 * Identify the torneo FK column on `registro`. Different deployments use
 * different names; we probe in priority order.
 */
function registro_torneo_col($conn) {
    // Prioridad: reg_id_torneo (canónico nuevo). Las demás se conservan
    // como fallback para esquemas legacy, pero ya no creamos torneoid.
    foreach (['reg_id_torneo', 'torneo_id', 'id_torneo', 'idtorneo', 'reg_torneoid', 'reg_torneo_id', 'torneoid'] as $c) {
        if (registro_has($conn, $c)) return $c;
    }

    // Si no existe ninguna columna de torneo, crea la canónica nueva.
    @$conn->query("ALTER TABLE registro ADD COLUMN reg_id_torneo INT(11) NULL");
    registro_columns($conn, true);
    if (registro_has($conn, 'reg_id_torneo')) return 'reg_id_torneo';

    return null;
}

/** Identify primary key column (id by convention; fallback to reg_id). */
function registro_pk_col($conn) {
    foreach (['id', 'reg_id', 'registro_id'] as $c) {
        if (registro_has($conn, $c)) return $c;
    }
    return null;
}

// ============= POST submission (public) =============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (optional_param('action') !== 'verify')) {
    $torneoid = (int) require_torneoid($conn);
    $torneoCol = registro_torneo_col($conn);
    $pkCol = registro_pk_col($conn);

    if (!$pkCol)     json_error('registro table has no recognizable primary key column.', 500);
    if (!$torneoCol) json_error('registro table has no recognizable torneo id column.',  500);

    // Make sure the flow columns exist before we try to write to them.
    ensure_registro_flow_cols($conn);

    /**
     * Duplicate guard: bloquear sólo cuando coinciden nombre+apellido+correo.
     * Esto permite a un mismo correo registrar a múltiples jugadores con
     * distinto nombre o distinto apellido (caso jugadores familiares que
     * comparten cuenta de correo).
     */
    $postedEmail    = trim((string)($_POST['reg_correo']   ?? ''));
    $postedNombre   = trim((string)($_POST['reg_nombre']   ?? ''));
    $postedApellido = trim((string)($_POST['reg_apellido'] ?? ''));
    if ($postedEmail !== '' && $postedNombre !== '' && $postedApellido !== ''
        && registro_triple_exists($conn, $torneoid, $postedEmail, $postedNombre, $postedApellido)) {
        json_error(
            'Ya existe un pre-registro con el mismo nombre, apellido y correo en este torneo. '
          . 'Si necesitas registrar a otra persona, cambia el nombre o el apellido.',
            409
        );
    }


    /** Whitelist of safe field_names accepted from the form. */
    $allowedTextFields = [
        'reg_nombre', 'reg_apellido', 'reg_correo', 'reg_telefono',
        'reg_handicap', 'reg_categoria', 'reg_sexo', 'reg_fechanac', 'reg_edad', 'akron_edad',
        'reg_es_socio', 'reg_tipo_socio', 'reg_club', 'reg_ghin',
        'reg_pais', 'reg_estado', 'reg_ciudad', 'reg_notas',
        // Canonical names from registro_campos
        'reg_genero', 'reg_celular', 'reg_mensaje', 'numghinspei',
        'reg_spei', 'reg_direccion', 'reg_cp', 'reg_id_club', 'reg_cargo',
        // Cargo a cuenta de socio (checkbox '1'/'' + clave de socio)
        'reg_cargo_socio', 'reg_numsocio',
        // Talla de gorra (única reg_talla_*; las demás van en akron_*)
        'reg_talla_gorra',
        // Akron-specific
        'akron_talla', 'akron_talla_guante', 'akron_calzado',
        'akron_codigo', 'akron_monto_pago',
        // Snapshot del precio mostrado al jugador al enviar el form.
        // Lo escribe el cliente con base en /api/registro_precios.php?action=match.
        'reg_precio_estimado', 'reg_precio_moneda', 'reg_precio_regla_id',
    ];

    $cols = [$torneoCol];
    $vals = [$torneoid];
    $writtenCols = []; // dedupe when alias maps two posted names to same column
    $writtenCols[$torneoCol] = true; // ya se añadió arriba; evitar duplicado

    foreach ($allowedTextFields as $f) {
        if (!isset($_POST[$f])) continue;
        $v = trim((string)$_POST[$f]);
        if ($v === '') continue;
        $target = resolve_reg_column($conn, $f);
        if (!$target) continue; // neither canonical nor alias exists on this server
        if (isset($writtenCols[$target])) continue;
        $writtenCols[$target] = true;
        $cols[] = $target;
        $vals[] = "'" . esc($conn, $v) . "'";
    }

    /**
     * Auto-calculate akron_edad against the tournament start date so a
     * player whose birthday falls one day after the cutoff stays in the
     * older bucket. We compute on the server with day-month precision.
     */
    $birth = trim((string)($_POST['reg_fechanac'] ?? ''));
    if ($birth !== '' && registro_has($conn, 'akron_edad') && !isset($writtenCols['akron_edad'])) {
        $cutoff = torneo_fecha_ini($conn, $torneoid);
        $age = $cutoff ? compute_age_at($birth, $cutoff) : null;
        if ($age !== null) {
            $writtenCols['akron_edad'] = true;
            $cols[] = 'akron_edad';
            $vals[] = (int)$age;
        }
    }

    /** Optional file upload into reg_archivo (LONGBLOB). */
    $haveFile = isset($_FILES['reg_archivo']) && is_uploaded_file($_FILES['reg_archivo']['tmp_name']);
    if ($haveFile) {
        if ($_FILES['reg_archivo']['size'] > MAX_REG_FILE_BYTES) {
            json_error('Archivo demasiado grande (máx 15 MB).', 400);
        }
        if (registro_has($conn, 'reg_archivo')) {
            $bin = file_get_contents($_FILES['reg_archivo']['tmp_name']);
            $cols[] = 'reg_archivo';
            $vals[] = "'" . $conn->real_escape_string($bin) . "'";
        }
        if (registro_has($conn, 'reg_archivo_nombre')) {
            $cols[] = 'reg_archivo_nombre';
            $vals[] = "'" . esc($conn, basename($_FILES['reg_archivo']['name'])) . "'";
        }
        if (registro_has($conn, 'reg_archivo_mime')) {
            $mime = $_FILES['reg_archivo']['type'] ?: 'application/octet-stream';
            $cols[] = 'reg_archivo_mime';
            $vals[] = "'" . esc($conn, $mime) . "'";
        }
    }

    /**
     * Marca de tiempo del registro. Preferimos el instante capturado por
     * el cliente (`reg_client_utc`, ISO 8601 UTC) sobre NOW() del servidor
     * para que la hora guardada sea independiente de la zona horaria del
     * host de MySQL/PHP y refleje exactamente cuándo el jugador envió el
     * formulario. Almacenamos siempre en UTC; el frontend convierte a la
     * zona local del usuario al mostrarlo.
     */
    $clientUtc = trim((string)($_POST['reg_client_utc'] ?? ''));
    $utcDateTime = null; // formato 'YYYY-MM-DD HH:MM:SS' UTC
    if ($clientUtc !== '') {
        $ts = strtotime($clientUtc);
        if ($ts !== false) $utcDateTime = gmdate('Y-m-d H:i:s', $ts);
    }
    if ($utcDateTime === null) {
        $utcDateTime = gmdate('Y-m-d H:i:s');
    }
    $utcLiteral = "'" . esc($conn, $utcDateTime) . "'";
    // reg_fecha / created_at / fecha_alta (heredados): usar el UTC del cliente.
    foreach (['reg_fecha', 'created_at', 'fecha_alta'] as $tc) {
        if (registro_has($conn, $tc) && !isset($writtenCols[$tc])) {
            $writtenCols[$tc] = true;
            $cols[] = $tc;
            $vals[] = $utcLiteral;
            break;
        }
    }
    // fecharegistro (columna canónica nueva): guardar SIEMPRE el UTC.
    if (registro_has($conn, 'fecharegistro') && !isset($writtenCols['fecharegistro'])) {
        $writtenCols['fecharegistro'] = true;
        $cols[] = 'fecharegistro';
        $vals[] = $utcLiteral;
    }

    /**
     * Auto-rellenar reg_id_torneo y reg_id_club si esas columnas existen
     * en la tabla `registro`. El form NO los manda directamente — los
     * derivamos en el servidor a partir del torneoid de la URL y del
     * nombre de club tecleado por el jugador (`reg_club`).
     */
    if (registro_has($conn, 'reg_id_torneo') && !isset($writtenCols['reg_id_torneo'])) {
        $writtenCols['reg_id_torneo'] = true;
        $cols[] = 'reg_id_torneo';
        $vals[] = (int)$torneoid;
    }
    if (registro_has($conn, 'reg_id_club') && !isset($writtenCols['reg_id_club'])) {
        $clubName = trim((string)($_POST['reg_club'] ?? ''));
        if ($clubName !== '') {
            $r = @$conn->query("SELECT id FROM clubs WHERE nombre = '" . esc($conn, $clubName) . "' LIMIT 1");
            if ($r && ($row = $r->fetch_assoc())) {
                $writtenCols['reg_id_club'] = true;
                $cols[] = 'reg_id_club';
                $vals[] = (int)$row['id'];
                $r->free();
            } elseif ($r) { $r->free(); }
        }
    }

    /**
     * Derivar `reg_cargo` ("SI"/"NO"/"") a partir de la respuesta del jugador:
     *   - Es socio + check marcado → "SI"
     *   - Es socio + check no marcado → "NO"
     *   - No socio → cadena vacía (se queda en blanco)
     * Se sobreescribe cualquier valor posteado para evitar manipulación
     * desde el cliente.
     */
    if (registro_has($conn, 'reg_cargo')) {
        $esSocioPost = strtoupper(trim((string)($_POST['reg_es_socio'] ?? '')));
        $cargoPost   = trim((string)($_POST['reg_cargo_socio'] ?? ''));
        $cargoVal    = '';
        if ($esSocioPost === 'SI') {
            $cargoVal = ($cargoPost === '1') ? 'SI' : 'NO';
        }
        if (isset($writtenCols['reg_cargo'])) {
            // Reemplazar el valor previamente añadido (posteado por el cliente)
            $idx = array_search('reg_cargo', $cols, true);
            if ($idx !== false) {
                $vals[$idx] = "'" . esc($conn, $cargoVal) . "'";
            }
        } else {
            $writtenCols['reg_cargo'] = true;
            $cols[] = 'reg_cargo';
            $vals[] = "'" . esc($conn, $cargoVal) . "'";
        }
    }

    /** Default verification flag = 0 si la columna `verificado` existe. */
    if (registro_has($conn, 'verificado') && !isset($writtenCols['verificado'])) {
        $cols[] = 'verificado';
        $vals[] = '0';
    }
    /**
     * Decisión de cupo: si la categoría seleccionada ya está llena
     * (jugadores reales >= categorias.maxjugadores), el registro entra
     * en LISTA DE ESPERA con status_pago=5. El flag `_waitlist=1` del
     * cliente sólo es una pista — la decisión real es del servidor.
     */
    $postedCategoriaId = (int)($_POST['reg_categoria'] ?? 0);
    $isWaitlist = ($postedCategoriaId > 0)
        && categoria_esta_llena($conn, $torneoid, $postedCategoriaId);
    if (registro_has($conn, 'status_pago') && !isset($writtenCols['status_pago'])) {
        $cols[] = 'status_pago';
        $vals[] = $isWaitlist ? '5' : '0';
    }

    /**
     * Always assign a per-row token so the player can be sent a public
     * "adjuntar comprobante" link later via email (registro_publico.php).
     */
    if (registro_has($conn, 'reg_token') && !isset($writtenCols['reg_token'])) {
        $writtenCols['reg_token'] = true;
        $cols[] = 'reg_token';
        $vals[] = "'" . gen_registro_token() . "'";
    }

    /**
     * `enviado` semántico:
     *  - 1 si el jugador subió comprobante con el formulario inicial.
     *  - 1 si el jugador eligió cargo a cuenta (no requiere comprobante).
     *  - 0 en cualquier otro caso (entra a "Sin validar" en el admin).
     */
    if (registro_has($conn, 'enviado') && !isset($writtenCols['enviado'])) {
        $cargoPost = trim((string)($_POST['reg_cargo_socio'] ?? ''));
        $esSocio   = strtoupper(trim((string)($_POST['reg_es_socio'] ?? '')));
        $autoSent  = $haveFile || ($esSocio === 'SI' && $cargoPost === '1');
        $writtenCols['enviado'] = true;
        $cols[] = 'enviado';
        $vals[] = $autoSent ? '1' : '0';
    }

    /**
     * Relleno defensivo: en algunos servidores la tabla `registro` tiene
     * columnas NOT NULL sin DEFAULT (p.ej. reg_genero). Si el formulario no
     * las manda, MySQL falla con "doesn't have a default value". Aquí
     * agregamos un valor neutro ('' o 0) para toda columna obligatoria que
     * no se esté escribiendo y que no sea autoincremental ni timestamp.
     */
    $meta = @$conn->query("SHOW COLUMNS FROM registro");
    if ($meta) {
        while ($m = $meta->fetch_assoc()) {
            $name = $m['Field'];
            if (isset($writtenCols[$name]) || in_array($name, $cols, true)) continue;
            if (($m['Null'] ?? 'YES') !== 'NO') continue;
            if ($m['Default'] !== null) continue;
            if (stripos($m['Extra'] ?? '', 'auto_increment') !== false) continue;
            $type = strtolower($m['Type'] ?? '');
            if (strpos($type, 'timestamp') !== false || strpos($type, 'datetime') !== false
                || strpos($type, 'date') !== false || strpos($type, 'time') !== false
                || strpos($type, 'blob') !== false || strpos($type, 'text') === 0) {
                // fechas: usar valor neutro seguro; blobs/text aceptan ''
                if (strpos($type, 'timestamp') !== false || strpos($type, 'datetime') !== false) {
                    $writtenCols[$name] = true; $cols[] = $name; $vals[] = 'NOW()';
                    continue;
                }
                if (strpos($type, 'date') !== false) {
                    $writtenCols[$name] = true; $cols[] = $name; $vals[] = "'1000-01-01'";
                    continue;
                }
            }
            $isNumeric = preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|float|double|year|bit)/', $type) === 1;
            $writtenCols[$name] = true;
            $cols[] = $name;
            $vals[] = $isNumeric ? '0' : "''";
        }
        $meta->free();
    }

    $sql = "INSERT INTO registro (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
    if (!$conn->query($sql)) {
        json_error('Failed to save registration: ' . $conn->error, 500);
    }

    /**
     * Capturar el ID inmediatamente después del INSERT. Cualquier query
     * posterior (por ejemplo el sync hacia jugadores) puede resetear
     * mysqli->insert_id a 0 y evitar que se mande el correo automático.
     */
    $newId = $conn->insert_id;

    // Best-effort sync to jugadores when SPEI/GHIN provided.
    sync_jugadores_from_registro($conn, $_POST, $birth);

    // Lista de espera: enviar correo automático con detalles del registro
    // (sin imagen bancaria, sin CTA de pago, con encabezado destacado).
    if ($isWaitlist) {
        send_waitlist_email($conn, $newId);
    } else {
        // Pre-registro normal: confirmar al jugador que su registro entró.
        send_registration_ack_email($conn, $newId);
    }

    json_response([
        'saved'    => true,
        'id'       => $newId,
        'waitlist' => $isWaitlist,
    ]);
}

// ============= POST verify (admin) =============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && optional_param('action') === 'verify') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) json_error('Invalid JSON', 400);
    if (!registro_admin_authorized($conn, $body)) json_error('Unauthorized', 401);

    $pkCol = registro_pk_col($conn);
    if (!$pkCol) json_error('registro PK not found', 500);
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) json_error('Missing id', 400);

    /**
     * Auto-crear únicamente las columnas que aún manejamos nosotros.
     * `verificado` y `status_pago` ya existían en el esquema base — no
     * las creamos ni las tocamos aquí. `reg_monto_confirmado` sí es
     * propia de este admin y se asegura su existencia.
     */
    $adminColsSpec = [
        'reg_monto_confirmado' => 'DECIMAL(10,2) NULL',
    ];
    $needRefresh = false;
    foreach ($adminColsSpec as $col => $spec) {
        if (!registro_has($conn, $col)) {
            @$conn->query("ALTER TABLE registro ADD COLUMN $col $spec");
            $needRefresh = true;
        }
    }
    if ($needRefresh) registro_columns($conn, true);

    /**
     * Admin update — acepta cualquier combinación de:
     *   - verified            → `verificado`     (TINYINT 0/1, columna nativa)
     *   - pago_verificado     → `status_pago`    (TINYINT 0/1, columna nativa)
     *   - monto_confirmado    → reg_monto_confirmado (DECIMAL, NULL si vacío)
     */
    $sets = [];
    $wantVerified = array_key_exists('verified', $body);
    $newVerified  = $wantVerified ? (!empty($body['verified']) ? 1 : 0) : null;

    if ($wantVerified && registro_has($conn, 'verificado')) {
        $sets[] = "verificado = $newVerified";
    }
    if (array_key_exists('pago_verificado', $body) && registro_has($conn, 'status_pago')) {
        $pv = !empty($body['pago_verificado']) ? 1 : 0;
        $sets[] = "status_pago = $pv";
    }
    /**
     * Nuevo: dropdown de status_pago con valores del catálogo `estatuspago`
     * (primeras 6 opciones). Acepta cualquier entero >= 0; el frontend se
     * encarga de restringir a las opciones permitidas.
     */
    if (array_key_exists('status_pago', $body) && registro_has($conn, 'status_pago')) {
        $sp = (int)$body['status_pago'];
        $sets[] = "status_pago = $sp";
    }
    if (array_key_exists('monto_confirmado', $body) && registro_has($conn, 'reg_monto_confirmado')) {
        $raw = trim((string)$body['monto_confirmado']);
        if ($raw === '' || !is_numeric($raw)) {
            $sets[] = "reg_monto_confirmado = NULL";
        } else {
            $sets[] = "reg_monto_confirmado = " . (float)$raw;
        }
    }
    if (!$sets) json_error('Nothing to update (missing columns or fields).', 400);

    if (!$conn->query("UPDATE registro SET " . implode(',', $sets) . " WHERE $pkCol = $id LIMIT 1")) {
        json_error('Update failed: ' . $conn->error);
    }

    // Verificación: ya NO dispara correo aquí — el correo de "registro
    // validado, sube comprobante" se manda explícitamente desde la
    // sección 1 del admin vía /api/registro_email.php.
    json_response(['saved' => true]);
}

// ============= POST unregister/baja (admin) =============
/**
 * Acciones administrativas para la sección 4 ("Registros completados").
 *
 *   action=unregister  → toggle status_pago entre 1 (registrado) y 99
 *                        (des-registrado / cancelado). Mismo botón
 *                        cambia de "Des-registrar" a "Registrar".
 *   action=baja        → marca al jugador correspondiente en la tabla
 *                        `jugadores` con estatus='BAJA'. Match por
 *                        correo (case-insensitive). No toca el registro.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array(optional_param('action'), ['unregister', 'baja'], true)) {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!registro_admin_authorized($conn, $body)) json_error('Unauthorized', 401);

    $pkCol = registro_pk_col($conn);
    if (!$pkCol) json_error('registro PK not found', 500);
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) json_error('Missing id', 400);

    $action = optional_param('action');

    if ($action === 'unregister') {
        if (!registro_has($conn, 'status_pago')) json_error('status_pago column missing', 500);
        // Toggle between 1 (registrado) and 99 (des-registrado).
        $r = $conn->query("SELECT status_pago FROM registro WHERE $pkCol = $id LIMIT 1");
        if (!$r) json_error('Lookup failed', 500);
        $row = $r->fetch_assoc(); $r->free();
        if (!$row) json_error('Registro no encontrado', 404);
        $cur = (int)$row['status_pago'];
        $next = ($cur === 99) ? 1 : 99;
        if (!$conn->query("UPDATE registro SET status_pago = $next WHERE $pkCol = $id LIMIT 1")) {
            json_error('Update failed: ' . $conn->error);
        }
        json_response(['saved' => true, 'status_pago' => $next]);
    }

    if ($action === 'baja') {
        // Resolve correo on the registro row, then match jugadores by it.
        $emailCol = registro_email_col($conn);
        if (!$emailCol) json_error('email column missing', 500);
        $r = $conn->query("SELECT $emailCol AS correo FROM registro WHERE $pkCol = $id LIMIT 1");
        if (!$r) json_error('Lookup failed', 500);
        $row = $r->fetch_assoc(); $r->free();
        $correo = trim((string)($row['correo'] ?? ''));
        if ($correo === '') json_error('Registro sin correo', 400);

        $updated = 0;
        if (jug_has_reg($conn, 'estatus') && jug_has_reg($conn, 'correo')) {
            $ok = @$conn->query(
                "UPDATE jugadores SET estatus = 'BAJA' WHERE LOWER(correo) = LOWER('"
                . esc($conn, $correo) . "')"
            );
            if ($ok) $updated = $conn->affected_rows;
        }
        json_response(['saved' => true, 'jugadores_updated' => $updated]);
    }
}

/**
 * Envía un correo al jugador notificando que su pre-registro fue verificado.
 * Lee nombre/correo/categoria desde la BD y arma un mensaje plano + HTML.
 * Devuelve true si mail() acepta el envío. Errores se loguean a error_log.
 */
function send_verification_email($conn, $regId) {
    $pkCol = registro_pk_col($conn);
    if (!$pkCol) return false;

    // Resolver columnas opcionales (algunas instalaciones usan reg_correo).
    $cols = ['reg_nombre','reg_apellido','reg_correo','reg_categoria',
             'reg_precio_estimado','reg_precio_moneda','reg_monto_confirmado'];
    $select = [];
    foreach ($cols as $c) if (registro_has($conn, $c)) $select[] = $c;
    if (!in_array('reg_correo', $select, true)) return false;

    $torneoCol = registro_torneo_col($conn);
    $select[] = "$torneoCol AS torneoid";

    $sql = "SELECT " . implode(',', $select) . " FROM registro WHERE $pkCol = " . (int)$regId . " LIMIT 1";
    $r = $conn->query($sql);
    if (!$r) return false;
    $row = $r->fetch_assoc();
    $r->free();
    if (!$row || empty($row['reg_correo'])) return false;

    // Resolver nombre de categoría y de torneo.
    $catName = '';
    if (!empty($row['reg_categoria'])) {
        $cr = $conn->query("SELECT categoria FROM categorias WHERE categoria_id = " . (int)$row['reg_categoria'] . " LIMIT 1");
        if ($cr) { $cc = $cr->fetch_assoc(); $cr->free(); if ($cc) $catName = $cc['categoria']; }
    }
    $torneoName = '';
    if (!empty($row['torneoid'])) {
        $tr = $conn->query("SELECT nombre FROM torneo WHERE torneo_id = " . (int)$row['torneoid'] . " LIMIT 1");
        if ($tr) { $tt = $tr->fetch_assoc(); $tr->free(); if ($tt) $torneoName = $tt['nombre']; }
    }

    $nombre = trim(($row['reg_nombre'] ?? '') . ' ' . ($row['reg_apellido'] ?? ''));
    if ($nombre === '') $nombre = 'Jugador';
    $monto = $row['reg_monto_confirmado'] ?? $row['reg_precio_estimado'] ?? '';
    $moneda = $row['reg_precio_moneda'] ?? 'MXN';

    $subject = 'Pre-registro verificado' . ($torneoName ? " · $torneoName" : '');
    $lines = [
        "Hola $nombre,",
        '',
        'Tu pre-registro ha sido verificado por el comité del torneo' . ($torneoName ? " ($torneoName)" : '') . '.',
    ];
    if ($catName) $lines[] = "Categoría: $catName";
    if ($monto !== '' && $monto !== null) $lines[] = "Monto: $monto $moneda";
    $lines[] = '';
    $lines[] = '¡Te esperamos en el campo!';

    $body = implode("\r\n", $lines);
    $headers  = "From: no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'torneos.mx') . "\r\n";
    $headers .= "Reply-To: no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'torneos.mx') . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $ok = @mail($row['reg_correo'], $subject, $body, $headers);
    if (!$ok) error_log("[registro] mail() failed for reg id=$regId to {$row['reg_correo']}");
    return $ok;
}

// ============= GET listing (admin) =============
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    /**
     * Public action — no password required.
     * GET /api/registro.php?action=check_email&torneoid=NN&email=foo@bar.com
     * Returns { exists: bool } to let the form warn before submit.
     */
    if (optional_param('action') === 'check_email') {
        $torneoid = (int) require_torneoid($conn);
        $email    = trim((string) optional_param('email', ''));
        $nombre   = trim((string) optional_param('nombre', ''));
        $apellido = trim((string) optional_param('apellido', ''));
        json_response([
            // Solo reporta `exists: true` cuando los tres campos coinciden
            // exactamente. Si falta cualquiera, devuelve false (el cliente
            // hará el chequeo definitivo al enviar el formulario).
            'exists' => registro_triple_exists($conn, $torneoid, $email, $nombre, $apellido),
        ]);
    }

    // Auth check first — admin password gates everything.
    if (!registro_admin_authorized($conn, [])) {
        json_error('Unauthorized', 401);
    }

    // Make sure the flow columns are present before SELECTing them.
    ensure_registro_flow_cols($conn);

    /**
     * torneoid es opcional en el GET admin:
     *   - Si se manda (>0): filtra por ese torneo (vista por dominio).
     *   - Si se omite o all=1: devuelve TODOS los registros del servidor
     *     para que el equipo administrativo pueda ver pruebas enviadas
     *     desde cualquier dominio/subdominio.
     */
    $torneoid = (int) optional_param('torneoid');
    $showAll  = ($torneoid <= 0) || (optional_param('all') === '1');

    $torneoCol = registro_torneo_col($conn);
    $pkCol     = registro_pk_col($conn);
    if (!$pkCol || !$torneoCol) json_error('registro table not configured properly.', 500);

    // Backfill: any existing row without a token gets one now. Cheap,
    // bounded by current row count, runs at most once per row.
    if (registro_has($conn, 'reg_token')) {
        $rs = @$conn->query("SELECT $pkCol AS id FROM registro WHERE reg_token IS NULL OR reg_token = ''");
        if ($rs) {
            while ($r = $rs->fetch_assoc()) {
                $tok = gen_registro_token();
                @$conn->query("UPDATE registro SET reg_token = '$tok' WHERE $pkCol = " . (int)$r['id'] . " LIMIT 1");
            }
            $rs->free();
        }
    }

    /** Fields to surface in the listing (skip blob). */
    $fields = ['r.' . $pkCol . ' AS id'];
    // Exponer el torneoid (alias 'torneoid') para que el admin lo muestre/filtre.
    $fields[] = "r.$torneoCol AS torneoid";
    $optional = [
        'reg_nombre','reg_apellido','reg_correo','reg_telefono','reg_handicap',
        'reg_categoria','reg_sexo','reg_fechanac','reg_es_socio','reg_tipo_socio',
        'reg_club','reg_ghin','reg_pais','reg_estado','reg_ciudad','reg_notas',
        'reg_fecha','created_at','fecha_alta','reg_archivo_nombre',
        // Cargo a cuenta de socio
        'reg_cargo_socio','reg_numsocio',
        // Monto confirmado por tesorería
        'reg_monto_confirmado',
        // Contador de correos enviados al jugador (sección 1)
        'reg_email_count','reg_email_last',
        // Contador de correos de BIENVENIDA (sección 4)
        'reg_welcome_count','reg_welcome_last',
        // Tallas (optional columns)
        'reg_talla_gorra',
        // Canonical / akron columns
        'reg_genero','reg_celular','reg_mensaje','numghinspei','reg_spei',
        'reg_direccion','reg_cp','reg_id_club','reg_cargo',
        'akron_edad','akron_talla','akron_talla_guante','akron_calzado',
        'akron_codigo','akron_monto_pago',
        'reg_precio_estimado','reg_precio_moneda','reg_precio_regla_id',
        // Timestamp original del registro (GMT en BD). El frontend lo convierte a -6.
        'fecharegistro',
    ];
    foreach ($optional as $c) if (registro_has($conn, $c)) $fields[] = "r.$c";
    // 4-section flow exposure: enviado (sección 1/2) y reg_token (link
    // de adjuntar comprobante que el admin manda por correo).
    if (registro_has($conn, 'enviado'))   $fields[] = 'r.enviado';
    if (registro_has($conn, 'reg_token')) $fields[] = 'r.reg_token';
    /**
     * Verificación administrativa: las columnas canónicas en la BD son
     * `verificado` y `status_pago`. Las exponemos con alias hacia los
     * nombres antiguos para no romper el frontend (reg_verificado y
     * reg_pago_verificado siguen siendo las claves que usa la UI).
     */
    if (registro_has($conn, 'verificado'))  $fields[] = "r.verificado AS reg_verificado";
    if (registro_has($conn, 'status_pago')) $fields[] = "r.status_pago AS reg_pago_verificado";
    /** Indicate whether a binary attachment exists without sending the bytes. */
    if (registro_has($conn, 'reg_archivo')) {
        $fields[] = "(r.reg_archivo IS NOT NULL AND OCTET_LENGTH(r.reg_archivo) > 0) AS has_archivo";
    }
    // JOIN a categorias para exponer el nombre legible de la categoría.
    $fields[] = "c.categoria AS categoria_name";
    /**
     * Cupo de la categoría asociada al registro. `cat_max` = maxjugadores
     * (NULL/0/99 = ilimitado en la UI); `cat_count` = jugadores activos
     * actualmente en esa categoría/torneo (excluye BAJA). Lo usa la
     * sección "Lista de espera" del admin para mostrar n/max y habilitar
     * el botón "Agregar a categoría" cuando se libera un lugar.
     */
    $fields[] = "c.maxjugadores AS cat_max";
    $fields[] = "(SELECT COUNT(*) FROM jugadores j
                   WHERE j.torneoid    = r.$torneoCol
                     AND j.categoriaid = r.reg_categoria
                     AND (j.estatus IS NULL OR j.estatus <> 'BAJA')) AS cat_count";

    $orderCol = "r.$pkCol";
    if (registro_has($conn, 'reg_fecha'))   $orderCol = 'r.reg_fecha';
    elseif (registro_has($conn, 'created_at')) $orderCol = 'r.created_at';

    $where = $showAll ? '1=1' : "r.$torneoCol = $torneoid";
    $sql = "SELECT " . implode(',', $fields) . " FROM registro r
            LEFT JOIN categorias c ON c.categoria_id = r.reg_categoria
            WHERE $where
            ORDER BY $orderCol DESC
            LIMIT 1000";
    json_response([
        'rows'        => query_all($conn, $sql),
        'scope'       => $showAll ? 'all' : 'torneo',
        'torneoid'    => $torneoid ?: null,
    ]);
}

json_error('Method not allowed', 405);