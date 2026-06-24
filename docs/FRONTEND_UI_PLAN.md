# Frontend UI Plan

## 1. Daftar Halaman

- Auth: login, lupa password, reset password, ganti password, profil, session expired, unauthorized, forbidden, not found.
- Operasional: dashboard, cluster, pelanggan, tagihan, pembayaran, cicilan, back payment, reversal, piutang.
- Administrasi: user management, detail user, audit log, notifikasi.
- Laporan dan dokumen: laporan bulanan, harian loket, rekonsiliasi, collector, PDF SPT/SPK/rekap.

## 2. Sitemap

- `/login`
- `/forgot-password`
- `/reset-password`
- `/`
- `/clusters`
- `/customers`
- `/billings`
- `/payments`
- `/installments`
- `/reversals`
- `/receivables`
- `/reports`
- `/documents`
- `/users`
- `/users/:id`
- `/audit-logs`
- `/notifications`
- `/profile`
- `/change-password`
- `/401`, `/403`, `/419`, `*`

## 3. Struktur Menu Berdasarkan Role

- `root`: semua menu dan seluruh aksi.
- `back_office`: dashboard, cluster, pelanggan, tagihan, approval, piutang, laporan, dokumen, notifikasi.
- `loket`: dashboard, pelanggan baca, pembayaran loket, receipt, reversal submit, notifikasi.
- `cs`: dashboard terbatas, pelanggan baca, tagihan baca, notifikasi.

## 4. Matriks Halaman dan Permission

- Dashboard: `reports.view|customers.view|billings.view`
- Cluster: `clusters.view`, rate update `clusters.update-rate`
- Pelanggan: `customers.view`, create/update/delete/convert sesuai permission.
- Tagihan: `billings.view`, prepare/prepare-special/prepare-back/approve sesuai permission.
- Pembayaran: `payments.view`, process/create/verify sesuai permission.
- Cicilan: `installments.view`, create `installments.create`
- Reversal: `reversals.view`, submit/approve sesuai permission.
- Piutang/Laporan/Dokumen: `reports.view`, dokumen `documents.generate`.
- User: `users.view`, create/update/delete/activate/reset-password.
- Audit log: `audit-logs.view`.

## 5. Komponen Reusable

- `Can`, `PageHeader`, `StatusBadge`, `ApiState`, `FilterBar`, `ResponsiveTable`.
- Form drawer untuk customer dan user.
- Notification bell, profile menu, mobile navigation drawer.
- Utility format uang, tanggal, periode, pesan error API.

## 6. Struktur Folder React

- `api/`, `services/`, `constants/`, `hooks/`, `components/common`, `components/forms`, `components/layout`, `components/tables`, `pages/`, `routes/`, `utils/`, `state/`.

## 7. Endpoint per Halaman

- Auth: `/auth/login`, `/auth/logout`, `/auth/me`, `/auth/change-password`.
- Dashboard: `/reports/dashboard`, `/reports/monthly`, `/receivables/aging`, `/notifications`.
- Pelanggan: `/customers`, `/customers/:id`, `/clusters`, `/lookup/regencies`, `/lookup/districts`.
- Tagihan: `/billings`, `/billings/prepare-monthly`, `/billings/prepare-special`, `/billings/prepare-back`, `/billings/:id/approve`, `/billings/approve-batch`.
- Pembayaran: `/payments/search`, `/payments/preview`, `/payments/process`, `/payments/receipts`, `/payments/gateway/config`, `/payments/gateway`, `/payments/gateway/transactions`.
- Cicilan: `/installments`.
- Reversal: `/reversals`.
- Piutang: `/receivables`, `/receivables/aging`.
- Laporan: `/reports/monthly`, `/reports/daily-receipt`, `/reports/reconciliation`, `/reports/collector`.
- Dokumen: `/documents/spt/:receipt`, `/documents/spk/:billing`, `/documents/billing-recap`, `/documents/customer-list`, `/documents/cluster-recap`.
- User: `/users`, `/users/:id`, `/users/:id/reset-password`, `/users/:id/toggle-status`, `/users/:id/activities`.
- Audit: `/audit-logs`.
- Notifikasi: `/notifications`, `/notifications/:id/read`, `/notifications/read-all`.

## 8. Layout Responsif

- Desktop: sidebar tetap, header penuh, filter inline/collapse, tabel horizontal scroll.
- Tablet: sidebar collapsible, grid dua kolom, form drawer lebar sedang.
- Mobile: navigation drawer, form satu kolom, filter collapse, action menu dropdown, chart dan tabel responsif.

## 9. Light dan Dark Mode

- Mode `light`, `dark`, dan `system` disimpan di local storage.
- Ant Design token dan CSS custom properties disinkronkan untuk layout, card, table, modal, chart, upload, dan status.

## 10. Tahapan Implementasi

1. Fondasi service API, auth, permission, theme, layout.
2. Dashboard, pelanggan, tagihan, pembayaran.
3. User management, audit log, notifikasi.
4. Modul tambahan: cicilan, reversal, piutang, laporan, dokumen.
5. Responsiveness, dark mode, error state, build verification.
