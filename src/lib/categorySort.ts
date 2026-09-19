/**
 * categorySort
 * --------------------------------------------------------------
 * Shared helper that produces a deterministic sort key for tournament
 * categories so that all matrix tables (Calendario, Horarios, plus their
 * embedded previews inside Bases) display rows in the same
 * canonical order requested by the user:
 *
 *   1. Primera (1RA)
 *   2. Letter categories: AA, A, B, C, D, ...
 *   3. Damas: DAMAS, DAMAS A, DAMAS B, ...
 *   4. Senior: SENIOR A, SENIOR B, SENIOR C, ...
 *   5. Novatos
 *   6. Anything else falls to the end, alphabetically.
 *
 * The function inspects category short/long names case-insensitively and
 * returns a numeric weight + secondary lexical key so `Array.sort` with
 * `(a,b)=>compareCategories(a,b)` yields the desired order.
 */

/** Group buckets used for the primary numeric weight. Lower = earlier. */
const GROUP = {
  PRIMERA: 0,
  LETTER:  1,
  DAMAS:   2,
  SENIOR:  3,
  NOVATOS: 4,
  OTHER:   5,
} as const;

/** Normalize a label for matching (uppercase, trimmed, accent-stripped). */
const norm = (s: string): string =>
  (s || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toUpperCase()
    .trim();

/**
 * Sub-rank for letter categories so AA precedes A, then B, C, D...
 * Returns a small number; lower comes first.
 */
const letterRank = (letters: string): number => {
  const L = letters.toUpperCase();
  if (L === 'AA') return 0;
  if (L.length === 1) {
    // 'A' -> 1, 'B' -> 2, ...
    return L.charCodeAt(0) - 'A'.charCodeAt(0) + 1;
  }
  // Multi-letter beyond AA → push after single letters but keep stable.
  return 100 + L.charCodeAt(0);
};

/**
 * Compute the [groupWeight, subWeight, fallbackLabel] sort key for a
 * category given its long name and (optional) short/abbreviation name.
 */
export const categorySortKey = (
  longName: string,
  shortName?: string,
): [number, number, string] => {
  const long  = norm(longName);
  const short = norm(shortName || '');
  const label = long || short;

  // ---- Primera ----------------------------------------------------------
  if (
    short === '1RA' ||
    long.startsWith('PRIMERA') ||
    long === '1RA' || long === '1ERA'
  ) {
    return [GROUP.PRIMERA, 0, label];
  }

  // ---- Novatos ----------------------------------------------------------
  if (long.startsWith('NOVATO') || short.startsWith('NOVA')) {
    return [GROUP.NOVATOS, 0, label];
  }

  // ---- Senior (SENIOR A / SEN A / etc.) ---------------------------------
  // Match either "SENIOR" or short codes starting with "SEN".
  const seniorMatch =
    long.match(/^SENIOR\s*([A-Z]{1,2})?/) ||
    short.match(/^SEN\s*([A-Z]{1,2})?/);
  if (seniorMatch) {
    const sub = seniorMatch[1] ? letterRank(seniorMatch[1]) : 0;
    return [GROUP.SENIOR, sub, label];
  }

  // ---- Damas (DAMAS / DAMAS A / DAM) ------------------------------------
  const damasMatch =
    long.match(/^DAMAS?\s*([A-Z]{1,2})?/) ||
    short.match(/^DAM\s*([A-Z]{1,2})?/);
  if (damasMatch) {
    const sub = damasMatch[1] ? letterRank(damasMatch[1]) : 0;
    return [GROUP.DAMAS, sub, label];
  }

  // ---- Letter categories (AA, A, B, C, D...) ----------------------------
  // Accept either the long name or the short name when it is a pure
  // 1-2 letter token.
  const letterToken =
    (long.match(/^([A-Z]{1,2})$/)?.[1]) ||
    (short.match(/^([A-Z]{1,2})$/)?.[1]);
  if (letterToken) {
    return [GROUP.LETTER, letterRank(letterToken), label];
  }

  // ---- Fallback ---------------------------------------------------------
  return [GROUP.OTHER, 0, label];
};

/**
 * Compare two categories using their long+short names. Use directly inside
 * `array.sort((a,b) => compareCategories(a,b))`.
 */
export const compareCategories = (
  a: { name: string; shortName?: string },
  b: { name: string; shortName?: string },
): number => {
  const ka = categorySortKey(a.name, a.shortName);
  const kb = categorySortKey(b.name, b.shortName);
  if (ka[0] !== kb[0]) return ka[0] - kb[0];
  if (ka[1] !== kb[1]) return ka[1] - kb[1];
  return ka[2].localeCompare(kb[2], 'es');
};