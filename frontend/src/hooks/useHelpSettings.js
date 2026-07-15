import { useQuery } from '@tanstack/react-query';
import { api } from '../services/estateApi.js';
import { useAuth } from '../state/AuthContext.jsx';

/**
 * Urutan resolusi harus sama persis dengan HelpSetting::isEnabled() di backend:
 * component > page > module > role > global (default true kalau tidak ada baris sama sekali).
 */
export function resolveHelpVisibility(settings = [], scope = {}) {
  for (const type of ['component', 'page', 'module', 'role']) {
    const key = scope[type];
    if (!key) continue;
    const match = settings.find((item) => item.scope_type === type && item.scope_key === key);
    if (match) return match.is_enabled;
  }
  const global = settings.find((item) => item.scope_type === 'global');
  return global ? global.is_enabled : true;
}

export function useHelpSettingsData() {
  const query = useQuery({
    queryKey: ['help-settings'],
    queryFn: api.helpSettings.list,
    staleTime: 5 * 60 * 1000,
  });

  return {
    settings: query.data?.data?.settings || [],
    appVersion: query.data?.data?.app_version || null,
    support: query.data?.data?.support || {},
    isLoading: query.isLoading,
  };
}

export function useHelpVisible(scope = {}) {
  const { settings } = useHelpSettingsData();
  const { roles } = useAuth();

  return resolveHelpVisibility(settings, { role: roles?.[0], ...scope });
}
