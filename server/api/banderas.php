<?php
/**
 * Banderas (Pin Sheet) Endpoint
 * -----------------------------------------------------------------------
 * GET  /api/banderas.php?torneoid=XXX[&fecha=YYYY-MM-DD][&admin=1&password=...]
 *      Sin `fecha`: devuelve la fecha activa (la más reciente <= hoy con datos)
 *        más la lista de fechas disponibles (sólo <= hoy salvo admin=1).
 *      Con `fecha`: devuelve `holes` para esa fecha. Fechas futuras se
 *        rechazan al público (sólo admin las puede pedir).
 *      Respuesta:
 *        {
 *          today:           'YYYY-MM-DD',
 *          activeDate:      'YYYY-MM-DD' | null,
 *          availableDates:  ['YYYY-MM-DD', ...],   // ordenadas asc
 *          holes:           [{ hole, depth, pinFromFront, pinFromSide,
 *                              pinSide, slope, title }]
 *        }
 *
 * POST /api/banderas.php
 *      Body JSON: { password, torneoid, fecha: 'YYYY-MM-DD', holes: [...] }
 *      Replace-all para (torneo, fecha). Sólo guarda filas con `hoyo` > 0.
 *      Si `fecha` viene vacía se usa CURDATE().
 *
 * Tabla: `banderas` (migración 2026_06_21 + 2026_06_22_banderas_fecha).
 */
require_once 'config.php';
require_once '_staff_auth.php';

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

/** Password admin compartido (mismo que el resto del panel). */
const BANDERAS_ADMIN_PWD = 'admin2025';

/** ¿Existe la tabla? Cacheado. */
function banderas_table_exists($conn) {
    static $exists = null;
    if ($exists !== null) return $exists;
    $r = $conn->query("SHOW TABLES LIKE 'banderas'");
    $exists = $r && $r->num_rows > 0;
    return $exists;
}

/** ¿La columna `fecha` ya está aplicada (migración 2026_06_22)? Cacheado. */
function banderas_has_fecha_column($conn) {
    static $has = null;
    if ($has !== null) return $has;
    $r = $conn->query("SHOW COLUMNS FROM banderas LIKE 'fecha'");
    $has = $r && $r->num_rows > 0;
    return $has;
}

/** Valida string de fecha (YYYY-MM-DD). Devuelve la fecha o null si inválida. */
function banderas_parse_fecha($s) {
    if (!is_string($s) || $s === '') return null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return null;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    if (!$d) return null;
    return $d->format('Y-m-d');
}

/** Normaliza fila de BD a JSON consumido por el cliente. */
function normalize_bandera($r) {
    return [
        'hole'         => (int)$r['hoyo'],
        'depth'        => (int)$r['depth'],
        'pinFromFront' => (int)$r['frente'],
        'pinFromSide'  => (int)$r['lateral'],
        'pinSide'      => $r['lateral_lado'] === 'R' ? 'R' : 'L',
        'slope'        => (int)$r['desde_centro'],
        'title'        => $r['titulo'] ?? null,
    ];
}

// ---------------------------------------------------------------------------
// GET
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $torneoid = (int) require_torneoid($conn);
    $today    = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    if (!banderas_table_exists($conn)) {
        json_response([
            'holes'          => [],
            'today'          => $today,
            'activeDate'     => null,
            'availableDates' => [],
            'source'         => 'no_table',
        ]);
    }

    $hasFecha = banderas_has_fecha_column($conn);

    // --- Modo legacy: tabla sin columna `fecha` (migración no corrida) ----
    if (!$hasFecha) {
        $sql = "SELECT hoyo, depth, frente, lateral, lateral_lado, desde_centro, titulo
                  FROM banderas
                 WHERE torneo_id = $torneoid
                 ORDER BY hoyo ASC";
        $rows = array_map('normalize_bandera', query_all($conn, $sql));
        json_response([
            'holes'          => $rows,
            'today'          => $today,
            'activeDate'     => $today,
            'availableDates' => [$today],
            'source'         => 'legacy_no_fecha',
        ]);
    }

    // --- ¿Admin? Si manda password válido puede ver fechas futuras --------
    $isAdmin = false;
    if (isset($_GET['admin']) && $_GET['admin'] === '1') {
        if (is_superadmin_password($conn, $_GET['password'] ?? '')) {
            $isAdmin = true;
        } else {
            $staff = staff_check_area($conn, [], 'banderas');
            if ($staff) $isAdmin = true;
        }
    }

    // --- Lista de TODAS las fechas con datos para este torneo -------------
    $sqlAllDates = "SELECT DISTINCT DATE_FORMAT(fecha, '%Y-%m-%d') AS f
                      FROM banderas
                     WHERE torneo_id = $torneoid
                     ORDER BY fecha ASC";
    $allDates = [];
    foreach (query_all($conn, $sqlAllDates) as $r) $allDates[] = $r['f'];

    /**
     * Reglas de visibilidad pública (cliente final):
     *   1. Sólo se muestra UNA fecha — la del día de hoy si existe.
     *   2. Si NO existe hoy pero existe mañana, sólo se muestra mañana
     *      cuando ya nadie está jugando hoy (todas las tarjetas del torneo
     *      con fecha_juego = hoy tienen statlsc = 1).
     *   3. Mientras haya jugadores con tarjeta abierta hoy, NO se publica
     *      mañana (aunque ya esté cargada en BD por el admin).
     *   4. Fechas pasadas y futuras (más allá de mañana) no son visibles.
     *
     * `playersStillPlayingToday` es true cuando existe al menos UNA tarjeta
     * con fecha_juego = hoy y statlsc <> 1.
     */
    $playersStillPlayingToday = false;
    $sqlOpen = "SELECT COUNT(*) AS n
                  FROM tarjetas
                 WHERE torneoid = $torneoid
                   AND DATE(fecha_juego) = '$today'
                   AND (statlsc IS NULL OR statlsc <> 1)";
    $openRow = @$conn->query($sqlOpen);
    if ($openRow && $row = $openRow->fetch_assoc()) {
        $playersStillPlayingToday = ((int)$row['n']) > 0;
    }

    $hasToday    = in_array($today, $allDates, true);
    $hasTomorrow = in_array($tomorrow, $allDates, true);

    // --- Determinar fecha solicitada / activa -----------------------------
    $requested = banderas_parse_fecha($_GET['fecha'] ?? null);

    if ($isAdmin) {
        // Admin: puede ver/editar cualquier fecha existente o nueva.
        $dates      = $allDates;
        $activeDate = $requested ?? ($hasToday ? $today : (count($allDates) ? end($allDates) : $today));
    } else {
        // Público: ignoramos `requested` — siempre devolvemos la única
        // fecha visible permitida por las reglas.
        // Público: ignoramos `requested`.
        //   1. Si hay pin sheet de HOY, se muestra hoy.
        //   2. Si no hay hoy pero sí mañana y ya nadie está jugando, mañana.
        //   3. Fallback (NUEVO): se conserva SIEMPRE la última fecha cargada
        //      del torneo — preferimos la última fecha <= hoy; si sólo hay
        //      fechas futuras, usamos la última disponible. Así las posiciones
        //      nunca desaparecen al terminar el torneo.
        $activeDate = null;
        if ($hasToday) {
            $activeDate = $today;
        } elseif ($hasTomorrow && !$playersStillPlayingToday) {
            $activeDate = $tomorrow;
        } elseif (count($allDates)) {
            /** Última fecha pasada (<= hoy) con datos. */
            $past = array_values(array_filter($allDates, function ($d) use ($today) {
                return $d <= $today;
            }));
            $activeDate = count($past) ? end($past) : end($allDates);
        }
        $dates = $activeDate !== null ? [$activeDate] : [];
    }

    // --- Cargar holes para la fecha activa --------------------------------
    $holes = [];
    if ($activeDate !== null) {
        $sql = "SELECT hoyo, depth, frente, lateral, lateral_lado, desde_centro, titulo
                  FROM banderas
                 WHERE torneo_id = $torneoid AND fecha = '$activeDate'
                 ORDER BY hoyo ASC";
        $holes = array_map('normalize_bandera', query_all($conn, $sql));
    }

    json_response([
        'holes'          => $holes,
        'today'          => $today,
        'activeDate'     => $activeDate,
        'availableDates' => $dates,
        'playersStillPlayingToday' => $playersStillPlayingToday,
    ]);
}

// ---------------------------------------------------------------------------
// POST — admin replace-all
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) json_error('Invalid JSON body', 400);

    $password = $body['password'] ?? '';
    if (!is_superadmin_password($conn, $password)) {
        // Permitir staff con área 'banderas'
        $staff = staff_check_area($conn, $body, 'banderas');
        if (!$staff) json_error('Unauthorized', 401);
    }

    $torneoid = isset($body['torneoid']) ? (int)$body['torneoid'] : 0;
    if ($torneoid <= 0) json_error('Missing torneoid', 400);

    if (!banderas_table_exists($conn)) {
        json_error('Tabla banderas no existe. Corre la migración 2026_06_21_banderas.sql.', 500);
    }

    $hasFecha = banderas_has_fecha_column($conn);
    if (!$hasFecha) {
        json_error('Falta correr la migración 2026_06_22_banderas_fecha.sql.', 500);
    }

    $fecha = banderas_parse_fecha($body['fecha'] ?? null);
    if ($fecha === null) $fecha = date('Y-m-d');

    $holes = $body['holes'] ?? [];
    if (!is_array($holes)) json_error('holes must be an array', 400);

    $conn->query("DELETE FROM banderas WHERE torneo_id = $torneoid AND fecha = '$fecha'");

    $count = 0;
    foreach ($holes as $h) {
        $hole = (int)($h['hole'] ?? 0);
        if ($hole <= 0) continue;
        $depth        = (int)($h['depth'] ?? 0);
        $frente       = (int)($h['pinFromFront'] ?? 0);
        $lateral      = (int)($h['pinFromSide'] ?? 0);
        $side         = (($h['pinSide'] ?? 'L') === 'R') ? 'R' : 'L';
        $desdeCentro  = (int)($h['slope'] ?? 0);
        $titulo       = $h['title'] ?? null;
        $tituloSql    = ($titulo === null || $titulo === '')
            ? 'NULL'
            : "'" . esc($conn, (string)$titulo) . "'";

        $sql = "INSERT INTO banderas
                  (torneo_id, fecha, hoyo, depth, frente, lateral, lateral_lado,
                   desde_centro, titulo)
                VALUES
                  ($torneoid, '$fecha', $hole, $depth, $frente, $lateral,
                   '$side', $desdeCentro, $tituloSql)";
        if ($conn->query($sql)) $count++;
    }
    json_response(['saved' => true, 'count' => $count, 'fecha' => $fecha]);
}

json_error('Method not allowed', 405);
