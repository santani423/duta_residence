import { useQuery } from '@tanstack/react-query';
import { api } from '../services/estateApi.js';

export const DISCOUNT_LIMIT_QUERY_KEY = ['discount-limit'];

// Batas maksimum diskon yang berlaku untuk user yang sedang login (null = tidak dibatasi).
// Hanya untuk UX; backend tetap yang memvalidasi.
export function useDiscountLimit() {
  const query = useQuery({
    queryKey: DISCOUNT_LIMIT_QUERY_KEY,
    queryFn: api.discountSettings.limit,
    staleTime: 60_000,
  });
  const data = query.data?.data;

  return { maximumPercent: data?.is_limited ? Number(data.maximum_percent) : null };
}
