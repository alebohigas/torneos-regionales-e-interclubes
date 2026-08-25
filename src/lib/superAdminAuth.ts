/**
 * Superadmin auth helpers
 * ---------------------------------------------------------------------------
 * The username-less superadmin is intentionally separate from staff users.
 * The entered password is kept in sessionStorage only so legacy PHP endpoints
 * can receive the current password after it is changed from /admin.
 */
import { API_BASE_URL } from '@/config/api';

/** Legacy password used until production config stores a custom hash. */
export const DEFAULT_SUPERADMIN_PASSWORD = 'admin2025';

/** Session-only key for the active superadmin password. */
const SUPERADMIN_PASSWORD_SESSION_KEY = 'speitour_superadmin_password_v1';

/** Fetch patch singleton flag to avoid double-wrapping fetch in dev/HMR. */
let fetchPatchInstalled = false;

/** LocalStorage key used by StaffAuthContext for normal/staff admin users. */
const STAFF_SESSION_STORAGE_KEY = 'staff_session_v1';

/** Read the active superadmin password for admin API payloads. */
export const getSuperAdminPassword = (): string => {
  if (typeof window === 'undefined') return DEFAULT_SUPERADMIN_PASSWORD;
  return sessionStorage.getItem(SUPERADMIN_PASSWORD_SESSION_KEY) || DEFAULT_SUPERADMIN_PASSWORD;
};

/** True only when the current tab really has the superadmin password captured. */
export const hasRememberedSuperAdminPassword = (): boolean => {
  if (typeof window === 'undefined') return false;
  return !!sessionStorage.getItem(SUPERADMIN_PASSWORD_SESSION_KEY);
};

/** Remember the password only for the current browser session. */
export const rememberSuperAdminPassword = (password: string): void => {
  if (typeof window === 'undefined') return;
  sessionStorage.setItem(SUPERADMIN_PASSWORD_SESSION_KEY, password);
};

/** Clear the session-scoped superadmin password. */
export const clearSuperAdminPassword = (): void => {
  if (typeof window === 'undefined') return;
  sessionStorage.removeItem(SUPERADMIN_PASSWORD_SESSION_KEY);
};

/** Ask the production API whether the password is valid. */
export const validateSuperAdminPassword = async (password: string): Promise<boolean> => {
  try {
    const response = await fetch(`${API_BASE_URL}/admin_auth.php?action=login`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ password }),
    });
    if (response.ok) {
      rememberSuperAdminPassword(password);
      return true;
    }
    if (response.status === 401) return false;
  } catch {
    // If production API is unavailable, keep the historical local fallback.
  }

  const matchesLegacyFallback = password === DEFAULT_SUPERADMIN_PASSWORD;
  if (matchesLegacyFallback) rememberSuperAdminPassword(password);
  return matchesLegacyFallback;
};

/** Persist a new superadmin password hash on the production API. */
export const changeSuperAdminPassword = async (
  currentPassword: string,
  newPassword: string
): Promise<void> => {
  const response = await fetch(`${API_BASE_URL}/admin_auth.php?action=change_password`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ current_password: currentPassword, new_password: newPassword }),
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.error || 'No se pudo cambiar la contraseña');
  rememberSuperAdminPassword(newPassword);
};

/** Check whether a URL is a same-origin PHP API request. */
const isSameOriginApiRequest = (url: URL): boolean =>
  typeof window !== 'undefined' && url.origin === window.location.origin && url.pathname.startsWith('/api/');

/** Resolve fetch input into a URL without reading or cloning request bodies. */
const getFetchUrl = (input: RequestInfo | URL): URL | null => {
  if (typeof window === 'undefined') return null;
  try {
    const href = input instanceof Request ? input.url : input.toString();
    return new URL(href, window.location.origin);
  } catch {
    return null;
  }
};

/** Read a valid staff token without importing React context into the fetch patch. */
const getRememberedStaffToken = (): string | null => {
  if (typeof window === 'undefined') return null;
  try {
    const raw = localStorage.getItem(STAFF_SESSION_STORAGE_KEY);
    if (!raw) return null;
    const session = JSON.parse(raw) as { token?: string; expira?: string };
    if (!session?.token) return null;
    if (session.expira && new Date(session.expira) <= new Date()) return null;
    return session.token;
  } catch {
    return null;
  }
};

/**
 * URLs whose `password` field must NOT be overwritten. These endpoints receive
 * a candidate password the user is trying to authenticate with (or a password
 * bound to a different account like staff login), so injecting the active
 * superadmin password would break them.
 */
const AUTH_PATHS_TO_SKIP = ['/api/admin_auth.php', '/api/staff_login.php', '/api/staff_session.php'];

/** True if the request targets an auth endpoint whose `password` field is untouchable. */
const isAuthEndpoint = (url: URL): boolean =>
  AUTH_PATHS_TO_SKIP.some(p => url.pathname === p || url.pathname.endsWith(p));

/** Attach staff_token to non-login admin requests so normal users can save. */
const attachStaffToken = (input: RequestInfo | URL, init: RequestInit, url: URL): [RequestInfo | URL, RequestInit] => {
  const staffToken = getRememberedStaffToken();
  if (!staffToken || isAuthEndpoint(url)) return [input, init];

  if (typeof input === 'string' || input instanceof URL) {
    const original = input.toString();
    const patchedUrl = new URL(original, window.location.origin);
    if (!patchedUrl.searchParams.has('staff_token')) {
      patchedUrl.searchParams.set('staff_token', staffToken);
      input = original.startsWith('http') ? patchedUrl.toString() : `${patchedUrl.pathname}${patchedUrl.search}`;
    }
  }

  if (!init.body) return [input, init];

  if (typeof init.body === 'string') {
    try {
      const parsed = JSON.parse(init.body);
      if (parsed && typeof parsed === 'object' && !('staff_token' in parsed)) {
        init.body = JSON.stringify({ ...parsed, staff_token: staffToken });
      }
    } catch {
      // Non-JSON string bodies are left untouched.
    }
  }

  if (init.body instanceof FormData && !init.body.has('staff_token')) {
    init.body.set('staff_token', staffToken);
  }

  if (init.body instanceof URLSearchParams && !init.body.has('staff_token')) {
    init.body.set('staff_token', staffToken);
  }

  return [input, init];
};

/**
 * Replace `password` values inside JSON / FormData / URLSearchParams / query strings.
 *
 * Two-tier logic so any admin action works with the current superadmin password
 * without every component having to import `getSuperAdminPassword()`:
 *   1) Always upgrade `password === DEFAULT_SUPERADMIN_PASSWORD` to the active
 *      password (legacy hard-coded 'admin2025' rescue).
 *   2) If the body has NO `usuario` field and the endpoint is not in
 *      AUTH_PATHS_TO_SKIP, overwrite ANY `password` value with the active
 *      superadmin password. This catches stale hardcoded values other than
 *      'admin2025' too, so /admin edits keep saving after the password changes.
 */
const replaceLegacyPassword = (input: RequestInfo | URL, init?: RequestInit): [RequestInfo | URL, RequestInit | undefined] => {
  if (typeof window === 'undefined') return [input, init];
  const activePassword = getSuperAdminPassword();

  const requestUrl = getFetchUrl(input);
  if (!requestUrl || !isSameOriginApiRequest(requestUrl)) return [input, init];

  const skipOverwrite = isAuthEndpoint(requestUrl);
  // When a staff user is logged in we must NOT overwrite the `password` field:
  // staff sessions don't have the superadmin password and endpoints like
  // registro*.php or *_archivo.php accept role-specific literals
  // (`registros2025`, etc.) or a `staff_token`. Overwriting the password to
  // `admin2025` (the default fallback when sessionStorage is empty) broke
  // those calls with 401. We still attach `staff_token` further down.
  const staffTokenPresent = !!getRememberedStaffToken();
  const suppressPasswordRewrite = skipOverwrite || staffTokenPresent;
  const nextInit: RequestInit = init ? { ...init } : {};
  let needsRescueHeader = requestUrl.searchParams.get('password') === DEFAULT_SUPERADMIN_PASSWORD;

  /** Attach the current password only for legacy admin calls that need rescue. */
  const withRescueHeader = (): RequestInit | undefined => {
    if (!needsRescueHeader) return init ? nextInit : undefined;
    const headers = new Headers(nextInit.headers ?? (input instanceof Request ? input.headers : undefined));
    headers.set('X-Superadmin-Password', activePassword);
    nextInit.headers = headers;
    return nextInit;
  };

  // Patch querystring password=admin2025 for admin GET endpoints.
  if (typeof input === 'string' || input instanceof URL) {
    const original = input.toString();
    const url = new URL(original, window.location.origin);
    const qsPwd = url.searchParams.get('password');
    if (!suppressPasswordRewrite && qsPwd) {
      url.searchParams.set('password', activePassword);
      input = original.startsWith('http') ? url.toString() : `${url.pathname}${url.search}`;
    }
  }

  if (!nextInit?.body) {
    const rescuedInit = withRescueHeader() ?? nextInit;
    const [tokenInput, tokenInit] = attachStaffToken(input, rescuedInit, requestUrl);
    return [tokenInput, tokenInit];
  }

  // Patch JSON bodies: { password: 'admin2025', ... }.
  if (typeof nextInit.body === 'string') {
    try {
      const parsed = JSON.parse(nextInit.body);
      if (parsed && typeof parsed === 'object' && 'password' in parsed) {
        const isLegacy = parsed.password === DEFAULT_SUPERADMIN_PASSWORD;
        const hasUsuario = 'usuario' in parsed && !!parsed.usuario;
        // Overwrite when: legacy default OR admin-only payload (no usuario, not auth endpoint).
        if (!suppressPasswordRewrite && (isLegacy || !hasUsuario)) {
          needsRescueHeader = true;
          nextInit.body = JSON.stringify({ ...parsed, password: activePassword });
        }
      }
    } catch {
      // Non-JSON string bodies are left untouched.
    }
  }

  // Patch multipart uploads: FormData password field.
  if (nextInit.body instanceof FormData) {
    const fdPwd = nextInit.body.get('password');
    const fdUsuario = nextInit.body.get('usuario');
    if (!suppressPasswordRewrite && fdPwd && (fdPwd === DEFAULT_SUPERADMIN_PASSWORD || !fdUsuario)) {
      needsRescueHeader = true;
      nextInit.body.set('password', activePassword);
    }
  }

  // Patch URLSearchParams bodies if ever used by an admin endpoint.
  if (nextInit.body instanceof URLSearchParams) {
    const upPwd = nextInit.body.get('password');
    const upUsuario = nextInit.body.get('usuario');
    if (!suppressPasswordRewrite && upPwd && (upPwd === DEFAULT_SUPERADMIN_PASSWORD || !upUsuario)) {
      needsRescueHeader = true;
      nextInit.body.set('password', activePassword);
    }
  }

  const rescuedInit = withRescueHeader() ?? nextInit;
  const [tokenInput, tokenInit] = attachStaffToken(input, rescuedInit, requestUrl);
  return [tokenInput, tokenInit];
};

/**
 * Install a small compatibility layer so existing admin components that still
 * send admin2025 automatically send the current changed superadmin password.
 */
export const installSuperAdminPasswordFetchPatch = (): void => {
  if (typeof window === 'undefined' || fetchPatchInstalled) return;
  fetchPatchInstalled = true;
  const originalFetch = window.fetch.bind(window);
  window.fetch = (input: RequestInfo | URL, init?: RequestInit) => {
    const [patchedInput, patchedInit] = replaceLegacyPassword(input, init);
    return originalFetch(patchedInput, patchedInit);
  };
};