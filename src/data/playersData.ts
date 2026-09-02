/**
 * Players Data Types & Fallback Helpers
 * Type definitions for players and categories
 * Actual fetching is done via React Query hooks in usePlayersData.ts
 */

// ============= Category Interface =============
/** Represents a tournament category as returned by categories.php */
export interface CategoryDetail {
  id: string;              // categoria_id from DB
  name: string;            // Full category name
  shortName: string;       // Abbreviated name
  system: string;          // Scoring system
  format: string;          // Competition format
  style: string;           // Style descriptor
  hcpMin: number;          // Min handicap index
  hcpMax: number;          // Max handicap index
  percentage: number;      // Handicap percentage applied
  /** Valor textual exacto de `categorias.porcentaje` en la BD (ej. "80.00").
   *  Se usa para mostrar VENTAJAS con el mismo redondeo/decimales que la BD. */
  percentageRaw?: string | null;
  holes: number;           // Holes to play
  cutHoles: number;        // Cut holes
  /** Final cut count (categorias.corte) — number of players advancing. */
  finalCut?: number;
  teeId: string;           // Tee identifier
  gross: number;           // Gross flag
  relatedCat: string;      // Related category ID
  teeColor: string;        // Tee color name (legacy)
  teeName: string;         // Tee name (e.g. "AZULES")
  teeColorName: string;    // Tee color from salidas table
  rating: number | null;   // Course rating
  slope: number | null;    // Course slope
  par: number | null;      // Course par
  playerCount: number;     // Number of registered players
  /** Maximum players allowed in the category (99 = unlimited) */
  maxPlayers?: number;
  /** Number of players already registered via Pre-Registro flow
   *  (jugadores.tipoinsc=1 AND tipoinsc2=3). Used to compute available
   *  spots in the public registration form. */
  registeredCount?: number;
  /** Holes per round (e.g. 9 or 18) — used for "RONDA" display */
  holesPerRound?: number;
  /** Optional gender restriction (e.g. 'M', 'F', or empty for both). */
  gender?: string;
  /** Optional age-range minimum (used to flag senior/age-restricted categories). */
  ageMin?: number | null;
  /** Optional age-range maximum. */
  ageMax?: number | null;
  /** Skin game grouping identifier (categorias.Skin_grupo_id). Categorías
   *  que comparten Skin_grupo_id juegan la misma bolsa de skins. */
  skinGroupId?: string;
  /** Porcentaje de handicap aplicado exclusivamente al Skin Game
   *  (categorias.Skeenporcent). Puede diferir de `percentage`. */
  skinPercent?: number;
}

// ============= Player Interface =============
/** Represents a player with club logo URL */
export interface Player {
  id: string;
  clubLogo: string;        // Full URL of the club logo image
  name: string;            // Player full name
  handicapIndex: number;   // HI - Handicap Index
  handicapJuego: number;   // HJ - Handicap de Juego
  handicapNeto: number;    // HN - Handicap Neto
  categoryId: string;      // Category ID reference
  /** Grupo de parejas (jugadores.grupoid). Vacío para categorías individuales. */
  grupoid?: string;
  /** Fecha de nacimiento (YYYY-MM-DD) — mostrada en /jugadores y /field-gira. */
  fechanac?: string;
}

// ============= Parejas Grouping =============
/**
 * Agrupación de jugadores por `grupoid` (ej. "C24" → "Grupo C24").
 * Solo se construye en el frontend cuando la respuesta de players.php trae
 * `isParejas: true`.
 */
export interface ParejaGroup {
  /** Identificador del grupo (jugadores.grupoid). Usado como display label. */
  grupoid: string;
  /** Suma de HN de los jugadores del grupo (clave de ordenamiento ascendente). */
  handicapTotal: number;
  /** Jugadores que componen la pareja (típicamente 2). */
  players: Player[];
}
