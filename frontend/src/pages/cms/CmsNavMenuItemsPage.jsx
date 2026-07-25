import CmsCollectionPage from './CmsCollectionPage.jsx';
import { api } from '../../services/estateApi.js';

const config = {
  titleSingular: 'Menu Navigasi',
  titlePlural: 'Navigasi Header & Footer',
  subtitle: 'Kelola menu navigasi header. Aktifkan "Tampilkan di Footer" agar menu yang sama juga muncul di footer sebagai tautan cepat.',
  resource: api.cms.navMenuItems,
  queryKey: 'cms-nav-menu-items',
  hasReorder: true,
  hasSearch: true,
  searchPlaceholder: 'Cari label menu',
  columns: [
    { title: 'Label', dataIndex: 'label' },
    { title: 'URL', dataIndex: 'url' },
    { title: 'Di Footer', dataIndex: 'show_in_footer', render: (value) => (value ? 'Ya' : 'Tidak') },
  ],
  fields: [
    { name: 'label', label: 'Label', type: 'text', rules: [{ required: true, message: 'Label wajib diisi' }] },
    { name: 'url', label: 'URL (mis. #layanan atau https://...)', type: 'text', rules: [{ required: true, message: 'URL wajib diisi' }] },
    { name: 'open_in_new_tab', label: 'Buka di Tab Baru', type: 'switch' },
    { name: 'show_in_footer', label: 'Tampilkan di Footer', type: 'switch' },
    { name: 'is_active', label: 'Status', type: 'switch' },
  ],
  initialValues: { is_active: true, show_in_footer: true, open_in_new_tab: false },
};

export default function CmsNavMenuItemsPage() {
  return <CmsCollectionPage config={config} />;
}
