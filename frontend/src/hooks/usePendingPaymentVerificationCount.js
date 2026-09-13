import { useQuery } from '@tanstack/react-query';
import { api } from '../services/estateApi.js';
import { useAuth } from '../state/AuthContext.jsx';

// Dipakai di sidebar (badge menu Pembayaran) dan tab Transaksi Gateway, jadi
// diletakkan sebagai satu hook bersama dengan query key yang sama agar react-query
// men-dedupe permintaannya alih-alih memanggil API dua kali untuk angka yang sama.
export function usePendingPaymentVerificationCount() {
  const { can } = useAuth();
  const enabled = can('payments.verify');

  const query = useQuery({
    queryKey: ['payment-transactions', 'pending-verification-count'],
    queryFn: () => api.payments.gatewayTransactions({ status: 'waiting_verification', per_page: 1 }),
    enabled,
    refetchInterval: 60000,
  });

  return enabled ? (query.data?.meta?.total || 0) : 0;
}
