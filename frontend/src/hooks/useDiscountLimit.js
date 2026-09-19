import { useQuery } from '@tanstack/react-query';
import { api } from '../services/estateApi.js';
import { DISCOUNT_TYPE_PERCENTAGE } from '../utils/discount.js';

export const DISCOUNT_LIMIT_QUERY_KEY = ['discount-limit'];

// Batas dan tipe input diskon yang berlaku untuk user yang sedang login. maximumPercent null =
// tidak dibatasi (tipe input nominal seperti biasa). Hanya untuk UX; backend tetap yang memvalidasi.
export function useDiscountLimit() {
  const query = useQuery({
    queryKey: DISCOUNT_LIMIT_QUERY_KEY,
    queryFn: api.discountSettings.limit,
    staleTime: 60_000,
  });
  const data = query.data?.data;
  const isLimited = Boolean(data?.is_limited);

  return {
    maximumPercent: isLimited ? Number(data.maximum_percent) : null,
    discountType: isLimited ? (data.discount_type || DISCOUNT_TYPE_PERCENTAGE) : 'nominal',
  };
}
