<?php
/**
 * Calendario de la GIRA
 * GET /api/calendario_gira.php?giraid=NN
 *
 * Una fila por ETAPA de la gira (cada torneo con `torneo.giraid = NN`):
 *   - Club(es): logo de los clubes dueños de las sedes donde se juega.
 *   - Sede: nombre(s) de `campos` concatenados + etiqueta de etapa
 *           (p.ej. "Misiones / Herradura Etapa-1").
 *   - Fecha: fechas reales de juego tomadas de `caljuego`
 *           (p.ej. "26 octubre 2025", "22 y 23 noviembre 2025").
 *
 * Las sedes se obtienen de `caljuego.campo` (distintos) del torneo; si el
 * torneo aún no tiene caljuego se usa `torneo.campo` como respaldo.
 * Todas las columnas opcionales se detectan en runtime porque el esquema
 * `golftour` no tiene varias columnas legacy.
 */
require_once 'config.php';

$giraid = isset($_GET['giraid']) ? trim((string)$_GET['giraid']) : '';
if (!ctype_digit($giraid) || (int)$giraid <= 0) {
    json_error('giraid requerido', 400);
}
$gid = (int)$giraid;

$MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
          'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

/** Etiqueta normalizada desde "ETAPA-3", "Etapa 3" o "etapa_3". */
function cg_etapa_label($nombre) {
    $n = str_replace(["\xc2\xa0", "\t", "\r", "\n"], ' ', (string)$nombre);
    $n = trim(preg_replace('/\s+/u', ' ', $n));
    if ($n === '') return '';
    if (preg_match('/etapa\s*[-_.\s]?\s*(\d+[A-Za-z]?)/iu', $n, $m)) {
        return 'ETAPA-' . strtoupper($m[1]);
    }
    $parts = explode(' ', $n);
    return $parts[0];
}

/** "ETAPA-3" -> "Etapa-3" */
function cg_pretty_label($label) {
    $l = strtolower((string)$label);
    return preg_replace_callback('/(^|[-_ ])([a-z\xC3\xA1\xC3\xA9\xC3\xAD\xC3\xB3\xC3\xBA])/u',
        function ($m) { return $m[1] . mb_strtoupper($m[2], 'UTF-8'); }, $l);
}

/** Número dentro de la etiqueta ("ETAPA-3" -> 3). */
function cg_etapa_num($label) {
    return preg_match('/(\d+)/', (string)$label, $m) ? (int)$m[1] : 0;
}

/**
 * Formatea un conjunto de fechas YYYY-MM-DD en español:
 *   1 fecha              -> "26 octubre 2025"
 *   mismas mes/año       -> "22 y 23 noviembre 2025"
 *   3+ del mismo mes     -> "22, 23 y 24 noviembre 2025"
 *   meses distintos      -> "30 noviembre 2025 y 1 diciembre 2025"
 */
function cg_format_dates(array $dates, array $MESES) {
    $dates = array_values(array_unique(array_filter($dates)));
    sort($dates);
    if (empty($dates)) return '';

    $parts = [];
    foreach ($dates as $d) {
        $p = explode('-', substr((string)$d, 0, 10));
        if (count($p) < 3) continue;
        $parts[] = ['y' => (int)$p[0], 'm' => (int)$p[1], 'd' => (int)$p[2]];
    }
    if (empty($parts)) return '';

    $sameMonth = true;
    foreach ($parts as $p) {
        if ($p['m'] !== $parts[0]['m'] || $p['y'] !== $parts[0]['y']) { $sameMonth = false; break; }
    }

    if ($sameMonth) {
        $days = array_map(function ($p) { return (string)$p['d']; }, $parts);
        if (count($days) === 1) $list = $days[0];
        else {
            $last = array_pop($days);
            $list = implode(', ', $days) . ' y ' . $last;
        }
        return $list . ' ' . $MESES[$parts[0]['m']] . ' ' . $parts[0]['y'];
    }

    $full = array_map(function ($p) use ($MESES) {
        return $p['d'] . ' ' . $MESES[$p['m']] . ' ' . $p['y'];
    }, $parts);
    $last = array_pop($full);
    return empty($full) ? $last : implode(', ', $full) . ' y ' . $last;
}

// ============= Columnas dinámicas =============
$torneoPk     = api_first_existing_column($conn, 'torneo', ['torneo_id', 'torneoid', 'id']) ?: 'torneo_id';
// `torneo.campos` es un CSV de ids de sedes ("113,174" = dos sedes).
$torneoCampos = api_first_existing_column($conn, 'torneo', ['campos']);
$torneoCampo  = api_first_existing_column($conn, 'torneo', ['campo', 'campoid', 'id_campo']);
$torneoClub   = api_first_existing_column($conn, 'torneo', ['club_id', 'clubid']);
$camposClub   = api_first_existing_column($conn, 'campos', ['clubid', 'club_id', 'id_club']);

$sel = "t.`$torneoPk` AS torneo_id, t.nombre, t.fecha_ini, t.fecha_fin, t.status";
if ($torneoCampos) $sel .= ", t.`$torneoCampos` AS torneo_campos";
if ($torneoCampo)  $sel .= ", t.`$torneoCampo` AS torneo_campo";
if ($torneoClub)   $sel .= ", t.`$torneoClub` AS torneo_club";

$torneos = query_all($conn, "SELECT $sel FROM torneo t WHERE t.giraid = $gid ORDER BY t.`$torneoPk` ASC");

// Clubes (para logo) indexados por id.
$clubs = [];
foreach (query_all($conn, "SELECT id, nombre, logo FROM clubs") as $c) {
    $clubs[(int)$c['id']] = $c;
}

// Catálogo completo de campos: los ids del CSV que no existan aquí se ignoran.
$camposById = [];
$camposSel = 'id, campo' . ($camposClub ? ", `$camposClub` AS club_id" : '');
foreach (query_all($conn, "SELECT $camposSel FROM campos") as $c) {
    $camposById[(int)$c['id']] = $c;
}

/** Convierte "113,174" en [113, 174] (tolera espacios, ; y |). */
function cg_csv_ids($csv) {
    $out = [];
    foreach (preg_split('/[,;|\s]+/', (string)$csv) as $v) {
        if ($v === '' || !ctype_digit($v)) continue;
        $id = (int)$v;
        if ($id > 0) $out[] = $id;
    }
    return array_values(array_unique($out));
}

$etapas = [];
$fallback = 0;

foreach ($torneos as $t) {
    $fallback++;
    $tid = (int)$t['torneo_id'];

    // ---- Sedes: ids listados en `torneo.campos` (uno o varios) ----
    $ids = cg_csv_ids($t['torneo_campos'] ?? '');
    if (empty($ids) && !empty($t['torneo_campo'])) $ids = cg_csv_ids($t['torneo_campo']);

    $rows = [];
    foreach ($ids as $id) {
        // Si el id no existe en `campos`, simplemente se ignora.
        if (!isset($camposById[$id])) continue;
        $c = $camposById[$id];
        $rows[] = [
            'campo_id'     => $id,
            'campo_nombre' => $c['campo'] ?? '',
            'club_id'      => $c['club_id'] ?? null,
        ];
    }

    // Respaldo: sedes usadas en caljuego cuando el torneo no tiene `campos`.
    if (empty($rows)) {
        $campoSel = "c.campo AS campo_id, ca.campo AS campo_nombre, MIN(c.fecha) AS primera";
        if ($camposClub) $campoSel .= ", ca.`$camposClub` AS club_id";
        $rows = query_all($conn, "SELECT $campoSel
                                  FROM caljuego c
                                  JOIN campos ca ON (c.campo = ca.id)
                                  WHERE c.torneoid = $tid AND c.campo > 0
                                  GROUP BY c.campo, ca.campo"
                                  . ($camposClub ? ", ca.`$camposClub`" : '') . "
                                  ORDER BY primera ASC, ca.campo ASC");
    }


    $venues = [];
    $logos  = [];
    foreach ($rows as $r) {
        $name = trim((string)($r['campo_nombre'] ?? ''));
        if ($name !== '') $venues[] = $name;

        $clubId = isset($r['club_id']) ? (int)$r['club_id'] : 0;
        if ($clubId <= 0 && !empty($t['torneo_club'])) $clubId = (int)$t['torneo_club'];
        $club = $clubs[$clubId] ?? null;
        if ($club) {
            $logos[$clubId] = [
                'clubId' => $clubId,
                'name'   => $club['nombre'] ?? '',
                'logo'   => !empty($club['logo']) ? $GLOBALS['LOGOS_BASE_URL'] . $club['logo'] : '',
            ];
        }
    }
    $venues = array_values(array_unique($venues));

    // ---- Fechas de juego ----
    $fechas = [];
    foreach (query_all($conn, "SELECT DISTINCT c.fecha FROM caljuego c
                               WHERE c.torneoid = $tid AND c.fecha IS NOT NULL
                                 AND c.fecha <> '0000-00-00'
                               ORDER BY c.fecha ASC") as $f) {
        $fechas[] = substr((string)$f['fecha'], 0, 10);
    }
    if (empty($fechas)) {
        foreach ([$t['fecha_ini'] ?? '', $t['fecha_fin'] ?? ''] as $d) {
            $d = substr((string)$d, 0, 10);
            if ($d && $d !== '0000-00-00') $fechas[] = $d;
        }
    }

    // Etapas sin sede ni fecha no aportan información: se omiten.
    if (empty($venues) && empty($fechas)) continue;

    $label  = cg_etapa_label($t['nombre'] ?? '');
    $num    = cg_etapa_num($label);
    if ($label === '') $label = 'ETAPA-' . $fallback;
    if ($num <= 0)     $num = $fallback;
    $pretty = cg_pretty_label($label);

    $etapas[] = [
        'etapa'        => $num,
        'etapaLabel'   => $label,
        'etapaDisplay' => $pretty,
        'torneoid'     => $tid,
        'name'         => $t['nombre'] ?? '',
        'status'       => (string)($t['status'] ?? ''),
        'venues'       => $venues,
        'sede'         => implode(' / ', $venues),
        'clubs'        => array_values($logos),
        'dates'        => $fechas,
        'dateLabel'    => cg_format_dates($fechas, $MESES),
    ];
}

/** Orden natural por etiqueta de etapa (ETAPA-2 antes de ETAPA-10). */
usort($etapas, function ($a, $b) {
    $c = strnatcasecmp($a['etapaLabel'], $b['etapaLabel']);
    return $c !== 0 ? $c : ($a['torneoid'] <=> $b['torneoid']);
});

json_response([
    'giraid' => $gid,
    'etapas' => $etapas,
]);
