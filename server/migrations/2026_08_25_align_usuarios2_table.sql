-- ===========================================================================
-- Usar `usuarios2` como tabla de usuarios de la app (golftour)
-- ---------------------------------------------------------------------------
-- `usuarios` está vacía en golftour; `usuarios2` es la tabla real con datos.
-- Este script agrega/ajusta SOLO las columnas que la app necesita.
-- Idempotente (seguro de correr varias veces). Compatible con MariaDB.
-- ===========================================================================

-- 1) pwd debe aceptar bcrypt (60 chars) -> VARCHAR(255)
SET @sql := IF(
  (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios2'
      AND COLUMN_NAME = 'pwd') < 255,
  'ALTER TABLE usuarios2 MODIFY COLUMN pwd VARCHAR(255) NOT NULL DEFAULT ""',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2) nombre (usado por staff y superadmin)
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios2'
      AND COLUMN_NAME = 'nombre') = 0,
  'ALTER TABLE usuarios2 ADD COLUMN nombre VARCHAR(100) NOT NULL DEFAULT ""',
  'ALTER TABLE usuarios2 MODIFY COLUMN nombre VARCHAR(100) NOT NULL DEFAULT ""');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3) clubid (compatibilidad con inserts del proyecto original)
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios2'
      AND COLUMN_NAME = 'clubid') = 0,
  'ALTER TABLE usuarios2 ADD COLUMN clubid INT NOT NULL DEFAULT 0',
  'ALTER TABLE usuarios2 MODIFY COLUMN clubid INT NOT NULL DEFAULT 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4) activo (usado por el login de staff)
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios2'
      AND COLUMN_NAME = 'activo') = 0,
  'ALTER TABLE usuarios2 ADD COLUMN activo INT NOT NULL DEFAULT 1',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 5) usuario más largo para claves como '__superadmin__' (ya es 75, por si acaso)
SET @sql := IF(
  (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios2'
      AND COLUMN_NAME = 'usuario') < 45,
  'ALTER TABLE usuarios2 MODIFY COLUMN usuario VARCHAR(75) NOT NULL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 6) ultent / gira / torneoid no deben bloquear inserts parciales
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios2'
      AND COLUMN_NAME = 'ultent' AND IS_NULLABLE = 'NO') = 1,
  'ALTER TABLE usuarios2 MODIFY COLUMN ultent DATETIME NULL DEFAULT NULL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios2'
      AND COLUMN_NAME = 'gira') = 1,
  'ALTER TABLE usuarios2 MODIFY COLUMN gira INT NOT NULL DEFAULT 0',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios2'
      AND COLUMN_NAME = 'torneoid') = 1,
  'ALTER TABLE usuarios2 MODIFY COLUMN torneoid INT NOT NULL DEFAULT 0',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 7) Sesiones de staff apuntan a usuarios2.id (la tabla ya existe sin FK)
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
