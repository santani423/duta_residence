import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../services/estateApi.js';
import { cmsImageUrl } from '../utils/cmsMedia.js';

const FALLBACK_SITE_NAME = 'Duta Indah Residences';

function useSiteIdentityQuery() {
  return useQuery({
    queryKey: ['site-identity'],
    queryFn: api.landing.identity,
    staleTime: 5 * 60_000,
  });
}

// Used outside the landing page's LandingContentProvider tree (login screen,
// authenticated app shell, admin page subtitles) - a small dedicated endpoint
// instead of the full `/landing/content` aggregate those pages don't need.
export function useSiteIdentity() {
  const { data } = useSiteIdentityQuery();
  return data?.data?.site_name || FALLBACK_SITE_NAME;
}

// Keeps the browser-tab icon in sync with the CMS logo (SEO favicon, else the
// header logo) on every route. When the CMS has neither, the bundled
// /favicon.png declared in index.html stays in place.
export function useSiteFavicon() {
  const { data } = useSiteIdentityQuery();
  const href = cmsImageUrl(data?.data?.favicon);

  useEffect(() => {
    if (!href) return undefined;
    const link = document.head.querySelector('link[rel="icon"]');
    if (!link) return undefined;

    const original = { href: link.getAttribute('href'), type: link.getAttribute('type') };
    link.removeAttribute('type');
    link.setAttribute('href', href);

    return () => {
      link.setAttribute('href', original.href);
      if (original.type) link.setAttribute('type', original.type);
    };
  }, [href]);
}
