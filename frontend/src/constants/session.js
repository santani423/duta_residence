// Kode error dari middleware `single-session` backend: token ini bukan lagi sesi aktif akun
// karena akun yang sama login di perangkat lain.
export const SESSION_REVOKED_CODE = 'SESSION_REVOKED';

// Ditampilkan di halaman login setelah sesi diambil alih perangkat lain.
export const SESSION_REVOKED_MESSAGE = 'Sesi Anda telah berakhir karena akun ini login pada perangkat lain.';

// Query `?reason=` pada /login yang menandakan alasan redirect adalah pengambilalihan sesi.
export const SESSION_REPLACED_REASON = 'session-replaced';

// Seberapa sering tab yang terbuka memeriksa sesinya ke server, supaya perangkat yang sesinya
// sudah diambil alih keluar otomatis walaupun pengguna tidak melakukan apa-apa.
export const SESSION_CHECK_INTERVAL_MS = 60_000;
