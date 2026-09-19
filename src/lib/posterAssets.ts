/**
 * posterAssets.ts
 * -------------------------------------------------------------
 * Auto-discovery of poster images bundled inside specific section
 * folders under `src/assets/`. The goal is to let editors add or
 * remove poster files (.webp / .jpg / .jpeg / .png / .gif) by simply
 * dropping them into the corresponding folder — no code edit, no
 * manual import — and have them picked up automatically on the
 * next `npm run build` (and during dev too, thanks to Vite HMR).
 *
 * Folders auto-scanned (eager glob, so URLs are resolved at build):
 *   - src/assets/eventos/       → Eventos page (AtraccionesSection)
 *   - src/assets/avisos/        → Avisos page (AvisosPostersSection)
 *   - src/assets/convocatoria/  → Bases page (reserved)
 *
 * Ordering rules:
 *   - Files are returned in **case-insensitive alphabetical order**
 *     by file name. To control the natural order, prefix file names
 *     with a numeric token (e.g. `01-clima.webp`, `02-tabla.webp`).
 *   - The admin panel can still override this order via the
 *     `posterOrder` field stored in `site_config` (see posterOrder.ts).
 *
 * Alt text:
 *   - The accessible label is auto-generated from the file name by
 *     stripping the extension, replacing dashes/underscores with
 *     spaces, and Title-Casing each word. This keeps the UX usable
 *     out of the box; richer copy can be added later via a sidecar
 *     metadata file if ever needed.
 */

/**
 * Shape returned for each discovered poster.
 * `src` : final asset URL (Vite-processed, hashed in production).
 * `alt` : human-friendly accessibility label derived from the file name.
 * `name`: original file name (without folder), useful for debugging.
 */
export interface DiscoveredPoster {
  src: string;
  alt: string;
  name: string;
}

/**
 * Convert a raw file name like `dia-24-viernes.webp` or
 * `01_aviso_climatologico.WEBP` into a Title-Cased label
 * ("Dia 24 Viernes", "01 Aviso Climatologico") suitable for `alt` text.
 */
function fileNameToAlt(fileName: string): string {
  // Strip extension
  const base = fileName.replace(/\.[^.]+$/, '');
  // Normalize separators to spaces
  const spaced = base.replace(/[-_]+/g, ' ').trim();
  // Title-case each word, preserving numbers as-is
  return spaced
    .split(/\s+/)
    .map((w) => (w.length === 0 ? w : w[0].toUpperCase() + w.slice(1).toLowerCase()))
    .join(' ');
}

/**
 * Extract the trailing file name from a Vite-resolved module path
 * such as `/src/assets/eventos/dia-24-viernes.webp` or its hashed
 * production equivalent. We want the human-readable source name,
 * so we use the key from the glob (which is the source path) rather
 * than the resolved URL.
 */
function fileNameFromPath(path: string): string {
  const idx = path.lastIndexOf('/');
  return idx >= 0 ? path.slice(idx + 1) : path;
}

/**
 * Build a sorted DiscoveredPoster[] from a Vite eager-glob record.
 *
 * Vite's `import.meta.glob` returns `Record<string, { default: string }>`
 * when called with `{ eager: true, query: '?url', import: 'default' }`.
 * Here we accept `Record<string, string>` (module-default already pulled)
 * to keep the call site simple.
 */
function buildPosterList(modules: Record<string, string>): DiscoveredPoster[] {
  const entries = Object.entries(modules).map(([path, url]) => {
    const name = fileNameFromPath(path);
    return {
      src: url,
      alt: fileNameToAlt(name),
      name,
    } satisfies DiscoveredPoster;
  });

  // Case-insensitive natural alphabetical sort by file name so numeric
  // prefixes like 01_, 02_, 10_ behave intuitively.
  entries.sort((a, b) =>
    a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' })
  );

  return entries;
}

// ---------------------------------------------------------------
// Eager globs — one per section folder.
//
// Vite requires the glob pattern to be a literal string at parse time,
// so we cannot factor these into a helper. Each call is cached by Vite,
// so repeated imports of this module are cheap.
// ---------------------------------------------------------------

/** All Eventos posters discovered under `src/assets/eventos/`. */
export const EVENTOS_POSTERS: DiscoveredPoster[] = buildPosterList(
  import.meta.glob('/src/assets/eventos/*.{webp,jpg,jpeg,png,gif,WEBP,JPG,JPEG,PNG,GIF}', {
    eager: true,
    query: '?url',
    import: 'default',
  }) as Record<string, string>
);

/** All Avisos posters discovered under `src/assets/avisos/`. */
export const AVISOS_POSTERS: DiscoveredPoster[] = buildPosterList(
  import.meta.glob('/src/assets/avisos/*.{webp,jpg,jpeg,png,gif,WEBP,JPG,JPEG,PNG,GIF}', {
    eager: true,
    query: '?url',
    import: 'default',
  }) as Record<string, string>
);

/** All Menús posters discovered under `src/assets/menus/`. */
export const MENUS_POSTERS: DiscoveredPoster[] = buildPosterList(
  import.meta.glob('/src/assets/menus/*.{webp,jpg,jpeg,png,gif,WEBP,JPG,JPEG,PNG,GIF}', {
    eager: true,
    query: '?url',
    import: 'default',
  }) as Record<string, string>
);

/** All Premios posters discovered under `src/assets/premios/`. */
export const PREMIOS_POSTERS: DiscoveredPoster[] = buildPosterList(
  import.meta.glob('/src/assets/premios/*.{webp,jpg,jpeg,png,gif,WEBP,JPG,JPEG,PNG,GIF}', {
    eager: true,
    query: '?url',
    import: 'default',
  }) as Record<string, string>
);

/** All Hoteles posters discovered under `src/assets/hoteles/`. */
export const HOTELES_POSTERS: DiscoveredPoster[] = buildPosterList(
  import.meta.glob('/src/assets/hoteles/*.{webp,jpg,jpeg,png,gif,WEBP,JPG,JPEG,PNG,GIF}', {
    eager: true,
    query: '?url',
    import: 'default',
  }) as Record<string, string>
);

/**
 * All Bases posters discovered under `src/assets/convocatoria/`.
 * Reserved for future use — the Convocatoria page does not currently
 * render a poster grid, but the folder is wired up so editors can drop
 * imagery in without code changes if/when needed.
 */
export const CONVOCATORIA_POSTERS: DiscoveredPoster[] = buildPosterList(
  import.meta.glob('/src/assets/convocatoria/*.{webp,jpg,jpeg,png,gif,WEBP,JPG,JPEG,PNG,GIF}', {
    eager: true,
    query: '?url',
    import: 'default',
  }) as Record<string, string>
);
