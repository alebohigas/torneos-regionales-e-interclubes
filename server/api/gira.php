<?php
/**
 * Gira Endpoint
 *
 * GET /api/gira.php                  -> lista de todas las giras (para /admin)
 * GET /api/gira.php?giraid=19        -> detalle de una gira: nombre, uso,
 *                                       copas de la gira (con su grupocopas
 *                                       resuelto) y torneos ligados.
 *
 * Modelo de datos (BD golftour):
 *   gira(giraid PK, nombre, uso)
 *   copas(copasid PK, giraid FK, nombre, grupocopas)
 *       - `grupocopas` es una lista CSV de copasid cuya información consolida
 *         esa copa. Todos los ids listados DEBEN pertenecer a la misma gira;
 *         los que no cumplan se descartan aquí.
 *   torneo(torneo_id PK, giraid FK, ...)
 *
 * `uso = 0` significa gira/copa terminada: el frontend muestra el nombre con
 * el aviso "COPA TERMINADA" y /admin levanta una alerta.
 */
require_once 'config.php';

$giraidParam = optional_param('giraid');

// ---------- Modo lista: todas las giras ----------
if ($giraidParam === null || $giraidParam === '') {
    $rows = query_all($conn, "SELECT giraid, nombre, uso FROM gira ORDER BY giraid DESC");
    $giras = [];
    foreach ($rows as $r) {
        $giras[] = [
            'giraid' => (int)$r['giraid'],
            'name'   => $r['nombre'],
            'uso'    => (int)$r['uso'],
            'active' => ((int)$r['uso']) === 1,
        ];
    }
    json_response(['giras' => $giras]);
}

// ---------- Modo detalle ----------
$gid = (int)$giraidParam;
$gira = query_one($conn, "SELECT giraid, nombre, uso FROM gira WHERE giraid = $gid");
if (!$gira) {
    json_error('Gira not found', 404);
}

// Copas de la gira
$copasRows = query_all(
    $conn,
    "SELECT copasid, nombre, grupocopas, giraid
     FROM copas
     WHERE giraid = $gid
     ORDER BY copasid ASC"
);

/** ids válidos dentro de esta gira, para filtrar grupocopas */
$validIds = [];
foreach ($copasRows as $r) {
    $validIds[(int)$r['copasid']] = true;
}

$copas = [];
foreach ($copasRows as $r) {
    $copasid = (int)$r['copasid'];

    // Resolver grupocopas: CSV -> array de enteros, sólo ids de esta gira.
    $group = [];
    $raw = trim((string)($r['grupocopas'] ?? ''));
    if ($raw !== '') {
        foreach (explode(',', $raw) as $piece) {
            $id = (int)trim($piece);
            if ($id > 0 && isset($validIds[$id])) {
                $group[] = $id;
            }
        }
    }
    if (empty($group)) {
        // Sin grupo válido => la copa usa solo su propia información.
        $group = [$copasid];
    }

    $copas[] = [
        'copasid'        => $copasid,
        'name'           => $r['nombre'],
        'grupocopasRaw'  => $r['grupocopas'],
        // copasid que aportan información a esta copa
        'group'          => $group,
        // true cuando consolida información de otras copas (copa general)
        'isConsolidated' => count($group) > 1 || (count($group) === 1 && $group[0] !== $copasid),
    ];
}

// Torneos de la gira
$torneos = query_all(
    $conn,
    "SELECT torneo_id, nombre, fecha_ini, fecha_fin, status
     FROM torneo
     WHERE giraid = $gid
     ORDER BY fecha_ini ASC, torneo_id ASC"
);

$torneosOut = [];
foreach ($torneos as $t) {
    $torneosOut[] = [
        'id'        => (int)$t['torneo_id'],
        'name'      => $t['nombre'],
        'startDate' => $t['fecha_ini'],
        'endDate'   => $t['fecha_fin'],
        'status'    => $t['status'],
    ];
}

json_response([
    'giraid'  => (int)$gira['giraid'],
    'name'    => $gira['nombre'],
    'uso'     => (int)$gira['uso'],
    'active'  => ((int)$gira['uso']) === 1,
    'copas'   => $copas,
    'torneos' => $torneosOut,
]);
