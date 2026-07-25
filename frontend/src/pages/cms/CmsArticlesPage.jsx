import { useQuery } from '@tanstack/react-query';
import CmsCollectionPage from './CmsCollectionPage.jsx';
import StatusBadge from '../../components/common/StatusBadge.jsx';
import { api } from '../../services/estateApi.js';

const statusOptions = [
  { value: 'draft', label: 'Draft' },
  { value: 'scheduled', label: 'Terjadwal' },
  { value: 'published', label: 'Terbit' },
];

export default function CmsArticlesPage() {
  const categories = useQuery({ queryKey: ['cms-categories', 'news'], queryFn: () => api.cms.categories.list({ group: 'news', per_page: 100 }) });
  const categoryOptions = (categories.data?.data?.data || []).map((category) => ({ value: category.id, label: category.name }));

  const config = {
    titleSingular: 'Artikel',
    titlePlural: 'Berita & Event — Artikel',
    subtitle: 'Kelola artikel/berita kawasan. Gunakan status "Terjadwal" dengan tanggal publikasi untuk menerbitkan otomatis di kemudian hari.',
    resource: api.cms.articles,
    queryKey: 'cms-articles',
    hasReorder: true,
    hasSearch: true,
    searchPlaceholder: 'Cari judul artikel',
    extraFilters: [
      { name: 'status', label: 'Status', options: statusOptions },
      { name: 'category_id', label: 'Kategori', options: categoryOptions },
    ],
    columns: [
      { title: 'Judul', dataIndex: 'title' },
      {
        title: 'Publikasi',
        dataIndex: 'status',
        width: 110,
        render: (value) => <StatusBadge type="approval" value={value === 'published' ? 'approved' : 'pending'}>{statusOptions.find((option) => option.value === value)?.label}</StatusBadge>,
      },
    ],
    fields: [
      { name: 'title', label: 'Judul Artikel', type: 'text', span: 'full', rules: [{ required: true, message: 'Judul wajib diisi' }] },
      { name: 'slug', label: 'Slug', type: 'text', rules: [{ required: true, message: 'Slug wajib diisi' }], tooltip: 'URL ramah-mesin-pencari' },
      { name: 'category_id', label: 'Kategori', type: 'select', options: categoryOptions },
      { name: 'excerpt', label: 'Ringkasan', type: 'textarea', span: 'full' },
      { name: 'featured_image_media_id', label: 'Gambar Utama', type: 'image', relation: 'featured_image' },
      { name: 'thumbnail_media_id', label: 'Thumbnail (opsional)', type: 'image', relation: 'thumbnail' },
      { name: 'content', label: 'Isi Artikel', type: 'richtext', span: 'full', rows: 12 },
      {
        name: 'status',
        label: 'Status Publikasi',
        type: 'select',
        required: true,
        options: statusOptions,
      },
      { name: 'published_at', label: 'Tanggal Publikasi', type: 'date', showTime: true, tooltip: 'Wajib diisi jika status = Terjadwal.' },
      { name: 'meta_title', label: 'Meta Title (SEO)', type: 'text' },
      { name: 'meta_description', label: 'Meta Description (SEO)', type: 'textarea' },
      { name: 'is_active', label: 'Aktif', type: 'switch' },
    ],
    initialValues: { is_active: true, status: 'draft' },
  };

  return <CmsCollectionPage config={config} />;
}
