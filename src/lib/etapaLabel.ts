/**
 * Formatea la etiqueta de etapa que viene de `torneo.nombre`
 * ("ETAPA-3" => "Etapa-3"). Si no llega etiqueta usa el número.
 */
export const formatEtapaLabel = (label?: string, etapa?: number): string => {
  const raw = (label || '').trim();
  if (!raw) return `Etapa-${etapa ?? ''}`;
  return raw
    .toLowerCase()
    .replace(/(^|[-_\s])([a-z\u00e0-\u00fc])/g, (_m, sep, ch) => sep + ch.toUpperCase());
};
