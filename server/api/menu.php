<?php
/**
 * Menu Items Endpoint
 * GET /api/menu.php?torneoid=XXX
 * Returns menu configuration for the tournament
 */
require_once 'config.php';

$torneoid = require_torneoid($conn);
$tid = esc($conn, $torneoid);

// Fetch menu groups and items
$sql = "SELECT m.id, m.nombre, m.grupo, m.orden, m.visible, m.url, m.icono, m.tipo
        FROM menu m
        WHERE m.torneoid = $tid AND m.visible = 1
        ORDER BY m.grupo ASC, m.orden ASC";

$rows = query_all($conn, $sql);

// Group by menu group
$groups = [];
foreach ($rows as $row) {
    $grupo = $row['grupo'];
    if (!isset($groups[$grupo])) {
        $groups[$grupo] = [];
    }
    $groups[$grupo][] = [
        'id'    => $row['id'],
        'name'  => $row['nombre'],
        'url'   => $row['url'],
        'icon'  => $row['icono'],
        'type'  => $row['tipo'],
        'order' => (int)$row['orden']
    ];
}

json_response($groups);
