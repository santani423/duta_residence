import CmsSettingsPage from './CmsSettingsPage.jsx';
import { api } from '../../services/estateApi.js';

const config = {
  title: 'Pengaturan Tentang Kami',
  subtitle: 'Konten section "Tentang Kami" beserta poin-poin kepercayaan (pillars) pada landing page.',
  resource: api.cms.settings.about,
  queryKey: 'cms-settings-about',
  fields: [
    { name: 'title', label: 'Judul', type: 'text' },
    { name: 'subtitle', label: 'Subjudul', type: 'text' },
    { name: 'description', label: 'Deskripsi', type: 'textarea', span: 'full' },
    { name: 'image_media_id', label: 'Gambar', type: 'image', relation: 'image' },
    { name: 'pillars', label: 'Poin Kepercayaan (Keamanan, Kenyamanan, dst.)', type: 'icon-text-list', span: 'full' },
  ],
};

export default function CmsAboutSettingsPage() {
  return <CmsSettingsPage config={config} />;
}
