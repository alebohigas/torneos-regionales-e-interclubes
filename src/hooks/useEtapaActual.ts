/**
 * useEtapaActual
 *
 * Lógica COMPARTIDA por /jugadores y /resultados para decidir qué torneo
 * (etapa de la gira) se muestra cuando el usuario no entra por el submenú
 * "Gira → Etapa N".
 *
 * Regla: se usa la ÚLTIMA etapa que tenga información registrada (el mayor
 * `etapa` devuelto por `jugadores_etapas.php`, que ya excluye las etapas sin
 * jugadores). Así /jugadores y /resultados siempre apuntan al mismo torneoid,
 * y ese contenido puede repetirse con "Jugadores/Resultados Etapa-N" final.
 */

import { useJugadoresEtapas } from '@/hooks/useJugadoresEtapas';

export const useEtapaActual = () => {
  const { data: etapas = [], isLoading, isError } = useJugadoresEtapas();
  const last = etapas.length ? etapas[etapas.length - 1] : undefined;

  return {
    /** torneoid de la última etapa con información (undefined si no hay) */
    torneoId: last ? String(last.torneoid) : undefined,
    /** número de etapa correspondiente */
    etapa: last?.etapa,
    /** nombre del torneo de esa etapa */
    name: last?.name ?? '',
    /** true mientras se resuelve la etapa (para no consultar el torneo erróneo) */
    isResolving: isLoading && !isError,
  };
};
