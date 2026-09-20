import { Button, Card, Form, Input, InputNumber, Modal, Select, Space, Switch, message } from 'antd';
import { DeleteOutlined, EditOutlined, EyeOutlined, PlusOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import PageHeader from '../components/common/PageHeader.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import Can from '../components/common/Can.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { api } from '../services/estateApi.js';
import { formatCurrency } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';

export default function ClustersPage() {
  const [modal, setModal] = useState({ mode: null, record: null });
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const [filters, setFilters] = useState({ code: '', name: '', status: undefined });
  const clusters = useQuery({ queryKey: ['clusters'], queryFn: () => api.clusters.list() });

  const filteredClusters = useMemo(() => {
    const code = filters.code.trim().toLowerCase();
    const name = filters.name.trim().toLowerCase();
    return (clusters.data?.data || []).filter((item) => (
      (!code || String(item.id).toLowerCase().includes(code))
      && (!name || String(item.name || '').toLowerCase().includes(name))
      && (filters.status === undefined || Boolean(item.is_active) === filters.status)
    ));
  }, [clusters.data, filters]);

  const create = useMutation({
    mutationFn: (values) => api.clusters.create(values),
    onSuccess: () => {
      message.success('Cluster berhasil dibuat');
      setModal({ mode: null, record: null });
      queryClient.invalidateQueries({ queryKey: ['clusters'] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const update = useMutation({
    mutationFn: (values) => api.clusters.update(modal.record.id, values),
    onSuccess: () => {
      message.success('Cluster berhasil diperbarui');
      setModal({ mode: null, record: null });
      queryClient.invalidateQueries({ queryKey: ['clusters'] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const remove = useMutation({
    mutationFn: (id) => api.clusters.remove(id),
    onSuccess: () => {
      message.success('Cluster berhasil dihapus');
      queryClient.invalidateQueries({ queryKey: ['clusters'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  function openCreate() {
    form.resetFields();
    setModal({ mode: 'create', record: null });
  }

  function openEdit(record) {
    form.setFieldsValue(record);
    setModal({ mode: 'edit', record });
  }

  function confirmDelete(record) {
    Modal.confirm({
      title: 'Hapus cluster?',
      content: `${record.id} - ${record.name}`,
      okText: 'Hapus',
      okButtonProps: { danger: true },
      onOk: () => remove.mutate(record.id),
    });
  }

  return (
    <section>
      <PageHeader
        title="Cluster"
        subtitle="Kelola cluster dan tarif iuran bulanan."
        breadcrumbs={[{ label: 'Cluster' }]}
        onRefresh={clusters.refetch}
        loading={clusters.isFetching}
        extra={<Can permission="clusters.create"><Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Tambah Cluster</Button></Can>}
      />
      <FilterBar>
        <Input allowClear placeholder="Kode" value={filters.code} onChange={(event) => setFilters({ ...filters, code: event.target.value })} className="filter-input" />
        <Input allowClear placeholder="Nama Cluster" value={filters.name} onChange={(event) => setFilters({ ...filters, name: event.target.value })} className="filter-input" />
        <Select
          allowClear
          placeholder="Status"
          value={filters.status}
          onChange={(value) => setFilters({ ...filters, status: value })}
          options={[
            { value: true, label: 'Aktif' },
            { value: false, label: 'Nonaktif' },
          ]}
          className="filter-input"
        />
      </FilterBar>
      <Card>
        <ResponsiveTable
          query={clusters}
          data={filteredClusters}
          pagination={{ defaultPageSize: 10, showSizeChanger: true, showTotal: (total) => `${total} data` }}
          scrollX={960}
          columns={[
            { title: 'Kode', dataIndex: 'id', width: 90 },
            { title: 'Nama Cluster', dataIndex: 'name' },
            { title: 'Tarif Bulanan', dataIndex: 'monthly_rate', render: formatCurrency },
            { title: 'Status', dataIndex: 'is_active', render: (value) => <StatusBadge type="active" value={value} /> },
            { title: 'Deskripsi', dataIndex: 'description' },
            {
              title: 'Aksi',
              width: 260,
              render: (_, record) => (
                <Space>
                  <Button size="small" icon={<EyeOutlined />} onClick={() => navigate(`/clusters/${record.id}`)}>Detail</Button>
                  <Can permission="clusters.update-rate">
                    <Button size="small" icon={<EditOutlined />} onClick={() => openEdit(record)}>Edit</Button>
                  </Can>
                  <Can permission="clusters.delete">
                    <Button size="small" danger icon={<DeleteOutlined />} onClick={() => confirmDelete(record)} />
                  </Can>
                </Space>
              ),
            },
          ]}
        />
      </Card>
      <Modal
        title={modal.mode === 'edit' ? `Edit Cluster ${modal.record?.name || ''}` : 'Tambah Cluster'}
        open={modal.mode === 'create' || modal.mode === 'edit'}
        onCancel={() => setModal({ mode: null, record: null })}
        onOk={() => form.submit()}
        confirmLoading={create.isPending || update.isPending}
        destroyOnHidden
      >
        <Form form={form} layout="vertical" onFinish={(values) => (modal.mode === 'edit' ? update.mutate(values) : create.mutate(values))}>
          {modal.mode === 'create' && (
            <>
              <Form.Item label="Kode Cluster" name="id" rules={[{ required: true }, { max: 2, message: 'Maksimal 2 karakter' }]}>
                <Input maxLength={2} style={{ textTransform: 'uppercase' }} />
              </Form.Item>
              <Form.Item label="Nama Cluster" name="name" rules={[{ required: true }]}>
                <Input maxLength={50} />
              </Form.Item>
            </>
          )}
          <Form.Item label="Tarif Bulanan" name="monthly_rate" rules={[{ required: true }]}>
            <InputNumber min={0} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Deskripsi" name="description">
            <Input.TextArea rows={3} />
          </Form.Item>
          <Form.Item label="Aktif" name="is_active" valuePropName="checked" initialValue={true}>
            <Switch />
          </Form.Item>
        </Form>
      </Modal>
    </section>
  );
}
