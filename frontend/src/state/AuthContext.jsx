import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { api } from '../services/estateApi.js';
import {
  SESSION_CHECK_INTERVAL_MS,
  SESSION_REPLACED_REASON,
  SESSION_REVOKED_CODE,
} from '../constants/session.js';

const AuthContext = createContext(null);

function readStoredUser() {
  const raw = localStorage.getItem('gd_user');
  if (!raw) return null;

  try {
    return JSON.parse(raw);
  } catch {
    localStorage.removeItem('gd_user');
    localStorage.removeItem('gd_token');
    return null;
  }
}

export function AuthProvider({ children }) {
  const [token, setToken] = useState(() => localStorage.getItem('gd_token'));
  const [user, setUser] = useState(readStoredUser);

  function clearSession() {
    localStorage.removeItem('gd_token');
    localStorage.removeItem('gd_user');
    setToken(null);
    setUser(null);
  }

  useEffect(() => {
    function onExpired(event) {
      // Sesi diambil alih perangkat lain: kembali ke login dengan notifikasi, bukan halaman 419.
      const replaced = event?.detail?.code === SESSION_REVOKED_CODE;
      clearSession();
      if (!window.location.pathname.includes('/login')) {
        window.location.assign(replaced ? `/login?reason=${SESSION_REPLACED_REASON}` : '/419');
      }
    }

    window.addEventListener('gd:session-expired', onExpired);
    return () => window.removeEventListener('gd:session-expired', onExpired);
  }, []);

  // Login baru di perangkat lain langsung mengakhiri sesi ini, tetapi tab yang diam tidak akan
  // tahu sampai pengguna mengklik sesuatu. Ping ringan ke /auth/me memicu jalur 401 yang sama
  // (gd:session-expired) begitu sesi tidak lagi valid. Kesalahan jaringan diabaikan di sini.
  useEffect(() => {
    if (!token) return undefined;

    function checkSession() {
      if (document.visibilityState !== 'visible') return;
      api.auth.me().catch(() => {});
    }

    const timer = window.setInterval(checkSession, SESSION_CHECK_INTERVAL_MS);
    document.addEventListener('visibilitychange', checkSession);
    return () => {
      window.clearInterval(timer);
      document.removeEventListener('visibilitychange', checkSession);
    };
  }, [token]);

  const permissions = user?.permissions || [];
  const roleNames = user?.roles?.map((role) => role.name || role) || [];

  const value = useMemo(() => ({
    token,
    user,
    permissions,
    roles: roleNames,
    can: (permission) => !permission || permissions.includes(permission),
    canAny: (items = []) => items.length === 0 || items.some((permission) => permissions.includes(permission)),
    canAll: (items = []) => items.every((permission) => permissions.includes(permission)),
    hasRole: (role) => roleNames.includes(role),
    async login(username, password) {
      const response = await api.auth.login({ username, password });
      localStorage.setItem('gd_token', response.data.token);
      localStorage.setItem('gd_user', JSON.stringify(response.data.user));
      setToken(response.data.token);
      setUser(response.data.user);
    },
    async logout() {
      try {
        await api.auth.logout();
      } finally {
        clearSession();
      }
    },
    async refreshMe() {
      const response = await api.auth.me();
      localStorage.setItem('gd_user', JSON.stringify(response.data));
      setUser(response.data);
    },
    clearSession,
  }), [token, user, permissions, roleNames]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  return useContext(AuthContext);
}
