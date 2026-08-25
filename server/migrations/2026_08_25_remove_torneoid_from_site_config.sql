-- Modelo Golftour: site_config se identifica por dominio y giraid, no torneoid.
-- Idempotente y seguro para instalaciones donde la columna ya fue eliminada.

SET @add_giraid = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE site_config ADD COLUMN giraid INT NULL DEFAULT NULL COMMENT ''Gira activa (gira.giraid)''',
    'SELECT ''site_config.giraid ya existe'''
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'site_config'
    AND COLUMN_NAME = 'giraid'
);
PREPARE stmt FROM @add_giraid;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_torneoid = (
  SELECT IF(
    COUNT(*) > 0,
    'ALTER TABLE site_config DROP COLUMN torneoid',
    'SELECT ''site_config.torneoid ya no existe'''
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'site_config'
    AND COLUMN_NAME = 'torneoid'
);
PREPARE stmt FROM @drop_torneoid;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;