import { Button, Card, DatePicker, Form, InputNumber, Modal, Select, Space, Tag, message } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import dayjs from 'dayjs';
import PageHeader from '../components/common/PageHeader.jsx';
import Can from '../components/common/Can.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { useTableState } from '../hooks/useTableState.js';
import { api } from '../services/estateApi.js';
import { formatCurrency } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';

const PERIOD_LABELS = { daily: 'Harian', weekly: 'Mingguan', monthly: 'Bulanan' };

export default function CollectorTargetsPage() {
  const table = useTableState();
  const [modal, setModal] = useState({ mode: null, record: null });
  const [form] = Form.useForm();
  const queryClient = useQueryClient();

  const list = useQuery({ queryKey: ['collector-targets', table.params], queryFn: () => api.collectorTargets.list(table.params) });
  const collectors = useQuery({ queryKey: ['users', 'collector'], queryFn: () => api.users.list({ role: 'collector', per_page: 100 }) });
  const collectorOptions = (collectors.data?.data?.data || collectors.data?.data || []).map((user) => ({ value: user.id, label: user.name }));

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ['collector-targets'] });
  }

  const save = useMutation({
    mutationFn: (values) => {
      const payload = { ...values, period_start: values.period_start.format('YYYY-MM-DD') };
      return modal.mode === 'edit' ? api.collectorTargets.update(modal.record.id, payload) : api.collectorTargets.create(payload);
    },
    onSuccess: () => {
      message.success('Target kolektor berhasil disimpan.');
      setModal({ mode: null, record: null });
      invalidate();
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const remove = useMutation({
    mutationFn: (id) => api.collectorTargets.remove(id),
    onSuccess: () => {
      message.success('Target kolektor berhasil dihapus.');
      invalidate();
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  function openCreate() {
    form.resetFields();
    setModal({ mode: 'create', record: null });
  }

  function openEdit(record) {
    form.setFieldsValue({ ...record, period_start: dayjs(record.period_start) });
    setModal({ mode: 'edit', record });
  }

  return (
    <section>
      <PageHeader
        title="Target Kolektor"
        subtitle="Tetapkan target penagihan harian, mingguan, atau bulanan per kolektor."
        breadcrumbs={[{ label: 'Manajemen Kolektor' }, { label: 'Target' }]}
        onRefresh={list.refetch}
        extra={
          <Can permission="collector-targets.create">
            <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Tambah Target</Button>
          </Can>
        }
      />

      <FilterBar>
        <Select
          allowClear
          placeholder="Filter kolektor"
          style={{ minWidth: 220 }}
          options={collectorOptions}
          value={table.filters.collector_id}
          onChange={(value) => table.setFilters({ ...table.filters, collector_id: value })}
        />
        <Select
          allowClear
          placeholder="Filter periode"
          style={{ minWidth: 160 }}
          options={Object.entries(PERIOD_LABELS).map(([value, label]) => ({ value, label }))}
          value={table.filters.period_type}
          onChange={(value) => table.setFilters({ ...table.filters, period_type: value })}
        />
      </FilterBar>

      <Card>
        <ResponsiveTable
          query={list}
          onChange={table.handleTableChange}
          columns={[
            { title: 'Kolektor', dataIndex: ['collector', 'name'] },
            { title: 'Periode', render: (_, record) => <Tag>{PERIOD_LABELS[record.period_type]}</Tag> },
            { title: 'Mulai', dataIndex: 'period_start' },
            { title: 'Target Nominal', dataIndex: 'target_amount', render: formatCurrency },
            { title: 'Target Kunjungan', dataIndex: 'target_visit_count', render: (value) => value ?? '-' },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 120,
              render: (_, record) => (
                <Space>
                  <Can permission="collector-targets.update">
                    <Button size="small" icon={<EditOutlined />} onClick={() => openEdit(record)} />
                  </Can>
                  <Can permission="collector-targets.delete">
                    <Button
                      size="small"
                      danger
                      icon={<DeleteOutlined />}
                      onClick={() => Modal.confirm({
                        title: 'Hapus target ini?',
                        okText: 'Hapus',
                        okButtonProps: { danger: true },
                        onOk: () => remove.mutate(record.id),
                      })}
                    />
                  </Can>
                </Space>
              ),
            },
          ]}
        />
      </Card>

      <Modal
        title={modal.mode === 'edit' ? 'Edit Target Kolektor' : 'Tambah Target Kolektor'}
        open={modal.mode === 'create' || modal.mode === 'edit'}
        onCancel={() => setModal({ mode: null, record: null })}
        onOk={() => form.submit()}
        confirmLoading={save.isPending}
        destroyOnHidden
      >
        <Form form={form} layout="vertical" onFinish={save.mutate}>
          <Form.Item label="Kolektor" name="collector_id" rules={[{ required: true, message: 'Pilih kolektor' }]}>
            <Select showSearch optionFilterProp="label" options={collectorOptions} loading={collectors.isLoading} />
          </Form.Item>
          <Form.Item label="Jenis Periode" name="period_type" rules={[{ required: true, message: 'Pilih jenis periode' }]}>
            <Select options={Object.entries(PERIOD_LABELS).map(([value, label]) => ({ value, label }))} />
          </Form.Item>
          <Form.Item label="Tanggal Mulai Periode" name="period_start" rules={[{ required: true, message: 'Pilih tanggal mulai' }]}>
            <DatePicker style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Target Nominal (Rp)" name="target_amount" rules={[{ required: true, message: 'Isi target nominal' }]}>
            <InputNumber min={0} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Target Jumlah Kunjungan (opsional)" name="target_visit_count">
            <InputNumber min={0} style={{ width: '100%' }} />
          </Form.Item>
        </Form>
      </Modal>
    </section>
  );
}
