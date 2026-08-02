import { useQuery } from '@tanstack/react-query';
import { api } from '../services/estateApi.js';

const FALLBACK_SITE_NAME = 'Duta Indah Residences';

// Used outside the landing page's LandingContentProvider tree (login screen,
// authenticated app shell, admin page subtitles) - a small dedicated endpoint
// instead of the full `/landing/content` aggregate those pages don't need.
export function useSiteIdentity() {
  const { data } = useQuery({
    queryKey: ['site-identity'],
    queryFn: api.landing.identity,
    staleTime: 5 * 60_000,
  });
  return data?.data?.site_name || FALLBACK_SITE_NAME;
}
