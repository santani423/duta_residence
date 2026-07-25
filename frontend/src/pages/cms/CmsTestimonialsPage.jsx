import CmsCollectionPage from './CmsCollectionPage.jsx';
import { api } from '../../services/estateApi.js';

const config = {
  titleSingular: 'Testimoni',
  titlePlural: 'Testimoni Penghuni',
  subtitle: 'Kelola testimoni penghuni yang tampil pada landing page.',
  resource: api.cms.testimonials,
  queryKey: 'cms-testimonials',
  hasReorder: true,
  hasSearch: true,
  searchPlaceholder: 'Cari nama',
  columns: [
    { title: 'Nama', dataIndex: 'name' },
    { title: 'Posisi', dataIndex: 'position' },
    { title: 'Rating', dataIndex: 'rating' },
  ],
  fields: [
    { name: 'name', label: 'Nama', type: 'text', rules: [{ required: true, message: 'Nama wajib diisi' }] },
    { name: 'position', label: 'Posisi / Keterangan', type: 'text' },
    { name: 'photo_media_id', label: 'Foto', type: 'image', relation: 'photo' },
    { name: 'rating', label: 'Rating (1-5)', type: 'number', rules: [{ type: 'number', min: 1, max: 5 }] },
    { name: 'content', label: 'Isi Testimoni', type: 'textarea', span: 'full', rules: [{ required: true, message: 'Isi testimoni wajib diisi' }] },
    { name: 'is_active', label: 'Status', type: 'switch' },
  ],
  initialValues: { is_active: true, rating: 5 },
};

export default function CmsTestimonialsPage() {
  return <CmsCollectionPage config={config} />;
}
