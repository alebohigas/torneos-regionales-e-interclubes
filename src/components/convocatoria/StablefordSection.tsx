/**
 * StablefordSection
 * ---------------------------------------------------------------
 * Muestra SIEMPRE los valores de puntaje Stableford del torneo activo,
 * leídos de la base de datos (`torneos.valorstable`, filtrada por
 * `torneoid`) mediante el hook `useValorStable`.
 *
 * Visibilidad: se controla desde /admin → "Secciones de Bases"
 * (sección `stableford`), igual que el resto de las secciones.
 * Si la BD no tiene fila para el torneo, la sección se auto-oculta.
 */

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Scale } from 'lucide-react';
import { useValorStable } from '@/hooks/useValorStable';
import StablefordTable from '@/components/shared/StablefordTable';

/** Renderiza la tabla de valores Stableford (Resultado / Puntos). */
const StablefordSection = () => {
  const { rows } = useValorStable();

  // Sin datos en BD -> no renderizar nada.
  if (!rows.length) return null;

  return (
    <div className="space-y-6">
      {/* Píldora de encabezado (mismo estilo que las demás secciones) */}
      <div className="text-center">
        <span className="inline-flex items-center gap-2 px-6 py-2 bg-accent text-accent-foreground rounded-full font-display font-bold text-xl">
          <Scale className="h-5 w-5" />
          Valores Stableford
        </span>
      </div>

      <Card className="shadow-card border-border/50 max-w-xs mx-auto">
        <CardHeader className="pb-3">
          <CardTitle className="font-display text-lg">Puntaje Stableford</CardTitle>
        </CardHeader>
        <CardContent className="flex justify-center">
          {/* Formato unificado (ver StablefordTable). */}
          <StablefordTable rows={rows} />
        </CardContent>
      </Card>
    </div>
  );
};

export default StablefordSection;
