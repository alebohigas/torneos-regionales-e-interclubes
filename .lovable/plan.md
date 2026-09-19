# Publicar Bases del Junior Classic 2026–2027

## Objetivo
Publicar el PDF recibido únicamente cuando el sitio esté configurado con la gira 22 y el torneo 135.

## Cambios
- Conservar el PDF como archivo servido por la aplicación.
- Registrar la asociación `gira 22 + torneo 135` en un catálogo sencillo de documentos de Bases.
- Hacer que el botón **Ver en PDF** use ese documento para esa combinación, manteniendo como respaldo el archivo cargado desde Admin para otros torneos.
- Verificar que el PDF abra correctamente y que la aplicación continúe sin errores.

## Detalle técnico
- Se añadirá un registro tipado de documentos por `giraid` y `torneoid`.
- La página Bases consultará los identificadores activos antes de decidir qué PDF enlazar.
