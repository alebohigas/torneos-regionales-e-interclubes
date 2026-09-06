<?php
/**
 * Distancias (Yardages) Endpoint
 * GET /api/distancias.php?giraid=XXX  (torneo se resuelve desde la gira activa)
 *
 * Devuelve un bloque por (categoría × campo) con:
 *   - categoría (nombre/abreviatura)
 *   - campo en el que juega esa categoría
 *   - tee de salida (nombre + color)
 *   - hoyos con yardaje y par, limitados a `hoyosajugar` cuando la categoría
 *     juega menos de 18 hoyos (p.ej. sólo los primeros 9)
 *   - totales de yardas y par
 *
 * Fuentes: categorias, caljuego (campo por categoría), campos, salidas,
 * hoyosxsalida (yardaje/par por hoyo) con fallback a campo_tee (CSV).
 */
require_once 'config.php';

$torneoid = require_torneoid($conn);
$tid = esc($conn, $torneoid);

$hasAbrev = api_column_exists($conn, 'categorias', 'abreviatura');

// ============= Tees (salidas) =============
$teeById = [];
$teeByName = [];
foreach (query_all($conn, "SELECT id, tee, color, bgcolor FROM salidas") as $t) {
    $teeById[(string)$t['id']] = $t;
    $teeByName[strtoupper(trim((string)$t['tee']))] = $t;
}

/** Resuelve `categorias.salida` (id numérico o nombre del tee) a un registro de salidas. */
function dist_resolve_tee($raw, $teeById, $teeByName) {
    $raw = trim((string)$raw);
    if ($raw === '') return null;
    if (ctype_digit($raw) && isset($teeById[$raw])) return $teeById[$raw];
    $key = strtoupper($raw);
    if (isset($teeByName[$key])) return $teeByName[$key];
    return null;
}

/** Convierte un CSV ("508,397,...") en arreglo de enteros. */
function dist_csv_ints($csv) {
    $out = [];
    foreach (preg_split('/[,;|\s]+/', (string)$csv) as $v) {
        if ($v === '' || !is_numeric($v)) continue;
        $out[] = (int)$v;
    }
    return $out;
}

// ============= Categorías del torneo =============
$catCols = 'categoria_id, categoria, salida, hoyosajugar, hoyosxronda, orden'
         . ($hasAbrev ? ', abreviatura' : '');
$cats = query_all($conn, "SELECT $catCols FROM categorias
                          WHERE torneo_id = $tid AND estatus > 0
                            AND categoria IS NOT NULL AND categoria <> ''
                          ORDER BY categoria_id ASC");

$hasHoyosxsalida = api_column_exists($conn, 'hoyosxsalida', 'yardaje');
$blocks = [];

foreach ($cats as $cat) {
    $cid = esc($conn, $cat['categoria_id']);
    $tee = dist_resolve_tee($cat['salida'] ?? '', $teeById, $teeByName);
    $teeId = $tee ? (int)$tee['id'] : 0;

    // Campos donde juega la categoría (uno o varios días).
    $courses = query_all($conn, "SELECT c.campo, ca.campo AS campo_nombre, MIN(c.fecha) AS primera
                                 FROM caljuego c
                                 LEFT JOIN campos ca ON (c.campo = ca.id)
                                 WHERE c.torneoid = $tid AND c.categoriaid = $cid AND c.campo > 0
                                 GROUP BY c.campo, ca.campo
                                 ORDER BY primera ASC");

    foreach ($courses as $course) {
        $campoId = (int)$course['campo'];
        $holes = [];

        if ($hasHoyosxsalida && $teeId > 0) {
            $rows = query_all($conn, "SELECT numero, par, yardaje FROM hoyosxsalida
                                      WHERE id_campo = $campoId AND salida = $teeId
                                      ORDER BY numero ASC");
            foreach ($rows as $r) {
                $holes[] = [
                    'numero'  => (int)$r['numero'],
                    'par'     => (int)$r['par'],
                    'yardaje' => (int)$r['yardaje'],
                ];
            }
        }

        // Fallback / complemento: CSV en campo_tee (parcampohoyo / yardaje).
        $needYardas = empty($holes);
        if (!$needYardas) {
            $sum = 0;
            foreach ($holes as $h) $sum += (int)$h['yardaje'];
            $needYardas = ($sum <= 0);
        }
        if ($needYardas) {
            $ct = query_one($conn, "SELECT parcampohoyo, yardaje FROM campo_tee
                                    WHERE id_campo = $campoId"
                                    . ($teeId > 0 ? " AND id_tee = $teeId" : '') . " LIMIT 1");
            if ($ct) {
                $pars = dist_csv_ints($ct['parcampohoyo'] ?? '');
                $yds  = dist_csv_ints($ct['yardaje'] ?? '');
                if (empty($holes)) {
                    $n = max(count($pars), count($yds));
                    for ($i = 0; $i < $n; $i++) {
                        $holes[] = [
                            'numero'  => $i + 1,
                            'par'     => $pars[$i] ?? 0,
                            'yardaje' => $yds[$i] ?? 0,
                        ];
                    }
                } else {
                    // Conserva los pares de hoyosxsalida y completa las yardas.
                    foreach ($holes as $i => $h) {
                        if ((int)$h['yardaje'] <= 0 && isset($yds[$i])) {
                            $holes[$i]['yardaje'] = (int)$yds[$i];
                        }
                        if ((int)$h['par'] <= 0 && isset($pars[$i])) {
                            $holes[$i]['par'] = (int)$pars[$i];
                        }
                    }
                }
            }
        }

        if (empty($holes)) continue;


        // Recorte por hoyos a jugar (9, 18, etc.).
        $toPlay = (int)($cat['hoyosajugar'] ?? 0);
        if ($toPlay <= 0) $toPlay = (int)($cat['hoyosxronda'] ?? 0);
        if ($toPlay > 0 && $toPlay < count($holes)) {
            $holes = array_slice($holes, 0, $toPlay);
        }

        $totalYardas = 0; $totalPar = 0;
        foreach ($holes as $h) { $totalYardas += $h['yardaje']; $totalPar += $h['par']; }

        $blocks[] = [
            'categoryId'   => (int)$cat['categoria_id'],
            'category'     => $cat['categoria'],
            'shortName'    => $hasAbrev ? ($cat['abreviatura'] ?: $cat['categoria']) : $cat['categoria'],
            'course'       => $course['campo_nombre'] ?: '',
            'teeName'      => $tee ? $tee['tee'] : '',
            'teeColor'     => $tee ? ($tee['bgcolor'] ?: $tee['color']) : '',
            'holes'        => $holes,
            'holeCount'    => count($holes),
            'totalYardas'  => $totalYardas,
            'totalPar'     => $totalPar,
        ];
    }
}

json_response([
    'torneoid' => (int)$torneoid,
    'blocks'   => $blocks,
]);
