// Memetakan path route ke slug modul Manual Book, supaya ikon bantuan di setiap
// halaman otomatis tahu panduan mana yang relevan tanpa perlu di-set manual per halaman.
const MODULE_ROUTES = [
  ['/resident', 'resident-portal'],
  ['/residents', 'residents'],
  ['/units', 'units'],
  ['/clusters', 'residents'],
  ['/billings', 'billings'],
  ['/receivables', 'billings'],
  ['/installments', 'billings'],
  ['/reversals', 'payments'],
  ['/payments', 'payments'],
  ['/users', 'users'],
  ['/reports', 'dashboard'],
  ['/documents', 'residents'],
  ['/audit-logs', 'users'],
  ['/admin/manual-book', 'general'],
  ['/admin/help-settings', 'general'],
  ['/admin/settings', 'payments'],
  ['/manual-book', 'general'],
];

export function resolveHelpModule(pathname) {
  if (pathname === '/' || pathname === '') return 'dashboard';
  const match = MODULE_ROUTES.find(([prefix]) => pathname.startsWith(prefix));
  return match ? match[1] : 'general';
}

export const MODULE_LABELS = {
  general: 'Umum',
  dashboard: 'Dashboard',
  residents: 'Penghuni',
  units: 'Unit',
  billings: 'Tagihan',
  payments: 'Pembayaran',
  users: 'User & Akses',
  'resident-portal': 'Portal Penghuni',
};

export function moduleLabel(module) {
  return MODULE_LABELS[module] || module;
}

