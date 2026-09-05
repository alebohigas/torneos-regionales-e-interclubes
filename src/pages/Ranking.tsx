/**
 * RANKING Page
 * Ranking acumulado de la gira: categorías por `catidoriginal` y suma de puntos
 * por jugador (endpoint ranking.php).
 */

import Layout from '@/components/layout/Layout';
import PageHero from '@/components/shared/PageHero';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowLeft, Trophy, Loader2 } from 'lucide-react';
import resultadosHero from '@/assets/jugadores-hero.jpg';
import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Star } from 'lucide-react';
import {
  useRankingCategories,
  useRankingPlayers,
  useRankingPlayerDetail,
  type RankingCategory,
} from '@/hooks/useRankingData';

/** Formatea puntos con un decimal como el legacy (round(puntos,1)) */
const formatPuntos = (value: number): string =>
  Number.isFinite(value) ? value.toFixed(1).replace(/\.0$/, '') : '0';

const Ranking = () => {
  /** Categoría seleccionada (null = grid de categorías) */
  const [selectedCategory, setSelectedCategory] = useState<RankingCategory | null>(null);

  const { data: categories = [], isLoading: loadingCats } = useRankingCategories();
  const { data: rankingData, isLoading: loadingPlayers } = useRankingPlayers(
    selectedCategory?.id ?? null,
    !!selectedCategory
  );
  const players = rankingData?.players ?? [];

  /** Jugador abierto en el desglose por etapas (null = cerrado) */
  const [detailPlayer, setDetailPlayer] = useState<{ numjugador: string; name: string } | null>(null);
  const { data: detail, isLoading: loadingDetail } = useRankingPlayerDetail(
    detailPlayer?.numjugador ?? null
  );

  const handleBack = () => setSelectedCategory(null);

  return (
    <Layout>
      <PageHero
        title="Ranking"
        subtitle="Puntos acumulados de la gira por categoría"
        backgroundImage={resultadosHero}
      />
      <section className="py-16 bg-white">
        <div className="container mx-auto px-4">
          {!selectedCategory ? (
            <>
              <div className="text-center mb-10">
                <h2 className="text-3xl font-bold text-foreground">
                  CATEGORÍAS: <span className="text-primary">{loadingCats ? '…' : categories.length}</span>
                </h2>
              </div>

              {loadingCats ? (
                <div className="flex justify-center py-12">
                  <Loader2 className="h-8 w-8 animate-spin text-primary" />
                </div>
              ) : categories.length === 0 ? (
                <div className="text-center text-muted-foreground py-12">
                  <Trophy className="h-8 w-8 mx-auto mb-2 opacity-50" />
                  No hay categorías con puntos registrados en esta gira
                </div>
              ) : (
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                  {categories.map((category) => (
                    <Card key={category.id} className="border-border/50 hover:border-primary/50 transition-colors">
                      <CardContent className="p-4 text-center">
                        <h3 className="font-bold text-foreground">{category.shortName}</h3>
                        <p className="text-2xl font-bold text-primary my-2">{category.playerCount}</p>
                        <Button size="sm" onClick={() => setSelectedCategory(category)} className="w-full">
                          Ver
                        </Button>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              )}
            </>
          ) : (
            <>
              <Button
                variant="ghost"
                onClick={handleBack}
                className="mb-6 gap-2 bg-primary/10 hover:bg-primary/20"
              >
                <ArrowLeft className="h-4 w-4" />
                Volver a categorías
              </Button>

              <div className="mb-8 text-left md:text-center">
                <h2 className="text-2xl font-light text-foreground mb-2">
                  Categoría:{' '}
                  <span className="font-bold">{rankingData?.name || selectedCategory.name}</span>
                </h2>
                <p className="text-muted-foreground">
                  <span className="font-bold text-foreground">Total jugadores:</span>{' '}
                  <span className="text-primary font-bold">
                    {rankingData?.playerCount ?? selectedCategory.playerCount}
                  </span>
                </p>
              </div>

              <Card className="border-border/50 bg-white w-full max-w-4xl mx-auto">
                <div className="overflow-x-auto bg-white">
                  {loadingPlayers ? (
                    <div className="flex justify-center py-12">
                      <Loader2 className="h-8 w-8 animate-spin text-primary" />
                    </div>
                  ) : (
                    <Table className="bg-white tournament-table">
                      <TableHeader>
                        <TableRow className="bg-primary hover:bg-primary">
                          <TableHead className="text-primary-foreground font-bold text-center w-14">Po</TableHead>
                          <TableHead className="text-primary-foreground font-bold text-center">Club</TableHead>
                          <TableHead className="text-primary-foreground font-bold">Jugador</TableHead>
                          <TableHead className="text-primary-foreground font-bold text-center">Puntos</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {players.length > 0 ? (
                          players.map((player) => (
                            <TableRow
                              key={`${player.numjugador}-${player.position}`}
                              className="bg-white hover:bg-primary/5 cursor-pointer"
                              onClick={() =>
                                setDetailPlayer({ numjugador: player.numjugador, name: player.name })
                              }
                            >
                              <TableCell className="text-center font-semibold">{player.position}</TableCell>
                              <TableCell className="p-1 text-center align-middle">
                                <img
                                  src={player.clubLogo}
                                  alt={player.club || 'Club logo'}
                                  className="w-auto object-contain rounded inline-block"
                                  style={{ height: '2.1375rem' }}
                                  onError={(e) => {
                                    (e.target as HTMLImageElement).src = `data:image/svg+xml,${encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" fill="%23166534" rx="4"/></svg>')}`;
                                  }}
                                />
                              </TableCell>
                              {/* Nombre recortado a 4 renglones en móvil (.player-name-clamp) */}
                              <TableCell className="player-name-cell">
                                <span className="player-name-clamp">{player.name}</span>
                              </TableCell>
                              <TableCell className="text-center font-bold text-primary">
                                {formatPuntos(player.puntos)}
                              </TableCell>
                            </TableRow>
                          ))
                        ) : (
                          <TableRow>
                            <TableCell colSpan={4} className="text-center text-muted-foreground py-8">
                              <Trophy className="h-8 w-8 mx-auto mb-2 opacity-50" />
                              No hay jugadores con puntos en esta categoría
                            </TableCell>
                          </TableRow>
                        )}
                      </TableBody>
                    </Table>
                  )}
                </div>
              </Card>
            </>
          )}
        </div>
      </section>

      {/* ============= Desglose por etapas del jugador ============= */}
      <Dialog open={!!detailPlayer} onOpenChange={(open) => !open && setDetailPlayer(null)}>
        <DialogContent className="max-w-2xl bg-white">
          <DialogHeader>
            <DialogTitle className="text-left">
              {detail?.jugador || detailPlayer?.name}
            </DialogTitle>
          </DialogHeader>

          {loadingDetail ? (
            <div className="flex justify-center py-10">
              <Loader2 className="h-6 w-6 animate-spin text-primary" />
            </div>
          ) : (detail?.etapas ?? []).length === 0 ? (
            <p className="text-muted-foreground py-6 text-center">
              Este jugador aún no tiene etapas con puntos registrados
            </p>
          ) : (
            <>
              <div className="overflow-x-auto">
                <Table className="bg-white tournament-table">
                  <TableHeader>
                    <TableRow className="bg-primary hover:bg-primary">
                      <TableHead className="text-primary-foreground font-bold">Torneo</TableHead>
                      <TableHead className="text-primary-foreground font-bold text-center">Score</TableHead>
                      <TableHead className="text-primary-foreground font-bold text-center">Lugar</TableHead>
                      <TableHead className="text-primary-foreground font-bold text-center">Puntos</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {(detail?.etapas ?? []).map((etapa) => (
                      <TableRow
                        key={etapa.torneoid}
                        className={
                          etapa.counted
                            ? 'bg-primary/10 hover:bg-primary/10 font-semibold'
                            : 'bg-white hover:bg-white'
                        }
                      >
                        <TableCell className="whitespace-nowrap">
                          <span className="inline-flex items-center gap-1">
                            {etapa.counted && <Star className="h-3.5 w-3.5 text-primary" />}
                            {etapa.etapa || etapa.nombre}
                          </span>
                        </TableCell>
                        <TableCell className="text-center">{etapa.score ?? '-'}</TableCell>
                        <TableCell className="text-center">{etapa.lugar ?? '-'}</TableCell>
                        <TableCell className="text-center text-primary">
                          {formatPuntos(etapa.puntos)}
                        </TableCell>
                      </TableRow>
                    ))}
                    <TableRow className="bg-muted/60 hover:bg-muted/60">
                      <TableCell className="font-bold">TOTAL</TableCell>
                      <TableCell />
                      <TableCell />
                      <TableCell className="text-center font-bold text-primary">
                        {formatPuntos(detail?.totalPuntos ?? 0)}
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </div>
              <p className="text-xs text-muted-foreground">
                <Star className="h-3 w-3 text-primary inline mr-1" />
                Las etapas resaltadas son las que cuentan para el total del ranking.
              </p>
            </>
          )}
        </DialogContent>
      </Dialog>
    </Layout>
  );
};

export default Ranking;
