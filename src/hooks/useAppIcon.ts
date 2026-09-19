/**
 * useAppIcon Hook
 * Dynamically sets the apple-touch-icon and favicon using the tournament's
 * header logo from the API. This allows the site to appear with the
 * tournament's branding when added to a phone's home screen.
 */

import { useEffect } from 'react';
import { useTournamentInfo } from '@/hooks/useTournamentData';

/** Marca los <link> creados por este hook para poder reemplazarlos limpiamente. */
const ICON_MARK = 'data-app-icon';

/**
 * Crea (o recrea) un <link> de icono. Se elimina el anterior en vez de
 * reutilizarlo: algunos navegadores ignoran un cambio de `href` en un link ya
 * existente y siguen mostrando el icono en caché.
 */
const setIconLink = (rel: string, href: string, sizes?: string) => {
  const selector = sizes
    ? `link[${ICON_MARK}][rel="${rel}"][sizes="${sizes}"]`
    : `link[${ICON_MARK}][rel="${rel}"]:not([sizes])`;
  document.head.querySelectorAll(selector).forEach((el) => el.remove());

  /** Quita también los links originales del index.html para evitar conflictos. */
  const legacy = sizes
    ? `link:not([${ICON_MARK}])[rel="${rel}"][sizes="${sizes}"]`
    : `link:not([${ICON_MARK}])[rel="${rel}"]`;
  document.head.querySelectorAll(legacy).forEach((el) => el.remove());

  const link = document.createElement('link');
  link.setAttribute(ICON_MARK, 'true');
  link.rel = rel;
  if (sizes) link.setAttribute('sizes', sizes);
  link.href = href;
  document.head.appendChild(link);
};

/**
 * Sets <link rel="apple-touch-icon"> and <link rel="icon"> dynamically
 * based on the tournament's logoHeaderUrl field from the database.
 */
export const useAppIcon = () => {
  const { data: tournament } = useTournamentInfo();

  useEffect(() => {
    const logoHeaderUrl = tournament?.logoHeaderUrl;
    if (!logoHeaderUrl) return;

    /**
     * Token de versión: cambia al cambiar de torneo o de logo, así el
     * navegador pide la imagen nueva en vez de servir la de su caché.
     */
    const version = `${tournament?.id ?? ''}-${logoHeaderUrl}`.replace(/\W+/g, '').slice(-24);
    const separator = logoHeaderUrl.includes('?') ? '&' : '?';
    const logoUrl = `${logoHeaderUrl}${separator}app-icon=${encodeURIComponent(version)}`;

    // ============= Apple (acceso en pantalla de inicio de iPhone) =============
    setIconLink('apple-touch-icon', logoUrl, '180x180');
    setIconLink('apple-touch-icon-precomposed', logoUrl, '180x180');

    // ============= Favicon estándar (pestañas del navegador) =============
    setIconLink('icon', logoUrl);
    setIconLink('shortcut icon', logoUrl);

    // ============= Tamaños adicionales para Android/Chrome =============
    setIconLink('icon', logoUrl, '192x192');
    setIconLink('icon', logoUrl, '512x512');
  }, [tournament?.id, tournament?.logoHeaderUrl]);
};
