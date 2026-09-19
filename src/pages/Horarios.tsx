/**
 * Horarios de Salidas Page
 * Displays the official kickoff time per category per tournament day.
 *
 * Source: /api/horarios.php (computes MIN(salidagrupo.horainicio1a) by
 * caljuegoid → caljuego.fecha + categoriaid, ignoring '00:00:00').
 *
 * Layout: matrix table with category rows × date columns. Cells show the
 * earliest tee time in 24h "HH:MM" format. Empty cells indicate no valid
 * kickoff time was recorded for that combination.
 */

import Layout from '@/components/layout/Layout';
import PageHero from '@/components/shared/PageHero';
import { Card, CardContent } from '@/components/ui/card';
import { Loader2, Clock } from 'lucide-react';
import { useHorariosData, type HorarioDate } from '@/hooks/useHorariosData';
import horariosHero from '@/assets/horarios-hero.jpg';
import { compareCategories } from '@/lib/categorySort';

// ============= Date label helpers (ES) =============

/** Spanish 3-letter month abbreviation, e.g. "Abr". */
const monthShortEs: Record<string, string> = {
  January: 'Ene', February: 'Feb', March: 'Mar', April: 'Abr',
  May: 'May', June: 'Jun', July: 'Jul', August: 'Ago',
  September: 'Sep', October: 'Oct', November: 'Nov', December: 'Dic',
};

/** Spanish 3-letter day-of-week abbreviation, e.g. "Vie". */
const dayShortEs: Record<string, string> = {
  Monday: 'Lun', Tuesday: 'Mar', Wednesday: 'Mie',
  Thursday: 'Jue', Friday: 'Vie', Saturday: 'Sab', Sunday: 'Dom',
};

/** Spanish full day-of-week label (e.g. "Jueves"). */
const dayLongEs: Record<string, string> = {
  Monday: 'Lunes', Tuesday: 'Martes', Wednesday: 'Miércoles',
  Thursday: 'Jueves', Friday: 'Viernes', Saturday: 'Sábado', Sunday: 'Domingo',
};

/** Spanish full month label (e.g. "Abril"). */
const monthLongEs: Record<string, string> = {
  January: 'Enero', February: 'Febrero', March: 'Marzo', April: 'Abril',
  May: 'Mayo', June: 'Junio', July: 'Julio', August: 'Agosto',
  September: 'Septiembre', October: 'Octubre', November: 'Noviembre', December: 'Diciembre',
};

/** Header line 1 (top): full localized weekday + day + month, e.g. "Jueves 2 de Abril". */
const formatDayLabel = (d: HorarioDate): string => {
  const day   = dayLongEs[d.dayOfWeek] ?? d.dayOfWeek ?? '';
  const month = monthLongEs[d.month]   ?? d.month     ?? '';
  return `${day} ${d.dayNum} de ${month}`.trim();
};

// ============= Component =============

const Horarios = () => {
  const { data, isLoading } = useHorariosData();

  const dates   = data?.dates   ?? [];
  /**
   * Sort categories with the canonical tournament order:
   * Primera → Letras (AA,A,B,...) → Damas → Senior → Novatos → Otros.
   * Backend currently returns rows alphabetically; we override that here
   * so all matrix tables (Calendario + Horarios + their previews inside
   * Bases) keep the same row sequence.
   */
  const entries = [...(data?.entries ?? [])].sort((a, b) =>
    compareCategories(
      { name: a.categoryName, shortName: a.shortName },
      { name: b.categoryName, shortName: b.shortName },
    ),
  );

  return (
    <Layout>
      <PageHero
        title="Horarios de Salidas"
        subtitle="Hora oficial de salida por categoría y día"
        backgroundImage={horariosHero}
        backgroundPosition="center 60%"
      />

      <section className="py-16 bg-background">
        <div className="container mx-auto px-4">
          <div className="text-center mb-6">
            <h2 className="text-2xl font-bold text-foreground">
              Horarios por Categoría
            </h2>
            <p className="text-sm text-muted-foreground mt-2">
              Hora más temprana válida registrada en el sistema de salidas.
            </p>
          </div>

          {isLoading ? (
            <div className="flex justify-center py-12">
              <Loader2 className="h-8 w-8 animate-spin text-primary" />
            </div>
          ) : entries.length === 0 || dates.length === 0 ? (
            <div className="text-center py-12 text-muted-foreground">
              No hay horarios de salida registrados para este torneo.
            </div>
          ) : (
            <Card className="border-border/50 max-w-6xl mx-auto overflow-hidden">
              <CardContent className="p-0">
                <div className="overflow-x-auto">
                  <table className="w-full border-collapse text-sm">
                    <thead>
                      {/* Solid green header row: full date label per column.
                          Mirrors the "barra verde" styling of the legacy
                          horarios table (primary background, white text). */}
                      <tr className="bg-primary text-primary-foreground">
                        {/* Sticky-left Categoría header. Stays pinned to
                            the left edge during horizontal scroll, with the
                            same solid `bg-primary` styling as the rest of
                            the header row. z-20 keeps it above sticky body
                            cells (z-10). */}
                        <th className="sticky left-0 z-20 bg-primary p-3 text-left font-bold w-40 align-middle">
                          Categoría
                        </th>
                        {dates.map((d) => (
                          <th
                            key={`d-${d.date}`}
                            className="p-3 text-center font-bold align-middle min-w-[140px] border-l border-primary-foreground/20"
                          >
                            {formatDayLabel(d)}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {entries.map((entry, idx) => (
                        <tr
                          key={entry.categoryId}
                          className={idx % 2 === 0 ? 'bg-card' : 'bg-muted/30'}
                        >
                          {/* Sticky-left Categoría cell. Inline solid
                              background recreates the alternating row tint
                              (`bg-card` / `bg-muted/30`) because sticky
                              cells render over the page background, not
                              the <tr> color. */}
                          <td
                            className="sticky left-0 z-10 border-b border-border/40 p-3 font-medium text-foreground whitespace-nowrap"
                            style={{
                              backgroundColor: 'hsl(var(--background))',
                              backgroundImage:
                                idx % 2 === 0
                                  ? 'linear-gradient(hsl(var(--card)), hsl(var(--card)))'
                                  : 'linear-gradient(hsl(var(--muted) / 0.3), hsl(var(--muted) / 0.3))',
                            }}
                          >
                            {entry.categoryName}
                          </td>
                          {dates.map((d) => {
                            const time = entry.times?.[d.date];
                            return (
                              <td
                                key={d.date}
                                className="border-b border-l border-border/40 p-3 text-center align-middle"
                              >
                                {time ? (
                                  <span className="inline-flex items-center gap-1.5 font-mono font-semibold text-foreground">
                                    <Clock className="h-3.5 w-3.5 text-primary" />
                                    {time}
                                  </span>
                                ) : (
                                  <span className="text-muted-foreground/40">—</span>
                                )}
                              </td>
                            );
                          })}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </CardContent>
            </Card>
          )}
        </div>
      </section>
    </Layout>
  );
};

export default Horarios;