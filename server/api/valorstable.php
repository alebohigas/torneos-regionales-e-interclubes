<?php
/**
 * Valores Stableford Endpoint
 * -----------------------------------------------------------------------
 * GET /api/valorstable.php?torneoid=XXX
 *
 * Devuelve TODAS las filas de la tabla `valorstable` del torneo.
 * La tabla legacy tiene una fila por diferencia al par:
 *   torneoid | difpar | valor
 * (ej. difpar = 3,2,1,0,-1,... y valor = puntos Stableford)
 *
 * Respuesta:
 *   { rows: [ { difpar: 1, valor: 1 }, ... ], row: <primera fila|null> }
 *
 * Se usa en la página /reglas para renderizar la tabla de puntaje
 * Stableford dentro de "Reglas Locales del Torneo" y en /convocatoria.
 *
 * Resiliente: si la tabla no existe o no hay fila para el torneo,
 * responde `{ rows: [], row: null }` en lugar de 500 — el frontend
 * oculta la tabla.
 */
require_once 'config.php';

$torneoid = (int) require_torneoid($conn);

// ¿Existe la tabla?
$check = $conn->query("SHOW TABLES LIKE 'valorstable'");
if (!$check || $check->num_rows === 0) {
    json_response(['rows' => [], 'row' => null, 'source' => 'no_table']);
}

// Detecta si la tabla usa el esquema por filas (difpar/valor).
$cols = [];
$rsCols = $conn->query("SHOW COLUMNS FROM valorstable");
if ($rsCols) {
    while ($c = $rsCols->fetch_assoc()) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }
}
$hasDifpar = isset($cols['difpar']);

$order = $hasDifpar ? " ORDER BY CAST(difpar AS SIGNED) DESC" : "";
$sql = "SELECT * FROM valorstable WHERE torneoid = " . esc($conn, $torneoid) . $order;
$rs = $conn->query($sql);
if (!$rs || $rs->num_rows === 0) {
    json_response(['rows' => [], 'row' => null]);
}

$rows = [];
while ($r = $rs->fetch_assoc()) {
    // Elimina llaves internas que no aportan al frontend.
    unset($r['id'], $r['torneoid']);
    $rows[] = $r;
}

json_response(['rows' => $rows, 'row' => $rows[0] ?? null]);