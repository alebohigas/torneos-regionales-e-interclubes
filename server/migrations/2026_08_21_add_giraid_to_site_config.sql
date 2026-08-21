-- ============================================================
-- Agrega `giraid` a site_config (BD golftour / modelo por giras)
-- Idempotente: se puede correr varias veces sin error.
--
-- Contexto: en este modelo la página ya no se basa en un torneo
-- único, sino en una GIRA (`gira.giraid`) que agrupa varias copas
-- (`copas.giraid`) y varios torneos (`torneo.giraid`).
-- `torneoid` se conserva para módulos que aún requieren un torneo
-- puntual (convocatoria, registro, heros, etc.).
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
