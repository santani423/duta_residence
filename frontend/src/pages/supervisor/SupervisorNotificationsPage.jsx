import { Button, Card, Form, Input, InputNumber, Modal, Select, Space, Switch, Tag, message } from 'antd';
import { CheckOutlined, RiseOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import PageHeader from '../../components/common/PageHeader.jsx';
import FilterBar from '../../components/common/FilterBar.jsx';
import Can from '../../components/common/Can.jsx';
import ResponsiveTable from '../../components/tables/ResponsiveTable.jsx';
import { useTableState } from '../../hooks/useTableState.js';
import { api } from '../../services/estateApi.js';
import { getApiErrorMessage, mapValidationErrors } from '../../utils/apiError.js';
import { formatDateTime } from '../../utils/format.js';

const PRIORITY_COLORS = { low: 'default', normal: 'blue', high: 'orange', critical: 'red' };
const HANDLED_COLORS = { open: 'gold', in_progress: 'blue', handled: 'green', escalated: 'purple' };

export default function SupervisorNotificationsPage() {
  const table = useTableState();
  const [escalateModal, setEscalateModal] = useState(null);
  const [escalateForm] = Form.useForm();
  const queryClient = useQueryClient();

  const list = useQuery({ queryKey: ['supervisor-notifications', table.params], queryFn: () => api.supervisorNotifications.list(table.params) });

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ['supervisor-notifications'] });
  }

  const markRead = useMutation({
    mutationFn: (id) => api.supervisorNotifications.markRead(id),
    onSuccess: invalidate,
  });

  const markHandled = useMutation({
    mutationFn: (id) => api.supervisorNotifications.markHandled(id),
    onSuccess: () => { message.success('Notifikasi ditandai selesai.'); invalidate(); },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const escalate = useMutation({
    mutationFn: ({ id, values }) => api.supervisorNotifications.escalate(id, values),
    onSuccess: () => {
      message.success('Notifikasi berhasil dieskalasi.');
      setEscalateModal(null);
      invalidate();
    },
    onError: (error) => {
      escalateForm.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  return (
    <section>
      <PageHeader
        title="Pusat Notifikasi & Eskalasi"
        subtitle="Pantau kondisi yang memerlukan perhatian: PTP jatuh tempo, broken promise, kolektor tidak aktif, dan lainnya."
        breadcrumbs={[{ label: 'Supervisor' }, { label: 'Notifikasi & Eskalasi' }]}
        onRefresh={list.refetch}
      />

      <FilterBar>
        <Select
          allowClear
          placeholder="Prioritas"
          options={['low', 'normal', 'high', 'critical'].map((p) => ({ value: p, label: p }))}
          value={table.filters.priority}
          onChange={(value) => table.setFilters({ ...table.filters, priority: value })}
          className="filter-input"
        />
        <Select
          allowClear
          placeholder="Status Penanganan"
          options={['open', 'in_progress', 'handled', 'escalated'].map((s) => ({ value: s, label: s }))}
          value={table.filters.handled_status}
          onChange={(value) => table.setFilters({ ...table.filters, handled_status: value })}
          className="filter-input"
        />
        <Space>
          <span>Belum ditangani saja</span>
          <Switch checked={Boolean(table.filters.unhandled_only)} onChange={(checked) => table.setFilters({ ...table.filters, unhandled_only: checked || undefined })} />
        </Space>
      </FilterBar>

      <Card>
        <ResponsiveTable
          query={list}
          onChange={table.handleTableChange}
          scrollX={1100}
          columns={[
            { title: 'Prioritas', dataIndex: 'priority', width: 100, render: (v) => <Tag color={PRIORITY_COLORS[v]}>{v}</Tag> },
            { title: 'Kategori', dataIndex: 'category', width: 180 },
            { title: 'Judul', dataIndex: 'title' },
            { title: 'Status', dataIndex: 'handled_status', width: 120, render: (v) => <Tag color={HANDLED_COLORS[v]}>{v}</Tag> },
            { title: 'Dibuat', dataIndex: 'created_at', render: formatDateTime, width: 160 },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 220,
              render: (_, record) => (
                <Space>
                  {record.read_status === 'unread' && (
                    <Button size="small" onClick={() => markRead.mutate(record.id)}>Tandai Dibaca</Button>
                  )}
                  {record.handled_status !== 'handled' && (
                    <Can permission="supervisor-notifications.view">
                      <Button size="small" icon={<CheckOutlined />} onClick={() => markHandled.mutate(record.id)}>Selesai</Button>
                    </Can>
                  )}
                  <Can permission="supervisor-notifications.escalate">
                    <Button size="small" icon={<RiseOutlined />} onClick={() => { escalateForm.resetFields(); setEscalateModal(record); }}>Eskalasi</Button>
                  </Can>
                </Space>
              ),
            },
          ]}
        />
      </Card>

      <Modal
        title="Eskalasi Notifikasi"
        open={Boolean(escalateModal)}
        onCancel={() => setEscalateModal(null)}
        onOk={() => escalateForm.submit()}
        confirmLoading={escalate.isPending}
        destroyOnHidden
      >
        <Form form={escalateForm} layout="vertical" onFinish={(values) => escalate.mutate({ id: escalateModal.id, values })}>
          <Form.Item label="ID User Penerima Eskalasi" name="escalated_to" rules={[{ required: true, message: 'Wajib diisi' }]}>
            <InputNumber style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Catatan" name="note">
            <Input.TextArea rows={3} />
          </Form.Item>
        </Form>
      </Modal>
    </section>
  );
}
