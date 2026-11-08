// Nama pembayar: pihak yang membayar tagihan (penyewa, anggota keluarga, kerabat, dll), yang belum tentu pemilik unit.
export const PAYER_NAME_MAX_LENGTH = 150;
export const PAYER_NAME_PLACEHOLDER = 'Masukkan nama pihak yang melakukan pembayaran (opsional)';
export const PAYER_NAME_HINT = 'Isi jika yang membayar bukan pemilik unit, misalnya penyewa atau keluarga. Kosongkan jika tidak diperlukan.';

// Nilai yang dikirim ke API: kosong atau spasi saja menjadi undefined, sehingga disimpan sebagai NULL.
export function cleanPayerName(value) {
  const trimmed = typeof value === 'string' ? value.trim() : '';
  return trimmed || undefined;
}
