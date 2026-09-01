/**
 * ResultadosEtapa
 *
 * Subpágina de resultados por etapa de la gira (/resultados/e/:etapa).
 * Por ahora sólo resuelve la etapa (torneo) y muestra el encabezado; la tabla
 * de resultados por torneoid se conecta en el siguiente paso.
 */

import { useParams } from 'react-router-dom';
import { Loader2 } from 'lucide-react';
import Layout from '@/components/layout/Layout';
import PageHero from '@/components/shared/PageHero';
import { Card, CardContent } from '@/components/ui/card';
import { useJugadoresEtapas } from '@/hooks/useJugadoresEtapas';
import resultadosHero from '@/assets/resultados-hero.jpg';

const ResultadosEtapa = () => {
  const { etapa } = useParams<{ etapa: string }>();
  const etapaNum = Number(etapa);
  const { data: etapas = [], isLoading } = useJugadoresEtapas();
  const match = etapas.find((e) => e.etapa === etapaNum);

  if (isLoading) {
    return (
      <Layout>
        <div className="flex justify-center py-24">
          <Loader2 className="h-8 w-8 animate-spin text-primary" />
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <PageHero
        title={match ? `Resultados Etapa ${match.etapa}` : 'Etapa no disponible'}
        subtitle={match?.name || 'Resultados por etapa de la gira'}
        backgroundImage={resultadosHero}
      />
      <section className="py-16 bg-white">
        <div className="container mx-auto px-4">
          <Card className="max-w-2xl mx-auto border-border/50">
            <CardContent className="p-8 text-center text-muted-foreground">
              {match
                ? 'Los resultados de esta etapa se conectarán en el siguiente paso.'
                : 'Esta etapa aún no tiene información publicada.'}
            </CardContent>
          </Card>
        </div>
      </section>
    </Layout>
  );
};

export default ResultadosEtapa;
