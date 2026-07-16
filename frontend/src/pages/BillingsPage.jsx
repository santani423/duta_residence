import { Button, Card, DatePicker, Drawer, Form, Input, InputNumber, Modal, Select, Space, Tabs, message } from 'antd';
import { CheckOutlined, PlusOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import dayjs from 'dayjs';
import { useState } from 'react';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import Can from '../components/common/Can.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { api } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { formatCurrency, formatDateTime, formatPeriod } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';

export default function BillingsPage() {
  const table = useTableState({ year: dayjs().year() });
  const [drawer, setDrawer] = useState(null);
  const [selected, setSelected] = useState([]);
  const [form] = Form.useForm();
  const [approveForm] = Form.useForm();
  const queryClient = useQueryClient();

  const billings = useQuery({ queryKey: ['billings', table.params], queryFn: () => api.billings.list(table.params) });
  const clusters = useQuery({ queryKey: ['clusters'], queryFn: () => api.clusters.list() });

  const monthly = useMutation({
    mutationFn: ({ period }) => api.billings.prepareMonthly({ year: period.year(), month: period.month() + 1 }),
    onSuccess: (response) => {
      message.success(response.message || 'Tagihan bulanan berhasil disiapkan');
      setDrawer(null);
      queryClient.invalidateQueries({ queryKey: ['billings'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const special = useMutation({
    mutationFn: (values) => api.billings.prepareSpecial({
      unit_id: values.unit_id,
      year: values.period.year(),
      month: values.period.month() + 1,
      amount: values.amount,
    }),
    onSuccess: () => {
      message.success('Tagihan khusus berhasil dibuat');
      setDrawer(null);
      queryClient.invalidateQueries({ queryKey: ['billings'] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const back = useMutation({
    mutationFn: (values) => api.billings.prepareBack({
      unit_id: values.unit_id,
      periods: values.periods.map((item) => ({
        year: item.period.year(),
        month: item.period.month() + 1,
        amount: item.amount,
      })),
    }),
    onSuccess: () => {
      message.success('Tagihan mundur berhasil dibuat');
      setDrawer(null);
      queryClient.invalidateQueries({ queryKey: ['billings'] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const approve = useMutation({
    mutationFn: ({ ids, notes }) => ids.length === 1
      ? api.billings.approve(ids[0], { approval_notes: notes })
      : api.billings.approveBatch({ billing_ids: ids, approval_notes: notes }),
    onSuccess: () => {
      message.success('Tagihan berhasil disetujui');
      setSelected([]);
      approveForm.resetFields();
      queryClient.invalidateQueries({ queryKey: ['billings'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const columns = [
    { title: 'ID', dataIndex: 'id', width: 80, fixed: 'left' },
    { title: 'Penghuni', dataIndex: ['unit', 'resident', 'name'], width: 220 },
    { title: 'Cluster', dataIndex: ['unit', 'cluster', 'name'], width: 140 },
    { title: 'Periode', render: (_, row) => formatPeriod(row.year, row.month), width: 140 },
    { title: 'Tipe', dataIndex: 'billing_type', width: 100 },
    { title: 'Nominal', dataIndex: 'amount', render: formatCurrency, width: 140 },
    { title: 'Umur Tunggakan', render: (_, row) => `${row.penalty_detail?.overdue_months ?? 0} bulan`, width: 130 },
    { title: 'Denda', render: (_, row) => formatCurrency(row.penalty_detail?.penalty_amount ?? 0), width: 130 },
    { title: 'Total', render: (_, row) => formatCurrency(row.penalty_detail?.total_amount ?? row.amount), width: 140 },
    { title: 'Sisa Tagihan', render: (_, row) => formatCurrency(row.penalty_detail?.total_outstanding ?? 0), width: 140 },
    { title: 'Status', dataIndex: 'status_id', render: (value) => <StatusBadge type="billing" value={value} />, width: 120 },
    { title: 'Approval', render: (_, row) => <StatusBadge type="approval" value={row.approved_at ? 'approved' : 'pending'} />, width: 130 },
    { title: 'Approved At', dataIndex: 'approved_at', render: formatDateTime, width: 170 },
    {
      title: 'Aksi',
      fixed: 'right',
      width: 120,
      render: (_, row) => (
        <Can permission="billings.approve">
          <Button
            size="small"
            icon={<CheckOutlined />}
            disabled={Boolean(row.approved_at)}
            onClick={() => approve.mutate({ ids: [row.id], notes: null })}
            loading={approve.isPending}
          >
            Approve
          </Button>
        </Can>
      ),
    },
  ];

  return (
    <section>
      <PageHeader
        title="Tagihan"
        subtitle="Generate, filter, dan approval tagihan estate."
        breadcrumbs={[{ label: 'Tagihan' }]}
        onRefresh={billings.refetch}
        loading={billings.isFetching}
        extra={
          <Space wrap>
            <Can permission="billings.prepare"><Button icon={<PlusOutlined />} onClick={() => setDrawer('monthly')}>Generate Bulanan</Button></Can>
            <Can permission="billings.prepare-special"><Button onClick={() => setDrawer('special')}>Tagihan Khusus</Button></Can>
            <Can permission="billings.prepare-back"><Button onClick={() => setDrawer('back')}>Tagihan Mundur</Button></Can>
          </Space>
        }
      />

      <FilterBar
        extra={
          <Can permission="billings.approve">
            <Button type="primary" icon={<CheckOutlined />} disabled={!selected.length} onClick={() => approve.mutate({ ids: selected, notes: approveForm.getFieldValue('approval_notes') })}>
              Approve Terpilih
            </Button>
          </Can>
        }
      >
        <Input allowClear placeholder="ID unit" value={table.filters.unit_id} onChange={(event) => table.setFilters({ ...table.filters, unit_id: event.target.value || undefined })} className="filter-input" />
        <Select allowClear placeholder="Cluster" options={(clusters.data?.data || []).map((item) => ({ value: item.id, label: item.name }))} value={table.filters.cluster_id} onChange={(value) => table.setFilters({ ...table.filters, cluster_id: value })} className="filter-input" />
        <InputNumber placeholder="Tahun" value={table.filters.year} onChange={(value) => table.setFilters({ ...table.filters, year: value })} className="filter-input" />
        <Select allowClear placeholder="Bulan" value={table.filters.month} onChange={(value) => table.setFilters({ ...table.filters, month: value })} className="filter-input" options={Array.from({ length: 12 }, (_, index) => ({ value: index + 1, label: dayjs().month(index).format('MMMM') }))} />
        <Select allowClear placeholder="Status" value={table.filters.status_id} onChange={(value) => table.setFilters({ ...table.filters, status_id: value })} className="filter-input" options={[{ value: '01', label: 'Belum Bayar' }, { value: '02', label: 'Lunas' }, { value: '03', label: 'Sebagian' }, { value: '04', label: 'Dibatalkan' }]} />
      </FilterBar>

      <Card>
        <Tabs
          items={[
            {
              key: 'all',
              label: 'Semua Tagihan',
              children: (
                <ResponsiveTable
                  query={billings}
                  columns={columns}
                  onChange={table.handleTableChange}
                  rowSelection={{ selectedRowKeys: selected, onChange: setSelected, getCheckboxProps: (record) => ({ disabled: Boolean(record.approved_at) }) }}
                  scrollX={1730}
                />
              ),
            },
          ]}
        />
      </Card>

      <Drawer title="Generate Tagihan Bulanan" open={drawer === 'monthly'} onClose={() => setDrawer(null)} width={460} extra={<Button type="primary" onClick={() => form.submit()} loading={monthly.isPending}>Generate</Button>} destroyOnHidden>
        <Form form={form} layout="vertical" onFinish={monthly.mutate} initialValues={{ period: dayjs() }}>
          <Form.Item label="Periode" name="period" rules={[{ required: true }]}>
            <DatePicker picker="month" style={{ width: '100%' }} />
          </Form.Item>
        </Form>
      </Drawer>

      <Drawer title={drawer === 'special' ? 'Tagihan Khusus' : 'Tagihan Mundur'} open={drawer === 'special' || drawer === 'back'} onClose={() => setDrawer(null)} width={620} extra={<Button type="primary" onClick={() => form.submit()} loading={special.isPending || back.isPending}>Simpan</Button>} destroyOnHidden>
        {drawer === 'special' ? (
          <Form form={form} layout="vertical" onFinish={special.mutate}>
            <Form.Item label="ID Unit" name="unit_id" rules={[{ required: true }]}><Input placeholder="GA001" /></Form.Item>
            <Form.Item label="Periode" name="period" rules={[{ required: true }]}>
              <DatePicker picker="month" style={{ width: '100%' }} />
            </Form.Item>
            <Form.Item label="Nominal" name="amount" rules={[{ required: true }]}><InputNumber min={0} style={{ width: '100%' }} /></Form.Item>
          </Form>
        ) : (
          <Form form={form} layout="vertical" onFinish={back.mutate} initialValues={{ periods: [{ period: dayjs(), amount: 0 }] }}>
            <Form.Item label="ID Unit" name="unit_id" rules={[{ required: true }]}><Input placeholder="GA001" /></Form.Item>
            <Form.List name="periods">
              {(fields, { add, remove }) => (
                <>
                  {fields.map((field) => (
                    <Space key={field.key} align="start" className="period-row">
                      <Form.Item {...field} label="Periode" name={[field.name, 'period']} rules={[{ required: true }]}><DatePicker picker="month" /></Form.Item>
                      <Form.Item {...field} label="Nominal" name={[field.name, 'amount']} rules={[{ required: true }]}><InputNumber min={0} /></Form.Item>
                      <Button danger onClick={() => remove(field.name)}>Hapus</Button>
                    </Space>
                  ))}
                  <Button onClick={() => add({ period: dayjs(), amount: 0 })}>Tambah Periode</Button>
                </>
              )}
            </Form.List>
          </Form>
        )}
      </Drawer>
    </section>
  );
}
