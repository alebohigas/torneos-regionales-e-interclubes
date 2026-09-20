/**
 * useCategoriasReglas
 * Hooks para leer/guardar las reglas de ELEGIBILIDAD de categoría usadas
 * por el formulario de Pre-Registro.
 *
 * Una regla = (categoría, género?, edad_min?, edad_max?, hcp_min?, hcp_max?).
 * Determina si una categoría aparece o no en el dropdown para un jugador
 * concreto. NO contiene precios — eso vive en `useRegistroPrecios`.
 *
 * Backend: server/api/categorias_reglas.php (tabla `categorias_reglas`).
 */

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getCategoriasReglasUrl } from '@/config/api';
import { useTorneoId } from '@/hooks/useTorneoId';

/** Una regla de elegibilidad tal como vive en la BD / API. */
export interface CategoriaRegla {
  id: number;
  /** Nombre exacto de la categoría (no ID). */
  categoria: string;
  /** 'M' | 'F' | null = ambos sexos. */
  genero: string | null;
  /** Edad mínima inclusiva (años cumplidos) o null = sin tope. */
  edad_min: number | null;
  /** Edad máxima inclusiva o null = sin tope. */
  edad_max: number | null;
  /** Handicap mínimo inclusivo o null = sin tope. */
  hcp_min: number | null;
  /** Handicap máximo inclusivo o null = sin tope. */
  hcp_max: number | null;
  display_order: number;
  is_active: number;
}

/** Lectura — usada por admin y por el formulario público. */
export const useCategoriasReglas = () => {
  const { torneoId } = useTorneoId();
  return useQuery({
    queryKey: ['categorias_reglas', torneoId],
    queryFn: async (): Promise<{ rules: CategoriaRegla[] }> => {
      const res = await fetch(getCategoriasReglasUrl());
      if (!res.ok) throw new Error('Error al cargar reglas de categoría');
      return res.json();
    },
    enabled: !!torneoId,
    staleTime: 30_000,
  });
};

/** Mutación admin — replace-all para el torneo activo. */
export const useSaveCategoriasReglas = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (payload: {
      torneoid: number;
      rules: Partial<CategoriaRegla>[];
      password: string;
    }) => {
      const res = await fetch(getCategoriasReglasUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(json.error || 'Error al guardar');
      if (!json.saved) throw new Error(json.error || 'El servidor no confirmó el guardado');
      return json;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['categorias_reglas'] }),
  });
};