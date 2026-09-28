import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { api } from '../services/estateApi.js';
import { useDebounce } from './useDebounce.js';

// Filter Cluster → Blok → Unit, Customer, dan Alamat yang dipakai bersama semua halaman Unit & Tagihan.
// Backend membaca kunci yang sama lewat App\Support\UnitFilters.
export const UNIT_FILTER_KEYS = ['cluster_id', 'block', 'unit_id', 'customer', 'address'];

export function unitOptionLabel(unit) {
  const address = [unit.cluster?.name || unit.cluster_id, unit.block && `Blok ${unit.block}`, unit.lot_number && `No ${unit.lot_number}`].filter(Boolean).join(' ');
  return [unit.id, address, unit.resident?.name || 'Belum ada penghuni'].filter(Boolean).join(' — ');
}

export function useClusterOptions() {
  const clusters = useQuery({ queryKey: ['clusters'], queryFn: () => api.clusters.list() });
  return {
    options: (clusters.data?.data || []).map((item) => ({ value: item.id, label: item.name })),
    loading: clusters.isFetching,
  };
}

export function useBlockOptions(clusterId) {
  const blocks = useQuery({
    queryKey: ['units', 'blocks', clusterId ?? null],
    queryFn: () => api.units.blocks({ cluster_id: clusterId }),
    staleTime: 5 * 60 * 1000,
  });
  return {
    options: (blocks.data?.data || []).map((block) => ({ value: block, label: `Blok ${block}` })),
    loading: blocks.isFetching,
  };
}

export function useUnitOptions(params) {
  const [search, setSearch] = useState('');
  const debounced = useDebounce(search);
  const query = { ...params, search: debounced || undefined, per_page: 20 };
  const units = useQuery({ queryKey: ['units', 'lookup', query], queryFn: () => api.units.list(query) });
  return {
    options: (units.data?.data || []).map((unit) => ({ value: unit.id, label: unitOptionLabel(unit) })),
    loading: units.isFetching,
    onSearch: setSearch,
  };
}
