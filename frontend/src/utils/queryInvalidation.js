// Semua query yang menampilkan status/nominal tagihan. Setelah pembayaran (loket, transfer,
// verifikasi, skema) seluruhnya harus di-invalidate supaya halaman Tagihan, Riwayat Tagihan,
// Piutang, dsb. tidak menampilkan cache lama (staleTime global 30 detik).
const PAYMENT_AFFECTED_QUERY_KEYS = [
  'billings',
  'dashboard',
  'payment-receipts',
  'payment-schemes',
  'payment-scheme-payments',
  'payment-transactions',
  'units',
  'residents',
  'receivables',
  'receivables-aging',
];

export function invalidatePaymentQueries(queryClient) {
  PAYMENT_AFFECTED_QUERY_KEYS.forEach((key) => queryClient.invalidateQueries({ queryKey: [key] }));
}
