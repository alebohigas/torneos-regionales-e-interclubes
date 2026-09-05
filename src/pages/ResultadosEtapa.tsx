/**
 * ResultadosEtapa
 *
 * Subpágina de resultados por etapa de la gira (/resultados/e/:etapa).
 * Reutiliza la página Resultados pasando el `torneoid` de la etapa, por lo que
 * usa los mismos endpoints (`resultados.php`, `resultados_jug.php` y el
 * scorecard de `resultados_tarjeta.php` / tarjeta de parejas).
 */

import { useParams } from 'react-router-dom';
import { Loader2 } from 'lucide-react';
import Layout from '@/components/layout/Layout';
import PageHero from '@/components/shared/PageHero';
import Resultados from '@/pages/Resultados';
import { useJugadoresEtapas } from '@/hooks/useJugadoresEtapas';
import { formatEtapaLabel } from '@/lib/etapaLabel';
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

  if (!match) {
    return (
      <Layout>
        <PageHero
          title="Etapa no disponible"
          subtitle="Esta etapa aún no tiene información publicada"
          backgroundImage={resultadosHero}
        />
      </Layout>
    );
  }

  return (
    <Resultados
      key={match.torneoid}
      torneoIdOverride={String(match.torneoid)}
      title={`Resultados ${formatEtapaLabel(match.etapaLabel, match.etapa)}`}
      subtitle={match.name || 'Resultados de la etapa'}
    />
  );
};

export default ResultadosEtapa;
