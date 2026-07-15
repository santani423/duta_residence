import { useQuery } from '@tanstack/react-query';
import { api } from '../services/estateApi.js';
import { useAuth } from '../state/AuthContext.jsx';

export function useManualBookSections() {
  const { token } = useAuth();
  const query = useQuery({
    queryKey: ['manual-book-sections'],
    queryFn: api.manualBook.list,
    enabled: Boolean(token),
    staleTime: 60000,
  });

  return { sections: query.data?.data || [], isLoading: query.isLoading };
}

export function useManualBookModule(module) {
  const { sections, isLoading } = useManualBookSections();

  return { sections: sections.filter((section) => section.module === module), isLoading };
}
