import { Button, Card, Drawer, Form, Input, Modal, Select, Space, message } from 'antd';
import { CheckOutlined, CloseOutlined, PlusOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import Can from '../components/common/Can.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { api } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { formatDateTime } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';

export default function ReversalsPage() {
  const table = useTableState();
  const [open, setOpen] = useState(false);
  const [form] = Form.useForm();
  const [reviewForm] = Form.useForm();
  const queryClient = useQueryClient();
  const reversals = useQuery({ queryKey: ['reversals', table.params], queryFn: () => api.reversals.list(table.params) });
  const create = useMutation({
    mutationFn: api.reversals.create,
    onSuccess: () => {
      message.success('Reversal berhasil diajukan');
      setOpen(false);
      form.resetFields();
      queryClient.invalidateQueries({ queryKey: ['reversals'] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });
  const review = useMutation({
    mutationFn: ({ id, action, notes }) => action === 'approve' ? api.reversals.approve(id, { review_notes: notes }) : api.reversals.reject(id, { review_notes: notes }),
    onSuccess: () => {
      message.success('Reversal diperbarui');
      reviewForm.resetFields();
      queryClient.invalidateQueries({ queryKey: ['reversals'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  function confirmReview(row, action) {
    Modal.confirm({
      title: action === 'approve' ? 'Setujui reversal?' : 'Tolak reversal?',
      content: <Form form={reviewForm} layout="vertical"><Form.Item label="Catatan" name="notes" rules={action === 'reject' ? [{ required: true }] : []}><Input.TextArea rows={3} /></Form.Item></Form>,
      onOk: () => review.mutate({ id: row.id, action, notes: reviewForm.getFieldValue('notes') }),
    });
  }

  return (
    <section>
      <PageHeader title="Reversal" subtitle="Pengajuan dan approval pembatalan transaksi." breadcrumbs={[{ label: 'Reversal' }]} onRefresh={reversals.refetch} extra={<Can permission="reversals.submit"><Button type="primary" icon={<PlusOutlined />} onClick={() => setOpen(true)}>Ajukan Reversal</Button></Can>} />
      <FilterBar>
        <Select allowClear placeholder="Status" value={table.filters.status} onChange={(value) => table.setFilters({ ...table.filters, status: value })} className="filter-input" options={['pending', 'approved', 'rejected'].map((value) => ({ value, label: value }))} />
      </FilterBar>
      <Card>
        <ResponsiveTable
          query={reversals}
          onChange={table.handleTableChange}
          scrollX={1050}
          columns={[
            { title: 'Receipt', dataIndex: 'receipt_number', width: 190 },
            { title: 'Pelanggan', render: (_, row) => row.receipt?.unit?.customer?.name || row.receipt?.customer_name },
            { title: 'Alasan', dataIndex: 'reason' },
            { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge type="approval" value={value} /> },
            { title: 'Diajukan', dataIndex: 'submitted_at', render: formatDateTime },
            {
              title: 'Aksi',
              width: 210,
              render: (_, row) => (
                <Can permission="reversals.approve">
                  <Space>
                    <Button size="small" icon={<CheckOutlined />} disabled={row.status !== 'pending'} onClick={() => confirmReview(row, 'approve')}>Setujui</Button>
                    <Button size="small" danger icon={<CloseOutlined />} disabled={row.status !== 'pending'} onClick={() => confirmReview(row, 'reject')}>Tolak</Button>
                  </Space>
                </Can>
              ),
            },
          ]}
        />
      </Card>
      <Drawer title="Ajukan Reversal" open={open} onClose={() => setOpen(false)} width={520} extra={<Button type="primary" loading={create.isPending} onClick={() => form.submit()}>Ajukan</Button>} destroyOnHidden>
        <Form form={form} layout="vertical" onFinish={create.mutate}>
          <Form.Item label="Nomor Receipt" name="receipt_number" rules={[{ required: true }]}><Input placeholder="GD.2026.06.000001" /></Form.Item>
          <Form.Item label="Alasan" name="reason" rules={[{ required: true }]}><Input.TextArea rows={4} /></Form.Item>
        </Form>
      </Drawer>
    </section>
  );
}
