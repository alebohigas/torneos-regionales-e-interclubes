-- ===========================================================================
-- Consolidar en `usuarios`: alinear estructura + copiar datos de `usuarios2`
-- ---------------------------------------------------------------------------
-- La app usará SIEMPRE `usuarios`. `usuarios2` queda como respaldo (no se
-- borra aquí; cuando valides todo puedes hacerle DROP manualmente).
-- Idempotente y compatible con MariaDB (sin CREATE PROCEDURE / DELIMITER).
-- ===========================================================================

-- 1) usuario: VARCHAR(75) (cabe '__superadmin__' y los usuarios de usuarios2)
SET @sql := IF(
  (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'usuario') < 75,
  'ALTER TABLE usuarios MODIFY COLUMN usuario VARCHAR(75) NOT NULL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2) pwd: VARCHAR(255) (bcrypt = 60 chars)
SET @sql := IF(
  (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'pwd') < 255,
  'ALTER TABLE usuarios MODIFY COLUMN pwd VARCHAR(255) NOT NULL DEFAULT ""',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3) nombre más largo y con default
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'nombre') = 0,
  'ALTER TABLE usuarios ADD COLUMN nombre VARCHAR(100) NOT NULL DEFAULT ""',
  'ALTER TABLE usuarios MODIFY COLUMN nombre VARCHAR(100) NOT NULL DEFAULT ""');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4) columnas propias de usuarios2 que la app/staff necesita
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'correo_electronico') = 0,
  'ALTER TABLE usuarios ADD COLUMN correo_electronico VARCHAR(45) DEFAULT ""',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'torneos') = 0,
  'ALTER TABLE usuarios ADD COLUMN torneos VARCHAR(55) DEFAULT NULL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'gira') = 0,
  'ALTER TABLE usuarios ADD COLUMN gira INT NOT NULL DEFAULT 0',
  'ALTER TABLE usuarios MODIFY COLUMN gira INT NOT NULL DEFAULT 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'mysqlusu') = 0,
  'ALTER TABLE usuarios ADD COLUMN mysqlusu VARCHAR(45) DEFAULT ""',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'activo') = 0,
  'ALTER TABLE usuarios ADD COLUMN activo INT NOT NULL DEFAULT 1',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'pwd2') = 0,
  'ALTER TABLE usuarios ADD COLUMN pwd2 VARCHAR(45) DEFAULT NULL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 5) columnas que no deben bloquear inserts parciales
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'ultent' AND IS_NULLABLE = 'NO') = 1,
  'ALTER TABLE usuarios MODIFY COLUMN ultent DATETIME NULL DEFAULT NULL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'clubid') = 0,
  'ALTER TABLE usuarios ADD COLUMN clubid INT NOT NULL DEFAULT 0',
  'ALTER TABLE usuarios MODIFY COLUMN clubid INT NOT NULL DEFAULT 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'tipo') = 0,
  'ALTER TABLE usuarios ADD COLUMN tipo INT NOT NULL DEFAULT 0',
  'ALTER TABLE usuarios MODIFY COLUMN tipo INT NOT NULL DEFAULT 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'torneoid') = 0,
  'ALTER TABLE usuarios ADD COLUMN torneoid INT NOT NULL DEFAULT 0',
  'ALTER TABLE usuarios MODIFY COLUMN torneoid INT NOT NULL DEFAULT 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 6) COPIA DE DATOS usuarios2 -> usuarios (conserva los mismos id)
--    Reejecutable: actualiza si el id ya existe.
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios2') = 1,
  'INSERT INTO usuarios
     (id, usuario, pwd, pwd2, correo_electronico, torneos, gira, clubid, tipo,
      torneoid, estatus, nombre, desde, hasta, ultent, mysqlusu, activo)
   SELECT u2.id, u2.usuario, u2.pwd, u2.pwd2, u2.correo_electronico, u2.torneos,
          u2.gira, u2.gira, u2.tipo, u2.torneoid,
          COALESCE(u2.estatus, "ACTIVO"), COALESCE(u2.usuario, ""),
          u2.desde, u2.hasta, u2.ultent, u2.mysqlusu, COALESCE(u2.activo, 1)
     FROM usuarios2 u2
   ON DUPLICATE KEY UPDATE
      pwd = VALUES(pwd), pwd2 = VALUES(pwd2),
      correo_electronico = VALUES(correo_electronico),
      torneos = VALUES(torneos), gira = VALUES(gira), tipo = VALUES(tipo),
      torneoid = VALUES(torneoid), estatus = VALUES(estatus),
      desde = VALUES(desde), hasta = VALUES(hasta), ultent = VALUES(ultent),
      mysqlusu = VALUES(mysqlusu), activo = VALUES(activo)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 7) Tablas auxiliares de staff (apuntan a usuarios.id)
CREATE TABLE IF NOT EXISTS usuario_sesion (
  id INT NOT NULL AUTO_INCREMENT,
  usuario_id INT NOT NULL,
  token VARCHAR(128) NOT NULL,
  expira DATETIME NOT NULL,
  creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY token (token),
  KEY usuario_id (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usuario_areas (
  id INT NOT NULL AUTO_INCREMENT,
  usuario_id INT NOT NULL,
  area VARCHAR(45) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY usuario_area (usuario_id, area)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8) Limpieza: app_auth ya no se usa
DROP TABLE IF EXISTS app_auth;

-- Verificación sugerida:
--   SELECT COUNT(*) FROM usuarios;   -- debe igualar el conteo de usuarios2
--   SELECT id, usuario, tipo, activo FROM usuarios ORDER BY id;
