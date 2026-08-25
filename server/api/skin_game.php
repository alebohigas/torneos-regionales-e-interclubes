<?php
/**
 * Skin Game Endpoint
 * GET /api/skin_game.php?torneoid=XXX&gpoid=XXX&fecha=YYYY-MM-DD&tipo=gross|neto
 *
 * Without gpoid/fecha: returns available dates and groups (master)
 * With gpoid+fecha+tipo: returns skin winners per hole (detail)
 */
require_once 'config.php';

$torneoid = require_torneoid($conn);
$gpoid    = optional_param('gpoid', null);
$fecha    = optional_param('fecha', null);
$tipo     = optional_param('tipo', 'gross');

$tid = esc($conn, $torneoid);

// ============= MASTER: dates and groups =============
if (!$gpoid || !$fecha) {
    $conn->query("SET lc_time_names = 'es_ES'");

    // Get dates with skin games
    $sql = "SELECT DISTINCT fecha,
                   DATE_FORMAT(fecha, '%W %M %e, %Y') as fechaFormato
            FROM categorias a
            JOIN caljuego c ON (a.categoria_id = c.categoriaid AND c.campo > 0 AND c.cierre = 1 AND c.skin = 1)
            WHERE a.torneo_id = $tid AND a.estatus = 1 AND a.Skin_grupo_id > 0
            ORDER BY fecha";
    $dateRows = query_all($conn, $sql);

    $days = [];
    foreach ($dateRows as $dr) {
        $fec = esc($conn, $dr['fecha']);

        // Get groups for this date + availability of gross/neto skins.
        // hasGross/hasNeto = 1 when any category in the group has a
        // non-empty resultmingross / resultminneto CSV (i.e. skin
        // winners were computed for that variant).
        $sql = "SELECT a.Skin_grupo_id AS gpoid,
                       MAX(CASE WHEN c.resultmingross IS NOT NULL AND c.resultmingross <> '' THEN 1 ELSE 0 END) AS has_gross,
                       MAX(CASE WHEN c.resultminneto  IS NOT NULL AND c.resultminneto  <> '' THEN 1 ELSE 0 END) AS has_neto
                FROM categorias a
                JOIN caljuego c ON (a.categoria_id = c.categoriaid AND c.campo > 0 AND c.cierre = 1 AND c.skin = 1)
                WHERE a.torneo_id = $tid AND a.estatus = 1 AND a.Skin_grupo_id > 0 AND c.fecha = '$fec'
                GROUP BY a.Skin_grupo_id
                ORDER BY a.Skin_grupo_id";
        $groupRows = query_all($conn, $sql);

        $groups = [];
        foreach ($groupRows as $gr) {
            $hg = (int)$gr['has_gross'] === 1;
            $hn = (int)$gr['has_neto']  === 1;
            // Skip groups with neither variant available
            if (!$hg && !$hn) continue;
            $groups[] = [
                'groupId'  => (int)$gr['gpoid'],
                'hasGross' => $hg,
                'hasNeto'  => $hn,
            ];
        }
        if (count($groups) === 0) continue;

        $days[] = [
            'date'          => $dr['fecha'],
            'dateFormatted' => $dr['fechaFormato'],
            'groups'        => $groups
        ];
    }

    json_response(['days' => $days]);
}

// ============= DETAIL: skin winners per hole =============
$gid = esc($conn, $gpoid);
$fec = esc($conn, $fecha);

$conn->query("SET lc_time_names = 'es_ES'");

// Format date for display
$sql = "SELECT DATE_FORMAT('$fec', '%W %M %e, %Y') as ffecha";
$dateInfo = query_one($conn, $sql);

$isGross = ($tipo === 'gross');
$skins = [];

for ($hoyo = 1; $hoyo <= 18; $hoyo++) {
    if ($isGross) {
        // GROSS: use f_mingross function
        $sql = "SELECT $hoyo as xxh, b.Skin_grupo_id,
                       MIN(h$hoyo) as minimo, COUNT(*) as tot, id_campo
                FROM Skeen_tarjetas a
                JOIN categorias b ON (b.categoria_id = a.categoriaid)
                WHERE a.torneoid = $tid AND Skin_grupo_id = $gid
                  AND h$hoyo = f_mingross($hoyo, '$fec', $gid, $tid)
                  AND a.fecha_juego = '$fec'
                GROUP BY b.Skin_grupo_id, id_campo";
    } else {
        // NETO: use subquery for min adjusted score
        $sql = "SELECT b.Skin_grupo_id,
                       MIN(h{$hoyo}_a) as minimo, COUNT(*) as tot, id_campo
                FROM Skeen_tarjetas a
                JOIN categorias b ON (b.categoria_id = a.categoriaid)
                WHERE a.torneoid = $tid AND Skin_grupo_id = $gid
                  AND h{$hoyo}_a = (
                      SELECT MIN(h{$hoyo}_a)
                      FROM Skeen_tarjetas sa
                      JOIN categorias sb ON (sb.categoria_id = sa.categoriaid)
                      WHERE sa.torneoid = $tid AND sb.Skin_grupo_id = $gid
                        AND sa.fecha_juego = '$fec'
                  )
                  AND a.fecha_juego = '$fec'
                GROUP BY b.Skin_grupo_id, id_campo";
    }

    $holeResult = query_one($conn, $sql);

    $skin = [
        'hole'   => $hoyo,
        'winner' => null
    ];

    // Only award skin if exactly one player has the minimum
    if ($holeResult && (int)$holeResult['tot'] === 1) {
        $minimo = esc($conn, $holeResult['minimo']);
        $scoreCol = $isGross ? "h$hoyo" : "h{$hoyo}_a";

        $sql = "SELECT $scoreCol as score, j.*,
                       c.logo, c.nombre as club, b.abreviatura as categoria
                FROM Skeen_tarjetas a
                JOIN categorias b ON (b.categoria_id = a.categoriaid)
                JOIN jugadores j ON (a.jugadorid = j.id)
                JOIN clubs c ON (j.clubid = c.id)
                WHERE a.torneoid = $tid AND Skin_grupo_id = $gid
                  AND $scoreCol = $minimo AND fecha_juego = '$fec'";

        $winner = query_one($conn, $sql);
        if ($winner) {
            $skin['winner'] = [
                'name'     => trim($winner['nombre'] . ' ' . $winner['apellido']),
                'club'     => $winner['club'],
                'clubLogo' => $winner['logo'] ? $LOGOS_BASE_URL . $winner['logo'] : '',
                'category' => $winner['categoria'],
                'score'    => (int)$winner['score']
            ];
        }
    }

    $skins[] = $skin;
}

json_response([
    'groupId'       => (int)$gpoid,
    'date'          => $fecha,
    'dateFormatted' => $dateInfo['ffecha'] ?? $fecha,
    'type'          => $tipo,
    'skins'         => $skins
]);
