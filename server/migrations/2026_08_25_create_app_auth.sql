-- ===========================================================================
-- app_auth — credenciales propias de la app (superadmin)
-- ---------------------------------------------------------------------------
-- Motivo: en golftour la tabla legacy `usuarios` tiene `pwd VARCHAR(10)` y no
-- tiene columna `activo`, por lo que el hash bcrypt del superadmin (60 chars)
-- no se podía guardar. Eso hacía que POST /api/site_config.php respondiera 401.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS app_auth (
  k          VARCHAR(64) NOT NULL PRIMARY KEY,
  v          TEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- La contraseña se define desde /admin (Cambiar contraseña) o vía
-- $SUPERADMIN_PASSWORD en credentials.php. Mientras no exista fila aquí ni
-- valor en credentials.php, aplica el fallback histórico 'admin2025'.
