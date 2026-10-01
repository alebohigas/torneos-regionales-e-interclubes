/**
 * JUGADORES POR CLUB ETAPA
 * Totales de jugadores (VAR / FEM / Total) por club de la etapa (torneo) activa,
 * desde field_gira.php?clubs=1&torneoid=NN (tabla `jugadores` del torneo).
 * Al hacer clic en un club se despliega su lista de jugadores (orden
 * alfabético) con la categoría de cada uno (clubs=1&clubid=NN&torneoid=NN).
 */
import { Fragment, useState } from 'react';
import Layout from '@/components/layout/Layout';
import PageHero from '@/components/shared/PageHero';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ChevronDown, ChevronUp, Loader2, Users } from 'lucide-react';
import jugadoresHero from '@/assets/jugadores-hero.jpg';
import { useParams } from 'react-router-dom';
import { useFieldGiraClubPlayers, useFieldGiraClubsEtapa, type FieldGiraClub } from '@/hooks/useFieldGiraData';
import { useJugadoresEtapas } from '@/hooks/useJugadoresEtapas';
import { formatEtapaLabel } from '@/lib/etapaLabel';

/** Filas del detalle de jugadores de un club (se muestra bajo la fila del club). */
const ClubPlayersRows = ({ club, torneoid }: { club: FieldGiraClub; torneoid?: string }) => {
  const { data: players = [], isLoading } = useFieldGiraClubPlayers(club.id, torneoid);

  if (isLoading) {
    return (
      <TableRow className="bg-muted/30">
        <TableCell colSpan={5} className="py-4 text-center">
          <Loader2 className="h-5 w-5 animate-spin text-primary inline-block" />
        </TableCell>
      </TableRow>
    );
  }

  if (players.length === 0) {
    return (
      <TableRow className="bg-muted/30">
        <TableCell colSpan={5} className="py-3 text-center text-sm text-muted-foreground">
          Sin jugadores registrados en este club
        </TableCell>
      </TableRow>
    );
  }

  return (
    <>
      {players.map((p) => (
        <TableRow key={p.id} className="bg-muted/30 hover:bg-muted/40">
          <TableCell />
          <TableCell className="py-1.5 pl-4 text-sm">
            {p.jugador}
          </TableCell>
          <TableCell colSpan={3} className="py-1.5 text-xs text-muted-foreground">
            {p.categoria}
          </TableCell>
        </TableRow>
      ))}
    </>
  );
};

const JugadoresClubEtapa = () => {
  const { torneoid } = useParams<{ torneoid?: string }>();
  const { data: etapas = [] } = useJugadoresEtapas();
  const etapaInfo = torneoid
    ? etapas.find((e) => String(e.torneoid) === String(torneoid))
    : undefined;
  const etapaTitle = etapaInfo ? formatEtapaLabel(etapaInfo.etapaLabel, etapaInfo.etapa) : '';
  const { data: clubs = [], isLoading } = useFieldGiraClubsEtapa(torneoid);
  const [openClubId, setOpenClubId] = useState<string | null>(null);
  const totals = clubs.reduce(
    (acc, c) => ({ var: acc.var + c.var, fem: acc.fem + c.fem, total: acc.total + c.total }),
    { var: 0, fem: 0, total: 0 },
  );

  return (
    <Layout>
      <PageHero title={`Jugadores por Club ${etapaTitle || 'Etapa'}`.trim()} subtitle="Total de jugadores por club de la etapa" backgroundImage={jugadoresHero} />
      <section className="py-16 bg-white">
        <div className="container mx-auto px-4 max-w-4xl">
          {isLoading ? (
            <div className="flex justify-center py-12"><Loader2 className="h-8 w-8 animate-spin text-primary" /></div>
          ) : clubs.length === 0 ? (
            <div className="text-center text-muted-foreground py-12">
              <Users className="h-8 w-8 mx-auto mb-2 opacity-50" />
              No hay jugadores registrados en la etapa
            </div>
          ) : (
            <>
              <h2 className="mb-6 font-serif text-2xl md:text-[1.8rem] font-bold text-foreground">
                JUGADORES: <span className="text-primary">{totals.total}</span>
              </h2>
              <div className="rounded-lg border overflow-hidden">
              <Table>
                <TableHeader>
                  <TableRow className="bg-primary hover:bg-primary">
                    <TableHead className="text-primary-foreground w-20">Logo</TableHead>
                    <TableHead className="text-primary-foreground">Club</TableHead>
                    <TableHead className="text-primary-foreground text-center">VAR</TableHead>
                    <TableHead className="text-primary-foreground text-center">FEM</TableHead>
                    <TableHead className="text-primary-foreground text-center">Total</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {clubs.map((c) => {
                    const isOpen = openClubId === c.id;
                    return (
                      <Fragment key={c.id}>
                        <TableRow
                          className="cursor-pointer"
                          onClick={() => setOpenClubId(isOpen ? null : c.id)}
                        >
                          <TableCell className="bg-white py-1">
                            {c.logo ? <img src={c.logo} alt={c.name} className="h-8 w-auto max-w-[60px] object-contain" loading="lazy" /> : null}
                          </TableCell>
                          <TableCell className="font-medium" title={c.name}>
                            <span className="inline-flex items-center gap-1.5">
                              {c.abr || c.name}
                              {isOpen
                                ? <ChevronUp className="h-4 w-4 text-muted-foreground" />
                                : <ChevronDown className="h-4 w-4 text-muted-foreground" />}
                            </span>
                          </TableCell>
                          <TableCell className="text-center">{c.var}</TableCell>
                          <TableCell className="text-center">{c.fem}</TableCell>
                          <TableCell className="text-center font-bold text-primary">{c.total}</TableCell>
                        </TableRow>
                        {isOpen ? <ClubPlayersRows club={c} torneoid={torneoid} /> : null}
                      </Fragment>
                    );
                  })}
                  <TableRow className="bg-muted/50 font-bold">
                    <TableCell />
                    <TableCell>TOTAL</TableCell>
                    <TableCell className="text-center">{totals.var}</TableCell>
                    <TableCell className="text-center">{totals.fem}</TableCell>
                    <TableCell className="text-center text-primary">{totals.total}</TableCell>
                  </TableRow>
                </TableBody>
              </Table>
              </div>
            </>
          )}
        </div>
      </section>
    </Layout>
  );
};

export default JugadoresClubEtapa;
