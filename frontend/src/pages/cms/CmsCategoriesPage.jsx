import CmsCollectionPage from './CmsCollectionPage.jsx';
import { api } from '../../services/estateApi.js';

const groupOptions = [
  { value: 'service', label: 'Layanan / Fasilitas' },
  { value: 'news', label: 'Berita' },
  { value: 'gallery', label: 'Galeri' },
  { value: 'faq', label: 'FAQ' },
];

const config = {
  titleSingular: 'Kategori',
  titlePlural: 'Kategori Konten',
  subtitle: 'Kategori dipakai bersama oleh modul Layanan, Berita, Galeri, dan FAQ untuk mengelompokkan konten.',
  resource: api.cms.categories,
  queryKey: 'cms-categories-admin',
  hasReorder: true,
  hasSearch: true,
  searchPlaceholder: 'Cari nama kategori',
  extraFilters: [{ name: 'group', label: 'Grup', options: groupOptions }],
  columns: [
    { title: 'Nama', dataIndex: 'name' },
    { title: 'Slug', dataIndex: 'slug' },
    { title: 'Grup', render: (_, record) => groupOptions.find((option) => option.value === record.group)?.label || record.group },
  ],
  fields: [
    { name: 'name', label: 'Nama Kategori', type: 'text', rules: [{ required: true, message: 'Nama wajib diisi' }] },
    { name: 'slug', label: 'Slug', type: 'text', rules: [{ required: true, message: 'Slug wajib diisi' }] },
    { name: 'group', label: 'Grup', type: 'select', required: true, options: groupOptions, rules: [{ required: true, message: 'Grup wajib dipilih' }] },
    { name: 'is_active', label: 'Status', type: 'switch' },
  ],
  initialValues: { is_active: true },
};

export default function CmsCategoriesPage() {
  return <CmsCollectionPage config={config} />;
}
