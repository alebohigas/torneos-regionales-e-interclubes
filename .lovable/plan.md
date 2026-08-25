# Corregir guardado de Gira y eliminar `torneoid` de configuración

## Objetivo
Hacer que **Admin → Config → Gira activa** guarde `giraid` realmente en `site_config`, sin depender de `torneoid`, y detener la petición incorrecta `tournament.php?torneoid=19`.

## Cambios
1. **Autorización del guardado**
   - Hacer que la petición de `AdminGira` envíe explícitamente la contraseña de la sesión administrativa en el cuerpo y encabezado aceptados por la API.
   - Alinear `site_config.php` con el mismo validador que usa el inicio de sesión y devolver un error de autenticación más preciso.
   - Mantener las credenciales exclusivamente en la sesión del navegador; no escribirlas en código ni base de datos desde el cliente.

2. **`site_config` basado solo en gira**
   - Retirar `torneoid` del contrato GET/POST de `site_config.php` y del tipo/sincronización de `useSiteConfig`.
   - Actualizar el bootstrap para crear `site_config.giraid` como identificador obligatorio del sitio, sin columna `torneoid`.
   - Añadir una migración idempotente para instalaciones ya creadas que permita eliminar `torneoid` después de confirmar `giraid`.

3. **Eliminar la petición heredada en Home**
   - Dejar de montar `useTournamentInfo()` en Home/Header/favicon cuando no existe un torneo activo individual.
   - Usar `gira.php?giraid=...` como fuente del nombre y estado de la portada.
   - Conservar vacíos los datos estrictamente propios de un torneo hasta adaptar cada página a copas/torneos, evitando inventar que `giraid` es un `torneoid`.

4. **Validación**
   - Verificar que guardar Gira 19 devuelve el mismo `giraid` persistido.
   - Confirmar que Home consulta `gira.php?giraid=19` y ya no solicita `tournament.php?torneoid=19`.
   - Revisar compilación y errores de ejecución.

## Nota técnica
Las páginas que todavía necesitan un torneo concreto seguirán deshabilitadas o sin datos hasta definir su selector Gira → Copa → Torneo. No se reasignará automáticamente el número de gira como número de torneo.
