import { SaveOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Alert, Button, Card, Form, InputNumber, message } from 'antd';
import { useEffect } from 'react';
import PageHeader from '../components/common/PageHeader.jsx';
import { ErrorState, LoadingState } from '../components/common/ApiState.jsx';
import { api } from '../services/estateApi.js';
import { DISCOUNT_LIMIT_QUERY_KEY } from '../hooks/useDiscountLimit.js';
import { formatDateTime } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';

export default function AdminDiscountSettingsPage() {
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ['admin-discount-settings'], queryFn: api.discountSettings.show });
  const record = query.data?.data;

  useEffect(() => {
    if (record) form.setFieldsValue({ maximum_admin_discount: Number(record.maximum_admin_discount) });
  }, [record, form]);

  const save = useMutation({
    mutationFn: (payload) => api.discountSettings.update(payload),
    onSuccess: () => {
      message.success('Batas maksimum diskon Admin berhasil disimpan.');
      queryClient.invalidateQueries({ queryKey: ['admin-discount-settings'] });
      queryClient.invalidateQueries({ queryKey: DISCOUNT_LIMIT_QUERY_KEY });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  if (query.isLoading) return <LoadingState rows={4} />;
  if (query.isError) return <ErrorState error={query.error} onRetry={query.refetch} />;

  return (
    <section>
      <PageHeader
        title="Batas Diskon Admin"
        subtitle="Atur persentase diskon maksimum yang boleh diberikan oleh Admin (khusus Super Admin)."
        breadcrumbs={[{ label: 'Settings' }, { label: 'Batas Diskon Admin' }]}
        helpModule="billings"
        onRefresh={query.refetch}
      />

      <Card title="Maksimum Diskon Admin" style={{ maxWidth: 560 }}>
        <Alert
          type="info"
          showIcon
          style={{ marginBottom: 16 }}
          message="Berlaku untuk seluruh diskon yang dibuat atau diterapkan oleh Admin."
          description="Super Admin tidak dibatasi. Perubahan langsung berlaku pada semua permintaan berikutnya."
        />
        <Form form={form} layout="vertical" onFinish={(values) => save.mutate(values)}>
          <Form.Item
            label="Maksimum Diskon Admin (%)"
            name="maximum_admin_discount"
            extra="Nilai antara 0 dan 100. Bawaan: 30%."
            rules={[
              { required: true, message: 'Batas maksimum diskon wajib diisi' },
              { type: 'number', min: 0, max: 100, message: 'Batas maksimum harus antara 0% dan 100%' },
            ]}
          >
            <InputNumber min={0} max={100} step={0.01} precision={2} addonAfter="%" style={{ width: '100%' }} />
          </Form.Item>
          {record?.updated_at ? (
            <p style={{ opacity: 0.65 }}>
              Terakhir diubah {formatDateTime(record.updated_at)}{record.updater?.name ? ` oleh ${record.updater.name}` : ''}.
            </p>
          ) : null}
          <Button type="primary" htmlType="submit" icon={<SaveOutlined />} loading={save.isPending}>
            Simpan Pengaturan
          </Button>
        </Form>
      </Card>
    </section>
  );
}
