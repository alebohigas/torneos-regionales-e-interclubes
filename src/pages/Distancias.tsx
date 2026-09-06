/**
 * Distancias Page
 * Muestra, en una sola vista, una tabla por categoría con el campo donde juega,
 * su tee de salida y el detalle de hoyo / yardas / par (solo los hoyos que
 * realmente juega esa categoría) más los totales.
 */

import Layout from '@/components/layout/Layout';
import PageHero from '@/components/shared/PageHero';
import { Card, CardContent } from '@/components/ui/card';
import { Loader2, Ruler } from 'lucide-react';
import { useDistanciasData, type DistanciaBlock } from '@/hooks/useDistanciasData';
import distanciasHero from '@/assets/distancias-hero.jpg';

/** Traduce el color del tee a un valor CSS usable como fondo de la cintilla. */
const teeBg = (color: string): string => {
  const c = (color || '').trim();
  if (!c) return 'hsl(var(--primary))';
  if (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(c)) return c;
  if (/^([0-9a-f]{6})$/i.test(c)) return `#${c}`;
  return c; // nombre CSS ("blue", "red", ...)
};

/** Tabla de un bloque categoría × campo. */
const DistanciaTable = ({ block }: { block: DistanciaBlock }) => {
  const bg = teeBg(block.teeColor);

  return (
    <Card className="overflow-hidden border-border/60">
      <CardContent className="p-0">
        {/* Encabezado: categoría + campo */}
        <div className="px-4 pt-4 pb-3">
          <h2 className="text-lg md:text-xl font-display font-bold text-foreground uppercase">
            {block.category}
          </h2>
          {block.course && (
            <p className="text-sm md:text-base text-muted-foreground uppercase">{block.course}</p>
          )}
        </div>

        {/* Cintilla del tee de salida */}
        {block.teeName && (
          <div
            className="px-4 py-1.5 text-sm font-semibold uppercase text-white"
            style={{ backgroundColor: bg }}
          >
            {block.teeName}
          </div>
        )}

        {/* Tabla hoyo / yardas / par */}
        <div className="overflow-x-auto">
          <table className="w-full text-sm border-collapse">
            <thead>
              <tr style={{ backgroundColor: bg }} className="text-white">
                <th className="px-3 py-2 text-left font-semibold whitespace-nowrap">Hoyo</th>
                {block.holes.map((h) => (
                  <th key={h.numero} className="px-3 py-2 text-center font-semibold">
                    {h.numero}
                  </th>
                ))}
                <th className="px-3 py-2 text-center font-semibold">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr className="bg-muted/40 border-b border-border/60">
                <td className="px-3 py-2 text-center text-muted-foreground">Yardas</td>
                {block.holes.map((h) => (
                  <td key={h.numero} className="px-3 py-2 text-center">{h.yardaje || '—'}</td>
                ))}
                <td className="px-3 py-2 text-center font-bold">{block.totalYardas}</td>
              </tr>
              <tr>
                <td className="px-3 py-2 text-center text-muted-foreground">Par</td>
                {block.holes.map((h) => (
                  <td key={h.numero} className="px-3 py-2 text-center">{h.par || '—'}</td>
                ))}
                <td className="px-3 py-2 text-center font-bold">{block.totalPar}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </CardContent>
    </Card>
  );
};

const Distancias = () => {
  const { data, isLoading } = useDistanciasData();
  const blocks = data?.blocks ?? [];

  return (
    <Layout>
      <PageHero
        title="Distancias"
        subtitle="Yardas y par por hoyo según la categoría y su tee de salida"
        backgroundImage={distanciasHero}
      />

      <section className="py-10 md:py-14">
        <div className="container mx-auto px-4 space-y-8">
          {isLoading ? (
            <div className="flex justify-center py-16">
              <Loader2 className="h-8 w-8 animate-spin text-primary" />
            </div>
          ) : blocks.length === 0 ? (
            <Card>
              <CardContent className="py-16 text-center text-muted-foreground">
                <Ruler className="h-10 w-10 mx-auto mb-3 opacity-50" />
                Aún no hay distancias configuradas para este torneo.
              </CardContent>
            </Card>
          ) : (
            blocks.map((b, i) => (
              <DistanciaTable key={`${b.categoryId}-${b.course}-${i}`} block={b} />
            ))
          )}
        </div>
      </section>
    </Layout>
  );
};

export default Distancias;
