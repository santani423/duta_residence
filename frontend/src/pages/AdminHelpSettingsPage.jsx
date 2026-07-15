import { Button, Card, Descriptions, Form, Input, Select, Space, Switch, Table, Tag, Typography, message } from 'antd';
import { DeleteOutlined, PlusOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import PageHeader from '../components/common/PageHeader.jsx';
import { ErrorState, LoadingState } from '../components/common/ApiState.jsx';
import { api } from '../services/estateApi.js';
import { getApiErrorMessage } from '../utils/apiError.js';
import { moduleLabel, MODULE_LABELS } from '../utils/helpModules.js';

const scopeTypeOptions = [
  { value: 'role', label: 'Role' },
  { value: 'module', label: 'Modul' },
  { value: 'page', label: 'Halaman (path)' },
  { value: 'component', label: 'Komponen' },
];

const scopeTypeLabels = { global: 'Global', role: 'Role', module: 'Modul', page: 'Halaman', component: 'Komponen' };

export default function AdminHelpSettingsPage() {
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ['admin-help-settings'], queryFn: api.helpSettings.list });
  const roles = useQuery({ queryKey: ['lookup-roles'], queryFn: api.lookup.roles });

  const settings = query.data?.data?.settings || [];
  const global = settings.find((item) => item.scope_type === 'global');
  const overrides = settings.filter((item) => item.scope_type !== 'global');

  const upsert = useMutation({
    mutationFn: (payload) => api.helpSettings.upsert([payload]),
    onSuccess: () => {
      message.success('Pengaturan bantuan berhasil disimpan');
      queryClient.invalidateQueries({ queryKey: ['admin-help-settings'] });
      queryClient.invalidateQueries({ queryKey: ['help-settings'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const remove = useMutation({
    mutationFn: api.helpSettings.remove,
    onSuccess: () => {
      message.success('Override berhasil dihapus');
      queryClient.invalidateQueries({ queryKey: ['admin-help-settings'] });
      queryClient.invalidateQueries({ queryKey: ['help-settings'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  function addOverride(values) {
    upsert.mutate({ ...values, is_enabled: values.is_enabled ?? true }, {
      onSuccess: () => form.resetFields(),
    });
  }

  if (query.isLoading) return <LoadingState rows={6} />;
  if (query.isError) return <ErrorState error={query.error} onRetry={query.refetch} />;

  return (
    <section>
      <PageHeader
        title="Pengaturan Bantuan"
        subtitle="Tampilkan atau sembunyikan ikon informasi dan panduan interaktif secara global maupun per role/modul/halaman/komponen."
        breadcrumbs={[{ label: 'Admin' }, { label: 'Pengaturan Bantuan' }]}
        helpModule="general"
        onRefresh={query.refetch}
      />

      <Card title="Saklar Global">
        <Space direction="vertical">
          <Typography.Text>
            Tampilkan Ikon Informasi dan Panduan Interaktif untuk seluruh aplikasi. Override di bawah bisa mengecualikan role/modul/halaman/komponen tertentu.
          </Typography.Text>
          <Switch
            checked={global?.is_enabled ?? true}
            checkedChildren="ON"
            unCheckedChildren="OFF"
            loading={upsert.isPending}
            onChange={(checked) => upsert.mutate({ scope_type: 'global', is_enabled: checked })}
          />
        </Space>
      </Card>

      <Card title="Override per Role / Modul / Halaman / Komponen" className="section-row">
        <Form form={form} layout="inline" onFinish={addOverride} className="section-row" initialValues={{ scope_type: 'role', is_enabled: true }}>
          <Form.Item name="scope_type" rules={[{ required: true }]}>
            <Select style={{ width: 150 }} options={scopeTypeOptions} />
          </Form.Item>
          <Form.Item name="scope_key" rules={[{ required: true, message: 'Isi role/modul/path/kode komponen' }]}>
            <Input placeholder="mis. cs / residents / /billings / phone-field" style={{ width: 220 }} />
          </Form.Item>
          <Form.Item name="is_enabled" valuePropName="checked">
            <Switch checkedChildren="ON" unCheckedChildren="OFF" />
          </Form.Item>
          <Form.Item>
            <Button type="primary" icon={<PlusOutlined />} htmlType="submit" loading={upsert.isPending}>Tambah Override</Button>
          </Form.Item>
        </Form>

        {roles.data?.data ? (
          <Typography.Text type="secondary" className="section-row">
            Role tersedia: {roles.data.data.join(', ')}. Modul tersedia: {Object.keys(MODULE_LABELS).join(', ')}.
          </Typography.Text>
        ) : null}

        <Table
          className="section-row"
          rowKey="id"
          dataSource={overrides}
          pagination={false}
          locale={{ emptyText: 'Belum ada override khusus.' }}
          columns={[
            { title: 'Scope', dataIndex: 'scope_type', render: (value) => <Tag>{scopeTypeLabels[value] || value}</Tag> },
            { title: 'Target', dataIndex: 'scope_key', render: (value, record) => (record.scope_type === 'module' ? moduleLabel(value) : value) },
            { title: 'Status', dataIndex: 'is_enabled', render: (value) => <Tag color={value ? 'green' : 'red'}>{value ? 'ON' : 'OFF'}</Tag> },
            {
              title: 'Aksi',
              width: 80,
              render: (_, record) => <Button size="small" danger icon={<DeleteOutlined />} onClick={() => remove.mutate(record.id)} />,
            },
          ]}
        />
      </Card>

      <Card title="Informasi Aplikasi" className="section-row">
        <Descriptions column={1} size="small">
          <Descriptions.Item label="Versi">{query.data?.data?.app_version || '-'}</Descriptions.Item>
          <Descriptions.Item label="Email Dukungan">{query.data?.data?.support?.email || 'Belum diatur (env SUPPORT_EMAIL)'}</Descriptions.Item>
          <Descriptions.Item label="Telepon Dukungan">{query.data?.data?.support?.phone || 'Belum diatur (env SUPPORT_PHONE)'}</Descriptions.Item>
        </Descriptions>
      </Card>
    </section>
  );
}
