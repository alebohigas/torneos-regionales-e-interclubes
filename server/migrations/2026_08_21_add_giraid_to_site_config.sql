-- ============================================================
-- Agrega `giraid` a site_config (BD golftour / modelo por giras)
-- Idempotente: se puede correr varias veces sin error.
--
-- Contexto: en este modelo la página ya no se basa en un torneo
-- único, sino en una GIRA (`gira.giraid`) que agrupa varias copas
-- (`copas.giraid`) y varios torneos (`torneo.giraid`).
-- `torneoid` deja de formar parte de site_config: los módulos que necesiten
-- un torneo puntual deberán resolverlo desde la gira/copa seleccionada.
-- ============================================================

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE site_config ADD COLUMN giraid INT NULL DEFAULT NULL COMMENT ''Gira activa (gira.giraid) en la que se basa el sitio''',
    'SELECT ''site_config.giraid ya existe'''
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'site_config'
    AND COLUMN_NAME  = 'giraid'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Elimina la configuración global heredada por torneo una vez disponible giraid.
SET @drop_torneoid = (
  SELECT IF(
    COUNT(*) > 0,
    'ALTER TABLE site_config DROP COLUMN torneoid',
    'SELECT ''site_config.torneoid ya no existe'''
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'site_config'
    AND COLUMN_NAME  = 'torneoid'
);
PREPARE stmt FROM @drop_torneoid;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
