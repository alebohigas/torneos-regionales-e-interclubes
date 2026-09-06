/**
 * useCalendarioGira
 *
 * Calendario de juego de la GIRA activa: una fila por etapa con los clubes
 * (logos), la sede (campos + "Etapa-n") y las fechas de juego.
 * Fuente: /api/calendario_gira.php?giraid=NN
 */

import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/apiClient';
import { getCalendarioGiraUrl, POLL_SLOW } from '@/config/api';
import { useGiraId } from '@/hooks/useGiraId';

export interface CalendarioGiraClub {
  clubId: number;
  name: string;
  logo: string;
}

export interface CalendarioGiraEtapa {
  etapa: number;
  etapaLabel: string;
  torneoid: number;
  name: string;
  status: string;
  /** Nombres de los campos (sedes) donde se juega la etapa. */
  venues: string[];
  /** Etiqueta lista para mostrar, ej. "Misiones / Herradura Etapa-1". */
  sede: string;
  clubs: CalendarioGiraClub[];
  dates: string[];
  /** Fechas formateadas, ej. "22 y 23 noviembre 2025". */
  dateLabel: string;
}

export const useCalendarioGira = () => {
  const { giraId } = useGiraId();

  return useQuery<CalendarioGiraEtapa[]>({
    queryKey: ['calendario-gira', giraId],
    enabled: !!giraId,
    queryFn: async () => {
      const data = await apiFetch<{ etapas?: CalendarioGiraEtapa[] }>(getCalendarioGiraUrl(giraId));
      return Array.isArray(data?.etapas) ? data.etapas : [];
    },
    staleTime: POLL_SLOW,
    refetchInterval: POLL_SLOW,
  });
};
