/**
 * Distancias Data Hook
 * Yardajes y pares por hoyo de cada categoría (tee de salida + campo).
 * Fuente: /api/distancias.php (hoyosxsalida / campo_tee).
 */

import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/apiClient';
import { getDistanciasUrl, POLL_SLOW } from '@/config/api';

/** Un hoyo del campo con su par y yardaje. */
export interface DistanciaHole {
  numero: number;
  par: number;
  yardaje: number;
}

/** Bloque = una categoría jugando un campo específico. */
export interface DistanciaBlock {
  categoryId: number;
  category: string;
  shortName: string;
  course: string;
  teeName: string;
  /** Color del tee (hex o nombre CSS) tal como viene de `salidas`. */
  teeColor: string;
  holes: DistanciaHole[];
  holeCount: number;
  totalYardas: number;
  totalPar: number;
}

interface DistanciasResponse {
  torneoid: number;
  blocks: DistanciaBlock[];
}

export const useDistanciasData = () =>
  useQuery<DistanciasResponse>({
    queryKey: ['distancias'],
    queryFn: () => apiFetch<DistanciasResponse>(getDistanciasUrl()),
    staleTime: POLL_SLOW,
    refetchInterval: POLL_SLOW,
  });
