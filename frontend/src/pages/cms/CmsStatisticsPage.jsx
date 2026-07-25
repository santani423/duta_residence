import CmsCollectionPage from './CmsCollectionPage.jsx';
import { api } from '../../services/estateApi.js';

const config = {
  titleSingular: 'Statistik',
  titlePlural: 'Statistik & Counter',
  subtitle: 'Kelola angka statistik pada hero section. Sumber "Otomatis" mengambil jumlah data real-time dari sistem, sumber "Manual" memakai angka yang Anda tulis sendiri.',
  resource: api.cms.statistics,
  queryKey: 'cms-statistics',
  hasReorder: true,
  hasSearch: true,
  searchPlaceholder: 'Cari label statistik',
  columns: [
    { title: 'Label', dataIndex: 'label' },
    { title: 'Sumber', dataIndex: 'source' },
    { title: 'Nilai Manual', dataIndex: 'value' },
  ],
  fields: [
    { name: 'label', label: 'Label', type: 'text', rules: [{ required: true, message: 'Label wajib diisi' }] },
    { name: 'icon', label: 'Ikon', type: 'icon' },
    {
      name: 'source',
      label: 'Sumber Nilai',
      type: 'select',
      required: true,
      options: [
        { value: 'manual', label: 'Manual (isi angka sendiri)' },
        { value: 'residents_count', label: 'Otomatis — Jumlah Penghuni' },
        { value: 'units_count', label: 'Otomatis — Jumlah Unit' },
        { value: 'clusters_count', label: 'Otomatis — Jumlah Cluster' },
      ],
    },
    { name: 'value', label: 'Nilai Manual', type: 'number', tooltip: 'Hanya dipakai jika sumber = Manual.' },
    { name: 'suffix', label: 'Akhiran (mis. +, %, /7)', type: 'text' },
    { name: 'is_active', label: 'Status', type: 'switch' },
  ],
  initialValues: { is_active: true, source: 'manual', value: 0 },
};

export default function CmsStatisticsPage() {
  return <CmsCollectionPage config={config} />;
}
