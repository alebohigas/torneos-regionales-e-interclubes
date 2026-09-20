/**
 * useRegistroPrecios
 * Hooks para leer/guardar las reglas de precio de Pre-Registro.
 *
 * Backend: server/api/registro_precios.php (tabla `registro_precios`).
 * Una "regla" describe cuánto cuesta la inscripción para una combinación
 * de filtros (categoría, tipo de socio, género, rango de edad). Los
 * filtros NULL actúan como comodín.
 */

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  getRegistroPreciosUrl,
  getRegistroPrecioMatchUrl,
} from '@/config/api';
import { useTorneoId } from '@/hooks/useTorneoId';

/** Una regla de precio tal como vive en la BD / API. */
export interface RegistroPrecioRule {
  id: number;
  etiqueta: string;
  /** Nombre de categoría exacto, o null = aplica a todas. */
  categoria: string | null;
  /** SOCIO / NO_SOCIO / TITULAR / EMERITO / DEPENDIENTE / INVITADO / FORANEO, o null. */
  tipo_socio: string | null;
  /** 'M' | 'F' | null */
  genero: string | null;
  /** Edad mínima inclusiva (años cumplidos) o null. */
  edad_min: number | null;
  /** Edad máxima inclusiva o null. */
  edad_max: number | null;
  /** Handicap mínimo inclusivo (acepta decimales y negativos) o null = comodín. */
  hcp_min: number | null;
  /** Handicap máximo inclusivo o null = comodín. */
  hcp_max: number | null;
  precio: number;
  moneda: string;
  incluye: string;
  /** Mayor prioridad = gana ante empates de especificidad. */
  prioridad: number;
  display_order: number;
  is_active: number;
}

/** Lista completa de reglas — usada por el admin. */
export const useRegistroPrecios = () => {
  const { torneoId } = useTorneoId();
  return useQuery({
    queryKey: ['registro_precios', torneoId],
    queryFn: async (): Promise<{ rules: RegistroPrecioRule[] }> => {
      const res = await fetch(getRegistroPreciosUrl());
      if (!res.ok) throw new Error('Error al cargar precios');
      return res.json();
    },
    enabled: !!torneoId,
    staleTime: 30_000,
  });
};

/** Mutación admin — reemplaza completamente el set de reglas del torneo. */
export const useSaveRegistroPrecios = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (payload: {
      torneoid: number;
      rules: Partial<RegistroPrecioRule>[];
      password: string;
    }) => {
      const res = await fetch(getRegistroPreciosUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(json.error || 'Error al guardar');
      if (!json.saved) throw new Error(json.error || 'El servidor no confirmó el guardado');
      return json;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['registro_precios'] }),
  });
};

/**
 * Match de precio para los datos actuales del jugador.
 * Hook reactivo: cualquier cambio en los parámetros refetchea.
 * Devuelve null cuando no hay regla aplicable.
 */
export const useRegistroPrecioMatch = (params: {
  categoria?: string;
  tipo_socio?: string;
  genero?: string;
  edad?: number | null;
  /** Hándicap actual del jugador (acepta decimales / negativos). */
  handicap?: number | null;
  enabled?: boolean;
}) => {
  const { torneoId } = useTorneoId();
  const { categoria, tipo_socio, genero, edad, handicap, enabled = true } = params;
  return useQuery({
    queryKey: ['registro_precio_match', torneoId, categoria, tipo_socio, genero, edad, handicap],
    queryFn: async (): Promise<{ match: RegistroPrecioRule | null }> => {
      const url = getRegistroPrecioMatchUrl({
        categoria,
        tipo_socio,
        genero,
        edad: edad ?? undefined,
        handicap: handicap ?? undefined,
      });
      const res = await fetch(url);
      if (!res.ok) throw new Error('Error al consultar precio');
      return res.json();
    },
    enabled: enabled && !!torneoId,
    staleTime: 10_000,
  });
};
