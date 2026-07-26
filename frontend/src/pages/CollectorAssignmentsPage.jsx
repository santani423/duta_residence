import { Button, Card, DatePicker, Form, Input, Modal, Select, Space, Switch, Tag, message } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined, SwapOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import dayjs from 'dayjs';
import PageHeader from '../components/common/PageHeader.jsx';
import Can from '../components/common/Can.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import { useTableState } from '../hooks/useTableState.js';
import { useDebounce } from '../hooks/useDebounce.js';
import { api } from '../services/estateApi.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';

const PRIORITY_OPTIONS = [
  { value: 'low', label: 'Rendah' },
  { value: 'normal', label: 'Normal' },
  { value: 'high', label: 'Tinggi' },
  { value: 'urgent', label: 'Mendesak' },
];

const SCOPE_LABELS = {
  cluster: 'Cluster',
  block: 'Blok',
  unit: 'Unit',
  resident: 'Penghuni',
};

function scopeSummary(record) {
  if (record.scope_type === 'cluster') return `Cluster ${record.cluster?.name || record.cluster_id}`;
  if (record.scope_type === 'block') return `${record.cluster?.name || record.cluster_id} — Blok ${record.block}`;
  if (record.scope_type === 'unit') return `Unit ${record.unit_id}`;
  if (record.scope_type === 'resident') return `Penghuni ${record.resident?.name || record.resident_id}`;
  return '-';
}

export default function CollectorAssignmentsPage() {
  const table = useTableState();
  const [modal, setModal] = useState({ mode: null, record: null });
  const [reassignModal, setReassignModal] = useState(null);
  const [form] = Form.useForm();
  const [reassignForm] = Form.useForm();
  const scopeType = Form.useWatch('scope_type', form);
  const queryClient = useQueryClient();

  const list = useQuery({ queryKey: ['collector-assignments', table.params], queryFn: () => api.collectorAssignments.list(table.params) });
  const collectors = useQuery({ queryKey: ['users', 'collector'], queryFn: () => api.users.list({ role: 'collector', per_page: 100 }) });
  const clusters = useQuery({ queryKey: ['clusters'], queryFn: () => api.clusters.list() });

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ['collector-assignments'] });
  }

  const save = useMutation({
    mutationFn: (values) => {
      const payload = {
        ...values,
        start_date: values.start_date ? values.start_date.format('YYYY-MM-DD') : undefined,
        end_date: values.end_date ? values.end_date.format('YYYY-MM-DD') : undefined,
      };
      return modal.mode === 'edit' ? api.collectorAssignments.update(modal.record.id, payload) : api.collectorAssignments.create(payload);
    },
    onSuccess: () => {
      message.success('Penugasan kolektor berhasil disimpan.');
      setModal({ mode: null, record: null });
      invalidate();
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const remove = useMutation({
    mutationFn: (id) => api.collectorAssignments.remove(id),
    onSuccess: () => {
      message.success('Penugasan kolektor berhasil dicabut.');
      invalidate();
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const reassign = useMutation({
    mutationFn: ({ id, values }) => api.collectorAssignments.reassign(id, values),
    onSuccess: () => {
      message.success('Penugasan berhasil dipindahkan.');
      setReassignModal(null);
      invalidate();
    },
    onError: (error) => {
      reassignForm.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  function openCreate() {
    form.resetFields();
    form.setFieldsValue({ is_active: true, priority: 'normal' });
    setModal({ mode: 'create', record: null });
  }

  function openEdit(record) {
    form.setFieldsValue({
      ...record,
      start_date: record.start_date ? dayjs(record.start_date) : undefined,
      end_date: record.end_date ? dayjs(record.end_date) : undefined,
    });
    setModal({ mode: 'edit', record });
  }

  function openReassign(record) {
    reassignForm.resetFields();
    setReassignModal(record);
  }

  const collectorOptions = (collectors.data?.data?.data || collectors.data?.data || []).map((user) => ({ value: user.id, label: user.name }));
  const clusterOptions = (clusters.data?.data || []).map((cluster) => ({ value: cluster.id, label: `${cluster.id} - ${cluster.name}` }));

  return (
    <section>
      <PageHeader
        title="Penugasan Kolektor"
        subtitle="Tentukan cluster, blok, unit, atau penghuni tertentu yang menjadi wilayah kerja setiap kolektor."
        breadcrumbs={[{ label: 'Manajemen Kolektor' }, { label: 'Penugasan' }]}
        onRefresh={list.refetch}
        extra={
          <Can permission="collector-assignments.assign">
            <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Tambah Penugasan</Button>
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
          placeholder="Filter aktif/nonaktif"
          style={{ minWidth: 160 }}
          options={[{ value: true, label: 'Aktif' }, { value: false, label: 'Nonaktif' }]}
          value={table.filters.is_active}
          onChange={(value) => table.setFilters({ ...table.filters, is_active: value })}
        />
        <Select
          allowClear
          placeholder="Filter status penugasan"
          style={{ minWidth: 180 }}
          options={[
            { value: 'active', label: 'Aktif' },
            { value: 'completed', label: 'Selesai' },
            { value: 'cancelled', label: 'Dibatalkan' },
            { value: 'transferred', label: 'Dipindahkan' },
          ]}
          value={table.filters.status}
          onChange={(value) => table.setFilters({ ...table.filters, status: value })}
        />
      </FilterBar>

      <Card>
        <ResponsiveTable
          query={list}
          onChange={table.handleTableChange}
          columns={[
            { title: 'Kolektor', dataIndex: ['collector', 'name'] },
            { title: 'Cakupan', render: (_, record) => <Tag>{SCOPE_LABELS[record.scope_type]}</Tag> },
            { title: 'Detail Cakupan', render: (_, record) => scopeSummary(record) },
            { title: 'Prioritas', dataIndex: 'priority', render: (value) => PRIORITY_OPTIONS.find((o) => o.value === value)?.label || value },
            { title: 'Periode', render: (_, record) => `${record.start_date || '-'} s/d ${record.end_date || 'sekarang'}` },
            { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge type="assignmentStatus" value={value} /> },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 180,
              render: (_, record) => (
                <Space>
                  <Can permission="collector-assignments.update">
                    <Button size="small" icon={<EditOutlined />} onClick={() => openEdit(record)} />
                  </Can>
                  <Can permission="collector.reassign">
                    <Button size="small" icon={<SwapOutlined />} onClick={() => openReassign(record)} disabled={!record.is_active} title="Pindahkan" />
                  </Can>
                  <Can permission="collector-assignments.delete">
                    <Button
                      size="small"
                      danger
                      icon={<DeleteOutlined />}
                      onClick={() => Modal.confirm({
                        title: 'Cabut penugasan ini?',
                        content: scopeSummary(record),
                        okText: 'Cabut',
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
        title={modal.mode === 'edit' ? 'Edit Penugasan Kolektor' : 'Tambah Penugasan Kolektor'}
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
          <Form.Item label="Jenis Cakupan" name="scope_type" rules={[{ required: true, message: 'Pilih jenis cakupan' }]}>
            <Select
              options={[
                { value: 'cluster', label: 'Seluruh Cluster' },
                { value: 'block', label: 'Blok Tertentu' },
                { value: 'unit', label: 'Unit Tertentu' },
                { value: 'resident', label: 'Penghuni Tertentu (mengikuti orangnya, bukan alamat)' },
              ]}
            />
          </Form.Item>
          {(scopeType === 'cluster' || scopeType === 'block') && (
            <Form.Item label="Cluster" name="cluster_id" rules={[{ required: true, message: 'Pilih cluster' }]}>
              <Select showSearch optionFilterProp="label" options={clusterOptions} loading={clusters.isLoading} />
            </Form.Item>
          )}
          {scopeType === 'block' && (
            <Form.Item label="Kode Blok" name="block" rules={[{ required: true, message: 'Isi kode blok' }]}>
              <Select showSearch mode="tags" maxCount={1} placeholder="Contoh: A" />
            </Form.Item>
          )}
          {scopeType === 'unit' && (
            <RemoteUnitField />
          )}
          {scopeType === 'resident' && (
            <RemoteResidentField />
          )}
          <Form.Item label="Prioritas" name="priority">
            <Select options={PRIORITY_OPTIONS} />
          </Form.Item>
          <Space style={{ width: '100%' }} size="middle">
            <Form.Item label="Tanggal Mulai" name="start_date" style={{ width: 180 }}>
              <DatePicker style={{ width: '100%' }} />
            </Form.Item>
            <Form.Item label="Tanggal Selesai (opsional)" name="end_date" style={{ width: 180 }}>
              <DatePicker style={{ width: '100%' }} />
            </Form.Item>
          </Space>
          <Form.Item label="Catatan Penugasan" name="notes">
            <Input.TextArea rows={2} />
          </Form.Item>
          <Form.Item label="Aktif" name="is_active" valuePropName="checked" initialValue={true}>
            <Switch />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title="Pindahkan Penugasan"
        open={Boolean(reassignModal)}
        onCancel={() => setReassignModal(null)}
        onOk={() => reassignForm.submit()}
        confirmLoading={reassign.isPending}
        destroyOnHidden
      >
        {reassignModal ? (
          <p>
            Memindahkan <strong>{scopeSummary(reassignModal)}</strong> dari kolektor <strong>{reassignModal.collector?.name}</strong> ke kolektor lain.
          </p>
        ) : null}
        <Form
          form={reassignForm}
          layout="vertical"
          onFinish={(values) => reassign.mutate({ id: reassignModal.id, values })}
        >
          <Form.Item label="Kolektor Tujuan" name="new_collector_id" rules={[{ required: true, message: 'Pilih kolektor tujuan' }]}>
            <Select
              showSearch
              optionFilterProp="label"
              options={collectorOptions.filter((option) => option.value !== reassignModal?.collector_id)}
            />
          </Form.Item>
          <Form.Item label="Catatan" name="notes">
            <Input.TextArea rows={2} />
          </Form.Item>
        </Form>
      </Modal>
    </section>
  );
}

function RemoteUnitField() {
  const [search, setSearch] = useState('');
  const debounced = useDebounce(search);
  const units = useQuery({ queryKey: ['units', 'search', debounced], queryFn: () => api.units.list({ search: debounced || undefined, per_page: 20 }) });
  const options = (units.data?.data || []).map((unit) => ({ value: unit.id, label: `${unit.id} — ${unit.cluster?.name || ''} ${unit.block}-${unit.lot_number}` }));

  return (
    <Form.Item label="Unit" name="unit_id" rules={[{ required: true, message: 'Pilih unit' }]}>
      <Select showSearch filterOption={false} onSearch={setSearch} options={options} loading={units.isFetching} notFoundContent={units.isFetching ? 'Mencari...' : 'Tidak ditemukan'} />
    </Form.Item>
  );
}

function RemoteResidentField() {
  const [search, setSearch] = useState('');
  const debounced = useDebounce(search);
  const residents = useQuery({ queryKey: ['residents', 'search', debounced], queryFn: () => api.residents.list({ search: debounced || undefined, per_page: 20 }) });
  const options = (residents.data?.data || []).map((resident) => ({ value: resident.id, label: `${resident.id} — ${resident.name}` }));

  return (
    <Form.Item label="Penghuni" name="resident_id" rules={[{ required: true, message: 'Pilih penghuni' }]}>
      <Select showSearch filterOption={false} onSearch={setSearch} options={options} loading={residents.isFetching} notFoundContent={residents.isFetching ? 'Mencari...' : 'Tidak ditemukan'} />
    </Form.Item>
  );
}
