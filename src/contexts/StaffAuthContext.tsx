/**
 * StaffAuthContext
 * -----------------------------------------------------------------
 * Maneja la sesión de usuarios staff temporales: token + áreas
 * permitidas + rango de fechas. Persistido en localStorage.
 *
 * El token se envía como `?staff_token=...` o body `staff_token`
 * al hacer llamadas admin desde el frontend.
 *
 * Áreas reconocidas (whitelist server + UI):
 *   preregistros, brackets, matchplay, live, banderas, pop, eventos, avisos,
 *   premios, convocatoria, reglas, uploads, stats, hoteles
 */
import { createContext, useContext, useEffect, useState, ReactNode, useCallback } from 'react';
import { API_BASE_URL } from '@/config/api';

export type StaffArea =
  | 'preregistros' | 'brackets' | 'banderas' | 'pop'
  | 'eventos' | 'avisos' | 'menus' | 'premios' | 'convocatoria'
  | 'reglas' | 'uploads' | 'stats' | 'hoteles' | 'matchplay' | 'live';

export interface StaffSession {
  token: string;
  usuario: string;
  nombre: string;
  torneoid: number;
  tipo?: number;
  is_superadmin?: boolean;
  areas: StaffArea[];
  expira: string;
}

interface Ctx {
  session: StaffSession | null;
  loading: boolean;
  login: (usuario: string, password: string) => Promise<{ ok: boolean; error?: string; isSuperadmin?: boolean }>;
  logout: () => Promise<void>;
  hasArea: (a: StaffArea) => boolean;
}

const STORAGE_KEY = 'staff_session_v1';
const StaffAuthContext = createContext<Ctx | undefined>(undefined);

export const StaffAuthProvider = ({ children }: { children: ReactNode }) => {
  const [session, setSession] = useState<StaffSession | null>(() => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return null;
      const s = JSON.parse(raw) as StaffSession;
      if (s && s.token && new Date(s.expira) > new Date()) return s;
    } catch { /* ignore */ }
    return null;
  });
  const [loading, setLoading] = useState(false);

  // Validar token contra el servidor al montar
  useEffect(() => {
    if (!session) return;
    (async () => {
      try {
        const r = await fetch(`${API_BASE_URL}/staff_session.php?staff_token=${encodeURIComponent(session.token)}`);
        if (!r.ok) throw new Error('invalid');
        const data = await r.json();
        setSession({
          token: session.token,
          usuario: data.usuario,
          nombre: data.nombre,
          torneoid: data.torneoid,
          tipo: data.tipo,
          is_superadmin: !!data.is_superadmin,
          areas: data.areas || [],
          expira: data.expira,
        });
      } catch {
        setSession(null);
        localStorage.removeItem(STORAGE_KEY);
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (session) localStorage.setItem(STORAGE_KEY, JSON.stringify(session));
    else localStorage.removeItem(STORAGE_KEY);
  }, [session]);

  const login = useCallback(async (usuario: string, password: string) => {
    setLoading(true);
    try {
      const r = await fetch(`${API_BASE_URL}/staff_login.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ usuario, password }),
      });
      const data = await r.json();
      if (!r.ok) return { ok: false, error: data.error || 'Login failed' };
      setSession({
        token: data.token,
        usuario: data.usuario,
        nombre: data.nombre,
        torneoid: data.torneoid,
        tipo: data.tipo,
        is_superadmin: !!data.is_superadmin,
        areas: data.areas || [],
        expira: data.expira,
      });
      return { ok: true, isSuperadmin: !!data.is_superadmin };
    } catch (e: any) {
      return { ok: false, error: e.message || 'Network error' };
    } finally {
      setLoading(false);
    }
  }, []);

  const logout = useCallback(async () => {
    if (session) {
      try {
        await fetch(`${API_BASE_URL}/staff_session.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'logout', staff_token: session.token }),
        });
      } catch { /* ignore */ }
    }
    setSession(null);
  }, [session]);

  const hasArea = useCallback((a: StaffArea) => !!session && session.areas.includes(a), [session]);

  return (
    <StaffAuthContext.Provider value={{ session, loading, login, logout, hasArea }}>
      {children}
    </StaffAuthContext.Provider>
  );
};

export const useStaffAuth = (): Ctx => {
  const c = useContext(StaffAuthContext);
  if (!c) {
    return {
      session: null,
      loading: false,
      login: async () => ({ ok: false, error: 'No provider' }),
      logout: async () => {},
      hasArea: () => false,
    };
  }
  return c;
};