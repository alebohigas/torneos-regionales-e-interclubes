-- ===========================================================================
-- BOOTSTRAP para una BASE DE DATOS NUEVA (ej. `golftour` en otra IP)
-- ---------------------------------------------------------------------------
-- Crea TODAS las tablas "propias de la app" (las que NO vienen del sistema
-- legacy de torneos) para que /admin, /setup, /convocatoria, /reglas,
-- /registro, /banderas y los switches de visibilidad funcionen igual que en
-- la instalación original.
--
-- Es IDEMPOTENTE: se puede correr varias veces sin romper nada.
-- NO incluye GRANTs (hosting compartido; privilegios los da el hosting).
--
-- Orden recomendado:
--   1) este archivo
--   2) 2026_05_19_registro_precios.sql
--   3) 2026_05_20_registro_precios_hcp.sql
--   4) 2026_05_22_categorias_reglas.sql
--   5) 2026_06_21_banderas.sql  +  2026_06_22_banderas_fecha.sql
--   6) 2026_06_26_staff_users.sql  +  2026_06_29_drop_pwd_hash.sql
--   7) 2026_07_16_registro_preferente.sql
--   8) 2026_07_21_registro_socio_tipos.sql
--   9) (opcional, solo si se usan brackets/matchplay)
--      2026_05_18_putt_finales_brackets.sql, 2026_07_03_matchplay_third_place.sql
--  Los pasos 2-9 ya son CREATE/ALTER seguros; este archivo cubre lo que no
--  tenía migración propia (site_config y convocatoria_content) y repite las
--  tablas críticas con IF NOT EXISTS para que un solo run deje todo listo.
-- ===========================================================================

-- ---------------------------------------------------------------------------
-- 1. site_config — configuración por DOMINIO (giraid, menús, visibilidad,
--    tema, heros, módulos de /setup, etc.)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS site_config (
  domain                 VARCHAR(255) NOT NULL PRIMARY KEY,
  giraid                 INT NULL DEFAULT NULL COMMENT 'Gira activa (gira.giraid)',
  menu_order             TEXT DEFAULT NULL COMMENT 'JSON pageId -> order',
  visibility             TEXT DEFAULT NULL COMMENT 'JSON pageId -> boolean',
  menu_groups            TEXT DEFAULT NULL COMMENT 'JSON array de grupos de menú',
  page_group_assignments TEXT DEFAULT NULL COMMENT 'JSON pageId -> groupId',
  live_scoring_config    TEXT DEFAULT NULL,
  sponsors_config        TEXT DEFAULT NULL,
  eventos_config         TEXT DEFAULT NULL,
  avisos_config          TEXT DEFAULT NULL,
  menus_config           TEXT DEFAULT NULL,
  premios_config         TEXT DEFAULT NULL,
  hoteles_config         TEXT DEFAULT NULL,
  theme_config           TEXT DEFAULT NULL,
  stats_config           TEXT DEFAULT NULL,
  stats_page_config      TEXT DEFAULT NULL,
  popup_config           TEXT DEFAULT NULL,
  anuncio_config         TEXT DEFAULT NULL,
  home_config            TEXT DEFAULT NULL,
  historial_config       TEXT DEFAULT NULL,
  hero_config            TEXT DEFAULT NULL,
  modules_config         TEXT DEFAULT NULL COMMENT 'JSON de módulos on/off (/setup)',
  updated_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Si site_config ya existía con menos columnas, se completan aquí.
-- Sin CREATE PROCEDURE (evita necesidad de DELIMITER en Workbench/MariaDB):
-- se arma un solo ALTER dinámico con las columnas que falten.
SET @cols = 'menu_order,visibility,menu_groups,page_group_assignments,live_scoring_config,sponsors_config,eventos_config,avisos_config,menus_config,premios_config,hoteles_config,theme_config,stats_config,stats_page_config,popup_config,anuncio_config,home_config,historial_config,hero_config,modules_config';

SET @missing = (
  SELECT GROUP_CONCAT(CONCAT('ADD COLUMN `', c.col, '` TEXT DEFAULT NULL') SEPARATOR ', ')
  FROM (
    SELECT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(@cols, ',', n.i), ',', -1)) AS col
    FROM (SELECT 1 i UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
          UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
          UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15
          UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20) n
  ) c
  WHERE NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS ic
    WHERE ic.TABLE_SCHEMA = DATABASE()
      AND ic.TABLE_NAME   = 'site_config'
      AND ic.COLUMN_NAME  = c.col
  )
);

SET @s = IF(@missing IS NULL OR @missing = '',
            'SELECT ''site_config ya tiene todas las columnas'' AS info',
            CONCAT('ALTER TABLE site_config ', @missing));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------------
-- 2. convocatoria_content — secciones editables de /convocatoria y /reglas
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS convocatoria_content (
  id           INT(11) NOT NULL AUTO_INCREMENT,
  torneoid     INT(11) NOT NULL,
  section_id   VARCHAR(64)  NOT NULL COMMENT 'ej. descripcion, costos, desempates, reglas_locales',
  section_type VARCHAR(32)  NOT NULL DEFAULT 'generic',
  title        VARCHAR(255) NULL,
  content      LONGTEXT     NULL COMMENT 'JSON con el contenido de la sección',
  sort_order   INT(11)      NOT NULL DEFAULT 0,
  enabled      TINYINT(1)   NOT NULL DEFAULT 1,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_torneo_section (torneoid, section_id),
  KEY idx_torneo (torneoid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. registro_form_fields — qué campos se muestran en /registro (Pre-Registro)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS registro_form_fields (
  id            INT(11) NOT NULL AUTO_INCREMENT,
  torneo_id     INT(11) NOT NULL,
  field_name    VARCHAR(64)  NOT NULL COMMENT 'columna real en `registro` (ej. reg_cargo_socio)',
  field_label   VARCHAR(160) NOT NULL DEFAULT '',
  is_enabled    TINYINT(1)   NOT NULL DEFAULT 1,
  is_required   TINYINT(1)   NOT NULL DEFAULT 0,
  display_order INT(11)      NOT NULL DEFAULT 0,
  section       VARCHAR(32)  NOT NULL DEFAULT 'basica',
  PRIMARY KEY (id),
  UNIQUE KEY uk_torneo_field (torneo_id, field_name),
  KEY idx_torneo (torneo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 4. Tablas de staff/sesiones para /admin con usuarios (no superadmin)
--    (equivalente a 2026_06_26_staff_users.sql; requiere tabla `usuarios`)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuario_areas (
  usuario_id INT NOT NULL,
  area       VARCHAR(40) NOT NULL,
  PRIMARY KEY (usuario_id, area),
  KEY idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usuario_sesion (
  id         INT NOT NULL AUTO_INCREMENT,
  usuario_id INT NOT NULL,
  token      CHAR(64) NOT NULL,
  expira     DATETIME NOT NULL,
  creado     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_token (token),
  KEY idx_usuario (usuario_id),
  KEY idx_expira (expira)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 5. Registrar el dominio nuevo con su giraid.
--    EDITA los valores antes de correr (o hazlo después desde /admin).
-- ---------------------------------------------------------------------------
-- INSERT INTO site_config (domain, giraid) VALUES ('pruebas.golftour.mx', 19)
--   ON DUPLICATE KEY UPDATE giraid = VALUES(giraid);
