/**
 * useJugadoresEtapas
 *
 * Etapas de la gira activa para las subpáginas dinámicas de /jugadores.
 * Cada torneo ligado al `giraid` es una etapa (etapa 1 = torneo_id más bajo).
 * El endpoint ya excluye las etapas sin jugadores inscritos, por lo que estas
 * no generan ruta ni entrada de menú.
 */

import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/apiClient';
import { getJugadoresEtapasUrl, POLL_SLOW } from '@/config/api';
import { useGiraId } from '@/hooks/useGiraId';

export interface JugadoresEtapa {
  /** Número de etapa (1..n) */
  etapa: number;
  torneoid: number;
  name: string;
  club: string;
  startDate: string;
  endDate: string;
  status: string;
  playerCount: number;
}

export const useJugadoresEtapas = () => {
  const { giraId } = useGiraId();

  return useQuery<JugadoresEtapa[]>({
    queryKey: ['jugadores-etapas', giraId],
    enabled: !!giraId,
    queryFn: async () => {
      const data = await apiFetch<{ etapas?: JugadoresEtapa[] }>(getJugadoresEtapasUrl(giraId));
      return Array.isArray(data?.etapas) ? data.etapas : [];
    },
    staleTime: POLL_SLOW,
    refetchInterval: POLL_SLOW,
  });
};
