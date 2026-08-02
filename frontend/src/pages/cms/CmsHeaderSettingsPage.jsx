import CmsSettingsPage from './CmsSettingsPage.jsx';
import { api } from '../../services/estateApi.js';

const config = {
  title: 'Pengaturan Header',
  subtitle: 'Logo dan tombol pada header landing page. Nama situs diatur di menu Pengaturan Umum.',
  resource: api.cms.settings.header,
  queryKey: 'cms-settings-header',
  fields: [
    { name: 'logo_media_id', label: 'Logo', type: 'image', relation: 'logo' },
    { name: 'sticky_enabled', label: 'Header Menempel Saat Scroll (Sticky)', type: 'switch' },
    { name: 'show_login_button', label: 'Tampilkan Tombol Login', type: 'switch' },
    { name: 'login_button_text', label: 'Teks Tombol Login', type: 'text' },
    { name: 'cta_button_enabled', label: 'Aktifkan Tombol CTA Tambahan', type: 'switch' },
    { name: 'cta_button_text', label: 'Teks Tombol CTA', type: 'text' },
    { name: 'cta_button_url', label: 'URL Tombol CTA', type: 'text' },
  ],
};

export default function CmsHeaderSettingsPage() {
  return <CmsSettingsPage config={config} />;
}
