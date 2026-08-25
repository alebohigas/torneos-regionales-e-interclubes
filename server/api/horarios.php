<?php
/**
 * Horarios de Salidas Endpoint
 * GET /api/horarios.php?torneoid=XXX
 *
 * Returns the official kickoff (start) time for each category on each
 * tournament day. The official time is defined as the earliest valid
 * `horainicio1a` in `salidagrupo` for the matching `caljuegoid` (which
 * itself belongs to the requested tournament).
 *
 * Validity rule:
 *   - Times equal to '00:00:00' are ignored entirely (not displayed and
 *     not considered when computing the per-category/day minimum).
 *
 * Data flow:
 *   torneo.torneoid → caljuego.torneoid
 *   caljuego.id     → salidagrupo.caljuegoid
 *   salidagrupo.categoriaid → categorias.categoria_id
 *
 * Response shape:
 *   {
 *     dates:   [{ date, dayOfWeek, dayNum, month }, ...],
 *     entries: [
 *       {
 *         categoryId:   "<categoria_id>",
 *         categoryName: "<long name>",
 *         shortName:    "<abbreviation>",
 *         times: { 'YYYY-MM-DD': 'HH:MM' or null }
 *       },
 *       ...
 *     ]
 *   }
 */
require_once 'config.php';

$torneoid = require_torneoid($conn);
$tid = esc($conn, $torneoid);

// ============= Query: earliest valid time per category + date =============
// We aggregate at SQL level (MIN) but ALSO filter '00:00:00' here as a
// second validity check (per spec: salidagrupo already filters them, this
// guards against any leaking invalid records).
$sql = "SELECT cj.fecha,
               sg.categoriaid,
               cat.categoria         AS categoria_nombre,
               cat.abreviatura       AS abreviatura,
               MIN(TIME(sg.horainicio1a))  AS hora_min,
               DATE_FORMAT(cj.fecha, '%W') AS dia_semana,
               DATE_FORMAT(cj.fecha, '%e') AS dia_num,
               DATE_FORMAT(cj.fecha, '%M') AS mes_nombre
        FROM salidagrupo sg
        JOIN caljuego   cj  ON (sg.caljuegoid = cj.id)
        JOIN categorias cat ON (sg.categoriaid = cat.categoria_id)
        WHERE cj.torneoid = $tid
          AND sg.horainicio1a IS NOT NULL
          AND TIME(sg.horainicio1a) <> '00:00:00'
        GROUP BY cj.fecha, sg.categoriaid, cat.categoria, cat.abreviatura,
                 dia_semana, dia_num, mes_nombre
        ORDER BY cj.fecha ASC, cat.categoria ASC";

$rows = query_all($conn, $sql);

/**
 * Extract HH:MM (24h) from any time-bearing string.
 * Accepts:
 *   - "HH:MM:SS"           (TIME column)
 *   - "YYYY-MM-DD HH:MM:SS" (DATETIME/TIMESTAMP column)
 * Returns null when the value is empty, '00:00:00' or unparseable.
 */
function fmt_hhmm($t) {
    if (!$t) return null;
    // Match the LAST HH:MM in the string so DATETIMEs work too.
    if (!preg_match('/(\d{1,2}):(\d{2})(?::\d{2})?\s*$/', trim($t), $m)) return null;
    $h = (int)$m[1];
    $mn = $m[2];
    if ($h === 0 && $mn === '00') return null;  // ignore 00:00 placeholders
    return str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':' . $mn;
}

// ============= Build response structure =============
$datesMap   = [];   // YYYY-MM-DD => { date, dayOfWeek, dayNum, month }
$catMap     = [];   // categoriaid => { categoryId, categoryName, shortName, times: {} }

foreach ($rows as $r) {
    $fecha = $r['fecha'];
    $minT  = fmt_hhmm($r['hora_min']);

    // Skip rows whose only times were '00:00:00' (defensive, MIN already filtered).
    if ($minT === null) continue;

    if (!isset($datesMap[$fecha])) {
        $datesMap[$fecha] = [
            'date'      => $fecha,
            'dayOfWeek' => $r['dia_semana'],
            'dayNum'    => $r['dia_num'],
            'month'     => $r['mes_nombre'],
        ];
    }

    $cid = $r['categoriaid'];
    if (!isset($catMap[$cid])) {
        $catMap[$cid] = [
            'categoryId'   => $cid,
            'categoryName' => $r['categoria_nombre'],
            'shortName'    => $r['abreviatura'] ?: $r['categoria_nombre'],
            'times'        => [],
        ];
    }

    $catMap[$cid]['times'][$fecha] = $minT;
}

// Sort dates ascending in the output.
ksort($datesMap);

json_response([
    'dates'   => array_values($datesMap),
    'entries' => array_values($catMap),
]);