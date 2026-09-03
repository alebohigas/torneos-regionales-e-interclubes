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
import { RoundScorecard, HoleScore, ScorecardType } from '@/data/resultadosData';
import { X } from 'lucide-react';
import { formatDbDateTime } from '@/lib/dbDateTime';

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


/** Label mapping for scorecard types */
const scorecardTypeLabels: Record<ScorecardType, string> = {
  hcp: 'Stroke Play (Neto)',
  stableford: 'Stableford',
  scratch: 'Scratch (Gross)',
};

const ScorecardRow = ({ scorecard, playerName, roundLabel, onClose, colSpan }: ScorecardRowProps) => {
  const front9 = scorecard.holes.slice(0, 9);
  const back9 = scorecard.holes.slice(9, 18);
  const type = scorecard.scorecardType;

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

  return (
    <TableRow className="bg-muted/10 hover:bg-muted/10">
      <TableCell colSpan={colSpan} className="p-0">
        <div className="p-4 border-t border-b border-primary/20">
          {/* Date header - prominent title on its own line */}
          {scorecard.date && scorecard.date !== '0' && (
            <div className="mb-3 pb-2 border-b border-border/50">
              <span className="text-muted-foreground text-sm font-medium uppercase tracking-wide">Fecha</span>
              <span className="ml-2 text-lg font-display font-bold text-primary">
                {scorecard.date}
              </span>
            </div>
          )}

          {/* Header row with player info and close button */}
          <div className="flex items-center justify-between mb-3">
            {/* Left section: player name, round, type */}
            <div className="flex items-center gap-2 flex-wrap">
              <span className="font-bold text-foreground">{playerName}</span>
              <span className="px-2 py-0.5 rounded-full bg-primary text-primary-foreground text-xs font-semibold">
                {roundLabel}
              </span>
              {/* Scorecard type badge */}
              <span className="px-2 py-0.5 rounded-full bg-muted text-muted-foreground text-xs">
                {scorecardTypeLabels[type]}
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

          {/* Scorecard grid */}
          <div className="space-y-2">
            {renderSection(front9, 'OUT')}
            {renderSection(back9, 'IN')}
          </div>

          {/* Totales: solo Par y Neto */}
          <div className="flex justify-end items-baseline gap-6 mt-3 text-sm flex-wrap">
            <span className="text-muted-foreground">
              Par: <strong className="text-foreground">
                {scorecard.holes.reduce((s, h) => s + h.par, 0)}
              </strong>
            </span>
            <span className="text-muted-foreground">
              Neto: <strong className="text-foreground font-bold">
                {scorecard.holes.reduce((s, h) => s + h.neto, 0)}
              </strong>
            </span>
          </div>
        </div>
      </TableCell>
    </TableRow>
  );
};

export default ScorecardRow;
