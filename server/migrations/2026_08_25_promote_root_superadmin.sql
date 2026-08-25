-- ===========================================================================
-- Promover el usuario legacy `root` a superadmin de la app
-- ---------------------------------------------------------------------------
-- No crea contraseñas ni toca el `pwd`: usa la contraseña que ya tiene root.
-- Después de ejecutar esto, entra a /admin con:
--   Usuario: root
--   Contraseña: la que ya existe en usuarios.pwd
-- ===========================================================================

UPDATE usuarios
   SET tipo = 100,
       activo = 1,
       estatus = 'ACTIVO'
 WHERE usuario = 'root';

-- Verificación:
-- SELECT id, usuario, tipo, activo, estatus, LENGTH(pwd) AS pwd_len
--   FROM usuarios
--  WHERE usuario = 'root';