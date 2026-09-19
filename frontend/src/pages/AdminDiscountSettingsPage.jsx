import { SaveOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Alert, Button, Card, Form, InputNumber, Radio, message } from 'antd';
import { useEffect } from 'react';
import PageHeader from '../components/common/PageHeader.jsx';
import { ErrorState, LoadingState } from '../components/common/ApiState.jsx';
import { api } from '../services/estateApi.js';
import { DISCOUNT_LIMIT_QUERY_KEY } from '../hooks/useDiscountLimit.js';
import { DISCOUNT_TYPE_NOMINAL, DISCOUNT_TYPE_PERCENTAGE } from '../utils/discount.js';
import { formatDateTime } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';

export default function AdminDiscountSettingsPage() {
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ['admin-discount-settings'], queryFn: api.discountSettings.show });
  const record = query.data?.data;

  useEffect(() => {
    if (record) {
      form.setFieldsValue({
        maximum_admin_discount: Number(record.maximum_admin_discount),
        admin_discount_type: record.admin_discount_type || DISCOUNT_TYPE_PERCENTAGE,
      });
    }
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

  const selectedType = Form.useWatch('admin_discount_type', form);
  const selectedMax = Form.useWatch('maximum_admin_discount', form);

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

      <Card title="Pengaturan Diskon Admin" style={{ maxWidth: 560 }}>
        <Alert
          type="info"
          showIcon
          style={{ marginBottom: 16 }}
          message="Berlaku untuk seluruh diskon yang dibuat atau diterapkan oleh Admin."
          description="Super Admin tidak dibatasi. Perubahan langsung berlaku pada semua permintaan berikutnya; diskon yang sudah tersimpan tidak berubah."
        />
        <Form form={form} layout="vertical" onFinish={(values) => save.mutate(values)}>
          <Form.Item
            label="Tipe Input Diskon Admin"
            name="admin_discount_type"
            extra={selectedType === DISCOUNT_TYPE_NOMINAL
              ? `Admin memasukkan diskon dalam Rupiah. Nominal dibatasi setara ${selectedMax ?? 0}% dari harga asli tagihan (mis. harga Rp1.000.000 dengan batas ${selectedMax ?? 0}% berarti maksimal Rp${Math.round(1000000 * (selectedMax ?? 0) / 100).toLocaleString('id-ID')}).`
              : `Admin memasukkan diskon dalam persen dan tidak boleh melebihi ${selectedMax ?? 0}%. Nominalnya dihitung otomatis dari harga asli tagihan.`}
          >
            <Radio.Group
              optionType="button"
              buttonStyle="solid"
              options={[
                { value: DISCOUNT_TYPE_PERCENTAGE, label: 'Persentase (%)' },
                { value: DISCOUNT_TYPE_NOMINAL, label: 'Nominal (Rp)' },
              ]}
            />
          </Form.Item>
          <Form.Item
            label="Maksimum Diskon Admin (%)"
            name="maximum_admin_discount"
            extra="Batas tertinggi diskon untuk Admin, selalu dinyatakan dalam persen (0–100). Bawaan: 30%."
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
