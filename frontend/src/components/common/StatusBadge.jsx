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
  balanceDirection: {
    credit: ['Masuk', 'green'],
    debit: ['Keluar', 'red'],
  },
};

export default function StatusBadge({ type, value, children }) {
  const key = String(value);
  const [label, color] = maps[type]?.[key] || [children || value || '-', 'default'];
  return <Tag color={color}>{label}</Tag>;
}
