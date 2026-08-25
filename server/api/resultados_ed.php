<?php
/**
 * Eliminación Directa (Match Play Brackets) Endpoint
 * GET /api/resultados_ed.php?catid=XXX&torneoid=XXX[&debug=1]
 *
 * Lee la(s) vista(s) legacy del esquema de Speitour:
 *   - `v_equipo_ed_par`  → categorías PAREJAS
 *   - `v_equipo_ed`      → categorías INDIVIDUAL (mismo shape)
 *
 * Shape de la vista (1 fila por LADO, 2 filas por match):
 *   torneoid, categoriaid, matchx ('101','109','115','201',...),
 *   posicion, postabla, gano, hoyo, resultado,
 *   jugador, logojug, fecha, club, clubid, idelimin_salidas
 *
 * Detección de ganador: cuando `gano == postabla` ese lado es el winner.
 * D1 (MATCH-1 / DRAW-1) → matchx 1xx. D2 (MATCH-2 / DRAW-2) → matchx 2xx.
 */
require_once 'config.php';

$catid    = require_param('catid');
$torneoid = require_torneoid($conn);

$cid = esc($conn, $catid);
$tid = esc($conn, $torneoid);

// ----- Categoría (incluye tipoed si la columna existe) ----------------------
$tipoedExists = $conn->query("SHOW COLUMNS FROM categorias LIKE 'tipoed'");
$tipoedSel = ($tipoedExists && $tipoedExists->num_rows > 0) ? 'tipoed' : "NULL AS tipoed";

$catInfo = query_one(
    $conn,
    "SELECT categoria_id, categoria, abreviatura, sistema, formato, $tipoedSel, sexo
       FROM categorias WHERE categoria_id = $cid"
);
if (!$catInfo) { json_error('Category not found', 404); }

$isParejas = (strtoupper((string)($catInfo['formato'] ?? '')) === 'PAREJAS');

/**
 * Carga las filas crudas (1 por lado) desde la vista legacy correspondiente.
 * Si la vista preferida no existe (esquema viejo), prueba la otra como fallback
 * y si tampoco está devuelve [] para no romper la página.
 */
function load_sides($conn, $cid, $tid, $isParejas) {
    $candidates = $isParejas
        ? ['v_equipo_ed_par', 'v_equipo_ed']
        : ['v_equipo_ed', 'v_equipo_ed_par'];

    foreach ($candidates as $view) {
        // Comprueba existencia antes de hacer SELECT para evitar un 500.
        $chk = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($view) . "'");
        if (!$chk || $chk->num_rows === 0) continue;

        $sql = "SELECT idelimin_salidas, categoriaid, torneoid,
                       matchx, posicion, postabla, gano, hoyo, resultado,
                       jugador, logojug,
                       DATE_FORMAT(fecha, '%Y-%m-%d %H:%i') AS fecha,
                       club, clubid
                  FROM $view
                 WHERE torneoid = $tid AND categoriaid = $cid
                 ORDER BY matchx ASC, posicion ASC";
        $rows = query_all($conn, $sql);
        if ($rows !== null) return $rows;
    }
    return [];
}

$sides = load_sides($conn, $cid, $tid, $isParejas);

/**
 * Fallback para el match por 3er lugar (matchx = 199 / 299): las vistas
 * legacy `v_equipo_ed(_par)` pueden no proyectar filas fuera del rango
 * contiguo 1..N-1 del bracket. Aquí levantamos MANUALMENTE los "sides"
 * de esas filas directo de `elimin_salidas_cat` + `jugadores` para que
 * lleguen al frontend como cualquier otro match.
 */
function inject_third_place_sides($conn, $tid, $cid, $existingSides, $LOGOS_BASE_URL) {
    $tid = (int)$tid;
    $cid = (int)$cid;

    // Matchx ya presentes en la vista (no duplicar).
    $already = [];
    foreach ($existingSides as $r) $already[(int)$r['matchx']] = true;

    // Toma filas de 3er lugar (offset 99): 199 (D1), 299 (D2).
    $rows = query_all($conn,
        "SELECT idelimin_salidas, catid, matchx, jugida, jugidb, gano, hoyo,
                DATE_FORMAT(fecha, '%Y-%m-%d %H:%i') AS fecha
           FROM elimin_salidas_cat
          WHERE catid = $cid AND (matchx = 199 OR matchx = 299)");
    if (empty($rows)) return $existingSides;

    // Recolecta jugadores para armar nombre + club.
    $ids = [];
    foreach ($rows as $r) {
        if ((int)$r['jugida'] > 0) $ids[(int)$r['jugida']] = true;
        if ((int)$r['jugidb'] > 0) $ids[(int)$r['jugidb']] = true;
    }
    $jugById = [];
    if ($ids) {
        $idIn = implode(',', array_keys($ids));
        $jugs = query_all($conn,
            "SELECT j.id, j.posicion,
                    CONCAT(j.nombre,' ',j.apellido) AS jugador,
                    j.clubid, c.abr AS club, c.logo AS logojug
               FROM jugadores j
               LEFT JOIN clubs c ON c.id = j.clubid
              WHERE j.id IN ($idIn)");
        foreach ($jugs as $j) $jugById[(int)$j['id']] = $j;
    }

    $out = $existingSides;
    foreach ($rows as $r) {
        $mx = (int)$r['matchx'];
        if (!empty($already[$mx])) continue;
        // Emite 1 fila por lado, replicando el shape del view.
        foreach (['jugida' => 1, 'jugidb' => 2] as $col => $postabla) {
            $jid = (int)$r[$col];
            $j = $jid > 0 ? ($jugById[$jid] ?? null) : null;
            $out[] = [
                'idelimin_salidas' => $r['idelimin_salidas'],
                'categoriaid'      => $cid,
                'torneoid'         => $tid,
                'matchx'           => (string)$mx,
                'posicion'         => $j ? (int)$j['posicion'] : 0,
                'postabla'         => $postabla,
                'gano'             => $r['gano'],
                'hoyo'             => $r['hoyo'],
                'resultado'        => null,
                'jugador'          => $j ? $j['jugador'] : '',
                'logojug'          => $j ? ($j['logojug'] ?? '') : '',
                'fecha'            => $r['fecha'],
                'club'             => $j ? ($j['club'] ?? '') : '',
                'clubid'           => $j ? $j['clubid'] : null,
            ];
        }
    }
    return $out;
}

$sides = inject_third_place_sides($conn, $tid, $cid, $sides, $LOGOS_BASE_URL);

/**
 * Para cada matchx cargamos la fila CANÓNICA desde elimin_salidas_cat con
 * jugida/jugidb/gano. Luego mapeamos cada lado del view a su `lado` real
 * (1 = jugida, 2 = jugidb) usando la `posicion` del jugador.
 *
 * Esto es CRÍTICO porque el view ordena por `posicion ASC` y no por lado,
 * así que el orden de las filas no garantiza que pair[0]==jugida. Si el
 * front asume que sí, mandaba `side` invertido al API y se marcaba al
 * ganador equivocado.
 */
$mxSet = [];
foreach ($sides as $r) { $mxSet[(int)$r['matchx']] = true; }
$mxList = array_keys($mxSet);
$sideMap = []; // matchx => [posJugida, posJugidb]
$ganoMap = []; // matchx => gano (1|2|0)
$hoyoMap = []; // matchx => hoyo
$fechaMap = []; // matchx => fecha
if ($mxList) {
    $mxIn = implode(',', array_map('intval', $mxList));
    $canon = query_all(
        $conn,
        "SELECT matchx, jugida, jugidb, gano, hoyo,
                DATE_FORMAT(fecha, '%Y-%m-%d %H:%i') AS fecha
           FROM elimin_salidas_cat
          WHERE catid = $cid AND matchx IN ($mxIn)"
    );
    $ids = [];
    foreach ($canon as $c) {
        if ((int)$c['jugida'] > 0) $ids[(int)$c['jugida']] = true;
        if ((int)$c['jugidb'] > 0) $ids[(int)$c['jugidb']] = true;
    }
    $posOf = [];
    if ($ids) {
        $idIn = implode(',', array_keys($ids));
        $jugs = query_all($conn, "SELECT id, posicion FROM jugadores WHERE id IN ($idIn)");
        foreach ($jugs as $j) { $posOf[(int)$j['id']] = (int)$j['posicion']; }
    }
    foreach ($canon as $c) {
        $mx = (int)$c['matchx'];
        $sideMap[$mx] = [
            $posOf[(int)$c['jugida']] ?? null,
            $posOf[(int)$c['jugidb']] ?? null,
        ];
        $ganoMap[$mx]  = (int)$c['gano'];
        $hoyoMap[$mx]  = $c['hoyo'];
        $fechaMap[$mx] = $c['fecha'];
    }
}

// ----- Agrupa las filas por matchx → estructura player1/player2 -------------
/**
 * Convierte una fila de la vista en el objeto BracketPlayer que espera el front.
 * El nombre lleva la posición del seed al inicio (ej: "1 Alfredo Hauter | Allan Salinas").
 */
function side_to_player($row, $LOGOS_BASE_URL) {
    if (!$row) {
        return ['id' => null, 'name' => null, 'clubLogo' => '', 'club' => ''];
    }
    $name = trim((string)$row['jugador']);
    $pos  = (int)($row['posicion'] ?? 0);
    if ($pos > 0 && stripos($name, (string)$pos) !== 0) {
        $name = $pos . ' ' . $name;
    }
    $logo = $row['logojug'] ?? '';
    return [
        'id'       => $row['idelimin_salidas'] ?? null,
        'name'     => $name,
        'clubLogo' => $logo ? $LOGOS_BASE_URL . $logo : '',
        'club'     => $row['club'] ?? ''
    ];
}

$grouped = [];
foreach ($sides as $row) {
    $mx = (string)$row['matchx'];
    if (!isset($grouped[$mx])) $grouped[$mx] = [];
    $grouped[$mx][] = $row;
}

$d1 = [];
$d2 = [];
foreach ($grouped as $mx => $pair) {
    $mid = (int)$mx;
    // Asigna lado real usando la posición del jugador en elimin_salidas_cat.
    $map = $sideMap[$mid] ?? [null, null];
    $r1 = null; $r2 = null;
    foreach ($pair as $row) {
        $pos = (int)($row['posicion'] ?? 0);
        if ($map[0] !== null && $pos === $map[0] && $r1 === null) { $r1 = $row; continue; }
        if ($map[1] !== null && $pos === $map[1] && $r2 === null) { $r2 = $row; continue; }
    }
    // Fallback si no se pudo mapear: usa orden del view.
    if (!$r1 && !$r2) { $r1 = $pair[0] ?? null; $r2 = $pair[1] ?? null; }
    elseif (!$r1) { $r1 = ($pair[0] === $r2) ? ($pair[1] ?? null) : ($pair[0] ?? null); }
    elseif (!$r2) { $r2 = ($pair[0] === $r1) ? ($pair[1] ?? null) : ($pair[0] ?? null); }

    // Winner = gano (1|2) directo de elimin_salidas_cat.
    $g = $ganoMap[$mid] ?? 0;
    $winner = ($g === 1 || $g === 2) ? $g : null;

    $entry = [
        'matchId'  => $mid,
        'player1'  => side_to_player($r1, $LOGOS_BASE_URL),
        'player2'  => side_to_player($r2, $LOGOS_BASE_URL),
        'winner'   => $winner,
        'hole'     => $hoyoMap[$mid] ?? ($r1['hoyo'] ?? null),
        'result'   => $r1['resultado'] ?? null,
        'fecha'    => $fechaMap[$mid] ?? ($r1['fecha'] ?? null),
        'round'    => 0,
        'position' => 0,
    ];

    if ($mid >= 200) $d2[] = $entry;
    else             $d1[] = $entry;
}

// Orden estable por matchId.
usort($d1, fn($a, $b) => $a['matchId'] - $b['matchId']);
usort($d2, fn($a, $b) => $a['matchId'] - $b['matchId']);

json_response([
    'categoryId'   => $catInfo['categoria_id'],
    'categoryName' => $catInfo['categoria'],
    'shortName'    => $catInfo['abreviatura'],
    'system'       => $catInfo['sistema'],
    'format'       => $catInfo['formato'],
    'tipoed'       => $catInfo['tipoed'],
    'isParejas'    => $isParejas,
    // `matches` se mantiene por back-compat (todos los matches concatenados).
    'matches'      => array_merge($d1, $d2),
    'd1'           => $d1,
    'd2'           => $d2,
]);