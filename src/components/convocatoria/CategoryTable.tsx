/**
 * CategoryTable
 * Auto-populated from /api/categories.php (table `categorias`).
 *
 * Column mapping (per product spec):
 *  - CATEGORÍAS       → categoria.categoria (name)
 *  - FORMATO           → categoria.sistema
 *  - CUPO              → "∞" if maxjugadores = 99, otherwise the number
 *  - RONDA             → "{hoyosajugar} HOYOS" — total holes to play (categorias.hoyosajugar)
 *  - MARCAS            → tee color name from `salidas` (teeColorName), blank if missing
 */

import { useCategories } from '@/hooks/usePlayersData';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/** Visual swatch colors per tee marker name (matches DB `salidas.color` / tee name) */
const teeMarkerColors: Record<string, string> = {
  AZULES: 'bg-blue-500',
  AZUL: 'bg-blue-500',
  BLANCAS: 'bg-gray-100 border border-gray-300',
  BLANCA: 'bg-gray-100 border border-gray-300',
  DORADAS: 'bg-amber-400',
  DORADA: 'bg-amber-400',
  AMARILLAS: 'bg-yellow-300',
  AMARILLA: 'bg-yellow-300',
  ROJAS: 'bg-red-500',
  ROJA: 'bg-red-500',
  NEGRAS: 'bg-black',
  NEGRA: 'bg-black',
  VERDES: 'bg-green-600',
  VERDE: 'bg-green-600',
};

/**
 * Map of common HEX color codes (uppercase, no spaces) to their Spanish
 * tee-marker color name. The DB sometimes stores `salidas.color` as a hex
 * string (e.g. "#FFFFFF") instead of the friendly name; this lookup converts
 * those codes to the readable label shown in the "MARCAS" column.
 * Unknown codes fall through and the raw value (uppercased) is shown.
 */
const hexToColorName: Record<string, string> = {
  '#FFFFFF': 'BLANCAS',
  '#FFF':    'BLANCAS',
  '#000000': 'NEGRAS',
  '#000':    'NEGRAS',
  '#0000FF': 'AZULES',
  '#1E40AF': 'AZULES',
  '#2563EB': 'AZULES',
  '#3B82F6': 'AZULES',
  '#FF0000': 'ROJAS',
  '#DC2626': 'ROJAS',
  '#EF4444': 'ROJAS',
  '#FFFF00': 'AMARILLAS',
  '#FACC15': 'AMARILLAS',
  '#FDE047': 'AMARILLAS',
  '#FFD700': 'DORADAS',
  '#D4AF37': 'DORADAS',
  '#B8860B': 'DORADAS',
  '#F59E0B': 'DORADAS',
  '#008000': 'VERDES',
  '#16A34A': 'VERDES',
  '#22C55E': 'VERDES',
  '#15803D': 'VERDES',
  '#C0C0C0': 'PLATEADAS',
  '#A0A0A0': 'PLATEADAS',
};

/**
 * Resolve any value (hex code or already-named color) into a friendly
 * Spanish tee marker name. Returns an uppercase string suitable both for
 * display and as a key into `teeMarkerColors`.
 */
const normalizeTeeColor = (raw?: string): string => {
  if (!raw) return '';
  const trimmed = raw.trim().toUpperCase();
  // Hex form: look up in the dictionary; if unknown, hide the raw hex
  // (we don't want to show "#FFFFFF" in the UI) and fall back to "".
  if (trimmed.startsWith('#')) return hexToColorName[trimmed] || '';
  return trimmed;
};

/** Format the "CUPO" column (99 ⇒ ∞, anything else ⇒ number) */
const formatCupo = (maxPlayers?: number): string => {
  if (!maxPlayers || maxPlayers === 99) return '∞';
  return String(maxPlayers);
};

/**
 * Resolve the tee marker name to display in the "MARCAS" column.
 * Prefers the friendly tee name (e.g. "BLANCAS") and falls back to converting
 * hex codes coming from `salidas.color` into a readable Spanish name.
 */
const resolveTeeName = (teeColorName?: string, teeName?: string): string => {
  // Try the explicit tee name first (already a label like "BLANCAS").
  const named = normalizeTeeColor(teeName);
  if (named) return named;
  // Otherwise normalize whatever is in `color` (may be hex or name).
  return normalizeTeeColor(teeColorName);
};

const CategoryTable = () => {
  const { data: categories = [], isLoading } = useCategories();

  return (
    <div className="overflow-x-auto rounded-xl border border-border shadow-card">
      <Table>
        {/*
          Sticky header: keeps the column titles visible while users scroll
          long category lists. `sticky top-0 z-10` pins the header row to the
          top of the scroll container, and the bg-primary background prevents
          row content from showing through.
        */}
        <TableHeader className="sticky top-0 z-10">
          <TableRow className="bg-primary hover:bg-primary">
            <TableHead className="text-primary-foreground font-semibold bg-primary sticky left-0 z-20">CATEGORÍAS</TableHead>
            <TableHead className="text-primary-foreground font-semibold text-center bg-primary">FORMATO</TableHead>
            <TableHead className="text-primary-foreground font-semibold text-center bg-primary">CUPO</TableHead>
            <TableHead className="text-primary-foreground font-semibold text-center bg-primary">RONDA</TableHead>
            <TableHead className="text-primary-foreground font-semibold text-center bg-primary">MARCAS</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading && (
            <TableRow>
              <TableCell colSpan={5} className="text-center text-muted-foreground py-6">
                Cargando categorías…
              </TableCell>
            </TableRow>
          )}
          {!isLoading && categories.length === 0 && (
            <TableRow>
              <TableCell colSpan={5} className="text-center text-muted-foreground py-6">
                No hay categorías registradas para este torneo.
              </TableCell>
            </TableRow>
          )}
          {categories.map((category, index) => {
            const sistema = (category.system || '').toUpperCase();
            const isStrokePlay = sistema.includes('STROKE');
            const teeName = resolveTeeName(category.teeColorName, category.teeName);
            // Ronda → hoyosajugar (total holes to play in the tournament)
            const ronda = category.holes ? `${category.holes} HOYOS` : '';
            return (
              <TableRow
                key={category.id}
                className="transition-colors bg-card"
              >
                {/* Sticky first column: keeps the category name visible while
                    horizontally scrolling wide tables. Background must match
                    the row background so underlying cells don't bleed
                    through the sticky cell. All rows share `bg-card` for a
                    uniform look (no zebra striping). */}
                <TableCell
                  className="font-medium text-foreground sticky left-0 z-10 bg-card"
                >
                  {category.name}
                </TableCell>
                <TableCell className="text-center">
                  <Badge
                    variant={isStrokePlay ? 'default' : 'secondary'}
                    className="font-normal"
                  >
                    {sistema || '—'}
                  </Badge>
                </TableCell>
                <TableCell className="text-center font-medium text-foreground">
                  {formatCupo(category.maxPlayers)}
                </TableCell>
                <TableCell className="text-center text-muted-foreground">
                  {ronda}
                </TableCell>
                <TableCell className="text-center">
                  {teeName ? (
                    <div className="flex items-center justify-center gap-2">
                      <span
                        className={cn(
                          'w-4 h-4 rounded-full',
                          teeMarkerColors[teeName] || 'bg-muted border border-border'
                        )}
                      />
                      <span className="text-sm text-muted-foreground">{teeName}</span>
                    </div>
                  ) : (
                    <span className="text-sm text-muted-foreground">—</span>
                  )}
                </TableCell>
              </TableRow>
            );
          })}
        </TableBody>
      </Table>
    </div>
  );
};

export default CategoryTable;
