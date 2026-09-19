/**
 * Historial Page (/historial)
 * ------------------------------------------------------------------
 * Consulta de ediciones pasadas. Dos bloques configurables en
 * Admin > Historial:
 *
 *  1) AÑOS: por cada año se guarda un `torneo_id` y se reutiliza la
 *     página /resultados en modo embebido.
 *  2) TEMPORADAS / GIRAS: por cada gira pasada se publica su Ranking
 *     Final y/o sus Copas, reutilizando esas páginas en modo embebido
 *     con la gira forzada (GiraOverrideContext).
 */

import { useState } from 'react';
import Layout from '@/components/layout/Layout';
import PageHero from '@/components/shared/PageHero';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ArrowLeft, CalendarClock, History, Loader2, Star, Trophy } from 'lucide-react';
// Hero HD propio de la página de Historial
import historialHero from '@/assets/historial-hero.jpg';
import Resultados from '@/pages/Resultados';
import RankingFinal from '@/pages/RankingFinal';
import Copa from '@/pages/Copa';
import { GiraOverrideContext } from '@/hooks/useGiraId';
import { useSiteConfig, type HistorialEdition, type HistorialSeason } from '@/hooks/useSiteConfig';

/** Vista abierta dentro de una temporada. */
type SeasonView = 'ranking' | 'copas';

const Historial = () => {
  const { data: siteConfig, isLoading } = useSiteConfig();

  /** Configured editions, most recent year first, capped at 5. */
  const editions: HistorialEdition[] = [...(siteConfig?.historial_config?.editions || [])]
    .filter(e => e && Number(e.year) > 0 && String(e.torneoId || '').trim() !== '')
    .sort((a, b) => Number(b.year) - Number(a.year))
    .slice(0, 5);

  /** Temporadas/giras publicadas con al menos un reporte activo. */
  const seasons: HistorialSeason[] = (siteConfig?.historial_config?.seasons || [])
    .filter(s => s && String(s.giraId || '').trim() !== '')
    .slice(0, 5);

  /** Selected year (null = show the year selector grid). */
  const [selectedYear, setSelectedYear] = useState<number | null>(null);
  const selected = editions.find(e => Number(e.year) === selectedYear) || null;

  /** Temporada y reporte abiertos. */
  const [openSeason, setOpenSeason] = useState<string | null>(null);
  const [seasonView, setSeasonView] = useState<SeasonView | null>(null);
  const season = seasons.find(s => String(s.giraId) === openSeason) || null;

  /** Vista embebida de una temporada (Ranking Final o Copas). */
  if (season && seasonView) {
    return (
      <Layout>
        <PageHero
          title={season.name || 'Historial'}
          subtitle={seasonView === 'ranking' ? 'Ranking Final de la temporada' : 'Copas de la temporada'}
          backgroundImage={historialHero}
        />
        <section className="pt-10 bg-white">
          <div className="container mx-auto px-4">
            <Button
              variant="ghost"
              onClick={() => setSeasonView(null)}
              className="gap-2 bg-primary/10 hover:bg-primary/20"
            >
              <ArrowLeft className="h-4 w-4" />
              Volver a {season.name || 'la temporada'}
            </Button>
          </div>
        </section>
        <GiraOverrideContext.Provider value={String(season.giraId)}>
          {seasonView === 'ranking' ? <RankingFinal embedded /> : <Copa embedded />}
        </GiraOverrideContext.Provider>
      </Layout>
    );
  }

  return (
    <Layout>
      <PageHero
        title="Historial"
        subtitle="Consulta los resultados de ediciones anteriores"
        backgroundImage={historialHero}
      />

      {!selected ? (
        <section className="py-16 bg-white">
          <div className="container mx-auto px-4">
            <div className="text-center mb-10">
              <h2 className="text-3xl font-bold text-foreground">
                SELECCIONA LA TEMPORADA
              </h2>
              <p className="text-muted-foreground mt-2">
                Historial de resultados de hasta 5 temporadas anteriores
              </p>
            </div>

            {isLoading ? (
              <div className="flex justify-center py-12">
                <Loader2 className="h-8 w-8 animate-spin text-primary" />
              </div>
            ) : editions.length === 0 ? (
              <div className="max-w-xl mx-auto text-center text-muted-foreground border border-dashed border-border rounded-lg p-10">
                <History className="h-8 w-8 mx-auto mb-3 text-primary" />
                Aún no hay ediciones anteriores configuradas.
              </div>
            ) : (
              <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4 max-w-4xl mx-auto">
                {editions.map((ed) => (
                  <Card
                    key={ed.year}
                    className="border-border/50 hover:border-primary/50 transition-all hover:shadow-lg cursor-pointer"
                    onClick={() => setSelectedYear(Number(ed.year))}
                  >
                    <CardContent className="p-5 text-center">
                      <CalendarClock className="h-6 w-6 mx-auto mb-2 text-primary" />
                      <h3 className="font-bold text-foreground text-2xl">{ed.year}</h3>
                      {ed.label ? (
                        <p className="text-xs text-muted-foreground mt-1">{ed.label}</p>
                      ) : null}
                    </CardContent>
                  </Card>
                ))}
              </div>
            )}

            {/* Temporadas / Giras: Ranking Final y Copas */}
            {seasons.length > 0 && (
              <div className="mt-16">
                <div className="text-center mb-10">
                  <h2 className="text-3xl font-bold text-foreground">TEMPORADAS</h2>
                  <p className="text-muted-foreground mt-2">
                    Ranking Final y Copas de temporadas anteriores
                  </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2 max-w-4xl mx-auto">
                  {seasons.map((s) => {
                    const isOpen = openSeason === String(s.giraId);
                    return (
                      <Card key={s.giraId} className="border-border/50">
                        <CardContent className="p-5">
                          <button
                            className="w-full text-left"
                            onClick={() => setOpenSeason(isOpen ? null : String(s.giraId))}
                          >
                            <h3 className="font-bold text-foreground text-xl flex items-center gap-2">
                              <Trophy className="h-5 w-5 text-primary" />
                              {s.name || `Gira ${s.giraId}`}
                            </h3>
                          </button>

                          {isOpen && (
                            <div className="mt-4 space-y-2">
                              {s.showRankingFinal !== false && (
                                <Button
                                  variant="ghost"
                                  className="w-full justify-start gap-2 bg-primary/10 hover:bg-primary/20"
                                  onClick={() => setSeasonView('ranking')}
                                >
                                  <Star className="h-4 w-4 text-primary" />
                                  Ranking Final
                                </Button>
                              )}
                              {s.showCopas !== false && (
                                <Button
                                  variant="ghost"
                                  className="w-full justify-start gap-2 bg-primary/10 hover:bg-primary/20"
                                  onClick={() => setSeasonView('copas')}
                                >
                                  <Trophy className="h-4 w-4 text-primary" />
                                  Copas
                                </Button>
                              )}
                            </div>
                          )}
                        </CardContent>
                      </Card>
                    );
                  })}
                </div>
              </div>
            )}
          </div>
        </section>
      ) : (
        <>
          <section className="pt-10 bg-white">
            <div className="container mx-auto px-4">
              <Button
                variant="ghost"
                onClick={() => setSelectedYear(null)}
                className="gap-2 bg-primary/10 hover:bg-primary/20"
              >
                <ArrowLeft className="h-4 w-4" />
                Volver a años
              </Button>
              <h2 className="text-2xl font-bold text-foreground mt-6 text-center">
                {selected.label || `Resultados ${selected.year}`}
              </h2>
            </div>
          </section>
          {/*
            Reuse the /resultados leaderboard verbatim, but pointed at the
            historical tournament id. `key` forces a clean remount (and fresh
            category selection) when the year changes.
          */}
          <Resultados key={selected.torneoId} embedded torneoIdOverride={String(selected.torneoId)} />
        </>
      )}
    </Layout>
  );
};

export default Historial;
