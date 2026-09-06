<?php
/**
 * Calendario (Calendar) Endpoint
 * GET /api/calendario.php?torneoid=XXX
 *
 * Returns the tournament calendar matrix sourced from `caljuego`.
 *
 * For each row we use:
 *   - fecha            -> column (date)
 *   - categoriaid/categoria -> row (category)
 *   - horainicio_1     -> tee time when at least one group starts at hole 1
 *   - horainicio_10    -> tee time when at least one group starts at hole 10
 *   - salhoyos         -> comma-separated list of starting holes per group
 *                         e.g. "1,1,1,1" -> all from hole 1
 *                              "1,1,10,10" -> 2 from hole 1, 2 from hole 10
 *   - numfoursome      -> total number of foursomes (groups) playing that day
 *
 * AM/PM is derived from the tee time: < 12:00:00 = AM, >= 12:00:00 = PM.
 *
 * Each entry in the response carries:
 *   - hasAM / hasPM      booleans for cell coloring
 *   - amTime / pmTime    formatted tee time strings (or null)
 *   - amGroups / pmGroups number of groups in each half (split using salhoyos)
 *
 * The response also contains daily totals (`amTotals`, `pmTotals`) that
 * aggregate group counts across all categories per date.
 */
require_once 'config.php';

$torneoid = require_torneoid($conn);
$tid = esc($conn, $torneoid);

// `categorias.abreviatura` no existe en el esquema golftour: se detecta en
// runtime para no romper el endpoint con "Unknown column".
$abrevSel = api_column_exists($conn, 'categorias', 'abreviatura')
    ? 'cat.abreviatura'
    : "'' AS abreviatura";

// Pull every relevant field from caljuego with category and course names.
$sql = "SELECT c.id, c.fecha, c.horainicio_1, c.horainicio_10,
               c.categoria, c.campo, c.salhoyos, c.numfoursome,
               ca.campo as campo_nombre,
               cat.categoria_id, cat.categoria as categoria_nombre, $abrevSel,
               DATE_FORMAT(c.fecha, '%W') as dia_semana,
               DATE_FORMAT(c.fecha, '%e') as dia_num,
               DATE_FORMAT(c.fecha, '%M') as mes_nombre
        FROM caljuego c
        LEFT JOIN campos ca ON (c.campo = ca.id)
        LEFT JOIN categorias cat ON (c.categoriaid = cat.categoria_id)
        WHERE c.torneoid = $tid
          AND c.categoria IS NOT NULL
          AND c.categoria != ''
          AND c.campo > 0
        ORDER BY c.fecha ASC, c.horainicio_1 ASC, c.categoria ASC";

$rows = query_all($conn, $sql);

/**
 * Decide if a HH:MM:SS tee time falls in the AM half of the day.
 * Treats anything strictly before 11:00:00 as AM.
 */
function is_am_time($t) {
    if (!$t) return false;
    $parts = explode(':', $t);
    $h = isset($parts[0]) ? (int)$parts[0] : 0;
    return $h < 11;
}

/** Format HH:MM:SS into a short label like "7:00 AM" / "1:30 PM". */
function fmt_time($t) {
    if (!$t) return null;
    $parts = explode(':', $t);
    $h = isset($parts[0]) ? (int)$parts[0] : 0;
    $m = isset($parts[1]) ? $parts[1] : '00';
    $ampm = $h >= 12 ? 'PM' : 'AM';
    $disp = $h % 12; if ($disp === 0) $disp = 12;
    return $disp . ':' . $m . ' ' . $ampm;
}

$datesMap = [];
$entries = [];
// Aggregate group counts per date split by AM/PM for the bottom rows.
$amTotals = [];
$pmTotals = [];

foreach ($rows as $row) {
    $fecha = $row['fecha'];

    if (!isset($datesMap[$fecha])) {
        $datesMap[$fecha] = [
            'date'      => $fecha,
            'dayOfWeek' => $row['dia_semana'],
            'dayNum'    => $row['dia_num'],
            'month'     => $row['mes_nombre'],
            'course'    => $row['campo_nombre']
        ];
        $amTotals[$fecha] = 0;
        $pmTotals[$fecha] = 0;
    }

    // Use ONLY horainicio_1 as the single tee time for the category/date.
    // Per business rule: horainicio_10 is intentionally ignored — every
    // group is treated as starting at the time recorded in horainicio_1.
    // This means each cell will show a single AM or PM half (never both),
    // even when the database has split-tee data in horainicio_10/salhoyos.
    $numFoursome = (int)$row['numfoursome'];
    $h1Time      = $row['horainicio_1'];

    $hasAM = false; $hasPM = false;
    $amGroups = 0;  $pmGroups = 0;
    $amTime = null; $pmTime = null;

    // Skip unconfigured tee times (00:00:00 means the category has not been
    // scheduled yet, even when other fields like numfoursome are set).
    if ($h1Time && $h1Time !== '00:00:00') {
        if (is_am_time($h1Time)) {
            $hasAM    = true;
            $amGroups = $numFoursome;
            $amTime   = $h1Time;
        } else {
            $hasPM    = true;
            $pmGroups = $numFoursome;
            $pmTime   = $h1Time;
        }
    }

    $amTotals[$fecha] += $amGroups;
    $pmTotals[$fecha] += $pmGroups;

    $entries[] = [
        'id'           => (int)$row['id'],
        'date'         => $fecha,
        'category'     => $row['categoria'],
        // categoriaId from the categorias table — used by the frontend to
        // sort matrix rows in the canonical DB order (ORDER BY categoria_id ASC).
        'categoriaId'  => isset($row['categoria_id']) ? (int)$row['categoria_id'] : 0,
        'categoryName' => $row['categoria_nombre'] ?: $row['categoria'],
        'shortName'    => $row['abreviatura'] ?: $row['categoria'],
        'course'       => $row['campo_nombre'],
        'hasAM'        => $hasAM,
        'hasPM'        => $hasPM,
        'amTime'       => fmt_time($amTime),
        'pmTime'       => fmt_time($pmTime),
        'amGroups'     => $amGroups,
        'pmGroups'     => $pmGroups,
        // Kept for backward compatibility with older clients.
        'startTime'    => $row['horainicio_1'],
    ];
}

json_response([
    'dates'    => array_values($datesMap),
    'entries'  => $entries,
    'amTotals' => $amTotals,
    'pmTotals' => $pmTotals,
]);
