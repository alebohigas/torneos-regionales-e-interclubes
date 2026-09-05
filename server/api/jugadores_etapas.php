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

/**
 * Etiqueta de etapa = primera "palabra" del nombre del torneo
 * ("  ETAPA-3  JUNIOR ..." -> "ETAPA-3"). Tolerante a espacios iniciales,
 * espacios dobles, tabuladores y NBSP.
 */
function je_etapa_label($nombre) {
    $n = (string)$nombre;
    $n = str_replace(["\xc2\xa0", "\t", "\r", "\n"], ' ', $n);
    $n = trim(preg_replace('/\s+/', ' ', $n));
    if ($n === '') return '';
    $parts = explode(' ', $n);
    return $parts[0];
}

/** Número contenido en la etiqueta ("ETAPA-3" -> 3); 0 si no hay. */
function je_etapa_num($label) {
    if (preg_match('/(\d+)/', (string)$label, $m)) return (int)$m[1];
    return 0;
}

$etapas = [];
$fallback = 0;
foreach ($torneos as $t) {
    $fallback++;
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

    $label = je_etapa_label($t['nombre'] ?? '');
    $num   = je_etapa_num($label);
    if ($label === '') $label = 'ETAPA-' . $fallback;
    if ($num <= 0) $num = $fallback;

    $etapas[] = [
        'etapa'       => $num,
        'etapaLabel'  => $label,
        'torneoid'    => $tid,
        'name'        => $t['nombre'] ?? '',
        'club'        => $t['club'] ?? '',
        'startDate'   => $t['fecha_ini'] ?? '',
        'endDate'     => $t['fecha_fin'] ?? '',
        'status'      => (string)($t['status'] ?? ''),
        'playerCount' => $count,
    ];
}

/** Orden alfanumérico natural por etiqueta (ETAPA-2 < ETAPA-10). */
usort($etapas, function ($a, $b) {
    $c = strnatcasecmp($a['etapaLabel'], $b['etapaLabel']);
    return $c !== 0 ? $c : ($a['torneoid'] <=> $b['torneoid']);
});

json_response([
    'giraid' => $gid,
    'etapas' => $etapas,
]);
