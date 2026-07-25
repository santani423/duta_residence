import { useQuery } from '@tanstack/react-query';
import { api } from '../services/estateApi.js';

export function useLandingContentQuery() {
  return useQuery({
    queryKey: ['landing-content'],
    queryFn: api.landing.content,
    staleTime: 60_000,
  });
}
