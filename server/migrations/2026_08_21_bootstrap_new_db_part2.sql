-- ===========================================================================
-- BOOTSTRAP PARTE 2 — resto de tablas "propias de la app" + ALTERs a `registro`
-- ---------------------------------------------------------------------------
-- Correr DESPUÉS de 2026_08_21_bootstrap_new_db.sql (que crea site_config,
-- convocatoria_content, registro_form_fields, usuario_areas, usuario_sesion).
--
-- Este archivo deja lista una BD nueva (ej. `golftour`) para:
--   /registro (precios, tipos de socio, registro preferente, elegibilidad)
--   /reglas y /convocatoria (usan convocatoria_content — parte 1)
--   /banderas, /premios, /patrocinadores, menú dinámico
--   Brackets de Putt Finales
--   Dropdowns país/estado/ciudad
--
-- IDEMPOTENTE: CREATE TABLE IF NOT EXISTS + ALTERs vía SQL dinámico.
-- SIN GRANTs (hosting compartido).
--
-- NOTA: NO hay tabla `reglas_config`. Las reglas locales y condiciones de
-- competencia viven en `convocatoria_content` con section_id
-- 'reglas_intro_cards' y 'reglas_locales' (creada en la parte 1).
-- ===========================================================================

-- ---------------------------------------------------------------------------
-- 1. Pre-Registro · precios
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `registro_precios` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `torneo_id`   INT(11) NOT NULL,
  `categoria`   VARCHAR(120) DEFAULT NULL,
  `tipo_socio`  VARCHAR(20)  DEFAULT NULL COMMENT 'TITULAR/EMERITO/DEPENDIENTE/INVITADO; NULL = comodín',
  `genero`      VARCHAR(2)   DEFAULT NULL COMMENT 'M/F; NULL = comodín',
  `edad_min`    INT(11)      DEFAULT NULL,
  `edad_max`    INT(11)      DEFAULT NULL,
  `hcp_min`     DECIMAL(5,1) DEFAULT NULL,
  `hcp_max`     DECIMAL(5,1) DEFAULT NULL,
  `precio`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `moneda`      VARCHAR(6)   NOT NULL DEFAULT 'MXN',
  `etiqueta`    VARCHAR(160) NOT NULL DEFAULT '',
  `incluye`     TEXT DEFAULT NULL,
  `prioridad`   INT(11)      NOT NULL DEFAULT 0,
  `display_order` INT(11)    NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_torneo` (`torneo_id`),
  KEY `idx_match` (`torneo_id`,`tipo_socio`,`genero`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2. Pre-Registro · mapeo etiqueta de club -> tipo de socio del sistema
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `registro_socio_tipos` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `torneoid`    INT(11) NOT NULL,
  `etiqueta`    VARCHAR(120) NOT NULL COMMENT 'texto que ve el usuario (ej. "Socio Honorario")',
  `tipo_socio`  VARCHAR(20)  NOT NULL COMMENT 'TITULAR | EMERITO | DEPENDIENTE',
  `orden`       INT(11)      NOT NULL DEFAULT 0,
  `activo`      TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_torneo_etiqueta` (`torneoid`,`etiqueta`),
  KEY `idx_torneo` (`torneoid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. Pre-Registro · registro preferente (ventana global por torneo)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `registro_preferente_config` (
  `torneoid`      INT(11) NOT NULL,
  `activo`        TINYINT(1) NOT NULL DEFAULT 0,
  `fecha_inicio`  DATE DEFAULT NULL,
  `fecha_fin`     DATE DEFAULT NULL,
  `same_range`    TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = todos los clubes usan la ventana global',
  `mensaje`       TEXT DEFAULT NULL,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`torneoid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clubes autorizados por torneo (usada por /registro y clubs.php?action=torneo)
CREATE TABLE IF NOT EXISTS `clubs_registro` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `torneoid`     INT(11) NOT NULL,
  `clubid`       INT(11) NOT NULL,
  `fecha_inicio` DATE DEFAULT NULL,
  `fecha_fin`    DATE DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_torneo_club` (`torneoid`,`clubid`),
  KEY `idx_torneo` (`torneoid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 4. Pre-Registro · elegibilidad de categoría (hcp / edad / sexo)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categorias_reglas` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `torneo_id`    INT(11) NOT NULL,
  `categoria_id` INT(11) DEFAULT NULL,
  `categoria`    VARCHAR(45) DEFAULT NULL,
  `genero`       VARCHAR(8)  DEFAULT NULL,
  `hcp_min`      DECIMAL(5,1) DEFAULT NULL,
  `hcp_max`      DECIMAL(5,1) DEFAULT NULL,
  `edad_min`     INT(11) DEFAULT NULL,
  `edad_max`     INT(11) DEFAULT NULL,
  `nota`         VARCHAR(255) DEFAULT NULL,
  `display_order` INT(11) NOT NULL DEFAULT 0,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_torneo` (`torneo_id`),
  KEY `idx_cat` (`categoria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 5. Banderas (pin sheet)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `banderas` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `torneoid`   INT(11) NOT NULL,
  `fecha`      DATE DEFAULT NULL,
  `hoyo`       INT(11) NOT NULL,
  `pos_x`      DECIMAL(6,2) DEFAULT NULL COMMENT 'pasos desde el borde (izq/der)',
  `pos_y`      DECIMAL(6,2) DEFAULT NULL COMMENT 'pasos desde el frente',
  `color`      VARCHAR(20) DEFAULT NULL,
  `nota`       VARCHAR(255) DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_torneo_fecha_hoyo` (`torneoid`,`fecha`,`hoyo`),
  KEY `idx_torneo` (`torneoid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 6. Menú dinámico (menu.php) y patrocinadores (sponsors.php)
--    Ambos endpoints degradan a vacío si la tabla no existe, pero se crean
--    para poder administrarlos igual que en la instalación original.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `menu` (
  `id`       INT(11) NOT NULL AUTO_INCREMENT,
  `torneoid` INT(11) NOT NULL,
  `nombre`   VARCHAR(80)  NOT NULL,
  `grupo`    VARCHAR(60)  NOT NULL DEFAULT '',
  `url`      VARCHAR(160) NOT NULL DEFAULT '/',
  `icono`    VARCHAR(60)  DEFAULT NULL,
  `tipo`     VARCHAR(30)  NOT NULL DEFAULT 'link',
  `orden`    INT(11)      NOT NULL DEFAULT 0,
  `visible`  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_torneo` (`torneoid`,`visible`),
  KEY `idx_orden` (`grupo`,`orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patrocinadores` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `torneoid`    INT(11) NOT NULL,
  `nombre`      VARCHAR(120) NOT NULL DEFAULT '',
  `logo`        VARCHAR(255) DEFAULT NULL COMMENT 'ruta relativa del archivo',
  `logo_nombre` VARCHAR(255) DEFAULT NULL COMMENT 'esquema viejo: sólo nombre de archivo',
  `contacto`    VARCHAR(160) DEFAULT NULL,
  `url`         VARCHAR(255) DEFAULT NULL,
  `orden`       INT(11) NOT NULL DEFAULT 0,
  `estatus`     INT(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_torneo` (`torneoid`,`estatus`),
  KEY `idx_orden` (`orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 7. Brackets (Putt Finales / Match Play)
--    Estructura ya con las columnas de 2026_05_18_putt_finales_brackets.sql.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bracket_config` (
  `id`               INT(11) NOT NULL AUTO_INCREMENT,
  `torneoid`         INT(11) NOT NULL,
  `prize_table`      ENUM('oyes','oyesx','approach','putt','driver','driverp','putt_finales') NOT NULL DEFAULT 'putt_finales',
  `prize_id`         INT(11) NOT NULL DEFAULT 1,
  `sexo`             CHAR(1) DEFAULT NULL COMMENT 'M = Caballero, F = Dama',
  `bracket_size`     INT(11) NOT NULL DEFAULT 16,
  `seed_source`      VARCHAR(40) DEFAULT NULL,
  `seed_categoriaid` INT(11) DEFAULT NULL,
  `seed_premio`      INT(11) DEFAULT NULL,
  `seed_hoyo`        INT(11) DEFAULT NULL,
  `seed_campo`       INT(11) DEFAULT NULL,
  `advance_mode`     VARCHAR(30) DEFAULT NULL,
  `status`           ENUM('pending','seeded','in_progress','completed') NOT NULL DEFAULT 'pending',
  `visible`          TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bracket` (`torneoid`,`prize_table`,`prize_id`),
  KEY `idx_torneo` (`torneoid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bracket_matches` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `bracket_id`    INT(11) NOT NULL,
  `round_num`     INT(11) NOT NULL,
  `match_num`     INT(11) NOT NULL COMMENT '99 = match por 3er lugar',
  `p1_jugadorid`  INT(11) DEFAULT NULL,
  `p2_jugadorid`  INT(11) DEFAULT NULL,
  `p1_score`      INT(11) DEFAULT NULL,
  `p2_score`      INT(11) DEFAULT NULL,
  `winner_slot`   TINYINT(1) DEFAULT NULL COMMENT '1 o 2',
  `next_match_id` INT(11) DEFAULT NULL,
  `next_slot`     TINYINT(1) DEFAULT NULL,
  `status`        VARCHAR(20) NOT NULL DEFAULT 'pending',
  `updated_at`    DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_match` (`bracket_id`,`round_num`,`match_num`),
  KEY `idx_bracket` (`bracket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 8. Dropdowns de ubicación (locations.php). Vacías = el form usa texto libre.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `countries` (
  `id`   INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `states` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `id_country` INT(11) NOT NULL,
  `name`       VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_country` (`id_country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cities` (
  `id`       INT(11) NOT NULL AUTO_INCREMENT,
  `id_state` INT(11) NOT NULL,
  `name`     VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_state` (`id_state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 9. ALTERs a la tabla legacy `registro` — columnas que agregó la app nueva.
--    La `registro` de esta BD trae sólo las columnas originales.
-- ---------------------------------------------------------------------------
-- Sin CREATE PROCEDURE (evita DELIMITER en Workbench/MariaDB): se listan las
-- columnas en una tabla temporal y se aplica un solo ALTER con las que falten.
DROP TEMPORARY TABLE IF EXISTS tmp_reg_cols;
CREATE TEMPORARY TABLE tmp_reg_cols (col VARCHAR(64) PRIMARY KEY, def TEXT NOT NULL);

INSERT INTO tmp_reg_cols (col, def) VALUES
  -- Socio / identificación
  ('reg_es_socio',          'VARCHAR(2) DEFAULT NULL'),
  ('reg_tipo_socio',        'VARCHAR(20) DEFAULT NULL'),
  ('reg_numsocio',          'VARCHAR(45) DEFAULT NULL'),
  ('reg_cargo_socio',       'VARCHAR(2) DEFAULT NULL'),
  ('reg_ghin',              'VARCHAR(45) DEFAULT NULL'),
  ('reg_sexo',              'VARCHAR(1) DEFAULT NULL'),
  ('reg_fechanac',          'DATE DEFAULT NULL'),
  ('reg_edad',              'INT(11) DEFAULT NULL'),
  ('reg_talla_gorra',       'VARCHAR(12) DEFAULT NULL'),
  -- Precio / pago
  ('reg_precio_estimado',   'DECIMAL(10,2) DEFAULT NULL'),
  ('reg_precio_regla_id',   'INT(11) DEFAULT NULL'),
  ('reg_precio_moneda',     "VARCHAR(6) DEFAULT 'MXN'"),
  ('reg_monto_confirmado',  'DECIMAL(10,2) DEFAULT NULL'),
  ('reg_pago_verificado',   'TINYINT(1) NOT NULL DEFAULT 0'),
  ('reg_notas',             'TEXT DEFAULT NULL'),
  -- Comprobante y token público
  ('reg_archivo_mime',      'VARCHAR(120) DEFAULT NULL'),
  ('reg_token',             'CHAR(64) DEFAULT NULL'),
  -- Control de correos
  ('reg_email_count',       'INT(11) NOT NULL DEFAULT 0'),
  ('reg_email_last',        'DATETIME DEFAULT NULL'),
  ('reg_welcome_count',     'INT(11) NOT NULL DEFAULT 0'),
  ('reg_welcome_last',      'DATETIME DEFAULT NULL'),
  -- Auditoría de hora del cliente
  ('reg_client_utc',        'DATETIME DEFAULT NULL'),
  ('reg_client_tz_offset',  'INT(11) DEFAULT NULL'),
  -- Campos opcionales usados por torneos con kit (Akron)
  ('akron_talla',           'VARCHAR(12) DEFAULT NULL'),
  ('akron_talla_guante',    'VARCHAR(12) DEFAULT NULL'),
  ('akron_calzado',         'VARCHAR(12) DEFAULT NULL'),
  ('akron_edad',            'INT(11) DEFAULT NULL'),
  ('akron_codigo',          'VARCHAR(45) DEFAULT NULL'),
  ('akron_codigo_admin',    'VARCHAR(45) DEFAULT NULL'),
  ('akron_monto_pago',      'DECIMAL(10,2) DEFAULT NULL');

SET @missing_reg = (
  SELECT GROUP_CONCAT(CONCAT('ADD COLUMN `', t.col, '` ', t.def) SEPARATOR ', ')
  FROM tmp_reg_cols t
  WHERE NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS ic
    WHERE ic.TABLE_SCHEMA = DATABASE()
      AND ic.TABLE_NAME   = 'registro'
      AND ic.COLUMN_NAME  = t.col
  )
);

SET @has_registro = (SELECT COUNT(*) FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registro');

SET @s = IF(@has_registro = 0 OR @missing_reg IS NULL OR @missing_reg = '',
            'SELECT ''registro: sin cambios pendientes'' AS info',
            CONCAT('ALTER TABLE `registro` ', @missing_reg));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

DROP TEMPORARY TABLE IF EXISTS tmp_reg_cols;

-- ---------------------------------------------------------------------------
-- 10. Match Play: columna de 3er lugar (sólo si existe elimin_salidas_cat)
--     En esta BD la tabla NO existe; se deja documentado para cuando se
--     habilite el módulo Match Play.
-- ---------------------------------------------------------------------------
-- ALTER TABLE `elimin_salidas_cat` ADD COLUMN `tl_grupo` INT NULL DEFAULT NULL;
-- CREATE INDEX `idx_elimin_tl_grupo` ON `elimin_salidas_cat` (`catid`,`tl_grupo`);
