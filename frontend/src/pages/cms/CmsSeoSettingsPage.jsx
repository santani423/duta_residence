import CmsSettingsPage from './CmsSettingsPage.jsx';
import { api } from '../../services/estateApi.js';

const config = {
  title: 'Pengaturan SEO',
  subtitle: 'Meta tag, Open Graph, dan structured data (JSON-LD) landing page. Diterapkan secara dinamis di sisi browser — mesin pencari yang menjalankan JavaScript (mis. Googlebot) akan membacanya.',
  resource: api.cms.settings.seo,
  queryKey: 'cms-settings-seo',
  fields: [
    { name: 'meta_title', label: 'Meta Title', type: 'text', span: 'full' },
    { name: 'meta_description', label: 'Meta Description', type: 'textarea', span: 'full' },
    { name: 'meta_keywords', label: 'Meta Keywords (pisahkan dengan koma)', type: 'text', span: 'full' },
    { name: 'og_title', label: 'Open Graph Title', type: 'text' },
    { name: 'og_description', label: 'Open Graph Description', type: 'textarea' },
    { name: 'og_image_media_id', label: 'Open Graph Image', type: 'image', relation: 'og_image' },
    {
      name: 'twitter_card_type',
      label: 'Tipe Twitter Card',
      type: 'select',
      options: [
        { value: 'summary', label: 'Summary' },
        { value: 'summary_large_image', label: 'Summary Large Image' },
      ],
    },
    { name: 'favicon_media_id', label: 'Favicon', type: 'image', relation: 'favicon' },
    {
      name: 'structured_data',
      label: 'Structured Data (JSON-LD)',
      type: 'textarea',
      span: 'full',
      rows: 6,
      tooltip: 'Harus berupa JSON valid, contoh: {"@context":"https://schema.org","@type":"Organization",...}',
    },
  ],
};

export default function CmsSeoSettingsPage() {
  return <CmsSettingsPage config={config} />;
}
