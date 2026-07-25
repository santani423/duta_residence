import CmsCollectionPage from './CmsCollectionPage.jsx';
import { api } from '../../services/estateApi.js';

const config = {
  titleSingular: 'Slide Hero',
  titlePlural: 'Hero Banner',
  subtitle: 'Kelola slide utama yang tampil di bagian atas landing page.',
  resource: api.cms.heroSlides,
  queryKey: 'cms-hero-slides',
  hasReorder: true,
  hasSearch: true,
  searchPlaceholder: 'Cari judul slide',
  columns: [
    { title: 'Judul', dataIndex: 'title' },
    { title: 'Subjudul', dataIndex: 'subtitle' },
  ],
  fields: [
    { name: 'title', label: 'Judul', type: 'text', rules: [{ required: true, message: 'Judul wajib diisi' }] },
    { name: 'subtitle', label: 'Subjudul', type: 'text' },
    { name: 'description', label: 'Deskripsi', type: 'textarea', span: 'full' },
    { name: 'background_media_id', label: 'Gambar Latar', type: 'image', relation: 'background_media' },
    { name: 'video_url', label: 'URL Video (opsional, menggantikan gambar)', type: 'text' },
    { name: 'cta_text', label: 'Teks Tombol CTA', type: 'text' },
    { name: 'cta_url', label: 'URL Tombol CTA', type: 'text' },
    {
      name: 'cta_target',
      label: 'Buka Tautan CTA di',
      type: 'select',
      options: [
        { value: '_self', label: 'Halaman yang sama' },
        { value: '_blank', label: 'Tab baru' },
      ],
    },
    { name: 'is_active', label: 'Status', type: 'switch' },
  ],
  initialValues: { is_active: true, cta_target: '_self' },
};

export default function CmsHeroSlidesPage() {
  return <CmsCollectionPage config={config} />;
}
