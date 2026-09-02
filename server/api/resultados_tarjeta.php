<?php
/**
 * Resultados Tarjeta del Jugador (Player Scorecard) Endpoint
 * GET /api/resultados_tarjeta.php?jugadorid=XXX&categoriaid=XXX&fecha=YYYY-MM-DD&tipo=stroke|stableford
 * Returns hole-by-hole scorecard for a player
 * Supports: Stroke (with/without HCP), Stableford, Parejas
 */
require_once 'config.php';

$jugadorid   = require_param('jugadorid');
$categoriaid = require_param('categoriaid');
$fecha       = optional_param('fecha', '0');
$tipo        = optional_param('tipo', 'stroke'); // stroke, stableford, parejas

$jid = esc($conn, $jugadorid);
$cid = esc($conn, $categoriaid);
$fec = esc($conn, $fecha);

// ============= Category info =============
$sql = "SELECT categoria_id, categoria, sistema, formato, estilo, porcentaje, salida, torneo_id
        FROM categorias WHERE categoria_id = $cid";
$catInfo = query_one($conn, $sql);
if (!$catInfo) { json_error('Category not found', 404); }

$formato = strtoupper($catInfo['formato']);
$torneoid = $catInfo['torneo_id'];

// ============= Detección de esquema (golftour legacy vs. nuevo) =============
/**
 * En la base `golftour` las vistas/tablas usan `id_campo` / `salida`
 * (ver tarjeta_jugador.php legacy), mientras que el esquema nuevo usa
 * `campoid` / `salidaid`. Se resuelven aquí para no duplicar el endpoint.
 */
$vsjTable   = ($formato === 'PAREJAS') ? 'v_sal_jug_par' : 'v_sal_jug';
$vsjCampo   = api_column_exists($conn, $vsjTable, 'campoid') ? 'campoid' : 'id_campo';
$hxsCampo   = api_column_exists($conn, 'hoyosxsalida', 'campoid') ? 'campoid' : 'id_campo';
$hxsSalida  = api_column_exists($conn, 'hoyosxsalida', 'salidaid') ? 'salidaid' : 'salida';
/** Columna con las ventajas por hoyo (CSV). Ausente en el esquema legacy. */
$hasVentajasJug = api_column_exists($conn, $vsjTable, 'ventajasjug');
/**
 * `golftour` no expone arso/arsa/arsap en v_sal_jug ni fec_ult_act en tarjetas.
 * Se detectan en runtime y se sustituyen por NULL / fallbacks para no romper
 * el endpoint con "Unknown column".
 */
$hasAr        = api_column_exists($conn, $vsjTable, 'arso');
$arSel        = $hasAr ? 'a.arso, a.arsa, a.arsap' : 'NULL as arso, NULL as arsa, NULL as arsap';
/** Ventajas por hoyo: v_sal_jug.ventajasjug (nuevo) o tarjetas.ventajas (golftour). */
$vtjExpr = $hasVentajasJug
    ? 'a.ventajasjug'
    : (api_column_exists($conn, 'tarjetas', 'ventajas') ? 'c.ventajas' : 'NULL');

// ============= Player + card data =============
if ($formato === 'PAREJAS') {
    $sql = "SELECT a.*, b.campo, c.*,
                   DATE_FORMAT(a.horainicio1a, '%w') as diajgo,
                   $arSel,
                   (c.so - c.sa) as handicapneto,
                   $vtjExpr as arvtj
            FROM v_sal_jug_par a
            JOIN campos b ON (a.`$vsjCampo` = b.id)
            JOIN tarjetas c ON (a.tarjetaid = c.id)
            WHERE a.jugadorid = $jid AND a.categoriaid = $cid";
    if ($fecha !== '0') { $sql .= " AND a.fecha_juego = '$fec'"; }
} else {
    $sql = "SELECT a.*, b.campo, c.*,
                   DATE_FORMAT(a.horainicio1a, '%w') as diajgo,
                   $arSel,
                   $vtjExpr as arvtj
            FROM v_sal_jug a
            JOIN campos b ON (a.`$vsjCampo` = b.id)
            JOIN tarjetas c ON (a.tarjetaid = c.id)
            WHERE a.jugadorid = $jid AND a.categoriaid = $cid";
    if ($fecha !== '0') { $sql .= " AND a.fecha_juego = '$fec'"; }
    $sql .= " ORDER BY a.fecha_juego, a.apellido, a.nombre";
}

debug_log_query('Player + card data (' . $formato . ')', $sql);
$playerData = query_one($conn, $sql);
if (!$playerData) { json_error('Player card not found', 404); }

// ============= Score adjusted (SA) per hole =============
$saTable = ($formato === 'PAREJAS') ? 'v_sal_jug_par' : 'v_sal_jug';
$sql = "SELECT c.h1_a, c.h2_a, c.h3_a, c.h4_a, c.h5_a, c.h6_a, c.h7_a, c.h8_a, c.h9_a,
               c.h10_a, c.h11_a, c.h12_a, c.h13_a, c.h14_a, c.h15_a, c.h16_a, c.h17_a, c.h18_a,
               c.SO, c.SA, $arSel,
               (c.so - c.sa) as handicapneto,
               $vtjExpr as arvtj
        FROM $saTable a
        JOIN campos b ON (a.`$vsjCampo` = b.id)
        JOIN tarjetas c ON (a.tarjetaid = c.id)
        WHERE a.jugadorid = $jid AND a.categoriaid = $cid";
if ($fecha !== '0') { $sql .= " AND a.fecha_juego = '$fec'"; }


debug_log_query('Score adjusted (SA) per hole', $sql);
$scoreData = query_one($conn, $sql);

// ============= Hole info (par + ventaja per hole) =============
$campoid  = $playerData['campoid'] ?? $playerData['id_campo'] ?? 0;
/** El tee real de la tarjeta manda; si la vista no lo trae usamos el de la categoría. */
$salidaid = $playerData['tee_salida'] ?? $playerData['teesalidaid'] ?? $catInfo['salida'];

$sql = "SELECT numero, par, `$hxsCampo` AS campoid, `$hxsSalida` AS salidaid, ventaja, yardaje
        FROM hoyosxsalida
        WHERE `$hxsCampo` = " . esc($conn, $campoid) . "
          AND `$hxsSalida` = " . esc($conn, $salidaid) . "
        ORDER BY numero ASC";

debug_log_query('Hole info (par + ventaja)', $sql);
$holeRows = query_all($conn, $sql);

// ============= Stableford values table =============
$stablefordValues = [];
if (strtoupper($catInfo['sistema']) === 'STABLEFORD') {
    $sql = "SELECT * FROM valorstable WHERE torneoid = " . esc($conn, $torneoid);
    $stablefordValues = query_all($conn, $sql);
}

// ============= Build response =============

// Parse ventajas (handicap strokes per hole) from CSV string
$ventajas = [];
if (isset($scoreData['arvtj']) && $scoreData['arvtj']) {
    $ventajas = array_map('intval', explode(',', $scoreData['arvtj']));
}

// Build holes array
$holes = [];
for ($h = 1; $h <= 18; $h++) {
    $holeInfo = null;
    foreach ($holeRows as $hr) {
        if ((int)$hr['numero'] === $h) { $holeInfo = $hr; break; }
    }

    $scoreSO = $playerData["h{$h}"] ?? null;   // Score Original
    $scoreSA = $scoreData["h{$h}_a"] ?? null;    // Score Adjusted

    $holes[] = [
        'hole'     => $h,
        'par'      => $holeInfo ? (int)$holeInfo['par'] : null,
        'ventaja'  => $holeInfo ? (int)$holeInfo['ventaja'] : null,
        'yardaje'  => $holeInfo ? (int)$holeInfo['yardaje'] : null,
        'scoreSO'  => $scoreSO !== null ? (int)$scoreSO : null,
        'scoreSA'  => $scoreSA !== null ? (int)$scoreSA : null,
        'hcpStrokes' => isset($ventajas[$h - 1]) ? $ventajas[$h - 1] : 0
    ];
}

// Front nine and back nine totals
$outSO = 0; $inSO = 0; $outSA = 0; $inSA = 0; $outPar = 0; $inPar = 0;
foreach ($holes as $h) {
    if ($h['hole'] <= 9) {
        $outSO += $h['scoreSO'] ?? 0;
        $outSA += $h['scoreSA'] ?? 0;
        $outPar += $h['par'] ?? 0;
    } else {
        $inSO += $h['scoreSO'] ?? 0;
        $inSA += $h['scoreSA'] ?? 0;
        $inPar += $h['par'] ?? 0;
    }
}

json_response([
    'player' => [
        'id'       => $playerData['jugadorid'],
        'number'   => $playerData['numjugador'] ?? '',
        'name'     => trim(($playerData['nombre'] ?? '') . ' ' . ($playerData['apellido'] ?? '')),
        'club'     => $playerData['club'] ?? '',
        'tee'      => $playerData['tee'] ?? '',
        'teeSal'   => $playerData['teesal'] ?? '',
        'time'     => $playerData['horainicio1a'] ?? '',
        'hcpIndex' => $playerData['indexjgo'] ?? '',
        'slope'    => $playerData['slope'] ?? '',
        'course'   => $playerData['campo'] ?? ''
    ],
    'category' => [
        'id'     => $catInfo['categoria_id'],
        'name'   => $catInfo['categoria'],
        'system' => $catInfo['sistema'],
        'format' => $catInfo['formato']
    ],
    'date'    => $fecha,
    /**
     * Last capture timestamp shown as "Fecha de captura".
     * Source of truth: tarjetas.fec_ult_act (fecha de registro real en la BD).
     * `fecha_cap` queda solo como respaldo si fec_ult_act viene vacio.
     */
    'fechaCap' => (!empty($playerData['fec_ult_act']) ? $playerData['fec_ult_act'] : ($playerData['fecha_cap'] ?? null)),
    'totals'  => [
        'SO'     => (int)($scoreData['SO'] ?? 0),
        'SA'     => (int)($scoreData['SA'] ?? 0),
        'outSO'  => $outSO,
        'inSO'   => $inSO,
        'outSA'  => $outSA,
        'inSA'   => $inSA,
        'outPar' => $outPar,
        'inPar'  => $inPar,
        'par'    => $outPar + $inPar
    ],
    'holes'           => $holes,
    'stablefordValues' => $stablefordValues
]);
