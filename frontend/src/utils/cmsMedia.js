import { storageUrl } from '../services/estateApi.js';

// Resolves a MediaAsset relation (as returned by the public /landing/content
// endpoint) to a displayable URL, preferring the medium WebP variant.
export function cmsImageUrl(media) {
  if (!media) return null;
  const path = media.variants?.webp_medium || media.variants?.webp || media.path;
  return storageUrl(path);
}
