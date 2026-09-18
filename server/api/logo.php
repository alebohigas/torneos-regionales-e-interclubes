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
$scan  = isset($_GET['scan'])  && $_GET['scan']  == '1';

// ============= Scan mode =============
// /api/logo.php?scan=1  → lists every "logos*" folder found near the site so we
// can discover where the images actually live on this hosting account.
if ($scan) {
    header('Content-Type: application/json');
    $roots = array_values(array_unique(array_filter([
        __DIR__,
        dirname(__DIR__),
        dirname(dirname(__DIR__)),
        !empty($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') : null,
        !empty($_SERVER['DOCUMENT_ROOT']) ? dirname(rtrim($_SERVER['DOCUMENT_ROOT'], '/')) : null,
    ])));
    $report = [];
    foreach ($roots as $root) {
        $entry = ['root' => $root, 'readable' => is_dir($root) && is_readable($root), 'dirs' => []];
        if ($entry['readable']) {
            foreach ((array)@scandir($root) as $name) {
                if ($name === '.' || $name === '..') continue;
                $p = $root . '/' . $name;
                if (!is_dir($p)) continue;
                $item = ['name' => $name];
                // Look one level deeper for a "logos" folder and sample files.
                foreach (['logos', 'logos_patrocinadores'] as $sub) {
                    if (is_dir($p . '/' . $sub)) {
                        $files = array_slice(array_values(array_diff((array)@scandir($p . '/' . $sub), ['.', '..'])), 0, 5);
                        $item[$sub] = ['path' => $p . '/' . $sub, 'sample' => $files];
                    }
                }
                $entry['dirs'][] = $item;
            }
        }
        $report[] = $entry;
    }
    echo json_encode(['scan' => $report], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

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

/*
 * Generic discovery: walk up the directory tree (both the real path and the
 * DOCUMENT_ROOT variant, which on IONOS may carry a /kunden prefix) and look
 * for ANY "*\/logos" folder, not just "alien_golftour/logos".
 */
$bases = array_filter([
    $apiDir,
    $docroot,
    $parent,
    dirname($parent),
    !empty($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') : null,
    !empty($_SERVER['DOCUMENT_ROOT']) ? dirname(rtrim($_SERVER['DOCUMENT_ROOT'], '/')) : null,
    !empty($_SERVER['DOCUMENT_ROOT']) ? dirname(dirname(rtrim($_SERVER['DOCUMENT_ROOT'], '/'))) : null,
]);

foreach ($bases as $base) {
    $candidates[] = $base . '/logos/' . $file;
    foreach ((array)@glob($base . '/*/logos', GLOB_ONLYDIR) as $dir) {
        $candidates[] = $dir . '/' . $file;
    }
    foreach ((array)@glob($base . '/*/*/logos', GLOB_ONLYDIR) as $dir) {
        $candidates[] = $dir . '/' . $file;
    }
}

$candidates = array_values(array_unique($candidates));

$found = null;
$tried = [];
foreach ($candidates as $path) {
    $exists = is_file($path) && is_readable($path);
    $tried[] = ['path' => $path, 'exists' => $exists];
    if ($exists) { $found = $path; break; }
}

/*
 * Case-insensitive retry: Linux is case sensitive but the DB may store
 * "mty_e1_2627.jpg" while the real file is "mty_e1_2627.JPG".
 */
if ($found === null) {
    $wanted = strtolower($file);
    $seenDirs = [];
    foreach ($candidates as $path) {
        $dir = dirname($path);
        if (isset($seenDirs[$dir]) || !is_dir($dir)) continue;
        $seenDirs[$dir] = true;
        foreach ((array)@scandir($dir) as $name) {
            if ($name === '.' || $name === '..') continue;
            if (strtolower($name) === $wanted) {
                $candidate = $dir . '/' . $name;
                if (is_file($candidate) && is_readable($candidate)) {
                    $found = $candidate;
                    $tried[] = ['path' => $candidate, 'exists' => true, 'ci' => true];
                    break 2;
                }
            }
        }
    }
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
