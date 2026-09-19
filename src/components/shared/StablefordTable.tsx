/**
 * StablefordTable
 * ---------------------------------------------------------------------
 * Tabla compacta de valores Stableford (difpar → valor) con el formato
 * de referencia del club: encabezados "difpar" / "valor", cuadrícula de
 * bordes finos en todas las celdas, números alineados a la izquierda y
 * tipografía tabular.
 *
 * Se reutiliza en /reglas, /convocatoria y /admin (Bases) para
 * garantizar el MISMO formato en todas las páginas.
 *
 * Props:
 * - rows: filas normalizadas por `useValorStable` ({ label, value }).
 * - className: clases extra para el contenedor (ancho, márgenes).
 */

import { cn } from '@/lib/utils';
import type { StablefordPointRow } from '@/hooks/useValorStable';

interface StablefordTableProps {
  rows: StablefordPointRow[];
  className?: string;
}

const StablefordTable = ({ rows, className }: StablefordTableProps) => {
  if (!rows.length) return null;

  return (
    <div className={cn('w-fit overflow-hidden border border-border', className)}>
      <table className="border-collapse text-sm tabular-nums">
        <thead>
          <tr className="bg-muted">
            <th className="border border-border px-4 py-1 text-left font-normal text-muted-foreground">
              difpar
            </th>
            <th className="border border-border px-4 py-1 text-left font-normal text-muted-foreground">
              valor
            </th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.label}>
              <td className="border border-border px-4 py-1 text-left text-foreground">
                {r.label}
              </td>
              <td className="border border-border px-4 py-1 text-left text-foreground">
                {r.value}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};

export default StablefordTable;
