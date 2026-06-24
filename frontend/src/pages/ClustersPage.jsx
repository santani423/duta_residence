import { Button, Card, Form, Input, InputNumber, Modal, Space, Switch, message } from 'antd';
import { EditOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import PageHeader from '../components/common/PageHeader.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import Can from '../components/common/Can.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { api } from '../services/estateApi.js';
import { formatCurrency } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';

export default function ClustersPage() {
  const [editing, setEditing] = useState(null);
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const clusters = useQuery({ queryKey: ['clusters'], queryFn: () => api.clusters.list() });
  const update = useMutation({
    mutationFn: (values) => api.clusters.update(editing.id, values),
    onSuccess: () => {
      message.success('Cluster berhasil diperbarui');
      setEditing(null);
      queryClient.invalidateQueries({ queryKey: ['clusters'] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  function openEdit(record) {
    setEditing(record);
    form.setFieldsValue(record);
  }

  return (
    <section>
      <PageHeader title="Cluster" subtitle="Kelola cluster dan tarif iuran bulanan." breadcrumbs={[{ label: 'Cluster' }]} onRefresh={clusters.refetch} loading={clusters.isFetching} />
      <Card>
        <ResponsiveTable
          query={clusters}
          data={clusters.data?.data || []}
          pagination={false}
          scrollX={760}
          columns={[
            { title: 'Kode', dataIndex: 'id', width: 90 },
            { title: 'Nama Cluster', dataIndex: 'name' },
            { title: 'Tarif Bulanan', dataIndex: 'monthly_rate', render: formatCurrency },
            { title: 'Status', dataIndex: 'is_active', render: (value) => <StatusBadge type="active" value={value} /> },
            { title: 'Deskripsi', dataIndex: 'description' },
            {
              title: 'Aksi',
              width: 120,
              render: (_, record) => (
                <Can permission="clusters.update-rate">
                  <Button size="small" icon={<EditOutlined />} onClick={() => openEdit(record)}>Edit</Button>
                </Can>
              ),
            },
          ]}
        />
      </Card>
      <Modal
        title={`Edit Cluster ${editing?.name || ''}`}
        open={Boolean(editing)}
        onCancel={() => setEditing(null)}
        onOk={() => form.submit()}
        confirmLoading={update.isPending}
        destroyOnHidden
      >
        <Form form={form} layout="vertical" onFinish={update.mutate}>
          <Form.Item label="Tarif Bulanan" name="monthly_rate" rules={[{ required: true }]}>
            <InputNumber min={0} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Deskripsi" name="description">
            <Input.TextArea rows={3} />
          </Form.Item>
          <Form.Item label="Aktif" name="is_active" valuePropName="checked">
            <Switch />
          </Form.Item>
        </Form>
      </Modal>
    </section>
  );
}
