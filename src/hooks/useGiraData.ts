/**
 * useGiraData
 *
 * Datos de la GIRA activa desde /api/gira.php.
 *  - `useGiraInfo()`  → detalle de la gira configurada (nombre, uso, copas, torneos)
 *  - `useGirasList()` → todas las giras (para el selector de /admin)
 *
 * Reglas del modelo:
 *  - `uso === 1` → gira vigente, la página opera normalmente.
 *  - `uso === 0` → gira terminada: se muestra el nombre con el aviso
 *    "COPA TERMINADA" y /admin levanta una alerta.
 *  - `copas.grupocopas` lista los copasid cuya información consolida esa copa;
 *    el endpoint ya filtra ids de otra gira.
 */

import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/apiClient';
import { API_BASE_URL, POLL_SLOW } from '@/config/api';
import { useGiraId } from '@/hooks/useGiraId';

// ============= Types =============

export interface GiraCopa {
  copasid: number;
  name: string;
  grupocopasRaw: string | null;
  /** copasid que aportan información a esta copa (ya validados dentro de la gira) */
  group: number[];
  /** true cuando consolida información de otras copas (copa general) */
  isConsolidated: boolean;
}

export interface GiraTorneo {
  id: number;
  name: string;
  startDate: string;
  endDate: string;
  status: string;
}

export interface GiraInfo {
  giraid: number;
  name: string;
  uso: number;
  active: boolean;
  copas: GiraCopa[];
  torneos: GiraTorneo[];
}

export interface GiraListItem {
  giraid: number;
  name: string;
  uso: number;
  active: boolean;
}

// ============= Hooks =============

/** Detalle de la gira activa (según site_config.giraid). */
export const useGiraInfo = () => {
  const { giraId } = useGiraId();

  return useQuery<GiraInfo>({
    queryKey: ['gira-info', giraId],
    enabled: !!giraId,
    queryFn: async () => {
      const data = await apiFetch<GiraInfo>(
        `${API_BASE_URL}/gira.php?giraid=${encodeURIComponent(giraId)}`
      );
      return {
        ...data,
        copas: Array.isArray(data?.copas) ? data.copas : [],
        torneos: Array.isArray(data?.torneos) ? data.torneos : [],
      };
    },
    staleTime: POLL_SLOW,
  });
};

/** Todas las giras disponibles (selector de /admin). */
export const useGirasList = () => {
  return useQuery<GiraListItem[]>({
    queryKey: ['giras-list'],
    queryFn: async () => {
      const data = await apiFetch<{ giras?: GiraListItem[] }>(`${API_BASE_URL}/gira.php`);
      return Array.isArray(data?.giras) ? data.giras : [];
    },
    staleTime: POLL_SLOW,
  });
};
