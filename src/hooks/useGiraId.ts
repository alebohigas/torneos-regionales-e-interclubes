/**
 * Gira ID Hook
 *
 * La gira (`gira.giraid`) es el eje de esta instalación: agrupa varias copas
 * (`copas.giraid`) y varios torneos (`torneo.giraid`). Se configura una sola
 * vez por dominio en /admin → Config y se guarda en `site_config.giraid`.
 *
 * Mismo patrón que `useTorneoId`: valor persistido en localStorage + evento
 * DOM propio para que todos los consumidores se enteren del cambio en el
 * momento en que `site_config.php` resuelve el valor.
 */

import { useState, useCallback, useEffect } from 'react';

/** localStorage key con la gira activa */
const GIRA_ID_KEY = 'golf-app-gira-id';

/** Sin configurar */
const DEFAULT_GIRA_ID = '';

/** Evento in-tab disparado al cambiar la gira activa */
const GIRA_ID_EVENT = 'golf-app-gira-id-changed';

/** Persiste la gira activa y notifica a todos los suscriptores de esta pestaña. */
export const setStoredGiraId = (id: string) => {
  const trimmed = (id ?? '').trim();
  const current = localStorage.getItem(GIRA_ID_KEY) || DEFAULT_GIRA_ID;
  if (current === trimmed) return;
  localStorage.setItem(GIRA_ID_KEY, trimmed);
  window.dispatchEvent(new Event(GIRA_ID_EVENT));
};

/** Getter estático para usar fuera de React (constructores de URL). */
export const getGiraId = (): string => {
  return localStorage.getItem(GIRA_ID_KEY) || DEFAULT_GIRA_ID;
};

/** Devuelve la gira activa y un setter que persiste + notifica. */
export const useGiraId = () => {
  const [giraId, setGiraIdState] = useState<string>(() => getGiraId());

  const setGiraId = useCallback((id: string) => {
    setStoredGiraId(id);
    setGiraIdState((id ?? '').trim());
  }, []);

  useEffect(() => {
    const sync = () => {
      const next = getGiraId();
      setGiraIdState((prev) => (prev === next ? prev : next));
    };
    sync();
    window.addEventListener(GIRA_ID_EVENT, sync);
    window.addEventListener('storage', sync);
    return () => {
      window.removeEventListener(GIRA_ID_EVENT, sync);
      window.removeEventListener('storage', sync);
    };
  }, []);

  return { giraId, setGiraId };
};
