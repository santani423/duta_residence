import { useState } from 'react';
import { useDebounce } from './useDebounce.js';

export function useTableState(initialFilters = {}) {
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search);
  const [filters, setFilters] = useState(initialFilters);
  const [pagination, setPagination] = useState({ current: 1, pageSize: 15 });

  const params = {
    ...filters,
    search: debouncedSearch || undefined,
    page: pagination.current,
    per_page: pagination.pageSize,
  };

  function handleTableChange(nextPagination) {
    setPagination({
      current: nextPagination.current || 1,
      pageSize: nextPagination.pageSize || 15,
    });
  }

  function updateFilters(nextFilters) {
    setFilters(nextFilters);
    setPagination((current) => ({ ...current, current: 1 }));
  }

  return {
    search,
    setSearch,
    filters,
    setFilters: updateFilters,
    pagination,
    setPagination,
    params,
    handleTableChange,
  };
}
