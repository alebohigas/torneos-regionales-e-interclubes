<?php
/**
 * Resultados Jugadores — GOLFTOUR (legacy gira schema)
 *
 * Este archivo NO se llama directamente: `resultados_jug.php` lo incluye
 * cuando detecta que la base activa es del esquema `golftour`, es decir
 * cuando existen las funciones/vistas legacy:
 *   - f_score_dia(jugadorid, fecha)     → score por ronda
 *   - f_torneoso(jugadorid, torneoid)   → total acumulado del torneo
 *   - v_cd_ulttar (c1..c5)              → desempate por retrogresión
 * y `campo_tee` usa las columnas `id_campo` / `id_tee`.
 *
 * Reproduce exactamente los queries del reporte legacy de /resultados:
 *   1. Categoría   : categorias JOIN jugadores (estatus>0, categoria_id=$catid)
 *   2. Torneo      : torneo (tiposalida)
 *   3. Campo/Tee   : caljuego JOIN campo_tee (id_campo/id_tee) JOIN salidas
 *   4. Días        : caljuego (campo>0 [+ estatus>1 si tiposalida<>1])
 *   5. Jugadores   : jugadores JOIN clubs JOIN v_cd_ulttar (estatus='NORMAL')
 *   6. Bajo corte  : jugadores JOIN clubs LEFT JOIN v_cd_ulttar (estatus<>'NORMAL')
 *
 * Devuelve el mismo shape JSON que `resultados_jug.php` para que el frontend
 * (`useResultadosData` / `Resultados.tsx`) lo consuma sin cambios.
 *
 * Variables ya definidas por el archivo que incluye: $conn, $catid, $torneoid,
 * $gross, $cid, $tid, $catInfo, $LOGOS_BASE_URL, y los helpers mapEstatus() /
 * statusLabel().
 */

/** Columnas reales de campo_tee en esta base (nuevas vs. legacy). */
$ctCampoCol = gira_column_exists($conn, 'campo_tee', 'id_campo') ? 'id_campo' : 'campoid';
$ctTeeCol   = gira_column_exists($conn, 'campo_tee', 'id_tee')   ? 'id_tee'   : 'salidaid';

$sistema  = strtoupper($catInfo['sistema'] ?? '');
$formato  = strtoupper($catInfo['formato'] ?? '');
$salidaId = esc($conn, $catInfo['salida'] ?? '0');

/** Medallas por tipo de scoring (con defaults si las columnas no existen). */
$medalCountNeto  = (int)($catInfo['numganadorneto'] ?? 3);
$medalCountGross = (int)($catInfo['numganadorgross'] ?? 1);
$medalCount = ($gross == '1') ? $medalCountGross : $medalCountNeto;

// ============= 2. Torneo (tiposalida) =============
/**
 * tiposalida = 1 → el torneo publica todas las rondas calendarizadas
 * (sin exigir caljuego.estatus > 1). Cualquier otro valor sólo publica
 * rondas ya liberadas (estatus > 1), igual que el reporte legacy.
 */
$tiposaltor = 0;
if (gira_column_exists($conn, 'torneo', 'tiposalida')) {
    $torRow = query_one($conn, "SELECT tiposalida FROM torneo WHERE torneo_id = $tid LIMIT 1");
    $tiposaltor = (int)($torRow['tiposalida'] ?? 0);
}

// ============= 3. Campo / Tee (rating, slope, par) =============
$sql = "SELECT b.`$ctCampoCol` AS campoid, b.`$ctTeeCol` AS salidaid,
               b.rating, b.slope, s.tee, b.parcampo
        FROM caljuego a
        JOIN campo_tee b ON (a.campo = b.`$ctCampoCol`
                             AND a.categoriaid = $cid
                             AND b.`$ctTeeCol` = $salidaId)
        JOIN salidas s ON (b.`$ctTeeCol` = s.id)
        LIMIT 1";
$courseInfo = query_one($conn, $sql);
debug_log_query('GIRA course info', $sql);
$parcampo = (int)($courseInfo['parcampo'] ?? 72);

// ============= 4. Días de juego =============
/** Días con score (los que alimentan el total y las columnas R1..Rn). */
$diasSql = "SELECT fecha FROM caljuego
            WHERE categoriaid = $cid AND campo > 0"
         . ($tiposaltor === 1 ? '' : ' AND estatus > 1')
         . " ORDER BY fecha";
$diasRows = query_all($conn, $diasSql);
debug_log_query('GIRA rondas con score', $diasSql);

/** Días del encabezado (todas las rondas calendarizadas con campo). */
$encaRows = query_all($conn, "SELECT fecha FROM caljuego
                              WHERE categoriaid = $cid AND campo > 0
                              ORDER BY fecha");

$dias = [];        // 1-indexed: ronda => fecha (con score disponible)
$diasPartial = []; // 1-indexed: ronda => true cuando aún no hay score publicado
$scoreDates = [];
foreach ($diasRows as $r) { $scoreDates[] = $r['fecha']; }

$idx = 0;
foreach ($encaRows as $r) {
    $idx++;
    $dias[$idx] = $r['fecha'];
    // Ronda calendarizada pero todavía no liberada → columna sin score ("—")
    $diasPartial[$idx] = !in_array($r['fecha'], $scoreDates, true);
}
// Sin rondas en el encabezado: caemos a las rondas con score (defensivo).
if (empty($dias)) {
    $idx = 0;
    foreach ($scoreDates as $f) { $idx++; $dias[$idx] = $f; $diasPartial[$idx] = false; }
}

/** Última ronda con score — usada como criterio de desempate legacy ($diax). */
$diax = !empty($scoreDates) ? end($scoreDates) : '';

// ============= Expresiones de score =============
/**
 * Score por ronda: f_score_dia(jugadorid, fecha). Las rondas sin liberar
 * devuelven NULL para que el frontend pinte "—".
 */
function gira_day_expr($conn, $fecha, $partial) {
    if ($partial) return 'NULL';
    $f = esc($conn, $fecha);
    return "f_score_dia(j.id, '$f')";
}

/** Total acumulado del torneo: f_torneoso(jugadorid, torneoid). */
$totalExpr = "f_torneoso(j.id, j.torneoid)";

/** Retrogresión legacy 9-6-3-1 sobre v_cd_ulttar (c1..c5). */
$dirCountback = ($sistema === 'STABLEFORD') ? 'DESC' : 'ASC';
$countback = ", (u.c1 + u.c2 + u.c3 + u.c4 + u.c5) $dirCountback"
           . ", (u.c1 + u.c2 + u.c3 + u.c4) $dirCountback"
           . ", (u.c1 + u.c2 + u.c3) $dirCountback"
           . ", u.c1 $dirCountback";

/** Dirección del total: Stableford gana con más puntos. */
$dirTotal = ($sistema === 'STABLEFORD') ? 'DESC' : 'ASC';

// ============= 5. Jugadores NORMAL (arriba del corte) =============
$dayCols = '';
foreach ($dias as $i => $fecha) {
    $dayCols .= ', ' . gira_day_expr($conn, $fecha, !empty($diasPartial[$i])) . " as d{$i}";
}

$sql = "SELECT j.id AS jugadorid, j.numjugador,
               CONCAT(j.nombre, ' ', j.apellido) as jugador, j.estatus,
               $totalExpr as sa,
               IFNULL(j.muertesubita, 0) as muertesubita
               $dayCols,
               c.abr, c.logo
        FROM jugadores j
        JOIN clubs c ON (j.clubid = c.id)
        JOIN v_cd_ulttar u ON (j.id = u.jugadorid)
        WHERE j.categoriaid = $cid
          AND $totalExpr > 0
          AND j.estatus = 'NORMAL'
        ORDER BY $totalExpr $dirTotal, IFNULL(j.muertesubita, 0) DESC"
        . ($diax !== '' ? ", f_score_dia(j.id, '" . esc($conn, $diax) . "') $dirTotal" : '')
        . $countback;

debug_log_query('GIRA leaderboard', $sql);
$rows = query_all($conn, $sql);

$players = [];
$position = 0;
foreach ($rows as $row) {
    $position++;
    $player = [
        'position'     => $position,
        'playerId'     => $row['jugadorid'],
        'number'       => $row['numjugador'],
        'name'         => $row['jugador'],
        'club'         => $row['abr'] ?? '',
        'clubLogo'     => $row['logo'] ? $LOGOS_BASE_URL . $row['logo'] : '',
        'total'        => (int)$row['sa'],
        'totalSA'      => (int)$row['sa'],
        'totalSO'      => (int)$row['sa'],
        'closedRounds' => 0,
    ];
    $closed = 0;
    foreach ($dias as $i => $fecha) {
        $val = $row["d{$i}"] ?? null;
        $player["r{$i}"] = ($val !== null && $val != 0) ? (int)$val : null;
        if ($player["r{$i}"] !== null) $closed++;
    }
    $player['closedRounds'] = $closed;
    $players[] = $player;
}

// ============= 6. Jugadores no NORMAL (bajo el corte) =============
$cutSql = "SELECT j.id AS jugadorid, j.numjugador,
                  CONCAT(j.nombre, ' ', j.apellido) as jugador, j.estatus,
                  $totalExpr as total_score
                  $dayCols,
                  c.abr, c.logo
           FROM jugadores j
           JOIN clubs c ON (j.clubid = c.id)
           LEFT JOIN v_cd_ulttar u ON (j.id = u.jugadorid)
           WHERE j.categoriaid = $cid
             AND j.estatus <> 'NORMAL'
           ORDER BY j.estatus DESC, IFNULL(j.muertesubita, 0) DESC"
           . $countback;

debug_log_query('GIRA cut players', $cutSql);
$cutRows = query_all($conn, $cutSql);

$cutPlayers = [];
foreach ($cutRows as $row) {
    $statusCode = mapEstatus($row['estatus']);
    $cutRounds = [];
    $closed = 0;
    foreach ($dias as $i => $fecha) {
        $val = $row["d{$i}"] ?? null;
        $cutRounds["r{$i}"] = ($val !== null && $val != 0) ? (int)$val : null;
        if ($cutRounds["r{$i}"] !== null) $closed++;
    }
    $cutPlayers[] = array_merge([
        'playerId'     => $row['jugadorid'],
        'number'       => $row['numjugador'],
        'name'         => $row['jugador'],
        'club'         => $row['abr'] ?? '',
        'clubLogo'     => $row['logo'] ? $LOGOS_BASE_URL . $row['logo'] : '',
        'statusCode'   => $statusCode ?? 'D',
        'statusLabel'  => statusLabel($statusCode ?? 'D'),
        'total'        => (int)($row['total_score'] ?? 0),
        'closedRounds' => $closed,
    ], $cutRounds);
}

/** Bajo el corte se ordenan por Total; sin score al final. */
usort($cutPlayers, function ($a, $b) use ($sistema) {
    $ta = (int)($a['total'] ?? 0);
    $tb = (int)($b['total'] ?? 0);
    $ea = ($ta === 0) ? 1 : 0;
    $eb = ($tb === 0) ? 1 : 0;
    if ($ea !== $eb) return $ea - $eb;
    if ($ea === 1 || $ta === $tb) return strcmp($a['name'], $b['name']);
    return ($sistema === 'STABLEFORD') ? ($tb - $ta) : ($ta - $tb);
});

json_response([
    'categoryId'      => $catInfo['categoria_id'],
    'categoryName'    => $catInfo['categoria'],
    'shortName'       => $catInfo['abreviatura'] ?? $catInfo['categoria'],
    'system'          => $catInfo['sistema'],
    'format'          => $catInfo['formato'],
    'gross'           => (int)$gross,
    'medalCount'      => $medalCount,
    'medalCountNeto'  => $medalCountNeto,
    'medalCountGross' => $medalCountGross,
    'course'          => $courseInfo ? [
        'rating' => (float)($courseInfo['rating'] ?? 0),
        'slope'  => (int)($courseInfo['slope'] ?? 0),
        'tee'    => $courseInfo['tee'] ?? '',
        'par'    => $parcampo,
    ] : null,
    'days'            => array_values($dias),
    'daysPartial'     => array_values($diasPartial),
    'players'         => $players,
    'cutPlayers'      => $cutPlayers,
]);
