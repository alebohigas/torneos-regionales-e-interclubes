<?php
/**
 * Convocatoria Content Endpoint
 * ------------------------------------------------------------
 * GET  /api/convocatoria_content.php?torneoid=NNN
 *      -> { sections: [{ section_id, section_type, title, content, sort_order, enabled }] }
 *
 * POST /api/convocatoria_content.php
 *      Body: { password, torneoid, section_id, section_type?, title?, content, sort_order?, enabled? }
 *      Upserts a single section row via (torneoid, section_id) unique key.
 *
 * Table: convocatoria_content (see migration 2026_06_10_convocatoria_content.sql).
 * `content` is stored as JSON in the DB and returned as a decoded object/array.
 */

require_once 'config.php';
require_once '_staff_auth.php';
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
/**
 * Sin caché: el contenido de la convocatoria (costos incluidos) debe
 * reflejar de inmediato cualquier cambio guardado desde /admin. Se
 * desactiva la caché de navegador/proxy para el GET.
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

/**
 * Detect whether the convocatoria_content table exists, so a missing
 * migration returns an empty payload instead of a 500.
 */
function convocatoria_content_table_exists($conn) {
    static $exists = null;
    if ($exists !== null) return $exists;
    $r = $conn->query("SHOW TABLES LIKE 'convocatoria_content'");
    $exists = $r && $r->num_rows > 0;
    return $exists;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $torneoid = (int) require_torneoid($conn);

    if (!convocatoria_content_table_exists($conn)) {
        json_response(['sections' => []]);
    }

    $sql = "SELECT section_id, section_type, title, content, sort_order, enabled
            FROM convocatoria_content
            WHERE torneoid = $torneoid
            ORDER BY sort_order ASC, id ASC";
    $rows = query_all($conn, $sql);

    $sections = [];
    foreach ($rows as $r) {
        $decoded = null;
        if (!empty($r['content'])) {
            $decoded = json_decode($r['content'], true);
            // Tolerate malformed rows: surface as null instead of failing the whole call.
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('convocatoria_content.php: bad JSON for section ' . $r['section_id'] . ': ' . json_last_error_msg());
                $decoded = null;
            }
        }
        $sections[] = [
            'section_id'   => $r['section_id'],
            'section_type' => $r['section_type'],
            'title'        => $r['title'],
            'content'      => $decoded,
            'sort_order'   => (int) $r['sort_order'],
            'enabled'      => (int) $r['enabled'] === 1,
        ];
    }

    json_response(['sections' => $sections]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) json_error('Invalid JSON body', 400);

    // Auth: superadmin or normal staff user with Convocatoria/Reglas area.
    if (!is_superadmin_password($conn, $body['password'] ?? '')) {
        $staff = staff_check_area($conn, $body, 'convocatoria');
        if (!$staff) $staff = staff_check_area($conn, $body, 'reglas');
        if (!$staff) json_error('Unauthorized', 401);
    }

    if (!convocatoria_content_table_exists($conn)) {
        json_error('Missing table convocatoria_content. Run the migration first.', 500);
    }

    $torneoid   = isset($body['torneoid']) ? (int) $body['torneoid'] : 0;
    $sectionId  = trim((string)($body['section_id'] ?? ''));
    if ($torneoid <= 0 || $sectionId === '') {
        json_error('torneoid and section_id are required', 400);
    }

    $sectionType = esc($conn, (string)($body['section_type'] ?? 'generic'));
    $title       = isset($body['title']) && $body['title'] !== null
        ? "'" . esc($conn, (string)$body['title']) . "'"
        : 'NULL';
    $content     = esc($conn, json_encode($body['content'] ?? null, JSON_UNESCAPED_UNICODE));
    $sortOrder   = isset($body['sort_order']) ? (int)$body['sort_order'] : 0;
    $enabled     = !empty($body['enabled']) ? 1 : 0;
    $sectionIdEsc = esc($conn, $sectionId);

    $sql = "INSERT INTO convocatoria_content
            (torneoid, section_id, section_type, title, content, sort_order, enabled)
            VALUES
            ($torneoid, '$sectionIdEsc', '$sectionType', $title, '$content', $sortOrder, $enabled)
            ON DUPLICATE KEY UPDATE
              section_type = VALUES(section_type),
              title        = VALUES(title),
              content      = VALUES(content),
              sort_order   = VALUES(sort_order),
              enabled      = VALUES(enabled)";

    if (!$conn->query($sql)) {
        json_error('Failed to save convocatoria section: ' . $conn->error, 500);
    }

    json_response(['saved' => true, 'torneoid' => $torneoid, 'section_id' => $sectionId]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    /**
     * DELETE /api/convocatoria_content.php
     * Body: { password, torneoid, section_id }
     *
     * Removes a single (torneoid, section_id) row. Used by the admin
     * "Limpiar" action so the badge returns to "Vacío" and the public
     * page hides the section entirely (no mock fallback).
     */
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) json_error('Invalid JSON body', 400);

    if (!is_superadmin_password($conn, $body['password'] ?? '')) {
        $staff = staff_check_area($conn, $body, 'convocatoria');
        if (!$staff) $staff = staff_check_area($conn, $body, 'reglas');
        if (!$staff) json_error('Unauthorized', 401);
    }

    if (!convocatoria_content_table_exists($conn)) {
        json_response(['deleted' => true, 'note' => 'table not present']);
    }

    $torneoid  = isset($body['torneoid']) ? (int) $body['torneoid'] : 0;
    $sectionId = trim((string)($body['section_id'] ?? ''));
    if ($torneoid <= 0 || $sectionId === '') {
        json_error('torneoid and section_id are required', 400);
    }
    $sectionIdEsc = esc($conn, $sectionId);

    $sql = "DELETE FROM convocatoria_content
            WHERE torneoid = $torneoid AND section_id = '$sectionIdEsc'";
    if (!$conn->query($sql)) {
        json_error('Failed to delete convocatoria section: ' . $conn->error, 500);
    }

    json_response([
        'deleted'    => true,
        'torneoid'   => $torneoid,
        'section_id' => $sectionId,
        'rows'       => $conn->affected_rows,
    ]);

} else {
    json_error('Method not allowed', 405);
}