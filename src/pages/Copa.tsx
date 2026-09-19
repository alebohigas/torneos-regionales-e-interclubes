/**
 * COPA Page
 * Copas de la gira (varonil, femenil y conjunta según `copas.grupocopas`):
 * cada jugador aporta sus puntos al club que representa y la tabla muestra la
 * sumatoria por club. Al hacer click en un club se abre el desglose de los
 * jugadores que aportaron esos puntos.
 */

import Layout from '@/components/layout/Layout';
import PageHero from '@/components/shared/PageHero';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowLeft, Trophy, Loader2, ChevronDown } from 'lucide-react';
import copaHero from '@/assets/premios-hero.jpg';
import { Fragment, useState } from 'react';
import { useCopas, useCopaClubs, useCopaClubEtapas, type Copa as CopaItem } from '@/hooks/useCopaData';

/** Formatea puntos con un decimal como el legacy (round(puntos,1)) */
const formatPuntos = (value: number): string =>
  Number.isFinite(value) ? value.toFixed(1).replace(/\.0$/, '') : '0';

const LOGO_FALLBACK = `data:image/svg+xml,${encodeURIComponent(
  '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" fill="%23166534" rx="4"/></svg>'
)}`;

/** Título y subtítulo que se muestran para cada copa. */
const copaDisplayName = (copa: CopaItem): string => {
  if (copa.sexo === 'M') return 'PUNTAJE VARONIL';
  if (copa.sexo === 'F') return 'PUNTAJE FEMENIL';
  return copa.name;
};
const copaTipo = (copa: CopaItem): string => {
  if (copa.sexo === 'M') return 'Solo jugadores varoniles';
  if (copa.sexo === 'F') return 'Solo jugadores femeniles';
  return 'Puntaje conjunto';
};

const Copa = () => {
  /** Copa seleccionada (null = grid de copas) */
  const [selectedCopa, setSelectedCopa] = useState<CopaItem | null>(null);
  /** Club con el desglose abierto */
  const [openClub, setOpenClub] = useState<string | null>(null);

  const { data: copas = [], isLoading: loadingCopas } = useCopas();
  const { data: copaData, isLoading: loadingClubs } = useCopaClubs(selectedCopa?.copasid ?? null);
  const { data: clubEtapas = [], isLoading: loadingPlayers } = useCopaClubEtapas(
    selectedCopa?.copasid ?? null,
    openClub
  );
  /** Etapa expandida dentro del club abierto */
  const [openEtapa, setOpenEtapa] = useState<string | null>(null);

  const clubs = copaData?.clubs ?? [];

  return (
    <Layout>
      <PageHero
        title="Copa"
        subtitle="Puntos que aporta cada jugador a su club"
        backgroundImage={copaHero}
      />
      <section className="py-16 bg-white">
        <div className="container mx-auto px-4">
          {!selectedCopa ? (
            <>
              <div className="text-center mb-10">
                <h2 className="text-3xl font-bold text-foreground">
                  COPAS: <span className="text-primary">{loadingCopas ? '…' : copas.length}</span>
                </h2>
              </div>

              {loadingCopas ? (
                <div className="flex justify-center py-12">
                  <Loader2 className="h-8 w-8 animate-spin text-primary" />
                </div>
              ) : copas.length === 0 ? (
                <div className="text-center text-muted-foreground py-12">
                  <Trophy className="h-8 w-8 mx-auto mb-2 opacity-50" />
                  Esta gira no tiene copas configuradas
                </div>
              ) : (
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 max-w-4xl mx-auto">
                  {copas.map((copa) => (
                    <Card
                      key={copa.copasid}
                      className="border-border/50 hover:border-primary/50 transition-colors"
                    >
                      <CardContent className="p-5 text-center">
                        <h3 className="font-bold text-foreground">{copaDisplayName(copa)}</h3>
                        <p className="text-sm text-muted-foreground my-2">{copaTipo(copa)}</p>
                        <Button
                          size="sm"
                          onClick={() => {
                            setSelectedCopa(copa);
                            setOpenClub(null);
                          }}
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
              <Button
                variant="ghost"
                onClick={() => {
                  setSelectedCopa(null);
                  setOpenClub(null);
                }}
                className="mb-6 gap-2 bg-primary/10 hover:bg-primary/20"
              >
                <ArrowLeft className="h-4 w-4" />
                Volver a copas
              </Button>

              <div className="mb-8 text-left md:text-center">
                <h2 className="text-2xl font-light text-foreground mb-2">
                  Copa: <span className="font-bold">{copaDisplayName(selectedCopa)}</span>
                </h2>
                <p className="text-muted-foreground">
                  <span className="font-bold text-foreground">Total clubes:</span>{' '}
                  <span className="text-primary font-bold">{clubs.length}</span>
                </p>
              </div>

              <Card className="border-border/50 bg-white w-full max-w-4xl mx-auto">
                <div className="overflow-x-auto bg-white">
                  {loadingClubs ? (
                    <div className="flex justify-center py-12">
                      <Loader2 className="h-8 w-8 animate-spin text-primary" />
                    </div>
                  ) : (
                    <Table className="bg-white tournament-table">
                      <TableHeader>
                        <TableRow className="bg-primary hover:bg-primary">
                          <TableHead className="text-primary-foreground font-bold text-center w-14">Po</TableHead>
                          <TableHead className="text-primary-foreground font-bold text-center">Logo</TableHead>
                          <TableHead className="text-primary-foreground font-bold">Club</TableHead>
                          <TableHead className="text-primary-foreground font-bold text-center">Puntos</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {clubs.length > 0 ? (
                          clubs.map((club) => {
                            const isOpen = openClub === club.clubid;
                            return (
                              <Fragment key={`${club.clubid}-${club.position}`}>
                                <TableRow
                                  className={
                                    isOpen
                                      ? 'bg-primary/5 hover:bg-primary/10 cursor-pointer'
                                      : 'bg-white hover:bg-primary/5 cursor-pointer'
                                  }
                                  onClick={() => {
                                    setOpenClub(isOpen ? null : club.clubid);
                                    setOpenEtapa(null);
                                  }}
                                >
                                  <TableCell className="text-center font-semibold">{club.position}</TableCell>
                                  <TableCell className="p-1 text-center align-middle">
                                    <img
                                      src={club.logo}
                                      alt={club.club || 'Club logo'}
                                      className="w-auto object-contain rounded inline-block"
                                      style={{ height: '2.1375rem' }}
                                      onError={(e) => {
                                        (e.target as HTMLImageElement).src = LOGO_FALLBACK;
                                      }}
                                    />
                                  </TableCell>
                                  <TableCell className="player-name-cell">
                                    <span className="player-name-clamp">{club.club}</span>
                                  </TableCell>
                                  <TableCell className="text-center font-bold text-primary">
                                    <span className="inline-flex items-center gap-1.5">
                                      {formatPuntos(club.puntos)}
                                      <ChevronDown
                                        className={`h-4 w-4 text-muted-foreground transition-transform ${
                                          isOpen ? 'rotate-180' : ''
                                        }`}
                                      />
                                    </span>
                                  </TableCell>
                                </TableRow>

                                {isOpen && (
                                  <TableRow className="bg-muted/10 hover:bg-muted/10">
                                    <TableCell colSpan={4} className="p-0">
                                      <div className="p-4 border-t border-b border-primary/20">
                                        {loadingPlayers ? (
                                          <div className="flex justify-center py-8">
                                            <Loader2 className="h-6 w-6 animate-spin text-primary" />
                                          </div>
                                        ) : clubEtapas.length === 0 ? (
                                          <p className="text-muted-foreground py-4 text-center">
                                            Este club aún no tiene puntos por etapa
                                          </p>
                                        ) : (
                                          <div className="overflow-x-auto">
                                            <Table className="bg-white tournament-table table-fixed w-full">
                                              <TableHeader>
                                                <TableRow className="bg-primary hover:bg-primary">
                                                  <TableHead className="text-primary-foreground font-bold">Torneo</TableHead>
                                                  <TableHead className="text-primary-foreground font-bold text-center">Puntos</TableHead>
                                                  <TableHead className="text-primary-foreground font-bold text-center">Penalties</TableHead>
                                                  <TableHead className="text-primary-foreground font-bold text-center">Total</TableHead>
                                                </TableRow>
                                              </TableHeader>
                                              <TableBody>
                                                {clubEtapas.map((et) => {
                                                  const etOpen = openEtapa === et.torneoid;
                                                  return (
                                                    <Fragment key={et.torneoid}>
                                                      <TableRow
                                                        className={
                                                          etOpen
                                                            ? 'bg-primary/5 hover:bg-primary/10 cursor-pointer'
                                                            : 'bg-white hover:bg-primary/5 cursor-pointer'
                                                        }
                                                        onClick={() => setOpenEtapa(etOpen ? null : et.torneoid)}
                                                      >
                                                        <TableCell className="font-medium">
                                                          <span className="inline-flex items-center gap-1.5">
                                                            <ChevronDown
                                                              className={`h-4 w-4 text-muted-foreground transition-transform ${
                                                                etOpen ? 'rotate-180' : ''
                                                              }`}
                                                            />
                                                            {et.etapa}
                                                          </span>
                                                        </TableCell>
                                                        <TableCell className="text-center font-bold text-primary">
                                                          {formatPuntos(et.puntos)}
                                                        </TableCell>
                                                        <TableCell className="text-center font-bold text-primary">
                                                          {formatPuntos(et.penalties)}
                                                        </TableCell>
                                                        <TableCell className="text-center font-bold text-primary">
                                                          {formatPuntos(et.total)}
                                                        </TableCell>
                                                      </TableRow>

                                                      {etOpen && (
                                                        <TableRow className="bg-muted/10 hover:bg-muted/10">
                                                          <TableCell colSpan={4} className="p-0">
                                                            <div className="p-3 border-t border-b border-primary/20">
                                                              {et.players.length === 0 ? (
                                                                <p className="text-muted-foreground py-3 text-center text-sm">
                                                                  Sin jugadores con puntos en esta etapa
                                                                </p>
                                                              ) : (
                                                                <Table className="bg-white tournament-table table-fixed w-full">
                                                                  <TableHeader>
                                                                    <TableRow className="bg-primary/80 hover:bg-primary/80">
                                                                      <TableHead className="text-primary-foreground font-bold text-center w-14">
                                                                        Po
                                                                      </TableHead>
                                                                      <TableHead className="text-primary-foreground font-bold">
                                                                        Jugador
                                                                      </TableHead>
                                                                      <TableHead className="text-primary-foreground font-bold text-center w-24">
                                                                        Puntos
                                                                      </TableHead>
                                                                    </TableRow>
                                                                  </TableHeader>
                                                                  <TableBody>
                                                                    {et.players.map((p) => (
                                                                      <TableRow
                                                                        key={`${et.torneoid}-${p.numjugador}`}
                                                                        className="bg-white hover:bg-white"
                                                                      >
                                                                        <TableCell className="text-center">{p.position}</TableCell>
                                                                        <TableCell className="player-name-cell">
                                                                          <span className="player-name-clamp">{p.jugador}</span>
                                                                        </TableCell>
                                                                        <TableCell className="text-center font-bold text-primary">
                                                                          {formatPuntos(p.puntos)}
                                                                        </TableCell>
                                                                      </TableRow>
                                                                    ))}
                                                                  </TableBody>
                                                                </Table>
                                                              )}
                                                            </div>
                                                          </TableCell>
                                                        </TableRow>
                                                      )}
                                                    </Fragment>
                                                  );
                                                })}
                                                <TableRow className="bg-muted hover:bg-muted">
                                                  <TableCell colSpan={3} className="font-bold text-right">
                                                    TOTAL CLUB
                                                  </TableCell>
                                                  <TableCell className="text-center font-bold text-primary">
                                                    {formatPuntos(club.puntos)}
                                                  </TableCell>
                                                </TableRow>
                                              </TableBody>
                                            </Table>
                                          </div>
                                        )}
                                      </div>
                                    </TableCell>
                                  </TableRow>
                                )}
                              </Fragment>
                            );
                          })
                        ) : (
                          <TableRow>
                            <TableCell colSpan={4} className="text-center text-muted-foreground py-8">
                              <Trophy className="h-8 w-8 mx-auto mb-2 opacity-50" />
                              No hay clubes con puntos en esta copa
                            </TableCell>
                          </TableRow>
                        )}
                      </TableBody>
                    </Table>
                  )}
                </div>
              </Card>

              <p className="text-center text-sm text-muted-foreground mt-4">
                Haz click en un club para ver sus etapas, y en una etapa para ver los jugadores que aportan puntos.
              </p>
            </>
          )}
        </div>
      </section>
    </Layout>
  );
};

export default Copa;
