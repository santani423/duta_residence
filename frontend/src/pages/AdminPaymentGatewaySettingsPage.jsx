import { Alert, Button, Card, Form, Input, InputNumber, Select, Space, Switch, message } from 'antd';
import { ReloadOutlined, SafetyCertificateOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect } from 'react';
import PageHeader from '../components/common/PageHeader.jsx';
import Can from '../components/common/Can.jsx';
import { ErrorState, LoadingState } from '../components/common/ApiState.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import { api } from '../services/estateApi.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';

const gatewayOptions = [
  { value: 'xendit', label: 'Xendit' },
  { value: 'midtrans', label: 'Midtrans' },
  { value: 'manual', label: 'Manual Payment' },
];

export default function AdminPaymentGatewaySettingsPage() {
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ['admin-payment-gateway-setting'], queryFn: api.adminPaymentGateway.show });
  const setting = query.data?.data;

  useEffect(() => {
    if (setting) {
      form.setFieldsValue({
        ...setting,
        enabled_gateways: setting.enabled_gateways || [setting.active_gateway],
        proof_allowed_extensions: setting.proof_allowed_extensions || ['jpg', 'jpeg', 'png', 'pdf'],
      });
    }
  }, [form, setting]);

  const update = useMutation({
    mutationFn: (values) => api.adminPaymentGateway.update(values),
    onSuccess: () => {
      message.success('Pengaturan payment gateway disimpan.');
      queryClient.invalidateQueries({ queryKey: ['admin-payment-gateway-setting'] });
      queryClient.invalidateQueries({ queryKey: ['payment-gateway-config'] });
      queryClient.invalidateQueries({ queryKey: ['resident-payment-config'] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const test = useMutation({
    mutationFn: api.adminPaymentGateway.test,
    onSuccess: (response) => {
      const result = response.data;
      message.info(`${result.gateway} ${result.mode}: ${result.ready ? 'credential siap' : 'credential belum lengkap'}`);
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  return (
    <section>
      <PageHeader
        title="Pengaturan Payment Gateway"
        subtitle="Konfigurasi aman untuk Xendit, Midtrans, dan pembayaran manual. Credential sensitif tetap berada di environment backend."
        breadcrumbs={[{ label: 'Admin' }, { label: 'Settings' }, { label: 'Payment Gateway' }]}
        actions={<Button icon={<ReloadOutlined />} onClick={() => test.mutate()} loading={test.isPending}>Uji Koneksi</Button>}
        onRefresh={query.refetch}
      />
      {query.isLoading ? <LoadingState rows={8} /> : query.isError ? <ErrorState error={query.error} onRetry={query.refetch} /> : (
        <div className="resident-grid resident-grid-2">
          <Card title="Gateway Aktif">
            <Form form={form} layout="vertical" className="responsive-form" onFinish={update.mutate}>
              <Form.Item label="Gateway utama" name="active_gateway" rules={[{ required: true }]}>
                <Select options={gatewayOptions} />
              </Form.Item>
              <Form.Item label="Gateway tersedia" name="enabled_gateways" rules={[{ required: true }]}>
                <Select mode="multiple" options={gatewayOptions} />
              </Form.Item>
              <Form.Item label="Status aktif" name="is_active" valuePropName="checked">
                <Switch checkedChildren="Aktif" unCheckedChildren="Nonaktif" />
              </Form.Item>
              <Form.Item label="Mode" name="mode" rules={[{ required: true }]}>
                <Select options={[{ value: 'sandbox', label: 'Sandbox' }, { value: 'production', label: 'Production' }]} />
              </Form.Item>
              <Form.Item label="Mata uang" name="currency" rules={[{ required: true }]}>
                <Input maxLength={3} />
              </Form.Item>
              <Form.Item label="Biaya administrasi" name="admin_fee" rules={[{ required: true }]}>
                <InputNumber min={0} style={{ width: '100%' }} />
              </Form.Item>
              <Form.Item label="Batas waktu pembayaran (menit)" name="payment_timeout_minutes" rules={[{ required: true }]}>
                <InputNumber min={5} max={10080} style={{ width: '100%' }} />
              </Form.Item>
              <Form.Item label="Public key Xendit" name="xendit_public_key">
                <Input placeholder="Public key saja, bukan secret key" />
              </Form.Item>
              <Form.Item label="Client key Midtrans" name="midtrans_client_key">
                <Input placeholder="Client key saja, bukan server key" />
              </Form.Item>
              <Form.Item label="Callback / webhook URL" name="callback_url" className="full-span">
                <Input />
              </Form.Item>
              <Form.Item label="Catatan webhook" name="webhook_notes" className="full-span">
                <Input.TextArea rows={3} />
              </Form.Item>
              <Form.Item label="Bank manual" name="manual_bank_name">
                <Input />
              </Form.Item>
              <Form.Item label="Nomor rekening" name="manual_account_number">
                <Input />
              </Form.Item>
              <Form.Item label="Nama pemilik rekening" name="manual_account_name">
                <Input />
              </Form.Item>
              <Form.Item label="Maksimal bukti transfer (KB)" name="proof_max_size_kb" rules={[{ required: true }]}>
                <InputNumber min={256} max={20480} style={{ width: '100%' }} />
              </Form.Item>
              <Form.Item label="Format bukti pembayaran" name="proof_allowed_extensions" className="full-span">
                <Select mode="multiple" options={['jpg', 'jpeg', 'png', 'pdf'].map((value) => ({ value, label: value.toUpperCase() }))} />
              </Form.Item>
              <Form.Item label="Instruksi pembayaran manual" name="manual_instructions" className="full-span">
                <Input.TextArea rows={4} />
              </Form.Item>
              <Form.Item className="full-span">
                <Can permission="payment-settings.update" fallback={<Alert type="warning" showIcon message="Anda hanya memiliki akses melihat pengaturan payment gateway." />}>
                  <Button type="primary" htmlType="submit" icon={<SafetyCertificateOutlined />} loading={update.isPending}>Simpan Pengaturan</Button>
                </Can>
              </Form.Item>
            </Form>
          </Card>
          <Card title="Konfigurasi Public untuk Penghuni">
            <Space direction="vertical" size="middle" style={{ width: '100%' }}>
              <Alert
                type="info"
                showIcon
                message="Data yang dikirim ke frontend penghuni"
                description="Secret key, server key, API key, dan callback token tidak pernah ditampilkan di halaman ini."
              />
              <p>Gateway aktif: <strong>{setting.public_config?.active_gateway}</strong></p>
              <p>Status layanan: <StatusBadge type="active" value={setting.public_config?.is_active} /></p>
              <p>Metode tersedia: {(setting.public_config?.available_methods || []).join(', ') || '-'}</p>
              <p>Mode: {setting.public_config?.mode}</p>
              <p>Currency: {setting.public_config?.currency}</p>
              <p>Biaya admin: {setting.public_config?.admin_fee}</p>
              <p>Batas waktu: {setting.public_config?.payment_timeout_minutes} menit</p>
              <p>Credential Xendit: <StatusBadge type="active" value={setting.public_config?.credential_status?.xendit} /></p>
              <p>Credential Midtrans: <StatusBadge type="active" value={setting.public_config?.credential_status?.midtrans} /></p>
            </Space>
          </Card>
        </div>
      )}
    </section>
  );
}
