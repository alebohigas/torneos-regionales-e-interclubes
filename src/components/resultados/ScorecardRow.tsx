/**
 * ScorecardRow Component
 * Renders an expandable scorecard (hole-by-hole) below a player row
 * Adapts layout based on scorecardType: 'hcp', 'stableford', or 'scratch'
 * 
 * Stableford: Hoyo, Par, Gross, Vtja, Hcp., Neto, Ptos
 * HCP (Stroke Neto): Hoyo, Par, Gross, Vtja, Hcp., Neto
 * Scratch (Gross): Hoyo, Par, Golpes, +/-
 */

import { TableRow, TableCell } from '@/components/ui/table';
import { RoundScorecard, HoleScore } from '@/data/resultadosData';
import { X } from 'lucide-react';
import { formatDbDateTime } from '@/lib/dbDateTime';
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
  PLATEADAS: 'bg-gray-300',
  PLATEADA: 'bg-gray-300',
  PLATINO: 'bg-slate-400',
};

const hexToColorName: Record<string, string> = {
  '#FFFFFF': 'BLANCAS',
  '#FFF': 'BLANCAS',
  '#000000': 'NEGRAS',
  '#000': 'NEGRAS',
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

const normalizeTeeColor = (raw?: string): string => {
  if (!raw) return '';
  const trimmed = raw.trim().toUpperCase();
  if (trimmed.startsWith('#')) return hexToColorName[trimmed] || '';
  return trimmed;
};

/** Parse YYYY-MM-DD (or DD-MM-YYYY) and return DD-MM-YYYY safely, no Date(). */
const formatDateBadge = (date?: string): string => {
  if (!date || date === '0') return '';
  const trimmed = date.trim();
  if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
    const [y, m, d] = trimmed.split('-');
    return `${d}-${m}-${y}`;
  }
  return trimmed;
};

interface ScorecardRowProps {
  /** The scorecard data to display */
  scorecard: RoundScorecard;
  /** Player name for the header */
  playerName: string;
  /** Round number label */
  roundLabel: string;
  /** Close handler */
  onClose: () => void;
  /** Number of columns to span in the parent table */
  colSpan: number;
}


const ScorecardRow = ({ scorecard, playerName, roundLabel, onClose, colSpan }: ScorecardRowProps) => {
  const front9 = scorecard.holes.slice(0, 9);
  const back9 = scorecard.holes.slice(9, 18);


  /** Render a 9-hole section adapted to scorecard type */
  const renderSection = (holes: HoleScore[], label: string) => (
    <div className="overflow-x-auto">
      <table className="w-full text-xs border-collapse">
        <thead>
          {/* Hole numbers header */}
          <tr className="bg-primary">
            <th className="px-2 py-1 text-primary-foreground font-bold text-center w-14">{label}</th>
            {holes.map(h => (
              <th key={h.hoyo} className="px-2 py-1 text-primary-foreground font-bold text-center min-w-[36px]">
                {h.hoyo}
              </th>
            ))}
            <th className="px-2 py-1 text-primary-foreground font-bold text-center min-w-[44px]">Tot</th>
          </tr>
        </thead>
        <tbody>
          {/* Par row - always shown, darker background for emphasis */}
          <tr className="bg-muted">
            <td className="px-2 py-1 font-semibold text-center text-foreground">Par</td>
            {holes.map(h => (
              <td key={h.hoyo} className="px-2 py-1 text-center font-medium text-foreground">{h.par}</td>
            ))}
            <td className="px-2 py-1 text-center font-semibold text-foreground">
              {holes.reduce((s, h) => s + h.par, 0)}
            </td>
          </tr>

          {/* Fila R: golpes reales de la tarjeta (tarjetas.h1..h18), igual que el legacy */}
          <tr className="bg-muted/30">
            <td className="px-2 py-1 font-semibold text-center text-muted-foreground">R</td>
            {holes.map(h => (
              <td key={h.hoyo} className="px-2 py-1 text-center font-bold text-foreground">{h.golpes}</td>
            ))}
            <td className="px-2 py-1 text-center font-semibold text-foreground">
              {holes.reduce((s, h) => s + h.golpes, 0)}
            </td>
          </tr>

        </tbody>
      </table>
    </div>
  );

  /** Cintilla legacy: TEE / CAMPO o CLUB / FECHA */
  const ribbonParts = [
    scorecard.tee,
    scorecard.course || scorecard.club,
    scorecard.date && scorecard.date !== '0' ? scorecard.date : null,
  ].filter(Boolean) as string[];

  return (
    <TableRow className="bg-muted/10 hover:bg-muted/10">
      <TableCell colSpan={colSpan} className="p-0">
        <div className="p-4 border-t border-b border-primary/20">
          {/* Header row with player info and close button */}
          <div className="flex items-center justify-between mb-3">
            {/* Left section: player name + round */}
            <div className="flex items-center gap-2 flex-wrap">
              <span className="font-bold text-foreground">{playerName}</span>
              <span className="px-2 py-0.5 rounded-full bg-primary text-primary-foreground text-xs font-semibold">
                {roundLabel}
              </span>
            </div>

            {/* Right section: last-update timestamp (live only) + close button */}
            <div className="flex items-center gap-3">
              {formatDbDateTime(scorecard.fechaCap) && (
                <span
                  className="text-xs text-muted-foreground whitespace-nowrap"
                  title="Última captura de la tarjeta (tarjetas.fec_ult_act)"
                >
                  Fecha de captura:{' '}
                  <span className="font-medium text-foreground">
                    {/* Formateo textual, sin new Date(): misma hora en iPhone/Safari y Android */}
                    {formatDbDateTime(scorecard.fechaCap)}
                  </span>
                </span>
              )}
              <button onClick={onClose} className="text-muted-foreground hover:text-foreground transition-colors">
                <X className="h-4 w-4" />
              </button>
            </div>
          </div>

          {/* Globitos de detalle: tee, fecha y sede */}
          <div className="flex flex-wrap items-center gap-2 mb-3">
            {scorecard.tee && (
              <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-muted text-foreground text-xs font-semibold uppercase">
                <span
                  className={cn(
                    'inline-block h-3 w-3 rounded-full',
                    teeMarkerColors[normalizeTeeColor(scorecard.tee)] || 'bg-primary'
                  )}
                />
                {normalizeTeeColor(scorecard.tee) || scorecard.tee}
              </span>
            )}
            {scorecard.date && scorecard.date !== '0' && (
              <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-muted text-foreground text-xs">
                <span className="font-bold">Fecha:</span>
                <span>{formatDateBadge(scorecard.date)}</span>
              </span>
            )}
            {(scorecard.course || scorecard.club) && (
              <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-muted text-foreground text-xs">
                <span className="font-bold">Sede:</span>
                <span>{scorecard.course || scorecard.club}</span>
              </span>
            )}
          </div>


          {/* Scorecard grid */}
          <div className="space-y-2">
            {renderSection(front9, 'OUT')}
            {renderSection(back9, 'IN')}
          </div>

          {/* Totales: solo Par y R (golpes) */}
          <div className="flex justify-end items-baseline gap-6 mt-3 text-sm flex-wrap">
            <span className="text-muted-foreground">
              Par: <strong className="text-foreground">
                {scorecard.holes.reduce((s, h) => s + h.par, 0)}
              </strong>
            </span>
            <span className="text-muted-foreground">
              Total: <strong className="text-foreground font-bold">
                {scorecard.holes.reduce((s, h) => s + h.golpes, 0)}
              </strong>
            </span>
          </div>

        </div>
      </TableCell>
    </TableRow>
  );
};

export default ScorecardRow;
