import { Tag } from 'antd';

const maps = {
  billing: {
    '01': ['Belum Bayar', 'gold'],
    '02': ['Lunas', 'green'],
    '03': ['Sebagian', 'blue'],
    '04': ['Dibatalkan', 'default'],
  },
  approval: {
    approved: ['Approved', 'green'],
    pending: ['Menunggu', 'gold'],
    rejected: ['Ditolak', 'red'],
  },
  paymentScheme: {
    pending: ['Menunggu Admin', 'gold'],
    approved: ['Disetujui', 'green'],
    rejected: ['Ditolak', 'red'],
    cancelled: ['Dibatalkan', 'default'],
  },
  transaction: {
    pending: ['Pending', 'gold'],
    waiting_verification: ['Menunggu Verifikasi', 'blue'],
    paid: ['Berhasil', 'green'],
    failed: ['Gagal', 'red'],
    expired: ['Kedaluwarsa', 'volcano'],
    cancelled: ['Dibatalkan', 'default'],
    rejected: ['Ditolak', 'red'],
  },
  active: {
    true: ['Aktif', 'green'],
    false: ['Nonaktif', 'red'],
  },
  read: {
    read: ['Dibaca', 'default'],
    unread: ['Belum Dibaca', 'blue'],
  },
  collectorAccount: {
    active: ['Aktif', 'green'],
    inactive: ['Nonaktif', 'default'],
    leave: ['Cuti', 'gold'],
    suspended: ['Suspend', 'red'],
  },
  assignmentStatus: {
    active: ['Aktif', 'green'],
    completed: ['Selesai', 'default'],
    cancelled: ['Dibatalkan', 'red'],
    transferred: ['Dipindahkan', 'blue'],
  },
  // Status akun penagihan dari backend (CollectionAccountState::STATUSES).
  collectionAccount: {
    current: ['Lancar', 'green'],
    due_soon: ['Segera Jatuh Tempo', 'cyan'],
    due_today: ['Jatuh Tempo Hari Ini', 'gold'],
    overdue: ['Menunggak', 'volcano'],
    partially_paid: ['Bayar Sebagian', 'blue'],
    promise_to_pay: ['Janji Bayar', 'geekblue'],
    disputed: ['Sengketa', 'purple'],
    escalated: ['Eskalasi', 'red'],
    paid: ['Lunas', 'green'],
  },
  // Level prioritas penagihan (CollectionAccountState::PRIORITY_LEVELS).
  priorityLevel: {
    critical: ['Kritis', 'red'],
    high: ['Tinggi', 'volcano'],
    medium: ['Sedang', 'gold'],
    normal: ['Normal', 'default'],
  },
  // Bucket umur tunggakan (CollectionAgingService::BUCKETS).
  agingBucket: {
    current: ['Lancar', 'green'],
    '1_30': ['1–30 Hari', 'gold'],
    '31_60': ['31–60 Hari', 'orange'],
    '61_90': ['61–90 Hari', 'volcano'],
    '91_180': ['91–180 Hari', 'red'],
    '180_plus': ['>180 Hari', 'magenta'],
  },
  balanceDirection: {
    credit: ['Masuk', 'green'],
    debit: ['Keluar', 'red'],
  },
  // Status unit yang dihitung backend (Unit::getOccupancyStatusAttribute) - satu-satunya
  // sumber warna/label status unit, dipakai di semua halaman (list/detail/cluster/peta).
  unitOccupancy: {
    ready_stock: ['Ready Stock', 'blue'],
    tanah_kosong: ['Tanah Kosong', 'gold'],
    booked: ['Booked', 'purple'],
    occupied: ['Occupied', 'green'],
  },
  // Status hubungan penghuni-unit dari backend (Resident::getUnitStatusAttribute).
  residentUnit: {
    with_unit: ['Punya Unit', 'green'],
    without_unit: ['Tanpa Unit', 'orange'],
    never_linked: ['Belum Ada Unit', 'default'],
  },
};

// Label untuk dipakai di Select/filter agar konsisten dengan badge.
// eslint-disable-next-line react-refresh/only-export-components
export function statusOptions(type) {
  return Object.entries(maps[type] || {}).map(([value, [label]]) => ({ value, label }));
}

export default function StatusBadge({ type, value, children }) {
  const key = String(value);
  const [label, color] = maps[type]?.[key] || [children || value || '-', 'default'];
  return <Tag color={color}>{label}</Tag>;
}
