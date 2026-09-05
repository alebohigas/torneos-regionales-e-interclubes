/**
 * RANKING Data Hooks
 * Consume ranking.php (réplica de los legacy ranking.php / lista_ranking.php):
 * categorías por `catidoriginal` de la gira y acumulado de puntos por jugador.
 */

import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/apiClient';
import { getRankingCategoriesUrl, getRankingPlayersUrl, POLL_SLOW } from '@/config/api';
import { useGiraId } from '@/hooks/useGiraId';

// ============= Types =============

/** Categoría de ranking (agrupada por categorias.catidoriginal) */
export interface RankingCategory {
  id: string;
  name: string;
  shortName: string;
  playerCount: number;
}

/** Jugador con puntos acumulados en la gira */
export interface RankingPlayer {
  position: number;
  numjugador: string;
  name: string;
  club: string;
  clubLogo: string;
  puntos: number;
}

interface RankingPlayersResponse {
  category: { id: string; name: string; playerCount: number };
  players: {
    position: number;
    numjugador: string;
    jugador: string;
    club: string;
    logo: string;
    puntos: number;
  }[];
}

// ============= Categorías =============

export const useRankingCategories = () => {
  const { giraId } = useGiraId();

  return useQuery<RankingCategory[]>({
    queryKey: ['ranking-categories', giraId],
    queryFn: () => apiFetch<RankingCategory[]>(getRankingCategoriesUrl(giraId)),
    enabled: !!giraId,
    staleTime: POLL_SLOW,
    refetchInterval: POLL_SLOW,
  });
};

// ============= Ranking por categoría =============

export const useRankingPlayers = (catId: string | null, enabled = true) => {
  const { giraId } = useGiraId();

  return useQuery<{ players: RankingPlayer[]; playerCount: number; name: string }>({
    queryKey: ['ranking-players', giraId, catId],
    queryFn: async () => {
      if (!catId) return { players: [], playerCount: 0, name: '' };
      const data = await apiFetch<RankingPlayersResponse>(getRankingPlayersUrl(catId, giraId));
      const players = (data.players || []).map((p, index) => ({
        position: p.position || index + 1,
        numjugador: p.numjugador || '',
        name: p.jugador,
        club: p.club || '',
        clubLogo: p.logo || '',
        puntos: Number(p.puntos) || 0,
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
