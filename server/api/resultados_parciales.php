<?php
/**
 * Resultados Parciales (Partial Results) Endpoint
 * GET /api/resultados_parciales.php?catid=XXX&torneoid=XXX&gross=0|1
 * Same logic as resultados_jug but hides days not yet played (returns null for score=0)
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

$sistema = strtoupper($catInfo['sistema']);

// Play dates (ALL dates, including not yet played)
$sql = "SELECT fecha FROM caljuego
        WHERE categoriaid = $cid AND campo > 0
        ORDER BY fecha";
$dateRows = query_all($conn, $sql);
$dias = [];
foreach ($dateRows as $i => $dr) { $dias[$i + 1] = $dr['fecha']; }

// Build query - same as resultados_jug but using IF to hide zero scores
if ($sistema === 'STABLEFORD') {
    $scoreFunc = $gross == '1' ? 'f_stl_gross' : 'f_torneosa';
    $orderDir  = 'DESC';
} else {
    $scoreFunc = $gross == '1' ? 'f_torneosox' : 'f_torneosax';
    $orderDir  = 'ASC';
}

$dayScoreFunc = $gross == '1' ? 'f_score_dia_sox' : 'f_score_dia_sax';

$sql = "SELECT a.jugadorid, j.numjugador,
               CONCAT(j.nombre, ' ', j.apellido) as jugador, j.estatus,
               $scoreFunc(a.jugadorid, a.torneoid) as sa,
               f_torneosox(a.jugadorid, a.torneoid) as so";

foreach ($dias as $i => $fecha) {
    $sql .= ", IF($dayScoreFunc(a.jugadorid, '$fecha') = 0, NULL, $dayScoreFunc(a.jugadorid, '$fecha')) as d{$i}";
}

$sql .= ", b.abr, b.logo
         FROM v_jugadores a
         JOIN jugadores j ON (a.jugadorid = j.id)
         JOIN clubs b ON (j.clubid = b.id)
         WHERE j.categoriaid = $cid
           AND j.estatus = 'NORMAL'";

if ($gross != '1') {
    $sql .= " AND j.campgross = 0";
}

$sql .= " AND $scoreFunc(a.jugadorid, a.torneoid) > 0
          ORDER BY $scoreFunc(a.jugadorid, a.torneoid) $orderDir";

$rows = query_all($conn, $sql);

$players = [];
$position = 0;
foreach ($rows as $row) {
    $position++;
    $player = [
        'position'  => $position,
        'playerId'  => $row['jugadorid'],
        'number'    => $row['numjugador'],
        'name'      => $row['jugador'],
        'club'      => $row['abr'],
        'clubLogo'  => $row['logo'] ? $LOGOS_BASE_URL . $row['logo'] : '',
        'total'     => (int)$row['sa'],
        'totalSO'   => (int)($row['so'] ?? 0)
    ];

    foreach ($dias as $i => $fecha) {
        $player["r{$i}"] = $row["d{$i}"] !== null ? (int)$row["d{$i}"] : null;
    }
    $players[] = $player;
}

json_response([
    'categoryId'   => $catInfo['categoria_id'],
    'categoryName' => $catInfo['categoria'],
    'shortName'    => $catInfo['abreviatura'],
    'system'       => $catInfo['sistema'],
    'format'       => $catInfo['formato'],
    'gross'        => (int)$gross,
    'partial'      => true,
    'days'         => array_values($dias),
    'players'      => $players
]);
