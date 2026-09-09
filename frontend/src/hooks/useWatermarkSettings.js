import { useQuery } from '@tanstack/react-query';
import { api } from '../services/estateApi.js';

export const WATERMARK_SETTINGS_QUERY_KEY = ['watermark-settings'];

export function useWatermarkSettings() {
  const query = useQuery({
    queryKey: WATERMARK_SETTINGS_QUERY_KEY,
    queryFn: api.watermarkSettings.show,
    staleTime: 5 * 60_000,
  });

  return { settings: query.data?.data || null, isLoading: query.isLoading };
}
