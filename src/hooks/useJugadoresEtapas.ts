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
import { useSiteConfig } from '@/hooks/useSiteConfig';

export interface JugadoresEtapa {
  /** Número de etapa tomado del nombre del torneo ("ETAPA-3" => 3) */
  etapa: number;
  /** Primera palabra del nombre del torneo, ej. "ETAPA-3" */
  etapaLabel?: string;
  torneoid: number;
  name: string;
  club: string;
  startDate: string;
  endDate: string;
  status: string;
  playerCount: number;
}

/**
 * @param opts.includeHidden  true = devuelve también las etapas apagadas en
 *   Admin > Gira (sólo para el panel de administración).
 */
export const useJugadoresEtapas = (opts?: { includeHidden?: boolean }) => {
  const { giraId } = useGiraId();
  const { data: siteConfig } = useSiteConfig();
  const hidden = (siteConfig?.gira_config?.hiddenTorneos ?? []).map(Number);
  const includeHidden = !!opts?.includeHidden;

  return useQuery<JugadoresEtapa[]>({
    queryKey: ['jugadores-etapas', giraId],
    enabled: !!giraId,
    queryFn: async () => {
      const data = await apiFetch<{ etapas?: JugadoresEtapa[] }>(getJugadoresEtapasUrl(giraId));
      return Array.isArray(data?.etapas) ? data.etapas : [];
    },
    staleTime: POLL_SLOW,
    refetchInterval: POLL_SLOW,
    // Las etapas ocultas desde Admin > Gira no existen para el sitio público.
    select: (etapas) =>
      includeHidden ? etapas : etapas.filter((e) => !hidden.includes(Number(e.torneoid))),
  });
};
