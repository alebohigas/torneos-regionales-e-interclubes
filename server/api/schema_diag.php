<?php
/**
 * schema_diag.php — Diagnóstico de esquema de la base activa.
 *
 * Uso:
 *   GET /api/schema_diag.php                      → lista de tablas + rutinas
 *   GET /api/schema_diag.php?tables=categorias,jugadores,caljuego,tarjetas
 *   GET /api/schema_diag.php?routines=1           → funciones/procedimientos
 *
 * Sirve para saber exactamente qué columnas existen en `golftour` antes de
 * escribir queries (evita 500 por "Unknown column").
 */
require_once 'config.php';

$out = ['database' => null, 'tables' => [], 'columns' => [], 'routines' => []];

$row = query_one($conn, 'SELECT DATABASE() AS db');
$out['database'] = $row['db'] ?? null;

$res = @$conn->query("SELECT TABLE_NAME, TABLE_TYPE FROM INFORMATION_SCHEMA.TABLES
                      WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $out['tables'][] = ['name' => $r['TABLE_NAME'], 'type' => $r['TABLE_TYPE']];
    }
    $res->free();
}

$tables = optional_param('tables', '');
if ($tables !== '') {
    foreach (explode(',', $tables) as $t) {
        $t = trim($t);
        if (!preg_match('/^[A-Za-z0-9_]+$/', $t)) continue;
        $cols = [];
        $r = @$conn->query("SHOW COLUMNS FROM `$t`");
        if (!$r) { $out['columns'][$t] = ['error' => $conn->error]; continue; }
        while ($c = $r->fetch_assoc()) {
            $cols[] = ['name' => $c['Field'], 'type' => $c['Type'], 'null' => $c['Null'], 'key' => $c['Key']];
        }
        $r->free();
        $out['columns'][$t] = $cols;
    }
}

if (optional_param('routines', '0') === '1') {
    $r = @$conn->query("SELECT ROUTINE_NAME, ROUTINE_TYPE FROM INFORMATION_SCHEMA.ROUTINES
                        WHERE ROUTINE_SCHEMA = DATABASE() ORDER BY ROUTINE_NAME");
    if ($r) {
        while ($x = $r->fetch_assoc()) {
            $out['routines'][] = ['name' => $x['ROUTINE_NAME'], 'type' => $x['ROUTINE_TYPE']];
        }
        $r->free();
    }
}

json_response($out);
