/**
 * RANKING FINAL Page
 * Ranking de la gira con los 5 mejores resultados (top5) por jugador.
 * Categorías en grid (mismo patrón que /jugadores y /resultados) y, al hacer
 * click en un jugador, desglose por etapa resaltando los puntos que sí cuentan.
 */

import Layout from '@/components/layout/Layout';
import PageHero from '@/components/shared/PageHero';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowLeft, Trophy, Loader2, Star, ChevronDown } from 'lucide-react';
import rankingHero from '@/assets/jugadores-hero.jpg';
import { Fragment, useState } from 'react';

import {
  useRankingFinalCategories,
  useRankingFinalPlayers,
  useRankingFinalPlayerDetail,
  type RankingFinalCategory,
  type RankingFinalPlayer,
} from '@/hooks/useRankingFinalData';

/** Formatea puntos con un decimal (round(puntos,1) del legacy) */
const formatPuntos = (value: number): string =>
  Number.isFinite(value) ? value.toFixed(1).replace(/\.0$/, '') : '0';

const LOGO_FALLBACK = `data:image/svg+xml,${encodeURIComponent(
  '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" fill="%23166534" rx="4"/></svg>'
)}`;

const RankingFinal = () => {
  const [selectedCategory, setSelectedCategory] = useState<RankingFinalCategory | null>(null);
  const [selectedPlayer, setSelectedPlayer] = useState<RankingFinalPlayer | null>(null);

  const { data: categories = [], isLoading: loadingCats } = useRankingFinalCategories();
  const { data: rankingData, isLoading: loadingPlayers } = useRankingFinalPlayers(
    selectedCategory?.id ?? null,
    !!selectedCategory
  );
  const { data: detail, isLoading: loadingDetail } = useRankingFinalPlayerDetail(
    selectedPlayer?.numjugador ?? null
  );
  const players = rankingData?.players ?? [];

  return (
    <Layout>
      <PageHero
        title="Ranking Final"
        subtitle="Mejores 5 resultados acumulados de la gira"
        backgroundImage={rankingHero}
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
                onClick={() => setSelectedCategory(null)}
                className="mb-6 gap-2 bg-primary/10 hover:bg-primary/20"
              >
                <ArrowLeft className="h-4 w-4" />
                Volver a categorías
              </Button>

              <div className="mb-8 text-left md:text-center">
                <h2 className="text-2xl font-light text-foreground mb-2">
                  Categoría: <span className="font-bold">{rankingData?.name || selectedCategory.name}</span>
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
                          <TableHead className="text-primary-foreground font-bold text-center">Etapas</TableHead>
                          <TableHead className="text-primary-foreground font-bold text-center">Puntos</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {players.length > 0 ? (
                          players.map((player) => {
                            const isOpen = selectedPlayer?.numjugador === player.numjugador;
                            return (
                              <Fragment key={`${player.numjugador}-${player.position}`}>

                                <TableRow
                                  key={`${player.numjugador}-${player.position}`}
                                  className={
                                    isOpen
                                      ? 'bg-primary/5 hover:bg-primary/10 cursor-pointer'
                                      : 'bg-white hover:bg-primary/5 cursor-pointer'
                                  }
                                  onClick={() => setSelectedPlayer(isOpen ? null : player)}
                                >
                                  <TableCell className="text-center font-semibold">{player.position}</TableCell>
                                  <TableCell className="p-1 text-center align-middle">
                                    <img
                                      src={player.clubLogo}
                                      alt={player.club || 'Club logo'}
                                      className="w-auto object-contain rounded inline-block"
                                      style={{ height: '2.1375rem' }}
                                      onError={(e) => {
                                        (e.target as HTMLImageElement).src = LOGO_FALLBACK;
                                      }}
                                    />
                                  </TableCell>
                                  <TableCell className="player-name-cell">
                                    <span className="player-name-clamp">{player.name}</span>
                                  </TableCell>
                                  <TableCell className="text-center text-muted-foreground">{player.etapas}</TableCell>
                                  <TableCell className="text-center font-bold text-primary">
                                    <span className="inline-flex items-center gap-1.5">
                                      {formatPuntos(player.puntos)}
                                      <ChevronDown
                                        className={`h-4 w-4 text-muted-foreground transition-transform ${
                                          isOpen ? 'rotate-180' : ''
                                        }`}
                                      />
                                    </span>
                                  </TableCell>
                                </TableRow>

                                {isOpen && (
                                  <TableRow
                                    key={`${player.numjugador}-detail`}
                                    className="bg-muted/10 hover:bg-muted/10"
                                  >
                                    <TableCell colSpan={5} className="p-0">
                                      <div className="p-4 border-t border-b border-primary/20">
                                        {loadingDetail ? (
                                          <div className="flex justify-center py-8">
                                            <Loader2 className="h-6 w-6 animate-spin text-primary" />
                                          </div>
                                        ) : (
                                          <>
                                            <div className="overflow-x-auto">
                                              <Table className="bg-white tournament-table">
                                                <TableHeader>
                                                  <TableRow className="bg-primary hover:bg-primary">
                                                    <TableHead className="text-primary-foreground font-bold">
                                                      Etapa
                                                    </TableHead>
                                                    <TableHead className="text-primary-foreground font-bold text-center">
                                                      Score
                                                    </TableHead>
                                                    <TableHead className="text-primary-foreground font-bold text-center">
                                                      Total
                                                    </TableHead>
                                                    <TableHead className="text-primary-foreground font-bold text-center">
                                                      Puntos
                                                    </TableHead>
                                                  </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                  {(detail?.etapas ?? []).map((etapa) => (
                                                    <TableRow
                                                      key={etapa.torneoid}
                                                      className={
                                                        etapa.counted
                                                          ? 'bg-primary/10 hover:bg-primary/10'
                                                          : 'bg-white hover:bg-white'
                                                      }
                                                    >
                                                      <TableCell className="font-semibold whitespace-nowrap">
                                                        <span className="inline-flex items-center gap-1.5">
                                                          {etapa.counted && (
                                                            <Star className="h-3.5 w-3.5 text-primary fill-primary" />
                                                          )}
                                                          {etapa.etapa || etapa.nombre}
                                                        </span>
                                                      </TableCell>
                                                      <TableCell className="text-center">
                                                        {etapa.rounds?.[0] ?? '—'}
                                                      </TableCell>
                                                      <TableCell className="text-center font-semibold">
                                                        {etapa.total ?? '—'}
                                                      </TableCell>
                                                      <TableCell
                                                        className={
                                                          etapa.counted
                                                            ? 'text-center font-bold text-primary'
                                                            : 'text-center text-muted-foreground'
                                                        }
                                                      >
                                                        {etapa.played ? formatPuntos(etapa.puntos) : '—'}
                                                      </TableCell>
                                                    </TableRow>
                                                  ))}
                                                  <TableRow className="bg-muted hover:bg-muted">
                                                    <TableCell colSpan={3} className="font-bold text-right">
                                                      Total ranking (mejores 5)
                                                    </TableCell>
                                                    <TableCell className="text-center font-bold text-primary">
                                                      {formatPuntos(detail?.totalPuntos ?? 0)}
                                                    </TableCell>
                                                  </TableRow>
                                                </TableBody>
                                              </Table>
                                            </div>

                                            <p className="text-xs text-muted-foreground mt-2">
                                              Las etapas resaltadas son las que suman al total del ranking. Puntos de
                                              todas las etapas:{' '}
                                              <span className="font-semibold">
                                                {formatPuntos(detail?.totalTodos ?? 0)}
                                              </span>
                                              .
                                            </p>
                                          </>
                                        )}
                                      </div>
                                    </TableCell>
                                  </TableRow>
                                )}
                              </>
                            );
                          })
                        ) : (
                          <TableRow>
                            <TableCell colSpan={5} className="text-center text-muted-foreground py-8">
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

              <p className="text-center text-sm text-muted-foreground mt-4">
                Haz click en un jugador para ver el desglose por etapa.
              </p>
            </>
          )}
        </div>
      </section>

    </Layout>
  );
};

export default RankingFinal;
