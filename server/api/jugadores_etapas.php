<?php
/**
 * Jugadores por Etapa (Gira)
 * GET /api/jugadores_etapas.php?giraid=XX
 *
 * Una GIRA agrupa varios torneos (`torneo.giraid`). Cada torneo es una ETAPA
 * de la gira: la etapa 1 corresponde al torneo_id más bajo, la etapa 2 al
 * siguiente, y así sucesivamente.
 *
 * Devuelve solo las etapas que YA tienen jugadores inscritos, replicando el
 * conteo legacy de jugadores.php:
 *
 *   SELECT a.torneo_id, count(*) as tot
 *     FROM categorias a JOIN jugadores b ON (a.categoria_id = b.categoriaid)
 *    WHERE a.estatus > 0 AND a.torneo_id = $torneoid
 *    GROUP BY a.torneo_id
 *
 * Respuesta: { giraid, etapas: [{ etapa, torneoid, name, club, startDate,
 *              endDate, status, playerCount }] }
 */
require_once 'config.php';

$giraid = optional_param('giraid');
if ($giraid === null || $giraid === '') {
    json_error('Missing required parameter: giraid', 400);
}
$gid = (int)$giraid;

/** Torneos de la gira ordenados por torneo_id ASC → etapa 1, 2, 3... */
$torneos = query_all(
    $conn,
    "SELECT t.torneo_id, t.nombre, t.fecha_ini, t.fecha_fin, t.status, c.nombre AS club
     FROM torneo t
     LEFT JOIN clubs c ON (t.club_id = c.id)
     WHERE t.giraid = $gid
     ORDER BY t.torneo_id ASC"
);

$etapas = [];
$etapa = 0;
foreach ($torneos as $t) {
    $etapa++;
    $tid = (int)$t['torneo_id'];
    $row = query_one(
        $conn,
        "SELECT COUNT(*) AS tot
         FROM categorias a
         JOIN jugadores b ON (a.categoria_id = b.categoriaid)
         WHERE a.estatus > 0 AND a.torneo_id = $tid"
    );
    $count = $row ? (int)$row['tot'] : 0;
    // Regla: si la etapa no tiene información (jugadores), no se muestra.
    if ($count <= 0) continue;

    $etapas[] = [
        'etapa'       => $etapa,
        'torneoid'    => $tid,
        'name'        => $t['nombre'] ?? '',
        'club'        => $t['club'] ?? '',
        'startDate'   => $t['fecha_ini'] ?? '',
        'endDate'     => $t['fecha_fin'] ?? '',
        'status'      => (string)($t['status'] ?? ''),
        'playerCount' => $count,
    ];
}

json_response([
    'giraid' => $gid,
    'etapas' => $etapas,
]);
