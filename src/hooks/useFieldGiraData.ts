/**
 * FIELD-GIRA Data Hooks
 * Duplicado de usePlayersData pero contra field_gira.php (tablas seed de gira:
 * categorias_tmp + jugadores_seed + clubs).
 */

import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/apiClient';
import { getFieldGiraCategoriesUrl, getFieldGiraPlayersUrl, POLL_SLOW } from '@/config/api';
import { useGiraId } from '@/hooks/useGiraId';

// ============= Types =============

/** Categoría seed (categorias_tmp) con conteo de jugadores_seed */
export interface FieldGiraCategory {
  id: string;
  name: string;
  shortName: string;
  playerCount: number;
}

/** Jugador seed (jugadores_seed + clubs.logo) */
export interface FieldGiraPlayer {
  id: string;
  numjugador: string;
  name: string;
  clubLogo: string;
  fechanac: string;
}

interface FieldGiraPlayersResponse {
  category: { id: string; name: string; playerCount: number };
  players: { id: string; numjugador: string; jugador: string; logo: string; fechanac: string }[];
}

// ============= Categorías =============

export const useFieldGiraCategories = () => {
  const { giraId } = useGiraId();

  return useQuery<FieldGiraCategory[]>({
    queryKey: ['field-gira-categories', giraId],
    queryFn: () => apiFetch<FieldGiraCategory[]>(getFieldGiraCategoriesUrl(giraId)),
    enabled: !!giraId,
    staleTime: POLL_SLOW,
    refetchInterval: POLL_SLOW,
  });
};

// ============= Jugadores por categoría =============

export const useFieldGiraPlayers = (catId: string | null, enabled = true) => {
  const { giraId } = useGiraId();

  return useQuery<{ players: FieldGiraPlayer[]; playerCount: number; name: string }>({
    queryKey: ['field-gira-players', giraId, catId],
    queryFn: async () => {
      if (!catId) return { players: [], playerCount: 0, name: '' };
      const data = await apiFetch<FieldGiraPlayersResponse>(getFieldGiraPlayersUrl(catId, giraId));
      const players = (data.players || []).map((p) => ({
        id: p.id,
        numjugador: p.numjugador || '',
        name: p.jugador,
        clubLogo: p.logo,
        fechanac: p.fechanac || '',
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
