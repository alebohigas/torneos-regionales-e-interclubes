<?php
/**
 * Showcase 300 Endpoint
 * GET /api/showcase300.php?torneoid=XXX&tipo=driver|approach|putt|oyes|oyesx
 *
 * Mirrors EXACTLY the queries used by the legacy PHP reports
 *   driver300.php, approach300.php, putt300.php, oyes300.php, oyesx300.php
 *
 * These reports use TABLES SEPARATE from the regular Competencias
 * endpoints — do NOT confuse them with /api/oyes.php, /api/oyesx.php or
 * /api/putt.php. Each tipo has its own catalogue + result + view tables:
 *
 *   driver   -> driver / driverjug      / v_driver / v_driverunico
 *   approach -> approach / approachjug  / v_approach / v_approachunico
 *   putt     -> putt / puttjug          / v_putt / v_puttunico
 *   oyes     -> premios / premiosjug    / v_oyesunicas (global)
 *   oyesx    -> oyesx / oyesxjug        / v_oyesx / v_oyesunicasxoyo
 *
 * Response shape:
 * {
 *   tournament: { name, club, logo },
 *   tipo:       'driver' | 'approach' | 'putt' | 'oyes' | 'oyesx',
 *   prizes: [
 *     { description, lugares, lastUpdated, players: [
 *         { position, name, club, clubLogo, hole, distance }
 *     ] }
 *   ]
 * }
 */
require_once 'config.php';

$torneoid = (int) require_torneoid($conn);
$tipo     = strtolower(optional_param('tipo', ''));

$VALID = ['driver', 'driverp', 'approach', 'putt', 'oyes', 'oyesx'];
if (!in_array($tipo, $VALID, true)) {
    json_error("Invalid tipo. Expected one of: " . implode(',', $VALID), 400);
}

$tid = $torneoid;

/**
 * safe_query
 * Run a SELECT and return all rows. On error log & return []
 * (some tournaments may not have the underlying table populated).
 */
function safe_query($conn, $sql) {
    debug_log_query('safe_query', $sql);
    $rs = @$conn->query($sql);
    if (!$rs) {
        error_log('[showcase300] query failed: ' . $conn->error . ' | SQL: ' . $sql);
        return [];
    }
    $rows = [];
    while ($r = $rs->fetch_assoc()) $rows[] = $r;
    $rs->free();
    return $rows;
}

/** safe_exec — for UPDATE statements; ignore errors silently */
function safe_exec($conn, $sql) {
    debug_log_query('safe_exec', $sql);
    if (!@$conn->query($sql)) {
        error_log('[showcase300] exec failed: ' . $conn->error . ' | SQL: ' . $sql);
    }
}

// ============= Tournament header =============
$trw = query_one($conn, "
    SELECT a.torneo_id, a.nombre, a.logo, a.oyesprese, a.oyesnumprem,
           a.oyesacum, a.oyesacumgpo, b.nombre AS club
    FROM torneo a
    JOIN clubs b ON (a.club_id = b.id)
    WHERE a.torneo_id = $tid
");
if (!$trw) json_error('Torneo no encontrado', 404);

$tournament = [
    'name' => $trw['nombre'],
    'club' => $trw['club'],
    'logo' => $trw['logo'] ? ('/api/logo.php?file=' . $trw['logo']) : '',
];

$oyesnumprem = (int) ($trw['oyesnumprem'] ?? 3);
$oyesprese   = (int) ($trw['oyesprese']   ?? 3);
$oyesacum    = (string) ($trw['oyesacum'] ?? '0');
$oyesacumgpo = (string) ($trw['oyesacumgpo'] ?? '0');

$prizes = [];

// ============================================================
// DRIVER  (driver300.php)
// ============================================================
if ($tipo === 'driver') {
    $groups = safe_query($conn, "
        SELECT premio, descripcion, hoyo,
               LEFT(f_ultfechadriver(descripcion, torneoid), 16) AS ultact
        FROM `driver`
        WHERE torneoid = $tid AND premio > 0
        GROUP BY premio, descripcion, hoyo
    ");

    foreach ($groups as $g) {
        $numjug = (int) $g['hoyo'];
        $decrip = esc($conn, $g['descripcion']);

        safe_exec($conn, "UPDATE `driverjug` AS a SET a.orden = 0 WHERE a.torneoid = $tid");
        safe_exec($conn, "
            UPDATE `driverjug` AS a
            JOIN v_driverunico AS b
              ON (a.jugadorid = b.jugadorid AND a.distancia = b.mindistancia AND a.torneoid = $tid)
            SET orden = 1
        ");

        $rows = safe_query($conn, "
            SELECT ROUND(TRUNCATE(a.distancia, 3), 2) AS distancia,
                   CONCAT(j.nombre, ' ', j.apellido) AS jugador,
                   j.club AS club_id, c.descripcion, f_logo(j.club) AS logo
            FROM driverjug a
            JOIN jugadores j ON (a.jugadorid = j.id)
            JOIN v_driver c
              ON (a.campo = c.campo
                  AND j.categoriaid = c.categoriaid
                  AND a.premiosjugcol = c.descripcion)
            WHERE a.torneoid = $tid AND c.descripcion = '$decrip'
            ORDER BY c.descripcion, a.distancia DESC
            LIMIT $numjug
        ");

        $players = [];
        $pos = 0;
        foreach ($rows as $r) {
            if ($r['distancia'] === '' || $r['distancia'] === null) continue;
            $pos++;
            $players[] = build_player_row($pos, $r, '');
        }

        $prizes[] = [
            'description' => $g['descripcion'],
            'lugares'     => $numjug,
            'lastUpdated' => $g['ultact'],
            'players'     => $players,
        ];
    }
}

// ============================================================
// DRIVERP — Driver Precisión (mirrors competencias.php driverp)
// Uses `driverp` / `driverjugp` / `v_driverp` / `v_driverunicop`.
// Ordered ASC (closer to centerline wins).
// ============================================================
if ($tipo === 'driverp') {
    $groups = safe_query($conn, "
        SELECT premio, descripcion, hoyo,
               LEFT(f_ultfechadriverp(descripcion, torneoid), 16) AS ultact
        FROM `driverp`
        WHERE torneoid = $tid AND premio > 0
        GROUP BY premio, descripcion, hoyo
    ");

    foreach ($groups as $g) {
        $numjug = (int) $g['hoyo'];
        $decrip = esc($conn, $g['descripcion']);

        safe_exec($conn, "UPDATE `driverjugp` AS a SET a.orden = 0 WHERE a.torneoid = $tid");
        safe_exec($conn, "
            UPDATE `driverjugp` AS a
            JOIN v_driverunicop AS b
              ON (a.jugadorid = b.jugadorid AND a.distancia = b.mindistancia AND a.torneoid = $tid)
            SET orden = 1
        ");

        $rows = safe_query($conn, "
            SELECT ROUND(TRUNCATE(a.distancia, 3), 2) AS distancia,
                   CONCAT(j.nombre, ' ', j.apellido) AS jugador,
                   j.club AS club_id, c.descripcion, f_logo(j.club) AS logo
            FROM driverjugp a
            JOIN jugadores j ON (a.jugadorid = j.id)
            JOIN v_driverp c
              ON (a.campo = c.campo
                  AND j.categoriaid = c.categoriaid
                  AND a.premiosjugcol = c.descripcion)
            WHERE a.torneoid = $tid AND c.descripcion = '$decrip'
            ORDER BY c.descripcion, a.distancia ASC
            LIMIT $numjug
        ");

        $players = [];
        $pos = 0;
        foreach ($rows as $r) {
            if ($r['distancia'] === '' || $r['distancia'] === null) continue;
            $pos++;
            $players[] = build_player_row($pos, $r, '');
        }

        $prizes[] = [
            'description' => $g['descripcion'],
            'lugares'     => $numjug,
            'lastUpdated' => $g['ultact'],
            'players'     => $players,
        ];
    }
}

// ============================================================
// APPROACH  (approach300.php)
// ============================================================
if ($tipo === 'approach') {
    $groups = safe_query($conn, "
        SELECT premio, descripcion, hoyo,
               LEFT(f_ultfechaapproach(descripcion, torneoid), 16) AS ultact
        FROM `approach`
        WHERE torneoid = $tid AND premio > 0
        GROUP BY premio, descripcion, hoyo
    ");

    foreach ($groups as $g) {
        $numjug = (int) $g['hoyo'];
        $decrip = esc($conn, $g['descripcion']);

        safe_exec($conn, "UPDATE `approachjug` AS a SET a.orden = 0 WHERE a.torneoid = $tid");
        safe_exec($conn, "
            UPDATE `approachjug` AS a
            JOIN v_approachunico AS b
              ON (a.jugadorid = b.jugadorid AND a.distancia = b.mindistancia AND a.torneoid = $tid)
            SET orden = 1
        ");

        $rows = safe_query($conn, "
            SELECT ROUND(TRUNCATE(a.distancia, 3), 2) AS distancia,
                   CONCAT(j.nombre, ' ', j.apellido) AS jugador,
                   j.club AS club_id, c.descripcion, f_logo(j.club) AS logo
            FROM approachjug a
            JOIN jugadores j ON (a.jugadorid = j.id)
            JOIN v_approach c
              ON (a.campo = c.campo
                  AND j.categoriaid = c.categoriaid
                  AND a.premiosjugcol = c.descripcion)
            WHERE a.torneoid = $tid AND c.descripcion = '$decrip'
            ORDER BY c.descripcion, a.distancia ASC
            LIMIT $numjug
        ");

        $players = [];
        $pos = 0;
        foreach ($rows as $r) {
            if ($r['distancia'] === '' || $r['distancia'] === null) continue;
            $pos++;
            $players[] = build_player_row($pos, $r, '');
        }

        $prizes[] = [
            'description' => $g['descripcion'],
            'lugares'     => $numjug,
            'lastUpdated' => $g['ultact'],
            'players'     => $players,
        ];
    }
}

// ============================================================
// PUTT  (putt300.php)
// ============================================================
if ($tipo === 'putt') {
    // Putt usa la misma detección robusta que /api/competencias.php:
    // 1) el catálogo sale de `putt`; 2) si la función de última fecha cambia
    // de firma o no existe, se vuelve a intentar sin ella; 3) como último
    // fallback se arma el catálogo desde `puttjug` para no ocultar resultados.
    $groups = safe_query($conn, "
        SELECT p.premio AS premioid,
               TRIM(p.descripcion) AS descripcion,
               MIN(NULLIF(p.hoyo, 0)) AS hoyo,
               LEFT(f_ultfechaputt(p.descripcion, p.torneoid), 16) AS ultact
        FROM `putt` p
        WHERE p.torneoid = $tid AND p.premio > 0
        GROUP BY p.premio, p.descripcion
        ORDER BY p.premio ASC
    ");
    if (empty($groups)) {
        $groups = safe_query($conn, "
            SELECT p.premio AS premioid,
                   TRIM(p.descripcion) AS descripcion,
                   MIN(NULLIF(p.hoyo, 0)) AS hoyo,
                   NULL AS ultact
            FROM `putt` p
            WHERE p.torneoid = $tid AND p.premio > 0
            GROUP BY p.premio, p.descripcion
            ORDER BY p.premio ASC
        ");
    }
    if (empty($groups)) {
        $groups = safe_query($conn, "
            SELECT pj.premio AS premioid,
                   TRIM(pj.premiosjugcol) AS descripcion,
                   NULL AS hoyo,
                   NULL AS ultact
            FROM puttjug pj
            WHERE pj.torneoid = $tid AND pj.premio > 0
            GROUP BY pj.premio, pj.premiosjugcol
            ORDER BY pj.premio ASC
        ");
    }

    safe_exec($conn, "UPDATE `puttjug` AS a SET a.orden = 0 WHERE a.torneoid = $tid");
    safe_exec($conn, "
        UPDATE `puttjug` AS a
        JOIN (
            SELECT torneoid, premio, premiosjugcol, jugadorid, MIN(distancia) AS mind
            FROM puttjug
            WHERE torneoid = $tid
            GROUP BY torneoid, premio, premiosjugcol, jugadorid
        ) AS b
          ON (a.torneoid = b.torneoid
              AND a.premio = b.premio
              AND a.premiosjugcol <=> b.premiosjugcol
              AND a.jugadorid = b.jugadorid
              AND a.distancia = b.mind)
        SET a.orden = 1
        WHERE a.torneoid = $tid
    ");

    foreach ($groups as $g) {
        $numjug = (int) ($g['hoyo'] ?? 0);
        if ($numjug <= 0) $numjug = $oyesnumprem;
        $decrip = esc($conn, $g['descripcion']);
        $premioId = (int) $g['premioid'];

        // Direct filter on puttjug — same approach competencias.php uses
        // (v_putt is missing/empty on some tournaments, breaking the join).
        $rows = safe_query($conn, "
            SELECT ROUND(TRUNCATE(a.distancia, 3), 2) AS distancia,
                   CONCAT(j.nombre, ' ', j.apellido) AS jugador,
                   cl.nombre AS club, cl.logo AS logo,
                   COALESCE(cat.abreviatura, cat.categoria, '') AS categoria
            FROM puttjug a
            JOIN jugadores j ON (a.jugadorid = j.id)
            JOIN clubs cl ON (j.clubid = cl.id)
            LEFT JOIN categorias cat ON (j.categoriaid = cat.categoria_id)
            WHERE a.torneoid = $tid
              AND a.premio = $premioId
              AND TRIM(a.premiosjugcol) = '$decrip'
              AND a.orden = 1
            ORDER BY a.distancia ASC
            LIMIT $numjug
        ");

        $players = [];
        $pos = 0;
        foreach ($rows as $r) {
            if ($r['distancia'] === '' || $r['distancia'] === null) continue;
            $pos++;
            $players[] = build_player_row($pos, $r, '');
        }

        $prizes[] = [
            'description' => $g['descripcion'],
            'lugares'     => $numjug,
            'lastUpdated' => $g['ultact'],
            'players'     => $players,
        ];
    }
}

// ============================================================
// OYESX  (oyesx300.php)
// ============================================================
if ($tipo === 'oyesx') {
    $groups = safe_query($conn, "
        SELECT premio, descripcion, hoyo,
               LEFT(f_ultfechaoyesx(descripcion, torneoid), 16) AS ultact
        FROM `oyesx`
        WHERE torneoid = $tid AND premio > 0
        GROUP BY premio, descripcion, hoyo
    ");

    foreach ($groups as $g) {
        $numjug = (int) $g['hoyo'];
        $decrip = esc($conn, $g['descripcion']);

        safe_exec($conn, "UPDATE `oyesxjug` AS a SET a.orden = 0 WHERE a.torneoid = $tid");
        safe_exec($conn, "
            UPDATE `oyesxjug` AS a
            JOIN v_oyesunicasxoyo AS b
              ON (a.jugadorid = b.jugadorid AND a.distancia = b.mindistancia AND a.torneoid = $tid)
            SET orden = 1
        ");

        $rows = safe_query($conn, "
            SELECT ROUND(TRUNCATE(a.distancia, 3), 2) AS distancia,
                   CONCAT(j.nombre, ' ', j.apellido) AS jugador,
                   j.club AS club_id, c.descripcion, f_logo(j.club) AS logo
            FROM oyesxjug a
            JOIN jugadores j ON (a.jugadorid = j.id)
            JOIN v_oyesx c
              ON (a.campo = c.campo
                  AND j.categoriaid = c.categoriaid
                  AND a.premiosjugcol = c.descripcion)
            WHERE a.torneoid = $tid AND c.descripcion = '$decrip'
            ORDER BY c.descripcion, a.distancia ASC
            LIMIT $numjug
        ");

        $players = [];
        $pos = 0;
        foreach ($rows as $r) {
            if ($r['distancia'] === '' || $r['distancia'] === null) continue;
            $pos++;
            $players[] = build_player_row($pos, $r, '');
        }

        $prizes[] = [
            'description' => $g['descripcion'],
            'lugares'     => $numjug,
            'lastUpdated' => $g['ultact'],
            'players'     => $players,
        ];
    }
}

// ============================================================
// OYES  (oyes300.php) — uses premios / premiosjug, hoyo SI se muestra
// ============================================================
if ($tipo === 'oyes') {
    $groups = safe_query($conn, "
        SELECT premio, descripcion
        FROM `premios`
        WHERE torneoid = $tid AND premio > 0
        GROUP BY premio, descripcion
    ");

    foreach ($groups as $g) {
        $gpo    = (int) $g['premio'];
        $numjug = $oyesprese > 0 ? $oyesprese : $oyesnumprem;

        // Base path = oyesacum=0
        if ($oyesacum === '1' && $oyesacumgpo === '0') {
            safe_exec($conn, "UPDATE `premiosjug` AS a SET a.orden = 0 WHERE a.torneoid = $tid");
            safe_exec($conn, "
                UPDATE `premiosjug` AS a
                JOIN v_oyesunicasxoyo AS b
                  ON (a.jugadorid = b.jugadorid AND a.distancia = b.mindistancia
                      AND a.hoyo = b.hoyo AND a.torneoid = $tid)
                SET orden = 1
            ");
        } else {
            safe_exec($conn, "UPDATE `premiosjug` AS a SET a.orden = 0 WHERE a.torneoid = $tid");
            safe_exec($conn, "
                UPDATE `premiosjug` AS a
                JOIN v_oyesunicas AS b
                  ON (a.jugadorid = b.jugadorid AND a.distancia = b.mindistancia AND a.torneoid = $tid)
                SET orden = 1
            ");
        }

        $rows = safe_query($conn, "
            SELECT a.hoyo,
                   ROUND(TRUNCATE(a.distancia, 3), 2) AS distancia,
                   CONCAT(j.nombre, ' ', j.apellido) AS jugador,
                   j.club AS club_id, c.premio, f_logo(j.club) AS logo,
                   `f_ultact`(a.torneoid, c.premio) AS ultact
            FROM premiosjug a
            JOIN jugadores j ON (a.jugadorid = j.id AND a.orden = 1)
            JOIN premios c ON (a.fecha = c.fecha AND a.campo = c.campo
                               AND a.hoyo = c.hoyo AND j.categoriaid = c.categoriaid)
            WHERE a.torneoid = $tid AND c.premio = $gpo
            ORDER BY c.premio, a.distancia ASC
            LIMIT $numjug
        ");

        $players = [];
        $pos = 0;
        $ultact = '';
        foreach ($rows as $r) {
            $pos++;
            if (!$ultact) $ultact = substr((string) ($r['ultact'] ?? ''), 0, 16);
            $players[] = build_player_row($pos, $r, $r['hoyo']);
        }

        $prizes[] = [
            'description' => $g['descripcion'],
            'lugares'     => $numjug,
            'lastUpdated' => $ultact,
            'players'     => $players,
        ];
    }
}

/**
 * build_player_row
 * Normalizes a result row into the JSON player shape.
 * @param int    $pos     1-based position in the prize list.
 * @param array  $r       raw DB row (must have jugador, distancia, logo).
 * @param mixed  $hoyo    optional hole label ('' for driver/approach/putt/oyesx).
 */
function build_player_row($pos, $r, $hoyo) {
    $logo = $r['logo'] ?? '';
    // f_logo() returns a path like "logos/xxx.png" — proxy it through logo.php.
    if ($logo !== '' && strpos($logo, 'http') !== 0) {
        // Strip leading "../" or "logos/" prefixes that may exist in legacy data.
        $clean = preg_replace('#^\.\./#', '', $logo);
        $clean = preg_replace('#^logos/#', '', $clean);
        $logo = '/api/logo.php?file=' . $clean;
    }
    return [
        'position' => $pos,
        'name'     => $r['jugador'] ?? '',
        'club'     => '',
        'clubLogo' => $logo,
        'hole'     => $hoyo === '' || $hoyo === null ? '' : (int) $hoyo,
        'distance' => isset($r['distancia']) ? (float) $r['distancia'] : 0,
    ];
}

json_response([
    'tipo'       => $tipo,
    'tournament' => $tournament,
    'prizes'     => $prizes,
]);