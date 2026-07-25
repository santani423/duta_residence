import CmsCollectionPage from './CmsCollectionPage.jsx';
import { api } from '../../services/estateApi.js';
import StatusBadge from '../../components/common/StatusBadge.jsx';

const config = {
  titleSingular: 'Acara',
  titlePlural: 'Berita & Event — Acara',
  subtitle: 'Kelola acara/kegiatan kawasan. Acara berstatus "Draft" tidak akan tampil di landing page.',
  resource: api.cms.events,
  queryKey: 'cms-events',
  hasReorder: true,
  hasSearch: true,
  searchPlaceholder: 'Cari judul acara',
  extraFilters: [
    {
      name: 'status',
      label: 'Status',
      options: [
        { value: 'draft', label: 'Draft' },
        { value: 'published', label: 'Terbit' },
      ],
    },
  ],
  columns: [
    { title: 'Judul', dataIndex: 'title' },
    { title: 'Lokasi', dataIndex: 'location' },
    {
      title: 'Publikasi',
      dataIndex: 'status',
      width: 110,
      render: (value) => <StatusBadge type="approval" value={value === 'published' ? 'approved' : 'pending'}>{value === 'published' ? 'Terbit' : 'Draft'}</StatusBadge>,
    },
  ],
  fields: [
    { name: 'title', label: 'Judul Acara', type: 'text', rules: [{ required: true, message: 'Judul wajib diisi' }] },
    { name: 'slug', label: 'Slug', type: 'text', rules: [{ required: true, message: 'Slug wajib diisi' }], tooltip: 'URL ramah-mesin-pencari, contoh: bazar-umkm-warga' },
    { name: 'banner_media_id', label: 'Banner', type: 'image', relation: 'banner' },
    { name: 'description', label: 'Deskripsi', type: 'textarea', span: 'full' },
    { name: 'location', label: 'Lokasi', type: 'text' },
    { name: 'starts_at', label: 'Mulai', type: 'date', showTime: true },
    { name: 'ends_at', label: 'Selesai', type: 'date', showTime: true },
    { name: 'registration_url', label: 'URL Pendaftaran', type: 'text' },
    {
      name: 'status',
      label: 'Status Publikasi',
      type: 'select',
      required: true,
      options: [
        { value: 'draft', label: 'Draft' },
        { value: 'published', label: 'Terbit' },
      ],
    },
    { name: 'is_active', label: 'Aktif', type: 'switch' },
  ],
  initialValues: { is_active: true, status: 'draft' },
};

export default function CmsEventsPage() {
  return <CmsCollectionPage config={config} />;
}
