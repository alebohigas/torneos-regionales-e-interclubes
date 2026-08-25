/**
 * FIELD-GIRA Page
 * Duplicado de /jugadores pero con los datos "seed" de la gira:
 * categorias_tmp + jugadores_seed + clubs (endpoint field_gira.php).
 */

import Layout from '@/components/layout/Layout';
import PageHero from '@/components/shared/PageHero';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowLeft, Users, Loader2 } from 'lucide-react';
import jugadoresHero from '@/assets/jugadores-hero.jpg';
import { useState } from 'react';
import { useFieldGiraCategories, useFieldGiraPlayers, type FieldGiraCategory } from '@/hooks/useFieldGiraData';

/** Formatea YYYY-MM-DD sin desfase de zona horaria */
const formatFecha = (value: string): string => {
  if (!value) return '—';
  const [y, m, d] = value.slice(0, 10).split('-').map(Number);
  if (!y || !m || !d) return '—';
  return new Date(y, m - 1, d).toLocaleDateString('es-MX', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
};

const FieldGira = () => {
  /** Categoría seleccionada (null = grid de categorías) */
  const [selectedCategory, setSelectedCategory] = useState<FieldGiraCategory | null>(null);

  const { data: categories = [], isLoading: loadingCats } = useFieldGiraCategories();
  const { data: playersData, isLoading: loadingPlayers } = useFieldGiraPlayers(
    selectedCategory?.id ?? null,
    !!selectedCategory
  );
  const players = playersData?.players ?? [];

  /** Total de jugadores en todas las categorías */
  const totalPlayers = categories.reduce((sum, cat) => sum + cat.playerCount, 0);

  const handleBack = () => setSelectedCategory(null);

  return (
    <Layout>
      <PageHero
        title="Field-Gira"
        subtitle="Listado del field por categoría de la gira"
        backgroundImage={jugadoresHero}
      />
      <section className="py-16 bg-white">
        <div className="container mx-auto px-4">
          {!selectedCategory ? (
            <>
              <div className="text-center mb-10">
                <h2 className="text-3xl font-bold text-foreground">
                  JUGADORES: <span className="text-primary">{loadingCats ? '…' : totalPlayers}</span>
                </h2>
              </div>

              {loadingCats ? (
                <div className="flex justify-center py-12">
                  <Loader2 className="h-8 w-8 animate-spin text-primary" />
                </div>
              ) : categories.length === 0 ? (
                <div className="text-center text-muted-foreground py-12">
                  <Users className="h-8 w-8 mx-auto mb-2 opacity-50" />
                  No hay categorías con jugadores registrados
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
                  Categoría: <span className="font-bold">{selectedCategory.name}</span>
                </h2>
                <p className="text-muted-foreground">
                  <span className="font-bold text-foreground">Total jugadores:</span>{' '}
                  <span className="text-primary font-bold">
                    {playersData?.playerCount ?? selectedCategory.playerCount}
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
                          <TableHead className="text-primary-foreground font-bold text-center">Club</TableHead>
                          <TableHead className="text-primary-foreground font-bold">Jugador</TableHead>
                          <TableHead className="text-primary-foreground font-bold text-center">
                            Fecha de nacimiento
                          </TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {players.length > 0 ? (
                          players.map((player) => (
                            <TableRow key={player.id} className="bg-white hover:bg-white">
                              <TableCell className="p-1 text-center align-middle">
                                <img
                                  src={player.clubLogo}
                                  alt="Club logo"
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
                              <TableCell className="text-center">{formatFecha(player.fechanac)}</TableCell>
                            </TableRow>
                          ))
                        ) : (
                          <TableRow>
                            <TableCell colSpan={3} className="text-center text-muted-foreground py-8">
                              <Users className="h-8 w-8 mx-auto mb-2 opacity-50" />
                              No hay jugadores registrados en esta categoría
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
    </Layout>
  );
};

export default FieldGira;
