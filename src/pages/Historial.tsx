/**
 * Historial Page (/historial)
 * ------------------------------------------------------------------
 * Consulta de ediciones pasadas. Publica temporadas/giras con sus
 * reportes de Ranking Final y Copas mediante GiraOverrideContext.
 */

import { useState } from 'react';
import Layout from '@/components/layout/Layout';
import PageHero from '@/components/shared/PageHero';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Loader2, Star, Trophy } from 'lucide-react';
// Hero HD propio de la página de Historial
import historialHero from '@/assets/historial-hero.jpg';
import RankingFinal from '@/pages/RankingFinal';
import Copa from '@/pages/Copa';
import { GiraOverrideContext } from '@/hooks/useGiraId';
import { useSiteConfig, type HistorialSeason } from '@/hooks/useSiteConfig';

/** Vista abierta dentro de una temporada. */
type SeasonView = 'ranking' | 'copas';

const Historial = () => {
  const { data: siteConfig, isLoading } = useSiteConfig();

  /** Temporadas/giras publicadas con al menos un reporte activo. */
  const seasons: HistorialSeason[] = (siteConfig?.historial_config?.seasons || [])
    .filter(s => s && String(s.giraId || '').trim() !== '')
    .slice(0, 5);

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
              Volver a temporadas
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

      <section className="py-16 bg-white">
        <div className="container mx-auto px-4">
          <div className="text-center mb-10">
            <h2 className="text-3xl font-bold text-foreground">SELECCIONA</h2>
            <p className="text-muted-foreground mt-2">TEMPORADAS ANTERIORES</p>
            <p className="text-muted-foreground mt-1">
              Ranking Final y Copas de temporadas anteriores
            </p>
          </div>

          {isLoading ? (
            <div className="flex justify-center py-12">
              <Loader2 className="h-8 w-8 animate-spin text-primary" />
            </div>
          ) : seasons.length === 0 ? (
            <div className="max-w-xl mx-auto text-center text-muted-foreground border border-dashed border-border rounded-lg p-10">
              <Trophy className="h-8 w-8 mx-auto mb-3 text-primary" />
              Aún no hay temporadas anteriores configuradas.
            </div>
          ) : (
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
          )}
        </div>
      </section>
    </Layout>
  );
};

export default Historial;
