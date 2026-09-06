/**
 * RANKING FINAL Data Hooks
 * Consumen ranking_final.php (unificación de los legacy
 * desk_Web_Ranking_21_gira5max / Web_ranking_21_gira_det5max* / popup_jugador5top).
 *
 * Puntos del ranking = suma únicamente de las etapas marcadas top5 = 1.
 */

import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/apiClient';
import {
  getRankingFinalCategoriesUrl,
  getRankingFinalPlayersUrl,
  getRankingFinalPlayerDetailUrl,
  POLL_SLOW,
} from '@/config/api';
import { useGiraId } from '@/hooks/useGiraId';

// ============= Types =============

export interface RankingFinalCategory {
  id: string;
  name: string;
  shortName: string;
  playerCount: number;
}

export interface RankingFinalPlayer {
  position: number;
  numjugador: string;
  name: string;
  club: string;
  clubLogo: string;
  /** Puntos que cuentan para el ranking (solo etapas top5). */
  puntos: number;
  /** Puntos totales acumulados en todas las etapas. */
  puntosTodos: number;
  /** Número de etapas jugadas. */
  etapas: number;
}

/** Desglose de una etapa para un jugador. */
export interface RankingFinalEtapa {
  torneoid: string;
  /** Etiqueta real de la etapa (primera palabra de torneo.nombre, ej. "ETAPA-3"). */
  etapa: string;
  nombre: string;
  played: boolean;
  puntos: number;
  /** true = estos puntos se usaron para el total del jugador. */
  counted: boolean;
  estatus: string;
  rounds: (number | null)[];
  total: number | null;
  /** Lugar del jugador en esa etapa (jugadores.posptos). */
  lugar: number | null;
}


export interface RankingFinalDetail {
  numjugador: string;
  jugador: string;
  etapas: RankingFinalEtapa[];
  totalPuntos: number;
  totalTodos: number;
}

interface PlayersResponse {
  category: { id: string; name: string; playerCount: number };
  players: {
    position: number;
    numjugador: string;
    jugador: string;
    club: string;
    logo: string;
    puntos: number;
    puntosx: number;
    etapas: number;
  }[];
}

// ============= Categorías =============

export const useRankingFinalCategories = () => {
  const { giraId } = useGiraId();

  return useQuery<RankingFinalCategory[]>({
    queryKey: ['ranking-final-categories', giraId],
    queryFn: () => apiFetch<RankingFinalCategory[]>(getRankingFinalCategoriesUrl(giraId)),
    enabled: !!giraId,
    staleTime: POLL_SLOW,
    refetchInterval: POLL_SLOW,
  });
};

// ============= Ranking por categoría =============

export const useRankingFinalPlayers = (catId: string | null, enabled = true) => {
  const { giraId } = useGiraId();

  return useQuery<{ players: RankingFinalPlayer[]; playerCount: number; name: string }>({
    queryKey: ['ranking-final-players', giraId, catId],
    queryFn: async () => {
      if (!catId) return { players: [], playerCount: 0, name: '' };
      const data = await apiFetch<PlayersResponse>(getRankingFinalPlayersUrl(catId, giraId));
      const players = (data.players || []).map((p, index) => ({
        position: p.position || index + 1,
        numjugador: p.numjugador || '',
        name: p.jugador,
        club: p.club || '',
        clubLogo: p.logo || '',
        puntos: Number(p.puntos) || 0,
        puntosTodos: Number(p.puntosx) || 0,
        etapas: Number(p.etapas) || 0,
      }));
      return {
        players,
        playerCount: data.category?.playerCount ?? players.length,
        name: data.category?.name ?? '',
      };
    },
    enabled: enabled && !!catId && !!giraId,
    staleTime: POLL_SLOW,
    refetchInterval: POLL_SLOW,
  });
};

// ============= Desglose por jugador =============

export const useRankingFinalPlayerDetail = (numjugador: string | null) => {
  const { giraId } = useGiraId();

  return useQuery<RankingFinalDetail>({
    queryKey: ['ranking-final-detail', giraId, numjugador],
    queryFn: () => apiFetch<RankingFinalDetail>(getRankingFinalPlayerDetailUrl(numjugador!, giraId)),
    enabled: !!numjugador && !!giraId,
    staleTime: POLL_SLOW,
  });
};
