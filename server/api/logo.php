<?php
/**
 * Logo Proxy
 * Serves tournament/club logos (logo, logo_fondo, logo_header, club logos)
 * through our own domain.
 *
 * Usage: /api/logo.php?file=logo_name.png
 *
 * Images live OUTSIDE the domain folder, one level up, in:
 *     <parent-of-docroot>/alien_golftour/logos/
 *
 * The DB stores only the file name (e.g. "fondo2026.jpg").
 * Resolution order:
 *   1) local filesystem candidates (fast, no network)
 *   2) optional remote fallback (legacy server)
 */

header('Access-Control-Allow-Origin: *');

// ============= Validate Input =============
$file = $_GET['file'] ?? '';
$file = trim((string)$file);

// Some legacy rows may include a folder prefix; keep only the basename.
$file = basename(str_replace('\\', '/', $file));

if ($file === '' || !preg_match('/^[a-zA-Z0-9_\-\. ]+$/', $file) || strpos($file, '..') !== false) {
    http_response_code(400);
    echo 'Invalid filename';
    exit;
}

$debug = isset($_GET['debug']) && $_GET['debug'] == '1';

// ============= Build candidate local paths =============
$apiDir  = __DIR__;                 // .../<docroot>/api
$docroot = dirname($apiDir);        // .../<docroot>
$parent  = dirname($docroot);       // one level ABOVE the domain folder

$candidates = [
    // primary: sibling folder of the domain root
    $parent  . '/alien_golftour/logos/' . $file,
    // in case the domain root is nested one level deeper
    dirname($parent) . '/alien_golftour/logos/' . $file,
    // in case api/ sits directly under the domain folder structure
    $docroot . '/alien_golftour/logos/' . $file,
    // plain logos folder inside the domain
    $docroot . '/logos/' . $file,
];

if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $dr = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
    $candidates[] = dirname($dr) . '/alien_golftour/logos/' . $file;
    $candidates[] = $dr . '/alien_golftour/logos/' . $file;
}

$candidates = array_values(array_unique($candidates));

$found = null;
$tried = [];
foreach ($candidates as $path) {
    $exists = is_file($path) && is_readable($path);
    $tried[] = ['path' => $path, 'exists' => $exists];
    if ($exists) { $found = $path; break; }
}

// ============= Detect content type =============
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mimeTypes = [
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'svg'  => 'image/svg+xml',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'ico'  => 'image/x-icon',
    'bmp'  => 'image/bmp',
];
$contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

// ============= Debug mode =============
if ($debug) {
    header('Content-Type: application/json');
    echo json_encode([
        'file'        => $file,
        'apiDir'      => $apiDir,
        'docroot'     => $docroot,
        'parent'      => $parent,
        'documentRoot'=> $_SERVER['DOCUMENT_ROOT'] ?? null,
        'resolved'    => $found,
        'candidates'  => $tried,
        'contentType' => $contentType,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// ============= Serve local file =============
if ($found !== null) {
    header('Content-Type: ' . $contentType);
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . (string)filesize($found));
    readfile($found);
    exit;
}

// ============= Remote fallback (legacy server) =============
$remoteBases = [
    'https://alien2019.speitour.mx/logos/',
];
foreach ($remoteBases as $base) {
    $imageData = @file_get_contents($base . rawurlencode($file));
    if ($imageData !== false && $imageData !== '') {
        header('Content-Type: ' . $contentType);
        header('Cache-Control: public, max-age=86400');
        header('Content-Length: ' . strlen($imageData));
        echo $imageData;
        exit;
    }
}

http_response_code(404);
echo 'Logo not found';
