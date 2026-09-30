/**
 * JUGADORES POR CLUB GIRA
 * Totales de jugadores (VAR / FEM / Total) por club de la gira, desde
 * field_gira.php?clubs=1 (jugadores_seed + clubs).
 */
import Layout from '@/components/layout/Layout';
import PageHero from '@/components/shared/PageHero';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Loader2, Users } from 'lucide-react';
import jugadoresHero from '@/assets/jugadores-hero.jpg';
import { useFieldGiraClubs } from '@/hooks/useFieldGiraData';

const JugadoresClubGira = () => {
  const { data: clubs = [], isLoading } = useFieldGiraClubs();
  const totals = clubs.reduce(
    (acc, c) => ({ var: acc.var + c.var, fem: acc.fem + c.fem, total: acc.total + c.total }),
    { var: 0, fem: 0, total: 0 },
  );

  return (
    <Layout>
      <PageHero title="Jugadores por Club" subtitle="Total de jugadores por club de la gira" backgroundImage={jugadoresHero} />
      <section className="py-16 bg-white">
        <div className="container mx-auto px-4 max-w-4xl">
          {isLoading ? (
            <div className="flex justify-center py-12"><Loader2 className="h-8 w-8 animate-spin text-primary" /></div>
          ) : clubs.length === 0 ? (
            <div className="text-center text-muted-foreground py-12">
              <Users className="h-8 w-8 mx-auto mb-2 opacity-50" />
              No hay jugadores registrados en la gira
            </div>
          ) : (
            <>
              <h2 className="mb-6 font-serif text-3xl md:text-4xl font-bold text-foreground">
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
                  {clubs.map((c) => (
                    <TableRow key={c.id}>
                      <TableCell className="bg-white py-1">
                        {c.logo ? <img src={c.logo} alt={c.name} className="h-8 w-auto max-w-[60px] object-contain" loading="lazy" /> : null}
                      </TableCell>
                      <TableCell className="font-medium" title={c.name}>{c.abr || c.name}</TableCell>
                      <TableCell className="text-center">{c.var}</TableCell>
                      <TableCell className="text-center">{c.fem}</TableCell>
                      <TableCell className="text-center font-bold text-primary">{c.total}</TableCell>
                    </TableRow>
                  ))}
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
          )}
        </div>
      </section>
    </Layout>
  );
};

export default JugadoresClubGira;
