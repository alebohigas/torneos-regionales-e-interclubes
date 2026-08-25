-- ===========================================================================
-- Igualar la tabla `usuarios` de golftour a la estructura del proyecto torneos
-- ---------------------------------------------------------------------------
-- No crea tablas nuevas: solo amplía/agrega las columnas que faltan para que
-- el superadmin y el staff temporal funcionen igual que en el otro proyecto.
-- Idempotente (seguro de correr varias veces). Compatible con MariaDB.
-- ===========================================================================

-- 1) pwd debe aceptar bcrypt (60 chars) -> VARCHAR(255)
SET @sql := IF(
  (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'pwd') < 255,
  'ALTER TABLE usuarios MODIFY COLUMN pwd VARCHAR(255) NOT NULL DEFAULT ""',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2) usuario más largo (15 -> 45) para claves como '__superadmin__'
SET @sql := IF(
  (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'usuario') < 45,
  'ALTER TABLE usuarios MODIFY COLUMN usuario VARCHAR(45) NOT NULL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3) nombre más largo (30 -> 100)
SET @sql := IF(
  (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'nombre') < 100,
  'ALTER TABLE usuarios MODIFY COLUMN nombre VARCHAR(100) NOT NULL DEFAULT ""',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4) activo (usado por staff_login.php)
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'activo') = 0,
  'ALTER TABLE usuarios ADD COLUMN activo INT NOT NULL DEFAULT 1',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 5) tipo (0 = staff, 100 = superadmin)
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'tipo') = 0,
  'ALTER TABLE usuarios ADD COLUMN tipo INT NOT NULL DEFAULT 0',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 6) ultent no debe bloquear inserts
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'ultent' AND IS_NULLABLE = 'NO') = 1,
  'ALTER TABLE usuarios MODIFY COLUMN ultent DATETIME NULL DEFAULT NULL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 7) clubid / torneoid con default para inserts parciales
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'clubid') = 1,
  'ALTER TABLE usuarios MODIFY COLUMN clubid INT NOT NULL DEFAULT 0',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'torneoid') = 1,
  'ALTER TABLE usuarios MODIFY COLUMN torneoid INT NOT NULL DEFAULT 0',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 8) Limpieza: ya no se usa la tabla auxiliar app_auth
DROP TABLE IF EXISTS app_auth;
