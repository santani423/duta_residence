import { Button, Card, DatePicker, Drawer, Form, Input, InputNumber, message } from 'antd';
import { PlusOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import dayjs from 'dayjs';
import { useState } from 'react';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import Can from '../components/common/Can.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { api } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { formatCurrency, formatDate } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';

export default function InstallmentsPage() {
  const table = useTableState();
  const [open, setOpen] = useState(false);
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const installments = useQuery({ queryKey: ['installments', table.params], queryFn: () => api.installments.list(table.params) });
  const create = useMutation({
    mutationFn: (values) => api.installments.create({ ...values, payment_date: values.payment_date.format('YYYY-MM-DD') }),
    onSuccess: () => {
      message.success('Cicilan berhasil dicatat');
      setOpen(false);
      form.resetFields();
      queryClient.invalidateQueries({ queryKey: ['installments'] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  return (
    <section>
      <PageHeader title="Cicilan" subtitle="Pencatatan pembayaran cicilan pelanggan." breadcrumbs={[{ label: 'Cicilan' }]} onRefresh={installments.refetch} extra={<Can permission="installments.create"><Button type="primary" icon={<PlusOutlined />} onClick={() => setOpen(true)}>Tambah Cicilan</Button></Can>} />
      <FilterBar>
        <Input allowClear placeholder="ID pelanggan" value={table.filters.customer_id} onChange={(event) => table.setFilters({ ...table.filters, customer_id: event.target.value || undefined })} className="filter-input" />
      </FilterBar>
      <Card>
        <ResponsiveTable
          query={installments}
          onChange={table.handleTableChange}
          columns={[
            { title: 'Pelanggan', dataIndex: ['customer', 'name'] },
            { title: 'Cluster', dataIndex: ['customer', 'cluster', 'name'] },
            { title: 'Tanggal', dataIndex: 'payment_date', render: formatDate },
            { title: 'Nominal', dataIndex: 'amount', render: formatCurrency },
            { title: 'Alokasi', dataIndex: 'allocated_to' },
            { title: 'Catatan', dataIndex: 'notes' },
          ]}
        />
      </Card>
      <Drawer title="Tambah Cicilan" open={open} onClose={() => setOpen(false)} width={520} extra={<Button type="primary" loading={create.isPending} onClick={() => form.submit()}>Simpan</Button>} destroyOnHidden>
        <Form form={form} layout="vertical" onFinish={create.mutate} initialValues={{ payment_date: dayjs() }}>
          <Form.Item label="ID Pelanggan" name="customer_id" rules={[{ required: true }]}><Input placeholder="GA001" /></Form.Item>
          <Form.Item label="Nominal" name="amount" rules={[{ required: true }]}><InputNumber min={1} style={{ width: '100%' }} /></Form.Item>
          <Form.Item label="Tanggal Bayar" name="payment_date" rules={[{ required: true }]}><DatePicker style={{ width: '100%' }} /></Form.Item>
          <Form.Item label="Alokasi" name="allocated_to"><Input placeholder="Jan 2026, Feb 2026" /></Form.Item>
          <Form.Item label="Catatan" name="notes"><Input.TextArea rows={3} /></Form.Item>
        </Form>
      </Drawer>
    </section>
  );
}
