import CmsCollectionPage from './CmsCollectionPage.jsx';
import { api } from '../../services/estateApi.js';

const config = {
  titleSingular: 'Keunggulan',
  titlePlural: 'Keunggulan Sistem',
  subtitle: 'Kelola poin-poin keunggulan sistem yang ditampilkan pada landing page.',
  resource: api.cms.features,
  queryKey: 'cms-features',
  hasReorder: true,
  hasSearch: true,
  searchPlaceholder: 'Cari keunggulan',
  columns: [
    { title: 'Judul', dataIndex: 'title' },
    { title: 'Badge', dataIndex: 'badge' },
  ],
  fields: [
    { name: 'title', label: 'Judul', type: 'text', rules: [{ required: true, message: 'Judul wajib diisi' }] },
    { name: 'icon', label: 'Ikon', type: 'icon' },
    { name: 'image_media_id', label: 'Gambar', type: 'image', relation: 'image' },
    { name: 'badge', label: 'Badge (opsional)', type: 'text' },
    { name: 'description', label: 'Deskripsi', type: 'textarea', span: 'full' },
    { name: 'cta_text', label: 'Teks Tombol', type: 'text' },
    { name: 'cta_url', label: 'URL Tombol', type: 'text' },
    { name: 'is_active', label: 'Status', type: 'switch' },
  ],
  initialValues: { is_active: true },
};

export default function CmsFeaturesPage() {
  return <CmsCollectionPage config={config} />;
}
