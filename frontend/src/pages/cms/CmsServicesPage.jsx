import { useQuery } from '@tanstack/react-query';
import CmsCollectionPage from './CmsCollectionPage.jsx';
import { api } from '../../services/estateApi.js';

export default function CmsServicesPage() {
  const categories = useQuery({ queryKey: ['cms-categories', 'service'], queryFn: () => api.cms.categories.list({ group: 'service', per_page: 100 }) });
  const categoryOptions = (categories.data?.data?.data || []).map((category) => ({ value: category.id, label: category.name }));

  const config = {
    titleSingular: 'Layanan',
    titlePlural: 'Layanan & Fasilitas',
    subtitle: 'Kelola daftar layanan dan fasilitas kawasan. Gunakan kategori "Layanan" atau "Fasilitas" untuk memisahkan tampilannya di landing page.',
    resource: api.cms.services,
    queryKey: 'cms-services',
    hasReorder: true,
    hasSearch: true,
    searchPlaceholder: 'Cari layanan/fasilitas',
    extraFilters: [{ name: 'category_id', label: 'Kategori', options: categoryOptions }],
    columns: [
      { title: 'Judul', dataIndex: 'title' },
      { title: 'Kategori', render: (_, record) => record.category?.name || '-' },
    ],
    fields: [
      { name: 'title', label: 'Judul', type: 'text', rules: [{ required: true, message: 'Judul wajib diisi' }] },
      { name: 'category_id', label: 'Kategori', type: 'select', options: categoryOptions },
      { name: 'icon', label: 'Ikon', type: 'icon' },
      { name: 'image_media_id', label: 'Gambar', type: 'image', relation: 'image' },
      { name: 'description', label: 'Deskripsi', type: 'textarea', span: 'full' },
      { name: 'is_active', label: 'Status', type: 'switch' },
    ],
    initialValues: { is_active: true },
  };

  return <CmsCollectionPage config={config} />;
}
