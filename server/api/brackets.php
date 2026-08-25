<?php
/**
 * Brackets Endpoint — Putt Finales (Caballero / Dama)
 * ----------------------------------------------------------------------------
 * Maneja dos brackets fijos por torneo, sembrados desde el ranking acumulado
 * de putt a lo largo del torneo:
 *
 *   prize_table = 'putt_finales', prize_id = 1, sexo = 'M' → Caballero
 *   prize_table = 'putt_finales', prize_id = 2, sexo = 'F' → Dama
 *
 * Tablas requeridas (ya existen): bracket_config, bracket_matches.
 * Columnas nuevas en bracket_config (ver migrations/2026_05_18_putt_finales_brackets.sql):
 *   - sexo CHAR(1) NULL
 *   - size INT NOT NULL DEFAULT 16
 *   - visible TINYINT(1) NOT NULL DEFAULT 0
 *
 * Ruteo (acción por ?action=):
 *   GET  ?torneoid=X&action=get_putt_finales       (público)
 *        → { M: {config, matches, visible}, F: {...} }
 *   GET  ?torneoid=X&action=get_putt_admin         (admin)
 *        → mismo shape, además incluye candidate count por sexo
 *   POST ?action=save_putt_config                  (admin)
 *        body: { torneoid, sexo, size, visible, password }
 *   POST ?action=generate_putt                     (admin)
 *        body: { torneoid, sexo, password }
 *        Regenera bracket_matches sembrando con ranking acumulado por sexo.
 *   POST ?action=record_score                      (admin)
 *        body: { match_id, player1_score, player2_score, password }
 *        Captura scores; ganador = mayor score; avanza al next_match_id.
 *   POST ?action=set_winner                        (admin)
 *        body: { match_id, winner_id, password }
 *        Override manual (ej. walkover / corrección).
 *   POST ?action=enable_third_place                 (admin)
 *        body: { torneoid, sexo, password }
 *        Crea (idempotente) el match por 3er lugar (round_num = última ronda,
 *        match_num = 99) y siembra a los perdedores de las dos semifinales.
 */

require_once 'config.php';
require_once '_staff_auth.php';

// ============= CORS — permitimos POST =============
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// ============= Constantes =============
/** Tamaños de bracket permitidos (potencias de 2). */
$ALLOWED_SIZES = [8, 16, 32, 64, 128];
/** Contraseña admin — alineada con el resto del panel. */
$ADMIN_PASSWORD = 'admin2025';
/** Identificador convencional del par de brackets putt-finales. */
$PUTT_PRIZE_TABLE = 'putt_finales';

// ============= Helpers =============

/** Lee body JSON del POST. */
function read_json_body() {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

/** Verifica password admin en body; falla con 401. */
function require_admin($body) {
    global $ADMIN_PASSWORD;
    global $conn;
    if (!isset($body['password']) || !is_superadmin_password($conn, $body['password'])) {
        $staff = staff_check_area($conn, $body, 'brackets');
        if (!$staff) json_error('Unauthorized — admin password required', 401);
    }
}

/**
 * Normaliza sexo a 'M', 'F' o 'A'; falla si distinto.
 * 'A' = bracket ÚNICO/unificado (una sola competición Putt Finales sin
 * separar Caballero/Dama). Se usa cuando el torneo compite en un solo bracket.
 */
function require_sexo($val) {
    $s = strtoupper((string)$val);
    if ($s !== 'M' && $s !== 'F' && $s !== 'A') {
        json_error("Invalid sexo '$val' — debe ser 'M', 'F' o 'A'", 400);
    }
    return $s;
}

/** prize_id convencional según sexo (1=M, 2=F, 3=A/unificado). */
function prize_id_for_sexo($sexo) {
    if ($sexo === 'M') return 1;
    if ($sexo === 'F') return 2;
    return 3;
}

/** Query single-row seguro (logea, no muere). */
function safe_one($conn, $sql) {
    $r = $conn->query($sql);
    if (!$r) { error_log('brackets.php query failed: ' . $conn->error . ' | SQL: ' . $sql); return null; }
    $row = $r->fetch_assoc();
    $r->free();
    return $row;
}

/** Query all rows seguro. */
function safe_all($conn, $sql) {
    $r = $conn->query($sql);
    if (!$r) { error_log('brackets.php query failed: ' . $conn->error . ' | SQL: ' . $sql); return []; }
    $out = [];
    while ($row = $r->fetch_assoc()) $out[] = $row;
    $r->free();
    return $out;
}

/**
 * Construye el orden estándar 1-vs-N de seeds para un bracket de $size.
 * Devuelve array de pares [seedA, seedB] para round 1 (1vsN, 8vs9, etc.).
 */
function build_seed_pairs($size) {
    $order = [1, 2];
    while (count($order) < $size) {
        $newOrder = [];
        $sum = count($order) * 2 + 1;
        foreach ($order as $s) {
            $newOrder[] = $s;
            $newOrder[] = $sum - $s;
        }
        $order = $newOrder;
    }
    $pairs = [];
    for ($i = 0; $i < count($order); $i += 2) {
        $pairs[] = [$order[$i], $order[$i + 1]];
    }
    return $pairs;
}

// ============= Ranking acumulado de putt (sembrado) =============
/**
 * Marca la mejor distancia de cada jugador dentro de cada grupo Putt.
 * Es el mismo criterio usado por `competencias.php`, por eso brackets y tablas
 * públicas leen del mismo universo de jugadores.
 */
function refresh_putt_best_flags($conn, $torneoid) {
    $tid = (int)$torneoid;
    $conn->query("UPDATE puttjug SET orden = 0 WHERE torneoid = $tid");
    $conn->query("UPDATE puttjug a
                  JOIN (
                      SELECT torneoid, premio, premiosjugcol, jugadorid, MIN(distancia) AS mindistancia
                      FROM puttjug
                      WHERE torneoid = $tid
                      GROUP BY torneoid, premio, premiosjugcol, jugadorid
                  ) b ON (a.torneoid = b.torneoid
                          AND a.premio = b.premio
                          AND a.premiosjugcol <=> b.premiosjugcol
                          AND a.jugadorid = b.jugadorid
                          AND a.distancia = b.mindistancia)
                  SET a.orden = 1
                  WHERE a.torneoid = $tid");
}

/**
 * Construye el ranking para sembrar brackets desde `putt` + `puttjug`.
 *
 * Flujo:
 *   1) Lee cada grupo configurado en `putt` por PREMIO + descripción.
 *   2) Toma los mejores `hoyo` jugadores de `puttjug` para ese grupo.
 *   3) Filtra por `jugadores.sexo` M/F.
 *   4) Deduplica por jugador conservando su mejor distancia global.
 *   5) Ordena por distancia ASC y corta al tamaño del bracket.
 *
 * Devuelve filas normalizadas con `jugadorid`, `jugador`, `categoria`,
 * `distancia` y `ultact`, que son las llaves que usa `action_generate_putt`.
 */
function collect_putt_ranking($conn, $torneoid, $sexo, $limit) {
    $tid = (int)$torneoid;
    $sx  = esc($conn, strtoupper((string)$sexo));
    $lim = max(1, (int)$limit);
    /**
     * En modo bracket ÚNICO ('A') no se filtra por sexo: el ranking mezcla
     * caballeros y damas en una sola competición.
     */
    $sexoFilter = ($sx === 'A') ? '' : "AND UPPER(TRIM(j.sexo)) = '$sx'";

    refresh_putt_best_flags($conn, $tid);

    // Catálogo de grupos Putt: incluye descripción porque premio puede repetirse
    // para Damas/Caballeros u otros cortes y `premiosjugcol` separa esos listados.
    $sqlPrizes = "SELECT p.premio AS premio,
                         TRIM(p.descripcion) AS descripcion,
                         MIN(NULLIF(p.hoyo, 0)) AS lugares
                  FROM putt p
                  WHERE p.torneoid = $tid AND p.premio > 0
                  GROUP BY p.premio, p.descripcion
                  ORDER BY p.premio ASC";
    $prizes = safe_all($conn, $sqlPrizes);
    if (empty($prizes)) return [];

    $rankingByPlayer = [];
    $sequence = 0;
    foreach ($prizes as $p) {
        $premio = (int)$p['premio'];
        $desc   = esc($conn, trim((string)($p['descripcion'] ?? '')));
        $places = (int)($p['lugares'] ?? 0);
        if ($premio <= 0 || $places <= 0) continue;

        $rows = safe_all($conn, "SELECT a.jugadorid AS jugadorid,
                                        CONCAT(j.nombre, ' ', j.apellido) AS jugador,
                                        COALESCE(NULLIF(cat.abreviatura,''), cat.categoria, a.premiosjugcol) AS categoria,
                                        a.distancia AS distancia,
                                        /**
                                         * `puttjug.ultact` es DATETIME con fecha + hh:mm:ss real de captura.
                                         * `puttjug.fecha` es sólo DATE (sin hora), por eso se usa ultact
                                         * tanto para mostrar como para ordenar empates por distancia.
                                         */
                                        a.ultact AS fecha,
                                        DATE_FORMAT(a.ultact, '%Y-%m-%d %H:%i:%s') AS fecha_full,
                                        a.id AS rowid
                                 FROM puttjug a
                                 JOIN jugadores j ON j.id = a.jugadorid
                                 LEFT JOIN categorias cat ON cat.categoria_id = j.categoriaid
                                 WHERE a.torneoid = $tid
                                   AND a.premio = $premio
                                   AND TRIM(a.premiosjugcol) = '$desc'
                                   AND a.orden = 1
                                   $sexoFilter
                                 ORDER BY a.distancia ASC, a.ultact ASC, a.id ASC
                                 LIMIT $places");

        foreach ($rows as $row) {
            $jid = (int)($row['jugadorid'] ?? 0);
            if ($jid <= 0) continue;
            $row['_seq'] = $sequence++;
            $existing = $rankingByPlayer[$jid] ?? null;
            if (!$existing || (float)$row['distancia'] < (float)$existing['distancia']) {
                $rankingByPlayer[$jid] = $row;
            }
        }
    }

    $ranking = array_values($rankingByPlayer);
    usort($ranking, function ($a, $b) {
        $byDistance = (float)$a['distancia'] <=> (float)$b['distancia'];
        if ($byDistance !== 0) return $byDistance;
        // Empate en distancia → la fecha/hora MÁS ANTIGUA gana (registrado primero).
        $fa = (string)($a['fecha_full'] ?? $a['fecha'] ?? '');
        $fb = (string)($b['fecha_full'] ?? $b['fecha'] ?? '');
        $byFecha = strcmp($fa, $fb);
        if ($byFecha !== 0) return $byFecha;
        return ((int)$a['_seq'] <=> (int)$b['_seq']);
    });
    return array_slice($ranking, 0, $lim);
}

/**
 * Re-siembra round 1 cuando un bracket existente fue generado con slots vacíos.
 * No toca brackets que ya tienen scores/ganadores para evitar pisar resultados.
 */
function repair_empty_seed_slots($conn, $cfg, $sexo) {
    if (!$cfg) return;
    $cfgId = (int)$cfg['id'];
    $tid   = (int)$cfg['torneoid'];
    $size  = (int)($cfg['bracket_size'] ?? $cfg['size'] ?? 0);
    if ($cfgId <= 0 || $tid <= 0 || $size <= 0) return;

    $progress = safe_one($conn, "SELECT COUNT(*) AS cnt
                                FROM bracket_matches
                                WHERE bracket_id = $cfgId
                                  AND (winner_player_id IS NOT NULL
                                       OR score_high IS NOT NULL
                                       OR score_low IS NOT NULL)");
    if ((int)($progress['cnt'] ?? 0) > 0) return;

    $seeded = safe_one($conn, "SELECT SUM((player_high_id IS NOT NULL) + (player_low_id IS NOT NULL)) AS cnt
                              FROM bracket_matches
                              WHERE bracket_id = $cfgId AND round_num = 1");
    $ranking = collect_putt_ranking($conn, $tid, $sexo, $size);
    $expectedPlayers = min($size, count($ranking));
    if ((int)($seeded['cnt'] ?? 0) >= $expectedPlayers || $expectedPlayers <= 0) return;

    $players = array_slice($ranking, 0, $size);
    while (count($players) < $size) $players[] = ['jugadorid' => null];

    foreach (build_seed_pairs($size) as $i => $pair) {
        $pos = $i + 1;
        $p1 = $players[$pair[0] - 1]['jugadorid'] ?? null;
        $p2 = $players[$pair[1] - 1]['jugadorid'] ?? null;
        $p1Sql = $p1 !== null ? (int)$p1 : 'NULL';
        $p2Sql = $p2 !== null ? (int)$p2 : 'NULL';
        $s1 = (int)$pair[0];
        $s2 = (int)$pair[1];
        $conn->query("UPDATE bracket_matches
                      SET player_high_id = $p1Sql, player_low_id = $p2Sql,
                          seed_high = $s1, seed_low = $s2, updated_at = NOW()
                      WHERE bracket_id = $cfgId AND round_num = 1 AND match_num = $pos");
        $matchRow = safe_one($conn, "SELECT id FROM bracket_matches WHERE bracket_id = $cfgId AND round_num = 1 AND match_num = $pos");
        $matchId = (int)($matchRow['id'] ?? 0);
        if ($matchId > 0 && $p1 !== null && $p2 === null)      advance_winner($conn, $matchId, (int)$p1);
        elseif ($matchId > 0 && $p2 !== null && $p1 === null)  advance_winner($conn, $matchId, (int)$p2);
    }
}

/**
 * Backfill de seeds en rondas avanzadas.
 * Recorre todos los matches con ganador definido y, si el next_match
 * destino tiene el seed correspondiente en NULL pero el jugador ya está
 * colocado, lo rellena con el seed del ganador en su match origen.
 * Esto repara brackets generados antes de propagar seeds.
 */
function backfill_advanced_seeds($conn, $cfgId) {
    $cfgId = (int)$cfgId;
    if ($cfgId <= 0) return;
    $rows = safe_all($conn, "SELECT id, winner_player_id, player_high_id, player_low_id,
                                    seed_high, seed_low, next_match_id, next_slot
                             FROM bracket_matches
                             WHERE bracket_id = $cfgId
                               AND winner_player_id IS NOT NULL
                               AND next_match_id IS NOT NULL");
    foreach ($rows as $r) {
        $wid = (int)$r['winner_player_id'];
        $seed = null;
        if ((int)$r['player_high_id'] === $wid) $seed = $r['seed_high'];
        elseif ((int)$r['player_low_id'] === $wid) $seed = $r['seed_low'];
        if ($seed === null || $seed === '') continue;
        $nextId  = (int)$r['next_match_id'];
        $seedCol = ($r['next_slot'] === 'high') ? 'seed_high' : 'seed_low';
        $playerCol = ($r['next_slot'] === 'high') ? 'player_high_id' : 'player_low_id';
        $seedInt = (int)$seed;
        $conn->query("UPDATE bracket_matches
                      SET $seedCol = $seedInt
                      WHERE id = $nextId
                        AND $playerCol = $wid
                        AND ($seedCol IS NULL)");
    }
}

// ============= Acción: get_putt_finales (público) =============
/**
 * Devuelve la configuración + matches actuales para ambos brackets (M/F).
 * Sólo se incluye `visible=1` cuando el público debe verlo — el front decide
 * si mostrar o no en /competicion según ese flag.
 */
function action_get_putt_finales($conn, $torneoid) {
    global $PUTT_PRIZE_TABLE;
    $tid = (int)$torneoid;
    $out = ['M' => null, 'F' => null, 'A' => null];
    /** 'A' = bracket único (una sola competición Putt Finales). */
    foreach (['M' => 1, 'F' => 2, 'A' => 3] as $sx => $pid) {
        $cfg = safe_one($conn,
            "SELECT *, bracket_size AS size FROM bracket_config
             WHERE torneoid = $tid AND prize_table = '$PUTT_PRIZE_TABLE' AND prize_id = $pid LIMIT 1");
        $matches = [];
        if ($cfg) {
            repair_empty_seed_slots($conn, $cfg, $sx);
            /**
             * Backfill de seeds en rondas avanzadas: brackets generados con la
             * versión anterior no propagaban seed_high/seed_low al avanzar al
             * siguiente match, por lo que sólo Ronda 1 mostraba el número de
             * posición del jugador. Aquí los rellenamos a partir de la Ronda 1.
             */
            backfill_advanced_seeds($conn, (int)$cfg['id']);
            $cfgId = (int)$cfg['id'];
            $matches = safe_all($conn,
                "SELECT m.*,
                        m.player_high_id  AS player1_id,
                        m.player_low_id   AS player2_id,
                        m.seed_high       AS player1_seed,
                        m.seed_low        AS player2_seed,
                        m.score_high      AS player1_score,
                        m.score_low       AS player2_score,
                        m.winner_player_id AS winner_id,
                        m.round_num       AS round,
                        m.match_num       AS position,
                        CONCAT(j1.nombre,' ',j1.apellido) AS player1_name,
                        CONCAT(j2.nombre,' ',j2.apellido) AS player2_name
                 FROM bracket_matches m
                 LEFT JOIN jugadores j1 ON j1.id = m.player_high_id
                 LEFT JOIN jugadores j2 ON j2.id = m.player_low_id
                 WHERE m.bracket_id = $cfgId
                 ORDER BY m.round_num ASC, m.match_num ASC");
        }
        /**
         * Lista de clasificados (jugadores que ya entraron al ranking
         * acumulado para sembrar el bracket) — se muestra en /competicion
         * debajo del bracket para que el público vea cómo se va llenando
         * el cupo de 1..N cada día. Usa la misma query de seeding.
         */
        $size = $cfg ? (int)($cfg['bracket_size'] ?? $cfg['size'] ?? 0) : 0;
        $qualifiers = [];
        if ($size > 0) {
            $rk = collect_putt_ranking($conn, $tid, $sx, $size);
            $rank = 0;
            foreach ($rk as $r) {
                $rank++;
                $qualifiers[] = [
                    'rank'     => $rank,
                    'name'     => $r['jugador'] ?? '',
                    'distance' => isset($r['distancia']) ? (float)$r['distancia'] : null,
                    'fecha'    => $r['fecha'] ?? null,
                    /**
                     * Fecha+hora capturada en `puttjug.fecha` (DATETIME).
                     * Formato fijo `YYYY-MM-DD HH:MM:SS`; el front separa fecha/hora.
                     */
                    'fecha_full' => $r['fecha_full'] ?? null,
                    /** Categoría/grupo del jugador (puttjug.premiosjugcol). */
                    'categoria'  => $r['categoria'] ?? null,
                ];
            }
        }
        $out[$sx] = [
            'config'  => $cfg,
            'matches' => $matches,
            'visible' => $cfg ? (int)$cfg['visible'] === 1 : false,
            'qualifiers' => $qualifiers,
            'bracket_size' => $size,
        ];
    }
    json_response($out);
}

// ============= Acción: get_putt_admin (admin) =============
/**
 * Igual que get_putt_finales pero agrega `candidates_count` por sexo —
 * cuántos jugadores únicos podrían entrar al bracket según el ranking actual.
 * Útil para que el admin elija el tamaño correcto antes de generar.
 */
function action_get_putt_admin($conn, $torneoid) {
    global $PUTT_PRIZE_TABLE;
    $tid = (int)$torneoid;
    $out = ['M' => null, 'F' => null, 'A' => null];
    /** 'A' = bracket único (una sola competición Putt Finales). */
    foreach (['M' => 1, 'F' => 2, 'A' => 3] as $sx => $pid) {
        $cfg = safe_one($conn,
            "SELECT *, bracket_size AS size FROM bracket_config
             WHERE torneoid = $tid AND prize_table = '$PUTT_PRIZE_TABLE' AND prize_id = $pid LIMIT 1");
        $matches = [];
        if ($cfg) {
            repair_empty_seed_slots($conn, $cfg, $sx);
            $cfgId = (int)$cfg['id'];
            $matches = safe_all($conn,
                "SELECT m.*,
                        m.player_high_id  AS player1_id,
                        m.player_low_id   AS player2_id,
                        m.seed_high       AS player1_seed,
                        m.seed_low        AS player2_seed,
                        m.score_high      AS player1_score,
                        m.score_low       AS player2_score,
                        m.winner_player_id AS winner_id,
                        m.round_num       AS round,
                        m.match_num       AS position,
                        CONCAT(j1.nombre,' ',j1.apellido) AS player1_name,
                        CONCAT(j2.nombre,' ',j2.apellido) AS player2_name
                 FROM bracket_matches m
                 LEFT JOIN jugadores j1 ON j1.id = m.player_high_id
                 LEFT JOIN jugadores j2 ON j2.id = m.player_low_id
                 WHERE m.bracket_id = $cfgId
                 ORDER BY m.round_num ASC, m.match_num ASC");
        }
        // Conteo de candidatos potenciales (límite alto para ver el universo).
        $ranking = collect_putt_ranking($conn, $tid, $sx, 9999);
        $out[$sx] = [
            'config'           => $cfg,
            'matches'          => $matches,
            'visible'          => $cfg ? (int)$cfg['visible'] === 1 : false,
            'candidates_count' => count($ranking),
        ];
    }
    json_response($out);
}

// ============= Acción: save_putt_config (admin) =============
/**
 * Upsert de la fila bracket_config para un bracket (M o F).
 * Body: { torneoid, sexo, size, visible, password }.
 * NO regenera matches — sólo guarda configuración y visibilidad.
 */
function action_save_putt_config($conn, $body) {
    global $ALLOWED_SIZES, $PUTT_PRIZE_TABLE;
    require_admin($body);
    $tid     = (int)($body['torneoid'] ?? 0);
    if ($tid <= 0) json_error('Invalid torneoid', 400);
    $sexo    = require_sexo($body['sexo'] ?? '');
    $size    = (int)($body['size'] ?? 0);
    if (!in_array($size, $ALLOWED_SIZES, true)) {
        json_error('Invalid size; allowed: ' . implode(',', $ALLOWED_SIZES), 400);
    }
    $visible = !empty($body['visible']) ? 1 : 0;
    $pid     = prize_id_for_sexo($sexo);

    // Upsert por unique key (torneoid, prize_table, prize_id).
    // NOTE: La tabla legacy `bracket_config` usa `bracket_size` (no `size`)
    // y el ENUM de status es ('pending','seeded','in_progress','completed').
    $sql = "INSERT INTO bracket_config
              (torneoid, prize_table, prize_id, sexo, bracket_size,
               seed_source, status, visible, created_at, updated_at)
            VALUES
              ($tid, '$PUTT_PRIZE_TABLE', $pid, '$sexo', $size,
               'standings', 'pending', $visible, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
               sexo = VALUES(sexo),
               bracket_size = VALUES(bracket_size),
               visible = VALUES(visible),
               updated_at = NOW()";
    if (!$conn->query($sql)) {
        json_error('Save config failed: ' . $conn->error, 500);
    }

    $saved = safe_one($conn,
        "SELECT *, bracket_size AS size FROM bracket_config
         WHERE torneoid = $tid AND prize_table = '$PUTT_PRIZE_TABLE' AND prize_id = $pid LIMIT 1");
    json_response(['config' => $saved]);
}

// ============= Acción: set_putt_mode (admin) =============
/**
 * Sincroniza el modo de competición del Putt Finales y LIMPIA los brackets
 * que dejan de aplicar, para que no queden resultados viejos mezclados
 * cuando el admin alterna entre:
 *   - mode = 'single' → bracket único (sexo 'A', prize_id 3).
 *   - mode = 'dual'   → brackets por sexo (M=1, F=2).
 *
 * Para cada bracket INACTIVO:
 *   1. Borra todos sus `bracket_matches` (scores, ganadores, 3er lugar).
 *   2. Deja su `bracket_config` en visible = 0 y status = 'pending'
 *      (se conserva la fila y el tamaño para poder volver al modo anterior).
 *
 * Body: { torneoid, mode, password }.
 * Respuesta: { mode, active: [...], purged: { prize_id: matches_borrados } }.
 */
function action_set_putt_mode($conn, $body) {
    global $PUTT_PRIZE_TABLE;
    require_admin($body);
    $tid  = (int)($body['torneoid'] ?? 0);
    if ($tid <= 0) json_error('Invalid torneoid', 400);
    $mode = strtolower((string)($body['mode'] ?? ''));
    if ($mode !== 'single' && $mode !== 'dual') {
        json_error("Invalid mode '$mode' — debe ser 'single' o 'dual'", 400);
    }

    /** prize_ids activos vs. inactivos según el modo elegido. */
    $activeIds   = $mode === 'single' ? [3]    : [1, 2];
    $inactiveIds = $mode === 'single' ? [1, 2] : [3];

    $purged = [];
    foreach ($inactiveIds as $pid) {
        $cfg = safe_one($conn,
            "SELECT id FROM bracket_config
             WHERE torneoid = $tid AND prize_table = '$PUTT_PRIZE_TABLE' AND prize_id = $pid LIMIT 1");
        if (!$cfg) { $purged[$pid] = 0; continue; }
        $cfgId = (int)$cfg['id'];
        // 1) Fuera los matches viejos (incluye scores, ganadores y 3er lugar).
        $conn->query("DELETE FROM bracket_matches WHERE bracket_id = $cfgId");
        $purged[$pid] = (int)$conn->affected_rows;
        // 2) Oculta el bracket y lo regresa a estado inicial.
        $conn->query("UPDATE bracket_config
                      SET visible = 0, status = 'pending', updated_at = NOW()
                      WHERE id = $cfgId");
    }

    json_response([
        'mode'   => $mode,
        'active' => $activeIds,
        'purged' => $purged,
    ]);
}

// ============= Acción: generate_putt (admin) =============
/**
 * Regenera bracket_matches para un sexo: tira los matches anteriores,
 * recolecta ranking acumulado de putt (sembrado), crea la estructura
 * completa de partidos vinculados por next_match_id y siembra el round 1.
 */
function action_generate_putt($conn, $body) {
    global $PUTT_PRIZE_TABLE;
    require_admin($body);
    $tid  = (int)($body['torneoid'] ?? 0);
    if ($tid <= 0) json_error('Invalid torneoid', 400);
    $sexo = require_sexo($body['sexo'] ?? '');
    $pid  = prize_id_for_sexo($sexo);

    $cfg = safe_one($conn,
        "SELECT *, bracket_size AS size FROM bracket_config
         WHERE torneoid = $tid AND prize_table = '$PUTT_PRIZE_TABLE' AND prize_id = $pid LIMIT 1");
    if (!$cfg) json_error('Configura primero el tamaño del bracket antes de generar.', 404);

    $cfgId = (int)$cfg['id'];
    $size  = (int)$cfg['bracket_size'];

    // 1) Recolectar ranking sembrado (jugadorid en orden 1..size).
    $ranking = collect_putt_ranking($conn, $tid, $sexo, $size);
    $players = array_slice($ranking, 0, $size);
    while (count($players) < $size) {
        // Padding con BYE (jugadorid = null) para tamaños sin suficientes inscritos.
        $players[] = ['jugadorid' => null];
    }

    // 2) Wipe matches anteriores.
    $conn->query("DELETE FROM bracket_matches WHERE bracket_id = $cfgId");

    // 3) Crear matches por ronda (final primero para poder linkear next_match_id).
    $totalRounds = (int)log($size, 2);
    $matchIds = []; // [round][position] => id
    for ($round = $totalRounds; $round >= 1; $round--) {
        $matchesInRound = (int)pow(2, $totalRounds - $round);
        for ($pos = 1; $pos <= $matchesInRound; $pos++) {
            $nextId   = 'NULL';
            $nextSlot = 'NULL';
            if ($round < $totalRounds) {
                $parentPos  = (int)ceil($pos / 2);
                // slot enum en bracket_matches: 'high' | 'low'
                $parentSlot = (($pos - 1) % 2) === 0 ? "'high'" : "'low'";
                $nextId   = (int)$matchIds[$round + 1][$parentPos];
                $nextSlot = $parentSlot;
            }
            $sql = "INSERT INTO bracket_matches
                      (bracket_id, round_num, match_num, next_match_id, next_slot, status, updated_at)
                    VALUES ($cfgId, $round, $pos, $nextId, $nextSlot, 'pending', NOW())";
            if (!$conn->query($sql)) {
                json_error('Insert match failed: ' . $conn->error, 500);
            }
            $matchIds[$round][$pos] = $conn->insert_id;
        }
    }

    // 4) Poblar round 1 con seeds (1vsN, etc.) + auto-avance de BYEs.
    $pairs = build_seed_pairs($size);
    foreach ($pairs as $i => $pair) {
        $pos = $i + 1;
        $matchId = (int)$matchIds[1][$pos];
        $p1 = $players[$pair[0] - 1]['jugadorid'] ?? null;
        $p2 = $players[$pair[1] - 1]['jugadorid'] ?? null;
        $p1Sql = $p1 !== null ? (int)$p1 : 'NULL';
        $p2Sql = $p2 !== null ? (int)$p2 : 'NULL';
        $s1 = (int)$pair[0];
        $s2 = (int)$pair[1];
        $conn->query("UPDATE bracket_matches
                      SET player_high_id = $p1Sql, player_low_id = $p2Sql,
                          seed_high = $s1, seed_low = $s2, updated_at = NOW()
                      WHERE id = $matchId");
        // BYE: avanzar al jugador presente
        if ($p1 !== null && $p2 === null)      advance_winner($conn, $matchId, (int)$p1);
        elseif ($p2 !== null && $p1 === null)  advance_winner($conn, $matchId, (int)$p2);
    }

    $conn->query("UPDATE bracket_config SET status = 'seeded', updated_at = NOW() WHERE id = $cfgId");

    json_response([
        'ok'             => true,
        'config_id'      => $cfgId,
        'rounds'         => $totalRounds,
        'players_seeded' => count(array_filter($players, fn($p) => $p['jugadorid'] !== null)),
        'size'           => $size,
    ]);
}

// ============= Avance automático de ganador =============
/**
 * Marca ganador en $matchId y lo coloca en el slot correspondiente del
 * match padre (next_match_id / next_slot). NO recursa: si el padre
 * también queda completo, el siguiente record_score lo avanzará.
 */
function advance_winner($conn, $matchId, $winnerId) {
    $mid = (int)$matchId;
    $wid = (int)$winnerId;
    /**
     * Determinar el seed del ganador ANTES de marcar completed, para poder
     * propagarlo al siguiente match y mantener visible el número de seed
     * del jugador a través de TODAS las rondas (no sólo Ronda 1).
     */
    $cur = safe_one($conn, "SELECT player_high_id, player_low_id, seed_high, seed_low
                              FROM bracket_matches WHERE id = $mid");
    $winnerSeed = null;
    if ($cur) {
        if ((int)$cur['player_high_id'] === $wid) $winnerSeed = $cur['seed_high'];
        elseif ((int)$cur['player_low_id'] === $wid) $winnerSeed = $cur['seed_low'];
    }
    $conn->query("UPDATE bracket_matches
                  SET winner_player_id = $wid, status = 'completed', updated_at = NOW()
                  WHERE id = $mid");
    /**
     * Si este match es una semifinal y el bracket ya tiene habilitado el match
     * por 3er lugar (match_num = 99 en la última ronda), coloca al PERDEDOR en
     * el slot que le corresponde de ese match.
     */
    propagate_putt_third_place($conn, $mid, $wid);
    $row = safe_one($conn, "SELECT next_match_id, next_slot FROM bracket_matches WHERE id = $mid");
    if (!$row || $row['next_match_id'] === null) return;
    $nextId  = (int)$row['next_match_id'];
    // next_slot es ENUM 'high'|'low' -> mapea a la columna del player correspondiente
    $slotCol  = ($row['next_slot'] === 'high') ? 'player_high_id' : 'player_low_id';
    $seedCol  = ($row['next_slot'] === 'high') ? 'seed_high'      : 'seed_low';
    $seedSql  = ($winnerSeed === null || $winnerSeed === '') ? 'NULL' : (int)$winnerSeed;
    $conn->query("UPDATE bracket_matches
                  SET $slotCol = $wid, $seedCol = $seedSql, updated_at = NOW()
                  WHERE id = $nextId");
}

// ============= Helpers: match por 3er lugar (putt) =============
/**
 * Devuelve la última ronda "real" del bracket (excluye el match por 3er
 * lugar, que se guarda con match_num = 99 en esa misma ronda).
 */
function putt_last_round($conn, $bracketId) {
    $bid = (int)$bracketId;
    $r = safe_one($conn, "SELECT MAX(round_num) AS mx FROM bracket_matches
                          WHERE bracket_id = $bid AND match_num <> 99");
    return (int)($r['mx'] ?? 0);
}

/**
 * Devuelve la fila del match por 3er lugar del bracket (match_num = 99) o
 * null si aún no está habilitado.
 */
function putt_third_place_row($conn, $bracketId) {
    $bid = (int)$bracketId;
    return safe_one($conn, "SELECT * FROM bracket_matches
                            WHERE bracket_id = $bid AND match_num = 99 LIMIT 1");
}

/**
 * Coloca al perdedor de una semifinal en el match por 3er lugar.
 * Semifinal 1 (match_num = 1) → slot high; Semifinal 2 (match_num = 2) → low.
 * No hace nada si el 3er lugar no está habilitado o el match no es semifinal.
 */
function propagate_putt_third_place($conn, $matchId, $winnerId) {
    $mid = (int)$matchId;
    $wid = (int)$winnerId;
    $m = safe_one($conn, "SELECT bracket_id, round_num, match_num, player_high_id, player_low_id,
                                 seed_high, seed_low
                          FROM bracket_matches WHERE id = $mid");
    if (!$m) return;
    $bid = (int)$m['bracket_id'];
    $lastRound = putt_last_round($conn, $bid);
    if ($lastRound <= 1) return;
    // Semifinales = penúltima ronda, match_num 1 y 2.
    if ((int)$m['round_num'] !== $lastRound - 1) return;
    $mn = (int)$m['match_num'];
    if ($mn !== 1 && $mn !== 2) return;

    $third = putt_third_place_row($conn, $bid);
    if (!$third) return;
    $tid = (int)$third['id'];

    // Perdedor + su seed.
    $loser = ((int)$m['player_high_id'] === $wid) ? (int)$m['player_low_id'] : (int)$m['player_high_id'];
    $loserSeed = ((int)$m['player_high_id'] === $wid) ? $m['seed_low'] : $m['seed_high'];
    if ($loser <= 0) return;
    $slotCol = ($mn === 1) ? 'player_high_id' : 'player_low_id';
    $seedCol = ($mn === 1) ? 'seed_high'      : 'seed_low';
    $seedSql = ($loserSeed === null || $loserSeed === '') ? 'NULL' : (int)$loserSeed;
    $conn->query("UPDATE bracket_matches
                  SET $slotCol = $loser, $seedCol = $seedSql, updated_at = NOW()
                  WHERE id = $tid");
}

/**
 * Limpia el slot del match por 3er lugar cuando se resetea una semifinal.
 * Sólo borra si el slot todavía contiene al perdedor propagado.
 */
function clear_putt_third_place_slot($conn, $matchId, $loserId) {
    $mid = (int)$matchId;
    $lid = (int)$loserId;
    if ($lid <= 0) return;
    $m = safe_one($conn, "SELECT bracket_id, round_num, match_num FROM bracket_matches WHERE id = $mid");
    if (!$m) return;
    $bid = (int)$m['bracket_id'];
    $lastRound = putt_last_round($conn, $bid);
    if ((int)$m['round_num'] !== $lastRound - 1) return;
    $mn = (int)$m['match_num'];
    if ($mn !== 1 && $mn !== 2) return;
    $third = putt_third_place_row($conn, $bid);
    if (!$third) return;
    $tid = (int)$third['id'];
    $slotCol = ($mn === 1) ? 'player_high_id' : 'player_low_id';
    $seedCol = ($mn === 1) ? 'seed_high'      : 'seed_low';
    $scoreCol = ($mn === 1) ? 'score_high'    : 'score_low';
    $conn->query("UPDATE bracket_matches
                  SET $slotCol = NULL, $seedCol = NULL, $scoreCol = NULL,
                      winner_player_id = NULL, status = 'pending', updated_at = NOW()
                  WHERE id = $tid AND $slotCol = $lid");
}

// ============= Acción: enable_third_place (admin) =============
/**
 * Habilita el match por 3er lugar del bracket putt indicado (torneoid+sexo).
 * Crea la fila con round_num = última ronda y match_num = 99 (sin next_match)
 * y, si las semifinales ya tienen ganador, siembra a los perdedores.
 */
function action_enable_third_place($conn, $body) {
    global $PUTT_PRIZE_TABLE;
    require_admin($body);
    $tid  = (int)($body['torneoid'] ?? 0);
    if ($tid <= 0) json_error('Invalid torneoid', 400);
    $sexo = require_sexo($body['sexo'] ?? '');
    $pid  = prize_id_for_sexo($sexo);

    $cfg = safe_one($conn,
        "SELECT id FROM bracket_config
         WHERE torneoid = $tid AND prize_table = '$PUTT_PRIZE_TABLE' AND prize_id = $pid LIMIT 1");
    if (!$cfg) json_error('Bracket no configurado para este sexo.', 404);
    $bid = (int)$cfg['id'];

    $lastRound = putt_last_round($conn, $bid);
    if ($lastRound < 2) json_error('El bracket aún no tiene semifinales (genera el bracket primero).', 400);

    // 1) Crear la fila del 3er lugar si no existe.
    if (!putt_third_place_row($conn, $bid)) {
        $ok = $conn->query("INSERT INTO bracket_matches
                              (bracket_id, round_num, match_num, next_match_id, next_slot, status, updated_at)
                            VALUES ($bid, $lastRound, 99, NULL, NULL, 'pending', NOW())");
        if (!$ok) json_error('No se pudo crear el match por 3er lugar: ' . $conn->error, 500);
    }

    // 2) Sembrar perdedores de semis ya resueltas.
    $semis = safe_all($conn, "SELECT id, winner_player_id FROM bracket_matches
                              WHERE bracket_id = $bid AND round_num = " . ($lastRound - 1) . "
                                AND match_num IN (1,2)");
    foreach ($semis as $s) {
        if ($s['winner_player_id'] === null) continue;
        propagate_putt_third_place($conn, (int)$s['id'], (int)$s['winner_player_id']);
    }

    json_response(['ok' => true, 'bracket_id' => $bid, 'round_num' => $lastRound, 'match_num' => 99]);
}

// ============= Acción: record_score (admin) =============
/**
 * Captura scores del match. Acepta decimales (hasta 3). Si ambos scores
 * están presentes y son distintos, avanza al jugador con MENOR score
 * (semántica putt/stroke: menos es mejor; 0 es válido y es el mejor score).
 */
function action_record_score($conn, $body) {
    require_admin($body);
    $matchId = (int)($body['match_id'] ?? 0);
    if ($matchId <= 0) json_error('Invalid match_id', 400);
    /** Parse score conservando decimales; null sólo si está vacío/no enviado. 0 es válido. */
    $s1 = isset($body['player1_score']) && $body['player1_score'] !== '' && $body['player1_score'] !== null
        ? round((float)$body['player1_score'], 3) : null;
    $s2 = isset($body['player2_score']) && $body['player2_score'] !== '' && $body['player2_score'] !== null
        ? round((float)$body['player2_score'], 3) : null;
    $s1Sql = $s1 !== null ? (float)$s1 : 'NULL';
    $s2Sql = $s2 !== null ? (float)$s2 : 'NULL';

    $conn->query("UPDATE bracket_matches
                  SET score_high = $s1Sql, score_low = $s2Sql, updated_at = NOW()
                  WHERE id = $matchId");

    $m = safe_one($conn, "SELECT * FROM bracket_matches WHERE id = $matchId");
    if (!$m) json_error('Match not found', 404);

    if ($s1 !== null && $s2 !== null && $s1 !== $s2) {
        /** Menor score gana (golf): el mejor es el más bajo, 0 incluido. */
        $winner = $s1 < $s2 ? (int)$m['player_high_id'] : (int)$m['player_low_id'];
        if ($winner > 0) advance_winner($conn, $matchId, $winner);
    }
    json_response(['ok' => true]);
}

// ============= Acción: set_winner (admin, override manual) =============
/**
 * Sobreescribe el ganador del match sin tocar scores. Útil para walkovers,
 * descalificaciones o correcciones manuales del admin.
 */
function action_set_winner($conn, $body) {
    require_admin($body);
    $matchId  = (int)($body['match_id']  ?? 0);
    $winnerId = (int)($body['winner_id'] ?? 0);
    if ($matchId <= 0 || $winnerId <= 0) json_error('Invalid match_id or winner_id', 400);
    $m = safe_one($conn, "SELECT player_high_id, player_low_id FROM bracket_matches WHERE id = $matchId");
    if (!$m) json_error('Match not found', 404);
    if ((int)$m['player_high_id'] !== $winnerId && (int)$m['player_low_id'] !== $winnerId) {
        json_error('winner_id no corresponde a player1 ni player2 de este match', 400);
    }
    advance_winner($conn, $matchId, $winnerId);
    json_response(['ok' => true]);
}

// ============= Acción: reset_match (admin) =============
/**
 * Resetea un match: limpia scores y ganador, y borra al ganador del
 * slot correspondiente del next_match (si fue avanzado previamente).
 * NO recursa más allá del padre inmediato — si rondas posteriores ya
 * se jugaron, el admin debe resetearlas también una por una.
 */
function action_reset_match($conn, $body) {
    require_admin($body);
    $matchId = (int)($body['match_id'] ?? 0);
    if ($matchId <= 0) json_error('Invalid match_id', 400);

    $m = safe_one($conn, "SELECT id, winner_player_id, next_match_id, next_slot
                          FROM bracket_matches WHERE id = $matchId");
    if (!$m) json_error('Match not found', 404);

    /** 0) Si era semifinal, quitar a su perdedor del match por 3er lugar. */
    if ($m['winner_player_id'] !== null) {
        $cur = safe_one($conn, "SELECT player_high_id, player_low_id FROM bracket_matches WHERE id = $matchId");
        if ($cur) {
            $wid = (int)$m['winner_player_id'];
            $loser = ((int)$cur['player_high_id'] === $wid) ? (int)$cur['player_low_id'] : (int)$cur['player_high_id'];
            clear_putt_third_place_slot($conn, $matchId, $loser);
        }
    }

    /** 1) Si había ganador avanzado al next, limpiar ese slot. */
    if ($m['winner_player_id'] !== null && $m['next_match_id'] !== null) {
        $nextId    = (int)$m['next_match_id'];
        $slotCol   = ($m['next_slot'] === 'high') ? 'player_high_id' : 'player_low_id';
        $seedCol   = ($m['next_slot'] === 'high') ? 'seed_high'      : 'seed_low';
        $scoreCol  = ($m['next_slot'] === 'high') ? 'score_high'     : 'score_low';
        $wid = (int)$m['winner_player_id'];
        // Sólo limpiar si el slot todavía contiene a este ganador (no fue
        // sobreescrito manualmente por el admin con set_winner posterior).
        $conn->query("UPDATE bracket_matches
                      SET $slotCol = NULL, $seedCol = NULL, $scoreCol = NULL,
                          winner_player_id = NULL, status = 'pending', updated_at = NOW()
                      WHERE id = $nextId AND $slotCol = $wid");
    }

    /** 2) Limpiar el match actual: scores + ganador + estado. */
    $conn->query("UPDATE bracket_matches
                  SET score_high = NULL, score_low = NULL,
                      winner_player_id = NULL, status = 'pending', updated_at = NOW()
                  WHERE id = $matchId");

    json_response(['ok' => true]);
}

// ============= Router =============
$method = $_SERVER['REQUEST_METHOD'];
$action = optional_param('action', '');

if ($method === 'GET') {
    $torneoid = require_torneoid($conn);
    if ($action === 'get_putt_finales')      action_get_putt_finales($conn, $torneoid);
    elseif ($action === 'get_putt_admin')    action_get_putt_admin($conn, $torneoid);
    else json_error("Unknown GET action '$action'. Try get_putt_finales | get_putt_admin.", 400);
} elseif ($method === 'POST') {
    $body = read_json_body();
    if     ($action === 'save_putt_config') action_save_putt_config($conn, $body);
    elseif ($action === 'set_putt_mode')    action_set_putt_mode($conn, $body);
    elseif ($action === 'generate_putt')    action_generate_putt($conn, $body);
    elseif ($action === 'record_score')     action_record_score($conn, $body);
    elseif ($action === 'set_winner')       action_set_winner($conn, $body);
    elseif ($action === 'reset_match')      action_reset_match($conn, $body);
    elseif ($action === 'enable_third_place') action_enable_third_place($conn, $body);
    else json_error("Unknown POST action '$action'.", 400);
} else {
    json_error('Method not allowed', 405);
}