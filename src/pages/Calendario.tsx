/**
 * Calendario de Juego (modelo por GIRA)
 *
 * Una fila por etapa de la gira activa:
 *   - Club: logo(s) del/los club(es) dueños de la sede
 *   - Sede: nombre(s) de campo + etiqueta de etapa ("Misiones / Herradura Etapa-1")
 *   - Fecha: fechas reales de juego ("22 y 23 noviembre 2025")
 *
 * Las etapas ocultas en Admin > Gira no se muestran.
 */

import Layout from '@/components/layout/Layout';
import PageHero from '@/components/shared/PageHero';
import { Card, CardContent } from '@/components/ui/card';
import { Loader2, CalendarDays } from 'lucide-react';
import calendarioHero from '@/assets/calendario-hero.jpg';
import { useCalendarioGira, type CalendarioGiraEtapa } from '@/hooks/useCalendarioGira';
import { useSiteConfig } from '@/hooks/useSiteConfig';

/** Logos de los clubes de una etapa, apilados como en el calendario impreso. */
const ClubLogos = ({ etapa }: { etapa: CalendarioGiraEtapa }) => {
  const logos = etapa.clubs.filter((c) => !!c.logo);
  if (logos.length === 0) return <span className="text-muted-foreground text-xs">—</span>;

  return (
    <div className="flex flex-col items-center gap-1 md:gap-2">
      {logos.map((club) => (
        <img
          key={club.clubId}
          src={club.logo}
          alt={club.name || 'Club'}
          title={club.name}
          className="h-10 md:h-11 w-auto max-w-[72px] md:max-w-[84px] object-contain bg-white p-0.5"
          loading="lazy"
          onError={(e) => {
            (e.currentTarget as HTMLImageElement).style.display = 'none';
          }}
        />
      ))}
    </div>
  );
};

/**
 * Splits a date label like "22 y 23 noviembre 2025" into two lines on mobile
 * when two dates are joined by " y ", so the Fecha column can be narrower.
 */
const DateLabel = ({ label }: { label?: string | null }) => {
  if (!label) return <span>—</span>;
  const parts = label.split(/\s+y\s+/i);
  if (parts.length === 2) {
    return (
      <span className="block whitespace-normal leading-tight">
        <span>{parts[0]} y</span>
        <br className="md:hidden" />
        <span className="md:hidden">{parts[1]}</span>
        <span className="hidden md:inline"> {parts[1]}</span>
      </span>
    );
  }
  return <span className="whitespace-normal">{label}</span>;
};

const Calendario = () => {
  const { data: etapas, isLoading } = useCalendarioGira();
  const { data: siteConfig } = useSiteConfig();

  const hidden = (siteConfig?.gira_config?.hiddenTorneos ?? []).map(Number);
  const rows = (etapas ?? []).filter((e) => !hidden.includes(Number(e.torneoid)));

  return (
    <Layout>
      <PageHero
        title="Calendario de Juego"
        subtitle="Sedes y fechas de cada etapa de la gira"
        backgroundImage={calendarioHero}
      />

      <section className="container mx-auto px-3 py-6 md:py-10">
        <Card className="bg-background shadow-card overflow-hidden mx-auto max-w-2xl lg:max-w-3xl">
          <CardContent className="p-0">
            {isLoading ? (
              <div className="flex items-center justify-center py-16">
                <Loader2 className="h-6 w-6 animate-spin text-primary" />
              </div>
            ) : rows.length === 0 ? (
              <div className="flex flex-col items-center gap-2 py-16 text-muted-foreground">
                <CalendarDays className="h-8 w-8" />
                <p>No hay etapas con calendario publicado.</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm bg-background">
                  <thead>
                    <tr className="bg-primary text-primary-foreground">
                      <th className="px-2 md:px-4 py-2 md:py-3 text-left font-semibold w-[72px] md:w-28">Etapa</th>
                      <th className="px-2 md:px-4 py-2 md:py-3 text-left font-semibold w-24 md:w-32">Club</th>
                      <th className="px-2 md:px-4 py-2 md:py-3 text-left font-semibold w-full">Fecha</th>
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((etapa) => (
                      <tr key={etapa.torneoid} className="border-b border-border/60 last:border-0 bg-background">
                        <td className="px-2 md:px-4 py-2 md:py-3 align-middle font-semibold text-foreground whitespace-nowrap">
                          {etapa.etapaDisplay || etapa.etapaLabel || '—'}
                        </td>
                        <td className="px-2 md:px-4 py-2 md:py-3 align-middle">
                          <ClubLogos etapa={etapa} />
                        </td>
                        <td className="px-2 md:px-4 py-2 md:py-3 align-middle text-muted-foreground text-sm md:text-base">
                          <DateLabel label={etapa.dateLabel} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>
      </section>
    </Layout>
  );
};

export default Calendario;
