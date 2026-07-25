import CmsCollectionPage from './CmsCollectionPage.jsx';
import { api } from '../../services/estateApi.js';

const config = {
  titleSingular: 'Mitra',
  titlePlural: 'Mitra & Kerja Sama',
  subtitle: 'Kelola logo dan daftar mitra/vendor yang tampil pada landing page.',
  resource: api.cms.partners,
  queryKey: 'cms-partners',
  hasReorder: true,
  hasSearch: true,
  searchPlaceholder: 'Cari nama mitra',
  columns: [{ title: 'Nama', dataIndex: 'name' }],
  fields: [
    { name: 'name', label: 'Nama Mitra', type: 'text', rules: [{ required: true, message: 'Nama mitra wajib diisi' }] },
    { name: 'logo_media_id', label: 'Logo', type: 'image', relation: 'logo' },
    { name: 'website_url', label: 'URL Website', type: 'text' },
    { name: 'is_active', label: 'Status', type: 'switch' },
  ],
  initialValues: { is_active: true },
};

export default function CmsPartnersPage() {
  return <CmsCollectionPage config={config} />;
}
