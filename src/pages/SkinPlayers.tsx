/**
 * SkinPlayers Page (/skinplayers)
 * ------------------------------------------------------------
 * Same UX as /jugadores but restricted to players enrolled in the
 * SKIN GAME (jugadores.Skeenjuga = 1). Reuses `useCategories` and
 * `usePlayers` with the `skin: true` option, which routes to the same
 * PHP endpoints with `?skin=1` appended.
 *
 * Grouping mirrors the legacy `jugadores_skin.php` view: cards per
 * tournament category (filtered to catrel=0, only categories with
 * at least one skin player), drill-down shows the player list.
 *
 * IMPORTANT: this page hides parejas grouping — skin-enrolled players
 * are shown as a flat list even in categories with formato='PAREJAS',
 * since the skin game is played individually.
 */

import Layout from '@/components/layout/Layout';
import PageHero from '@/components/shared/PageHero';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowLeft, Users, Loader2 } from 'lucide-react';
// Hero HD propio de Skin Players
import skinHero from '@/assets/skin-players-hero.jpg';
import { useState } from 'react';
import { useCategories, usePlayers } from '@/hooks/usePlayersData';
import type { CategoryDetail } from '@/data/playersData';

const SkinPlayers = () => {
  /** Currently selected category (null = show grid) */
  const [selectedCategory, setSelectedCategory] = useState<CategoryDetail | null>(null);

  // Skin-only categories and players (Skeenjuga = 1).
  const { data: categories = [], isLoading: loadingCats } = useCategories({ skin: true });
  const { data: playersData, isLoading: loadingPlayers } = usePlayers(
    selectedCategory?.id ?? null,
    !!selectedCategory,
    { skin: true }
  );
  const players = playersData?.players ?? [];

  /** Total skin-enrolled players across all categories */
  const totalPlayers = categories.reduce((sum, cat) => sum + cat.playerCount, 0);

  /** Navigate back to category grid */
  const handleBack = () => setSelectedCategory(null);

  return (
    <Layout>
      <PageHero
        title="Skin Players"
        subtitle="Jugadores inscritos al Skin Game"
        backgroundImage={skinHero}
      />
      <section className="py-16 bg-white">
        <div className="container mx-auto px-4">
          {!selectedCategory ? (
            <>
              {/* Total skin players header */}
              <div className="text-center mb-10">
                <h2 className="text-3xl font-bold text-foreground">
                  SKIN PLAYERS: <span className="text-primary">{loadingCats ? '…' : totalPlayers}</span>
                </h2>
              </div>

              {loadingCats ? (
                <div className="flex justify-center py-12">
                  <Loader2 className="h-8 w-8 animate-spin text-primary" />
                </div>
              ) : categories.length === 0 ? (
                <div className="text-center text-muted-foreground py-12">
                  <Users className="h-8 w-8 mx-auto mb-2 opacity-50" />
                  Aún no hay jugadores inscritos al Skin Game.
                </div>
              ) : (
                /* Categories grid — only categories with at least 1 skin player */
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                  {categories.map((category) => (
                    <Card key={category.id} className="border-border/50 hover:border-primary/50 transition-colors">
                      <CardContent className="p-4 text-center">
                        <h3 className="font-bold text-foreground">{category.shortName}</h3>
                        <p className="text-2xl font-bold text-primary my-2">{category.playerCount}</p>
                        <Button
                          size="sm"
                          onClick={() => setSelectedCategory(category)}
                          className="w-full"
                        >
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
              {/* Detail view for a category */}
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
                  Categoría: <span className="font-bold">{selectedCategory.name}</span>
                  {selectedCategory.skinGroupId ? (
                    <> / <span className="font-bold">GRUPO {selectedCategory.skinGroupId}</span></>
                  ) : null}
                </h2>
                <p className="text-muted-foreground mb-1">
                  <span className="font-bold text-foreground">Jugadores en Skin Game:</span>{' '}
                  <span className="text-primary font-bold">{selectedCategory.playerCount}</span>
                </p>
                {selectedCategory.teeName && (
                  <p className="text-muted-foreground">
                    <span className="font-bold text-foreground">Tee Salida:</span> {selectedCategory.teeName}
                  </p>
                )}
              </div>

              <Card className="border-border/50 bg-white w-full max-w-3xl mx-auto">
                <div className="overflow-x-auto bg-white">
                  {loadingPlayers ? (
                    <div className="flex justify-center py-12">
                      <Loader2 className="h-8 w-8 animate-spin text-primary" />
                    </div>
                  ) : (
                    <Table className="bg-white tournament-table">
                      <TableHeader>
                        <TableRow className="bg-primary hover:bg-primary">
                          <TableHead className="text-primary-foreground font-bold text-center">Club</TableHead>
                          <TableHead className="text-primary-foreground font-bold">Jugador</TableHead>
                          <TableHead className="text-primary-foreground font-bold text-center">HI</TableHead>
                          <TableHead className="text-primary-foreground font-bold text-center">HJ</TableHead>
                          <TableHead className="text-primary-foreground font-bold text-center">HN</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {players.length > 0 ? (
                          players.map((player) => (
                            <TableRow key={player.id} className="bg-white hover:bg-white">
                              <TableCell className="p-1 text-center align-middle">
                                <img
                                  src={player.clubLogo}
                                  alt="Club"
                                  className="w-auto object-contain rounded inline-block"
                                  style={{ height: '2.1375rem' }}
                                  onError={(e) => {
                                    (e.target as HTMLImageElement).src = `data:image/svg+xml,${encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" fill="%23166534" rx="4"/></svg>')}`;
                                  }}
                                />
                              </TableCell>
                              {/* Nombre recortado a 4 renglones en móvil (.player-name-clamp) */}
                              <TableCell className="player-name-cell"><span className="player-name-clamp">{player.name}</span></TableCell>
                              {/* HI = Handicap Index (indexjgo) */}
                              <TableCell className="text-center">{player.handicapIndex.toFixed(1)}</TableCell>
                              {/* HJ = Handicap de Juego (f_hdccampo) */}
                              <TableCell className="text-center">{player.handicapJuego}</TableCell>
                              {/* HN = Handicap Neto con Skeenporcent (f_hdccamponeto) */}
                              <TableCell className="text-center font-bold">{player.handicapNeto}</TableCell>
                            </TableRow>
                          ))
                        ) : (
                          <TableRow>
                            <TableCell colSpan={5} className="text-center text-muted-foreground py-8">
                              <Users className="h-8 w-8 mx-auto mb-2 opacity-50" />
                              No hay jugadores inscritos al Skin Game en esta categoría
                            </TableCell>
                          </TableRow>
                        )}
                      </TableBody>
                    </Table>
                  )}
                </div>
              </Card>
              {/* Leyenda de handicaps — mirrors legacy footer */}
              <p className="text-xs text-muted-foreground text-center mt-3 max-w-3xl mx-auto">
                H.I = Handicap Índice · H.J = Handicap de Juego · H.N = Handicap Neto
              </p>
            </>
          )}
        </div>
      </section>
    </Layout>
  );
};

export default SkinPlayers;