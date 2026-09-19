<?php
/**
 * Registro Form Fields Endpoint
 * -----------------------------------------------------------------------
 * GET  /api/registro_fields.php?torneoid=XXX
 *      → returns the configured form fields for the public /registro page
 *
 * POST /api/registro_fields.php?torneoid=XXX  (JSON body)
 *      → admin: upserts the configuration (fields[] = { field_name, field_label, is_enabled, is_required, display_order })
 *
 * Backed by table `registro_form_fields` (see migration block previously sent
 * to the user). On a fresh tournament with no rows we synthesize a default
 * field set so the public page is usable out of the box.
 */
require_once 'config.php';
require_once '_staff_auth.php';

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

/**
 * Default field set — used when the DB has no rows for this tournament.
 * `section` groups fields into the progressive-disclosure UI sections:
 *   - 'basica'      → identity, contact, hcp, género, fechanac, categoría
 *   - 'socios'      → es_socio, tipo_socio, club, location
 *   - 'adicionales' → comprobante, notas, future tallas
 */
$DEFAULT_FIELDS = [
    ['field_name' => 'reg_nombre',     'field_label' => 'Nombre',                                       'is_enabled' => 1, 'is_required' => 1, 'display_order' => 10,  'section' => 'basica'],
    ['field_name' => 'reg_apellido',   'field_label' => 'Apellido',                                     'is_enabled' => 1, 'is_required' => 1, 'display_order' => 20,  'section' => 'basica'],
    ['field_name' => 'reg_correo',     'field_label' => 'Correo electrónico',                           'is_enabled' => 1, 'is_required' => 1, 'display_order' => 30,  'section' => 'basica'],
    ['field_name' => 'reg_telefono',   'field_label' => 'Teléfono',                                     'is_enabled' => 1, 'is_required' => 0, 'display_order' => 40,  'section' => 'basica'],
    ['field_name' => 'reg_handicap',   'field_label' => 'Hándicap',                                     'is_enabled' => 1, 'is_required' => 1, 'display_order' => 50,  'section' => 'basica'],
    ['field_name' => 'reg_sexo',       'field_label' => 'Género',                                       'is_enabled' => 1, 'is_required' => 0, 'display_order' => 60,  'section' => 'basica'],
    ['field_name' => 'reg_fechanac',   'field_label' => 'Fecha de nacimiento',                          'is_enabled' => 1, 'is_required' => 0, 'display_order' => 70,  'section' => 'basica'],
    // Datos del tutor (juveniles / menores de edad)
    ['field_name' => 'reg_tutor',      'field_label' => 'Nombre del tutor',                             'is_enabled' => 1, 'is_required' => 0, 'display_order' => 71,  'section' => 'basica'],
    ['field_name' => 'reg_emailtutor', 'field_label' => 'Correo del tutor',                             'is_enabled' => 1, 'is_required' => 0, 'display_order' => 72,  'section' => 'basica'],
    ['field_name' => 'reg_celtutor',   'field_label' => 'Celular del tutor',                            'is_enabled' => 1, 'is_required' => 0, 'display_order' => 73,  'section' => 'basica'],

    // Edad — alternativa o complemento de fechanac. Si fechanac está activa
    // se auto-calcula y queda en gris; si no, el jugador la captura.
    ['field_name' => 'akron_edad',     'field_label' => 'Edad',                                         'is_enabled' => 0, 'is_required' => 0, 'display_order' => 75,  'section' => 'basica'],
    ['field_name' => 'reg_categoria',  'field_label' => 'Categoría',                                    'is_enabled' => 1, 'is_required' => 1, 'display_order' => 80,  'section' => 'basica'],
    ['field_name' => 'reg_es_socio',   'field_label' => '¿Es socio del club que realiza el torneo?',    'is_enabled' => 1, 'is_required' => 1, 'display_order' => 90,  'section' => 'socios'],
    ['field_name' => 'reg_tipo_socio', 'field_label' => 'Tipo de socio',                                'is_enabled' => 1, 'is_required' => 0, 'display_order' => 100, 'section' => 'socios'],
    // Cargo a cuenta de socio: checkbox opcional. Cuando se activa,
    // requiere capturar `reg_numsocio` y oculta el comprobante
    // (`reg_archivo`). El admin lo controla desde el panel.
    ['field_name' => 'reg_cargo_socio', 'field_label' => 'Cargo a cuenta de socio',                     'is_enabled' => 1, 'is_required' => 0, 'display_order' => 105, 'section' => 'socios'],
    ['field_name' => 'reg_numsocio', 'field_label' => 'Número / Clave de socio',                     'is_enabled' => 1, 'is_required' => 0, 'display_order' => 106, 'section' => 'socios'],
    ['field_name' => 'reg_club',       'field_label' => 'Club de procedencia',                          'is_enabled' => 1, 'is_required' => 0, 'display_order' => 110, 'section' => 'socios'],
    ['field_name' => 'reg_pais',       'field_label' => 'País',                                         'is_enabled' => 1, 'is_required' => 0, 'display_order' => 120, 'section' => 'socios'],
    ['field_name' => 'reg_estado',     'field_label' => 'Estado',                                       'is_enabled' => 1, 'is_required' => 0, 'display_order' => 130, 'section' => 'socios'],
    ['field_name' => 'reg_ciudad',     'field_label' => 'Ciudad',                                       'is_enabled' => 1, 'is_required' => 0, 'display_order' => 140, 'section' => 'socios'],
    // Identificadores oficiales — al llenarse, sincroniza con tabla jugadores
    ['field_name' => 'reg_spei',       'field_label' => 'ID SPEI (interno)',                            'is_enabled' => 0, 'is_required' => 0, 'display_order' => 145, 'section' => 'socios'],
    ['field_name' => 'numghinspei',    'field_label' => 'GHIN (FMG / USGA)',                            'is_enabled' => 1, 'is_required' => 0, 'display_order' => 150, 'section' => 'socios'],
    // Dirección postal
    ['field_name' => 'reg_direccion',  'field_label' => 'Dirección',                                    'is_enabled' => 0, 'is_required' => 0, 'display_order' => 152, 'section' => 'socios'],
    ['field_name' => 'reg_cp',         'field_label' => 'Código postal',                                'is_enabled' => 0, 'is_required' => 0, 'display_order' => 154, 'section' => 'socios'],
    ['field_name' => 'reg_archivo',         'field_label' => 'Comprobante de pago',                          'is_enabled' => 1, 'is_required' => 0, 'display_order' => 160, 'section' => 'adicionales'],
    ['field_name' => 'reg_notas',           'field_label' => 'Notas adicionales',                            'is_enabled' => 1, 'is_required' => 0, 'display_order' => 170, 'section' => 'adicionales'],
    // Tallas — desactivadas por defecto; el admin las activa según necesidad
    ['field_name' => 'akron_talla',         'field_label' => 'Talla de playera',                             'is_enabled' => 0, 'is_required' => 0, 'display_order' => 180, 'section' => 'adicionales'],
    ['field_name' => 'reg_talla_gorra',     'field_label' => 'Talla de gorra',                               'is_enabled' => 0, 'is_required' => 0, 'display_order' => 185, 'section' => 'adicionales'],
    ['field_name' => 'akron_talla_guante',  'field_label' => 'Talla de guante',                              'is_enabled' => 0, 'is_required' => 0, 'display_order' => 190, 'section' => 'adicionales'],
    ['field_name' => 'akron_calzado',       'field_label' => 'Talla de calzado / tenis',                     'is_enabled' => 0, 'is_required' => 0, 'display_order' => 210, 'section' => 'adicionales'],
    // Código de promoción (jugador lo llena, admin lo valida)
    ['field_name' => 'akron_codigo',        'field_label' => 'Código de promoción',                          'is_enabled' => 0, 'is_required' => 0, 'display_order' => 240, 'section' => 'adicionales'],
    // === Sección REVISIÓN ADMIN — el jugador NO ve estos campos ===
    ['field_name' => 'reg_cargo',           'field_label' => 'Cargo / pago registrado',                      'is_enabled' => 0, 'is_required' => 0, 'display_order' => 300, 'section' => 'revision'],
    ['field_name' => 'akron_monto_pago',    'field_label' => 'Monto pagado',                                 'is_enabled' => 0, 'is_required' => 0, 'display_order' => 310, 'section' => 'revision'],
    ['field_name' => 'akron_codigo_admin',  'field_label' => 'Código promoción (validación admin)',          'is_enabled' => 0, 'is_required' => 0, 'display_order' => 320, 'section' => 'revision'],
];

/** Ensure the registro_form_fields table exists; if not, return defaults / refuse writes. */
function registro_fields_table_exists($conn) {
    static $exists = null;
    if ($exists !== null) return $exists;
    $r = $conn->query("SHOW TABLES LIKE 'registro_form_fields'");
    $exists = $r && $r->num_rows > 0;
    return $exists;
}

/**
 * Ensure the `section` column exists on `registro_form_fields`. Runs a
 * harmless ALTER TABLE the first time and caches the result for the
 * remainder of the request.
 */
function ensure_section_column($conn) {
    static $checked = null;
    if ($checked !== null) return $checked;
    if (!registro_fields_table_exists($conn)) return $checked = false;
    $r = $conn->query("SHOW COLUMNS FROM registro_form_fields LIKE 'section'");
    if ($r && $r->num_rows > 0) return $checked = true;
    @$conn->query("ALTER TABLE registro_form_fields ADD COLUMN section VARCHAR(32) NOT NULL DEFAULT 'basica'");
    $r = $conn->query("SHOW COLUMNS FROM registro_form_fields LIKE 'section'");
    return $checked = ($r && $r->num_rows > 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $torneoid = (int) require_torneoid($conn);

    if (!registro_fields_table_exists($conn)) {
        json_response(['fields' => $DEFAULT_FIELDS, 'source' => 'defaults']);
    }

    $hasSection = ensure_section_column($conn);
    /**
     * Migración silenciosa: filas legadas con field_name = 'reg_edad'
     * se renombran a 'akron_edad' (columna real en la tabla `registro`).
     * IGNORE evita conflicto si ya existe la fila con el nuevo nombre.
     */
    @$conn->query("UPDATE IGNORE registro_form_fields SET field_name = 'akron_edad' WHERE field_name = 'reg_edad'");
    @$conn->query("DELETE FROM registro_form_fields WHERE field_name = 'reg_edad'");
    $sectionSql = $hasSection ? ", section" : "";
    $sql = "SELECT field_name, field_label, is_enabled, is_required, display_order $sectionSql
            FROM registro_form_fields
            WHERE torneo_id = $torneoid
            ORDER BY display_order ASC, field_name ASC";
    $rows = query_all($conn, $sql);

    if (count($rows) === 0) {
        json_response(['fields' => $DEFAULT_FIELDS, 'source' => 'defaults']);
    }

    /** Cast booleans / ints for the client */
    /** Default-section lookup (so legacy rows without `section` map sensibly). */
    $defaultSectionByName = [];
    foreach ($DEFAULT_FIELDS as $df) {
        $defaultSectionByName[$df['field_name']] = $df['section'];
    }
    $fields = array_map(function($r) use ($defaultSectionByName) {
        $section = isset($r['section']) && $r['section'] !== ''
            ? $r['section']
            : ($defaultSectionByName[$r['field_name']] ?? 'basica');
        return [
            'field_name'    => $r['field_name'],
            'field_label'   => $r['field_label'],
            'is_enabled'    => (int)$r['is_enabled'] ? 1 : 0,
            'is_required'   => (int)$r['is_required'] ? 1 : 0,
            'display_order' => (int)$r['display_order'],
            'section'       => $section,
        ];
    }, $rows);

    /**
     * Backfill: garantizar que TODOS los campos definidos en $DEFAULT_FIELDS
     * aparezcan en la respuesta aunque la fila en BD no exista todavía. Esto
     * permite que campos agregados después de la primera configuración
     * (ej. `akron_edad`) sean visibles en el panel admin sin requerir una
     * migración manual por torneo. Los campos nuevos se insertan como
     * deshabilitados para no alterar formularios ya publicados.
     */
    $existingNames = array_flip(array_map(function($f){ return $f['field_name']; }, $fields));
    foreach ($DEFAULT_FIELDS as $df) {
        if (isset($existingNames[$df['field_name']])) continue;
        $fields[] = [
            'field_name'    => $df['field_name'],
            'field_label'   => $df['field_label'],
            'is_enabled'    => 0, // siempre off por defecto al backfillar
            'is_required'   => 0,
            'display_order' => (int)$df['display_order'],
            'section'       => $df['section'],
        ];
    }
    // Ordenar de nuevo por display_order tras el merge.
    usort($fields, function($a, $b){ return $a['display_order'] <=> $b['display_order']; });

    json_response(['fields' => $fields, 'source' => 'db']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) json_error('Invalid JSON body', 400);

    $password = $body['password'] ?? '';
    if (!is_superadmin_password($conn, $password)) {
        $staff = staff_check_area($conn, $body, 'preregistros');
        if (!$staff) json_error('Unauthorized', 401);
    }

    $torneoid = isset($body['torneoid']) ? (int)$body['torneoid'] : 0;
    if ($torneoid <= 0) json_error('Missing torneoid', 400);

    if (!registro_fields_table_exists($conn)) {
        json_error('Table registro_form_fields not found. Run the migration block first.', 500);
    }

    $hasSection = ensure_section_column($conn);

    $fields = $body['fields'] ?? [];
    if (!is_array($fields)) json_error('fields must be an array', 400);

    /** Replace strategy: wipe this tournament's rows then insert the new set. */
    $conn->query("DELETE FROM registro_form_fields WHERE torneo_id = $torneoid");

    $insertCount = 0;
    foreach ($fields as $f) {
        $rawName = (string)($f['field_name']  ?? '');
        // Compat: cualquier envío con 'reg_edad' se persiste como 'akron_edad'
        // (columna real en la tabla `registro`).
        if ($rawName === 'reg_edad') $rawName = 'akron_edad';
        $name  = esc($conn, $rawName);
        $label = esc($conn, (string)($f['field_label'] ?? ''));
        $en    = !empty($f['is_enabled'])  ? 1 : 0;
        $req   = !empty($f['is_required']) ? 1 : 0;
        $ord   = (int)($f['display_order'] ?? 0);
        $sect  = esc($conn, (string)($f['section'] ?? 'basica'));
        if ($name === '') continue;

        if ($hasSection) {
            $sql = "INSERT INTO registro_form_fields
                    (torneo_id, field_name, field_label, is_enabled, is_required, display_order, section)
                    VALUES ($torneoid, '$name', '$label', $en, $req, $ord, '$sect')";
        } else {
            $sql = "INSERT INTO registro_form_fields
                    (torneo_id, field_name, field_label, is_enabled, is_required, display_order)
                    VALUES ($torneoid, '$name', '$label', $en, $req, $ord)";
        }
        if ($conn->query($sql)) $insertCount++;
    }

    json_response(['saved' => true, 'count' => $insertCount]);
}

json_error('Method not allowed', 405);