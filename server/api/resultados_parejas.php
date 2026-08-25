<?php
/**
 * Resultados Parejas (Pairs) Endpoint
 * GET /api/resultados_parejas.php?catid=XXX&torneoid=XXX&gross=0|1
 * Returns pairs tournament results
 */
require_once 'config.php';

$catid    = require_param('catid');
$torneoid = require_torneoid($conn);
$gross    = optional_param('gross', '0');

$cid = esc($conn, $catid);
$tid = esc($conn, $torneoid);

// Category info
$sql = "SELECT categoria_id, categoria, abreviatura, sistema, formato, salida
        FROM categorias WHERE categoria_id = $cid";
$catInfo = query_one($conn, $sql);
if (!$catInfo) { json_error('Category not found', 404); }

/**
 * sistema controla qué funciones / tiebreakers usar.
 *  - STROKE PLAY → totales con f_torneosax / f_torneosox; ASC; countback NETO via u.c1..c5, GROSS via j.cd1..cd6.
 *  - STABLEFORD  → totales con f_torneosa (neto) / f_stl_gross (gross); DESC; mismos buckets pero DESC.
 */
$sistema = strtoupper(trim($catInfo['sistema']));
$isStroke = ($sistema === 'STROKE PLAY');

// Play dates
$sql = "SELECT fecha FROM caljuego
        WHERE categoriaid = $cid AND campo > 0 AND estatus > 1
        ORDER BY fecha";
$dateRows = query_all($conn, $sql);
$dias = [];
foreach ($dateRows as $i => $dr) { $dias[$i + 1] = $dr['fecha']; }

/**
 * Construye la query principal de parejas replicando legacy
 * (servicios/resultados_jug_parejas.php). Se elige rama según
 * (sistema, gross). Funciones SQL legacy:
 *   STROKE PLAY  neto  → total f_torneosax,  día f_score_dia_sax,  countback u.c1..c5 ASC
 *   STROKE PLAY  gross → total f_torneosox,  día f_score_dia_sox,  countback j.cd1..cd6 ASC
 *   STABLEFORD   neto  → total f_torneosa,   día f_score_dia_sax,  countback u.c1..c5 DESC
 *   STABLEFORD   gross → total f_stl_gross,  día f_score_stbl_gross, countback j.cd1..cd6 DESC
 */
function build_parejas_sql($cid, $sistema, $gross, array $dias, $estatusFilter) {
    $isStroke = ($sistema === 'STROKE PLAY');
    $diax = !empty($dias) ? reset($dias) : '';

    // --- columnas de día + total + función "score último día" para tiebreak ---
    // Replica literal de reportes_bootstrap/resultados_jug_parejas.php (legacy).
    if ($gross === '1') {
        if ($isStroke) {
            $totalExpr  = "f_torneosox(a.jugadorid, a.torneoid)";   // gross stroke
            $diaFn      = "f_score_dia_sox";
            $lastDayFn  = "f_score_dia_soxU";
            $cbDir      = "ASC";
        } else {
            $totalExpr  = "f_stl_gross(a.jugadorid, a.torneoid)";    // gross stableford
            $diaFn      = "f_score_stbl_gross";
            $lastDayFn  = "f_score_dia_satblU";
            $cbDir      = "DESC";
        }
        // Countback GROSS (legacy textual): cd1..cd5 sin COALESCE.
        $cbCols = "(j.cd1+j.cd2+j.cd3+j.cd4+j.cd5) $cbDir, (j.cd1+j.cd2+j.cd3+j.cd4) $cbDir, (j.cd1+j.cd2+j.cd3) $cbDir, j.cd1 $cbDir";
    } else {
        if ($isStroke) {
            $totalExpr  = "f_torneosax(a.jugadorid, a.torneoid)";    // neto stroke
            $diaFn      = "f_score_dia_sax";
            $lastDayFn  = "f_score_dia_saxU";
            $cbDir      = "ASC";
        } else {
            $totalExpr  = "f_torneosa(a.jugadorid, a.torneoid)";     // neto stableford
            $diaFn      = "f_score_dia_sax";
            $lastDayFn  = "f_score_dia_saxU";
            $cbDir      = "DESC";
        }
        // Countback NETO (legacy textual): c1..c5 vía u (v_cd_ulttar_sa).
        $cbCols = "(c1+c2+c3+c4+c5) $cbDir, (c1+c2+c3+c4) $cbDir, (c1+c2+c3) $cbDir, c1 $cbDir";
    }

    // Total tal cual lo retorna la función legacy (sin sintetizar suma de días).
    $resolvedTotalExpr = $totalExpr;

    // Totales SA/SO también se devuelven siempre para que el frontend pueda alternar.
    $sql = "SELECT a.jugadorid, j.numjugador, j2.grupoid AS gpo,
                   CONCAT(j.nombre, ' ', j.apellido) AS jugador,
                   CONCAT(j2.nombre, ' ', j2.apellido) AS jugador2,
                   j.estatus, j.muertesubita,
                   f_torneosax(a.jugadorid, a.torneoid) AS sa,
                   f_torneosox(a.jugadorid, a.torneoid) AS so,
                   $totalExpr AS total_main";
    foreach ($dias as $i => $fecha) {
        $sql .= ", $diaFn(a.jugadorid, '$fecha') AS d{$i}";
    }
    $sql .= ", j.cd1, j.cd2, j.cd3, j.cd4, j.cd5, j.cd6,
               b.abr, b.logo, b2.logo AS logo2,
               REPLACE(pareja, '|', ' / ') AS pareja
         FROM v_jugadores_parejas a
         LEFT JOIN v_cd_ulttar_sa u ON (a.jugadorid = u.jugadorid)
         JOIN jugadores j        ON (a.jugadorid  = j.id)
         JOIN jugadores j2       ON (a.jugadorid2 = j2.id)
         JOIN clubs b            ON (j.clubid  = b.id)
         JOIN clubs b2           ON (j2.clubid = b2.id)
         WHERE j.categoriaid = $cid
           AND f_torneoso(a.jugadorid, a.torneoid) > 0";

    // Filtro estatus: NORMAL (activos) vs <>'NORMAL' (cortados/abandono/desc).
    if ($estatusFilter === 'NORMAL') {
        $sql .= " AND j.estatus = 'NORMAL'";
    } else {
        $sql .= " AND j.estatus <> 'NORMAL'";
    }
    // En el sólo-neto excluimos quienes juegan exclusivamente gross.
    if ($gross !== '1') {
        $sql .= " AND j.campgross = 0";
    }

    // ORDER BY legacy completo: total → muerte súbita → último día → countback (→ score último día como cierre).
    $dir = $cbDir;
    $tieLastDay = $lastDayFn . "(a.jugadorid)";
    $sql .= " ORDER BY j.estatus DESC, $totalExpr $dir, j.muertesubita DESC, $tieLastDay $dir, $cbCols";
    if (!$isStroke || $gross === '1') {
        // legacy añade un cierre extra con score del día previo
        $sql .= ", $diaFn(a.jugadorid, '$diax') $dir";
    }
    return $sql;
}

// --- rama activos (NORMAL) ---
$sqlMain = build_parejas_sql($cid, $sistema, $gross, $dias, 'NORMAL');
$rows    = query_all($conn, $sqlMain);

// --- rama cortados / abandonos / descalificados ---
$sqlCut  = build_parejas_sql($cid, $sistema, $gross, $dias, 'CUT');
$cutRows = query_all($conn, $sqlCut);

$players = [];
$position = 0;
foreach ($rows as $row) {
    $position++;
    $player = [
        'position'  => $position,
        'playerId'  => $row['jugadorid'],
        'name'      => $row['jugador'],
        'partner'   => $row['jugador2'],
        'pairName'  => $row['pareja'],
        'club'      => $row['abr'],
        'clubLogo'  => $row['logo'] ? $LOGOS_BASE_URL . $row['logo'] : '',
        'clubLogo2' => $row['logo2'] ? $LOGOS_BASE_URL . $row['logo2'] : '',
        'total'     => (int)($row['total_main'] ?? 0),
        'totalSO'   => (int)($row['so'] ?? 0),
        'totalSA'   => (int)($row['sa'] ?? 0),
        /** grupoid manual del admin (p.e. 'C24'). Se muestra como "Grupo {gpo}". */
        'grupoid'   => $row['gpo'] ?? ''
    ];

    foreach ($dias as $i => $fecha) {
        $val = $row["d{$i}"] ?? null;
        $player["r{$i}"] = $val !== null && $val != 0 ? (int)$val : null;
    }
    $players[] = $player;
}

/**
 * cutPlayers replica la tabla inferior del legacy (C/A/D). Comparten estructura
 * con players pero llevan la inicial del estatus en lugar de posición numérica.
 */
$cutPlayers = [];
foreach ($cutRows as $row) {
    $cp = [
        'position'  => substr($row['estatus'] ?? '', 0, 1),  // C/A/D
        'playerId'  => $row['jugadorid'],
        'name'      => $row['jugador'],
        'partner'   => $row['jugador2'],
        'pairName'  => $row['pareja'],
        'club'      => $row['abr'],
        'clubLogo'  => $row['logo'] ? $LOGOS_BASE_URL . $row['logo'] : '',
        'clubLogo2' => $row['logo2'] ? $LOGOS_BASE_URL . $row['logo2'] : '',
        'total'     => (int)($row['total_main'] ?? 0),
        'totalSO'   => (int)($row['so'] ?? 0),
        'totalSA'   => (int)($row['sa'] ?? 0),
        'grupoid'   => $row['gpo'] ?? '',
        'status'    => $row['estatus'] ?? ''
    ];
    foreach ($dias as $i => $fecha) {
        $val = $row["d{$i}"] ?? null;
        $cp["r{$i}"] = $val !== null && $val != 0 ? (int)$val : null;
    }
    $cutPlayers[] = $cp;
}

json_response([
    'categoryId'   => $catInfo['categoria_id'],
    'categoryName' => $catInfo['categoria'],
    'shortName'    => $catInfo['abreviatura'],
    'system'       => $catInfo['sistema'],
    'format'       => 'PAREJAS',
    /** Bandera consumida por el frontend para activar layout/render de parejas */
    'isParejas'    => true,
    'gross'        => (int)$gross,
    'days'         => array_values($dias),
    'daysPartial'  => array_fill(0, count($dias), false),
    'medalCount'      => 3,
    'medalCountNeto'  => 3,
    'medalCountGross' => 1,
    'cutPlayers'   => $cutPlayers,
    'players'      => $players
]);
