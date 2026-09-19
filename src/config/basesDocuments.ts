/**
 * Catálogo de documentos de Bases por gira/torneo.
 *
 * Los PDF viven en `public/bases/`, así se sirven desde el propio sitio
 * (funciona en el hosting final y en la vista previa) sin depender de un CDN
 * externo que pueda no existir en el dominio publicado.
 */

interface BasesDocument {
  giraId: string;
  torneoId: string;
  url: string;
}

const BASES_DOCUMENTS: BasesDocument[] = [
  {
    giraId: '22',
    torneoId: '135',
    url: '/bases/Bases_Junior_Classic_2026-2027.pdf',
  },
];

export const getBasesDocumentUrl = (giraId: string, torneoId: string): string | null => {
  const document = BASES_DOCUMENTS.find(
    (item) => item.giraId === giraId.trim() && item.torneoId === torneoId.trim(),
  );

  return document?.url ?? null;
};
