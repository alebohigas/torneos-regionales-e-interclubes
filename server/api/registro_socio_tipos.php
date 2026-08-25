<?php
/**
 * Registro Socio Tipos Endpoint
 * -----------------------------------------------------------------------
 * GET  /api/registro_socio_tipos.php?torneoid=XXX
 *      → returns the club-specific socio labels + their system mapping
 *        used by the public /registro dropdown ("Tipo de socio").
 *
 * POST /api/registro_socio_tipos.php  (JSON body: { torneoid, items[], password })
 *      → admin: replaces the full mapping for a tournament.
 *        Each item = { club_label, system_type, display_order, is_enabled }
 *
 * When the table is empty for a tournament the endpoint responds with a
 * default set of 3 rows (Titular / Emérito / Dependiente) so the public
 * form keeps the historical behavior.
 *
 * Security: superadmin password OR staff token scoped to 'preregistros'.
 */
require_once 'config.php';
require_once '_staff_auth.php';

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

/** Valid system types accepted from the client. */
$VALID_SYSTEM_TYPES = ['TITULAR', 'EMERITO', 'DEPENDIENTE'];

/** Default mapping when the table is empty for a tournament. */
$DEFAULT_ITEMS = [
    ['club_label' => 'Titular',     'system_type' => 'TITULAR',     'display_order' => 10, 'is_enabled' => 1],
    ['club_label' => 'Emérito',     'system_type' => 'EMERITO',     'display_order' => 20, 'is_enabled' => 1],
    ['club_label' => 'Dependiente', 'system_type' => 'DEPENDIENTE', 'display_order' => 30, 'is_enabled' => 1],
];

/** Detect whether the storage table exists. */
function socio_tipos_table_exists($conn) {
    static $exists = null;
    if ($exists !== null) return $exists;
    $r = $conn->query("SHOW TABLES LIKE 'registro_socio_tipos'");
    $exists = $r && $r->num_rows > 0;
    return $exists;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $torneoid = (int) require_torneoid($conn);

    if (!socio_tipos_table_exists($conn)) {
        json_response(['items' => $DEFAULT_ITEMS, 'source' => 'defaults']);
    }

    $rows = query_all(
        $conn,
        "SELECT club_label, system_type, display_order, is_enabled
         FROM registro_socio_tipos
         WHERE torneo_id = $torneoid
         ORDER BY display_order ASC, id ASC"
    );

    if (count($rows) === 0) {
        json_response(['items' => $DEFAULT_ITEMS, 'source' => 'defaults']);
    }

    $items = array_map(function ($r) {
        return [
            'club_label'    => (string)$r['club_label'],
            'system_type'   => strtoupper((string)$r['system_type']),
            'display_order' => (int)$r['display_order'],
            'is_enabled'    => (int)$r['is_enabled'] ? 1 : 0,
        ];
    }, $rows);

    json_response(['items' => $items, 'source' => 'db']);
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

    if (!socio_tipos_table_exists($conn)) {
        json_error("Table registro_socio_tipos not found. Run migration 2026_07_21_registro_socio_tipos.sql first.", 500);
    }

    $items = $body['items'] ?? [];
    if (!is_array($items)) json_error('items must be an array', 400);

    // Replace strategy: wipe this tournament's rows, then insert new set.
    $conn->query("DELETE FROM registro_socio_tipos WHERE torneo_id = $torneoid");

    $insertCount = 0;
    foreach ($items as $it) {
        $label  = trim((string)($it['club_label'] ?? ''));
        $sys    = strtoupper(trim((string)($it['system_type'] ?? '')));
        if ($label === '' || !in_array($sys, $VALID_SYSTEM_TYPES, true)) continue;
        $labelE = esc($conn, $label);
        $sysE   = esc($conn, $sys);
        $ord    = (int)($it['display_order'] ?? 0);
        $en     = !empty($it['is_enabled']) ? 1 : 0;
        $sql = "INSERT INTO registro_socio_tipos
                (torneo_id, club_label, system_type, display_order, is_enabled)
                VALUES ($torneoid, '$labelE', '$sysE', $ord, $en)";
        if ($conn->query($sql)) $insertCount++;
    }

    json_response(['saved' => true, 'count' => $insertCount]);
}

json_error('Method not allowed', 405);