import { useQuery } from '@tanstack/react-query';
import CmsCollectionPage from './CmsCollectionPage.jsx';
import { api } from '../../services/estateApi.js';

export default function CmsFaqsPage() {
  const categories = useQuery({ queryKey: ['cms-categories', 'faq'], queryFn: () => api.cms.categories.list({ group: 'faq', per_page: 100 }) });
  const categoryOptions = (categories.data?.data?.data || []).map((category) => ({ value: category.id, label: category.name }));

  const config = {
    titleSingular: 'FAQ',
    titlePlural: 'Pertanyaan Umum (FAQ)',
    subtitle: 'Kelola daftar pertanyaan dan jawaban yang tampil pada landing page.',
    resource: api.cms.faqs,
    queryKey: 'cms-faqs',
    hasReorder: true,
    hasSearch: true,
    searchPlaceholder: 'Cari pertanyaan',
    extraFilters: [{ name: 'category_id', label: 'Kategori', options: categoryOptions }],
    rowLabel: (record) => record.question,
    columns: [
      { title: 'Pertanyaan', dataIndex: 'question' },
      { title: 'Kategori', render: (_, record) => record.category?.name || '-' },
    ],
    fields: [
      { name: 'question', label: 'Pertanyaan', type: 'text', span: 'full', rules: [{ required: true, message: 'Pertanyaan wajib diisi' }] },
      { name: 'category_id', label: 'Kategori', type: 'select', options: categoryOptions },
      {
        name: 'answer',
        label: 'Jawaban',
        type: 'richtext',
        span: 'full',
        rules: [{ required: true, message: 'Jawaban wajib diisi' }],
      },
      { name: 'is_active', label: 'Status', type: 'switch' },
    ],
    initialValues: { is_active: true },
  };

  return <CmsCollectionPage config={config} />;
}
