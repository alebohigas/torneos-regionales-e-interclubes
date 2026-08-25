<?php
/**
 * Players Endpoint
 * GET /api/players.php?torneoid=XXX&catid=XXX
 * Returns players for a specific category
 * Uses: jugadores table joined with clubs for logo
 */
require_once 'config.php';

$torneoid = require_torneoid($conn);
$catid = require_param('catid');
$cid = esc($conn, $catid);
$tid = esc($conn, $torneoid);

/**
 * Optional `?skin=1` flag — restrict results to players enrolled in the
 * SKIN GAME (jugadores.Skeenjuga = 1). Used by the /skinplayers page.
 */
$skinOnly = isset($_GET['skin']) && $_GET['skin'] === '1';
$skinPlayerFilter = $skinOnly ? " AND p.Skeenjuga = 1 " : '';
/**
 * Para /skinplayers el HN se calcula con `categorias.Skeenporcent` (porcentaje
 * específico del Skin Game) en lugar del porcentaje regular de la categoría.
 */
$pctColumn = $skinOnly ? 'cat.skeenporcent' : 'cat.porcentaje';

/**
 * Detectar si la categoría es de parejas (formato='PAREJAS'). El frontend lo
 * usa para agrupar jugadores por grupoid (cada grupo = una pareja).
 */
$catInfoRow = query_one($conn, "SELECT formato FROM categorias WHERE categoria_id = $cid LIMIT 1");
$isParejas = $catInfoRow && strtoupper($catInfoRow['formato'] ?? '') === 'PAREJAS';

/** Query: fetch players with club logo and calculated handicaps using DB functions */
$sql = "SELECT p.id, p.numjugador,
               CONCAT(p.nombre, ' ', p.apellido) as jugador,
               c.logo, p.indexjgo as hi,
               f_hdccampo(p.indexjgo, p.teesalidaid, cat.campoid) as hj,
               f_hdccamponeto(p.indexjgo, p.teesalidaid, cat.campoid, $pctColumn) as hn,
               p.club, p.sexo, p.estatus, p.equipo, p.grupoid
        FROM jugadores p
        LEFT JOIN clubs c ON (p.clubid = c.id)
        LEFT JOIN (
            SELECT cat.categoria_id, cj.campo as campoid, cat.porcentaje, cat.Skeenporcent as skeenporcent
            FROM categorias cat
            JOIN caljuego cj ON (cat.categoria_id = cj.categoriaid)
            WHERE cat.categoria_id = '$cid' and campo>0
            LIMIT 1
        ) cat ON (p.categoriaid = cat.categoria_id)
        WHERE p.categoriaid = '$cid' AND p.torneoid = $tid $skinPlayerFilter
        ORDER BY p.apellido, p.nombre ASC";

$result = $conn->query($sql);
if (!$result) {
    json_error('Query failed: ' . $conn->error);
}

$players = [];
/**
 * fechaHandicap: Tournament-wide handicap effective date.
 * Source: categorias.fechaHandicap (PK: categoria_id), looked up by the
 * current $catid. Per requirement, the handicap effective date is now stored
 * per category instead of at the tournament level (torneo.fecha_hand) or
 * per player (jugadores.fechahandicap).
 */
$fechaHandicap = '';
while ($row = $result->fetch_assoc()) {
    $players[] = [
        'id'         => $row['id'],
        'numjugador' => $row['numjugador'] ?? '',
        'jugador'    => $row['jugador'],
        'logo'       => $row['logo'] ? $LOGOS_BASE_URL . $row['logo'] : '',
        'hi'         => $row['hi'] ?? '0',
        'hj'         => $row['hj'] ?? '0',
        'hn'         => $row['hn'] ?? '0',
        'club'       => $row['club'] ?? '',
        'sexo'       => $row['sexo'] ?? '',
        'estatus'    => $row['estatus'] ?? 'NORMAL',
        /** grupoid: agrupador de parejas (ej. "C24"). El frontend usa este
         *  campo cuando isParejas=true para mostrar "Grupo C24". */
        'grupoid'    => $row['grupoid'] ?? ''
    ];
}
$result->free();

/**
 * Fetch fechaHandicap from categorias table (PK: categoria_id).
 * Uses escaped category id; safely returns empty string if not found,
 * if the value is empty, or if it equals known placeholder dates
 * ('0000-00-00' or the legacy default '1900-01-01').
 */
$catSql = "SELECT fechaHandicap FROM categorias WHERE categoria_id = '$cid' LIMIT 1";
$catRes = $conn->query($catSql);
if ($catRes) {
    if ($catRow = $catRes->fetch_assoc()) {
        $val = $catRow['fechaHandicap'] ?? '';
        if (!empty($val) && $val !== '0000-00-00' && $val !== '1900-01-01') {
            $fechaHandicap = $val;
        }
    }
    $catRes->free();
}

json_response([
    'players'       => $players,
    'fechaHandicap' => $fechaHandicap,
    'isParejas'     => $isParejas,
]);