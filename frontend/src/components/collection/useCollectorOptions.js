import { useQuery } from '@tanstack/react-query';
import { api } from '../../services/estateApi.js';

/**
 * Daftar collector ringkas untuk dropdown/filter: GET /collection/collectors/options.
 * Sudah di-scope backend (supervisor hanya melihat collector dalam wilayahnya), maks 500, urut nama.
 * Item: { id, name, username, collector_code, account_status, is_active }.
 */
export function useCollectorOptions({ includeInactive = false, search, enabled = true } = {}) {
  const params = { include_inactive: includeInactive ? 1 : 0, ...(search ? { search } : {}) };
  const query = useQuery({
    queryKey: ['collection', 'collector-options', params],
    queryFn: () => api.collection.collectorOptions(params),
    staleTime: 5 * 60 * 1000,
    enabled,
  });
  const payload = query.data?.data;
  const collectors = Array.isArray(payload) ? payload : (Array.isArray(payload?.data) ? payload.data : []);

  return { ...query, collectors };
}

export function collectorOptionLabel(collector) {
  if (!collector) return '';
  const code = collector.collector_code ? ` · ${collector.collector_code}` : '';
  const inactive = collector.account_status && collector.account_status !== 'active'
    ? ' (Nonaktif)'
    : (collector.is_active === false ? ' (Nonaktif)' : '');
  return `${collector.name}${code}${inactive}`;
}
