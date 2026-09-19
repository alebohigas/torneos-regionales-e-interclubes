import juniorClassic2026Bases from '@/assets/bases/Bases_Junior_Classic_2026-2027.pdf.asset.json';

interface BasesDocument {
  giraId: string;
  torneoId: string;
  url: string;
}

const BASES_DOCUMENTS: BasesDocument[] = [
  {
    giraId: '22',
    torneoId: '135',
    url: juniorClassic2026Bases.url,
  },
];

export const getBasesDocumentUrl = (giraId: string, torneoId: string): string | null => {
  const document = BASES_DOCUMENTS.find(
    (item) => item.giraId === giraId.trim() && item.torneoId === torneoId.trim(),
  );

  return document?.url ?? null;
};