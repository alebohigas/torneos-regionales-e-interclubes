/**
 * JugadoresEtapa
 *
 * Subpágina dinámica de /jugadores por cada torneo (etapa) ligado a la gira
 * activa: /jugadores/e/1 → torneo_id más bajo, /jugadores/e/2 → el siguiente…
 *
 * Reutiliza la página Jugadores pasando el `torneoId` de la etapa. Las etapas
 * sin jugadores inscritos no se listan en el endpoint, por lo que aquí se
 * muestra "Etapa no disponible" si alguien entra por URL directa.
 */

import { useParams } from 'react-router-dom';
import { Loader2 } from 'lucide-react';
import Layout from '@/components/layout/Layout';
import PageHero from '@/components/shared/PageHero';
import Jugadores from '@/pages/Jugadores';
import { useJugadoresEtapas } from '@/hooks/useJugadoresEtapas';
import { formatEtapaLabel } from '@/lib/etapaLabel';
import jugadoresHero from '@/assets/jugadores-hero.jpg';

const JugadoresEtapa = () => {
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
          subtitle="Esta etapa aún no tiene jugadores inscritos"
          backgroundImage={jugadoresHero}
        />
      </Layout>
    );
  }

  return (
    <Jugadores
      key={match.torneoid}
      torneoId={String(match.torneoid)}
      title={`Jugadores ${formatEtapaLabel(match.etapaLabel, match.etapa)}`}
      subtitle={match.name || 'Lista completa de participantes inscritos en la etapa'}
    />
  );
};

export default JugadoresEtapa;
