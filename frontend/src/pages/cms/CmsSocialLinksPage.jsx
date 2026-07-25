import CmsCollectionPage from './CmsCollectionPage.jsx';
import { api } from '../../services/estateApi.js';

const platformOptions = [
  { value: 'facebook', label: 'Facebook' },
  { value: 'instagram', label: 'Instagram' },
  { value: 'tiktok', label: 'TikTok' },
  { value: 'youtube', label: 'YouTube' },
  { value: 'linkedin', label: 'LinkedIn' },
  { value: 'twitter_x', label: 'X (Twitter)' },
  { value: 'telegram', label: 'Telegram' },
  { value: 'custom', label: 'Lainnya' },
];

const config = {
  titleSingular: 'Tautan Sosial',
  titlePlural: 'Media Sosial',
  subtitle: 'Kelola tautan media sosial yang tampil pada header/footer landing page.',
  resource: api.cms.socialLinks,
  queryKey: 'cms-social-links',
  hasReorder: true,
  columns: [
    { title: 'Platform', render: (_, record) => platformOptions.find((option) => option.value === record.platform)?.label || record.platform },
    { title: 'URL', dataIndex: 'url' },
  ],
  fields: [
    { name: 'platform', label: 'Platform', type: 'select', required: true, options: platformOptions, rules: [{ required: true, message: 'Platform wajib dipilih' }] },
    { name: 'url', label: 'URL Profil', type: 'text', rules: [{ required: true, message: 'URL wajib diisi' }] },
    { name: 'is_active', label: 'Status', type: 'switch' },
  ],
  initialValues: { is_active: true },
  rowLabel: (record) => record.platform,
};

export default function CmsSocialLinksPage() {
  return <CmsCollectionPage config={config} />;
}
