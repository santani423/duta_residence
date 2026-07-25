import CmsSettingsPage from './CmsSettingsPage.jsx';
import { api } from '../../services/estateApi.js';

const config = {
  title: 'Pengaturan Footer',
  subtitle: 'Logo, deskripsi, dan tampilan footer landing page.',
  resource: api.cms.settings.footer,
  queryKey: 'cms-settings-footer',
  fields: [
    { name: 'logo_media_id', label: 'Logo (kosongkan untuk memakai logo header)', type: 'image', relation: 'logo' },
    { name: 'description', label: 'Deskripsi Singkat', type: 'textarea', span: 'full' },
    { name: 'copyright_text', label: 'Teks Copyright', type: 'text', span: 'full' },
    { name: 'show_social_links', label: 'Tampilkan Tautan Media Sosial', type: 'switch' },
    { name: 'show_quick_links', label: 'Tampilkan Tautan Cepat (Menu)', type: 'switch' },
  ],
};

export default function CmsFooterSettingsPage() {
  return <CmsSettingsPage config={config} />;
}
