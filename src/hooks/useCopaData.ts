/**
 * COPA Data Hooks
 * Consumen copa.php (unificación de los legacy copas.php / lista_copas.php /
 * copas_gira.php): cada jugador aporta sus puntos al club que representa en la
 * gira y la copa muestra la sumatoria por club.
 */

import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/apiClient';
import {
  getCopaListUrl,
  getCopaClubsUrl,
  getCopaClubPlayersUrl,
  POLL_SLOW,
} from '@/config/api';
import { useGiraId } from '@/hooks/useGiraId';

// ============= Types =============

export interface Copa {
  copasid: string;
  name: string;
  /** 'M' = varonil, 'F' = femenil, '' = conjunta */
  sexo: 'M' | 'F' | '';
  /** true cuando la copa consolida otras copas de la gira (conjunta) */
  consolidated: boolean;
}

export interface CopaClub {
  position: number;
  clubid: string;
  club: string;
  logo: string;
  puntos: number;
  jugadores: number;
}

export interface CopaClubPlayer {
  position: number;
  numjugador: string;
  jugador: string;
  puntos: number;
  penalties: number;
}

/** Etapa de la gira con los jugadores del club que aportaron puntos. */
export interface CopaClubEtapa {
  torneoid: string;
  etapa: string;
  nombre: string;
  puntos: number;
  penalties: number;
  total: number;
  players: CopaClubPlayer[];
}

// ============= Copas de la gira =============

export const useCopas = () => {
  const { giraId } = useGiraId();

  return useQuery<Copa[]>({
    queryKey: ['copa-list', giraId],
    queryFn: async () => {
      const data = await apiFetch<{ copas?: Copa[] }>(getCopaListUrl(giraId));
      return Array.isArray(data?.copas) ? data.copas : [];
    },
    enabled: !!giraId,
    staleTime: POLL_SLOW,
  });
};

// ============= Ranking de clubes de una copa =============

export const useCopaClubs = (copasid: string | null) => {
  const { giraId } = useGiraId();

  return useQuery<{ name: string; clubs: CopaClub[] }>({
    queryKey: ['copa-clubs', giraId, copasid],
    queryFn: async () => {
      const data = await apiFetch<{ copa?: { name?: string }; clubs?: CopaClub[] }>(
        getCopaClubsUrl(copasid!, giraId)
      );
      return {
        name: data?.copa?.name ?? '',
        clubs: Array.isArray(data?.clubs) ? data.clubs : [],
      };
    },
    enabled: !!copasid && !!giraId,
    staleTime: POLL_SLOW,
    refetchInterval: POLL_SLOW,
  });
};

// ============= Etapas (y sus jugadores) que aportan puntos a un club =============

export const useCopaClubEtapas = (copasid: string | null, clubid: string | null) => {
  const { giraId } = useGiraId();

  return useQuery<CopaClubEtapa[]>({
    queryKey: ['copa-club-etapas', giraId, copasid, clubid],
    queryFn: async () => {
      const data = await apiFetch<{ etapas?: CopaClubEtapa[] }>(
        getCopaClubPlayersUrl(copasid!, clubid!, giraId)
      );
      return Array.isArray(data?.etapas) ? data.etapas : [];
    },
    enabled: !!copasid && !!clubid && !!giraId,
    staleTime: POLL_SLOW,
  });
};
