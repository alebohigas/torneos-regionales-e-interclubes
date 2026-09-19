/**
 * useAppIcon Hook
 * Dynamically sets the apple-touch-icon and favicon using the tournament's
 * header logo from the API. This allows the site to appear with the
 * tournament's branding when added to a phone's home screen.
 */

import { useEffect } from 'react';
import { useTournamentInfo } from '@/hooks/useTournamentData';


/**
 * Sets <link rel="apple-touch-icon"> and <link rel="icon"> dynamically
 * based on the tournament's logoHeaderUrl field from the database.
 */
export const useAppIcon = () => {
  const { data: tournament } = useTournamentInfo();

  useEffect(() => {
    if (!tournament?.logoHeaderUrl) return;

    /** Logo URL - already includes proxy path from API response */
    const separator = tournament.logoHeaderUrl.includes('?') ? '&' : '?';
    const logoUrl = `${tournament.logoHeaderUrl}${separator}app-icon=${encodeURIComponent(String(tournament.id))}`;

    // ============= Apple Touch Icon (home screen shortcut) =============
    let appleTouchIcon = document.querySelector<HTMLLinkElement>(
      'link[rel="apple-touch-icon"]'
    );
    if (!appleTouchIcon) {
      appleTouchIcon = document.createElement('link');
      appleTouchIcon.rel = 'apple-touch-icon';
      document.head.appendChild(appleTouchIcon);
    }
    appleTouchIcon.href = logoUrl;
    appleTouchIcon.setAttribute('sizes', '180x180');

    // ============= Standard Favicon (browser tabs) =============
    // Also update the standard favicon so mobile browsers use the
    // tournament logo when creating home-screen shortcuts.
    let favicon = document.querySelector<HTMLLinkElement>(
      'link[rel="icon"]'
    );
    if (!favicon) {
      favicon = document.createElement('link');
      favicon.rel = 'icon';
      document.head.appendChild(favicon);
    }
    favicon.href = logoUrl;
    favicon.removeAttribute('type');

    // ============= Additional sizes for Android/Chrome =============
    let icon192 = document.querySelector<HTMLLinkElement>(
      'link[rel="icon"][sizes="192x192"]'
    );
    if (!icon192) {
      icon192 = document.createElement('link');
      icon192.rel = 'icon';
      icon192.setAttribute('sizes', '192x192');
      document.head.appendChild(icon192);
    }
    icon192.href = logoUrl;

    /** Some Android browsers also inspect a 512px icon declaration. */
    let icon512 = document.querySelector<HTMLLinkElement>(
      'link[rel="icon"][sizes="512x512"]'
    );
    if (!icon512) {
      icon512 = document.createElement('link');
      icon512.rel = 'icon';
      icon512.setAttribute('sizes', '512x512');
      document.head.appendChild(icon512);
    }
    icon512.href = logoUrl;

  }, [tournament?.id, tournament?.logoHeaderUrl]);
};
