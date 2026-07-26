import { Button, Card, DatePicker, Form, Input, Modal, Select, Space, Switch, message } from 'antd';
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
import { api } from '../services/estateApi.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';

export default function SupervisorAssignmentsPage() {
  const table = useTableState();
  const [modal, setModal] = useState({ mode: null, record: null });
  const [reassignModal, setReassignModal] = useState(null);
  const [form] = Form.useForm();
  const [reassignForm] = Form.useForm();
  const queryClient = useQueryClient();

  const list = useQuery({ queryKey: ['supervisor-assignments', table.params], queryFn: () => api.supervisorAssignments.list(table.params) });
  const supervisors = useQuery({ queryKey: ['supervisors', 'lookup'], queryFn: () => api.supervisors.list({ per_page: 100 }) });
  const clusters = useQuery({ queryKey: ['clusters'], queryFn: () => api.clusters.list() });

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ['supervisor-assignments'] });
  }

  const save = useMutation({
    mutationFn: (values) => {
      const payload = {
        ...values,
        start_date: values.start_date ? values.start_date.format('YYYY-MM-DD') : undefined,
        end_date: values.end_date ? values.end_date.format('YYYY-MM-DD') : undefined,
      };
      return modal.mode === 'edit' ? api.supervisorAssignments.update(modal.record.id, payload) : api.supervisorAssignments.create(payload);
    },
    onSuccess: () => {
      message.success('Penugasan wilayah supervisor berhasil disimpan.');
      setModal({ mode: null, record: null });
      invalidate();
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const remove = useMutation({
    mutationFn: (id) => api.supervisorAssignments.remove(id),
    onSuccess: () => {
      message.success('Penugasan berhasil dicabut.');
      invalidate();
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const reassign = useMutation({
    mutationFn: ({ id, values }) => api.supervisorAssignments.reassign(id, values),
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
    form.setFieldsValue({ is_active: true });
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

  const supervisorOptions = (supervisors.data?.data?.data || supervisors.data?.data || []).map((s) => ({ value: s.id, label: s.name }));
  const clusterOptions = (clusters.data?.data || []).map((cluster) => ({ value: cluster.id, label: `${cluster.id} - ${cluster.name}` }));

  return (
    <section>
      <PageHeader
        title="Penugasan Wilayah Supervisor"
        subtitle="Tentukan cluster yang menjadi tanggung jawab pengawasan setiap supervisor."
        breadcrumbs={[{ label: 'Manajemen Supervisor' }, { label: 'Penugasan Wilayah' }]}
        onRefresh={list.refetch}
        extra={
          <Can permission="supervisor-assignments.assign">
            <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Tambah Penugasan</Button>
          </Can>
        }
      />

      <FilterBar>
        <Select
          allowClear
          placeholder="Filter supervisor"
          style={{ minWidth: 220 }}
          options={supervisorOptions}
          value={table.filters.supervisor_id}
          onChange={(value) => table.setFilters({ ...table.filters, supervisor_id: value })}
        />
        <Select
          allowClear
          placeholder="Filter aktif/nonaktif"
          style={{ minWidth: 160 }}
          options={[{ value: true, label: 'Aktif' }, { value: false, label: 'Nonaktif' }]}
          value={table.filters.is_active}
          onChange={(value) => table.setFilters({ ...table.filters, is_active: value })}
        />
      </FilterBar>

      <Card>
        <ResponsiveTable
          query={list}
          onChange={table.handleTableChange}
          columns={[
            { title: 'Supervisor', dataIndex: ['supervisor', 'name'] },
            { title: 'Cluster', render: (_, record) => record.cluster?.name || record.cluster_id },
            { title: 'Periode', render: (_, record) => `${record.start_date || '-'} s/d ${record.end_date || 'sekarang'}` },
            { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge type="assignmentStatus" value={value} /> },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 160,
              render: (_, record) => (
                <Space>
                  <Can permission="supervisor-assignments.assign">
                    <Button size="small" icon={<EditOutlined />} onClick={() => openEdit(record)} />
                  </Can>
                  <Can permission="supervisor-assignments.reassign">
                    <Button size="small" icon={<SwapOutlined />} onClick={() => { reassignForm.resetFields(); setReassignModal(record); }} disabled={!record.is_active} title="Pindahkan" />
                  </Can>
                  <Can permission="supervisor-assignments.assign">
                    <Button
                      size="small"
                      danger
                      icon={<DeleteOutlined />}
                      onClick={() => Modal.confirm({
                        title: 'Cabut penugasan ini?',
                        content: record.cluster?.name || record.cluster_id,
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
        title={modal.mode === 'edit' ? 'Edit Penugasan Wilayah' : 'Tambah Penugasan Wilayah'}
        open={modal.mode === 'create' || modal.mode === 'edit'}
        onCancel={() => setModal({ mode: null, record: null })}
        onOk={() => form.submit()}
        confirmLoading={save.isPending}
        destroyOnHidden
      >
        <Form form={form} layout="vertical" onFinish={save.mutate}>
          <Form.Item label="Supervisor" name="supervisor_id" rules={[{ required: true, message: 'Pilih supervisor' }]}>
            <Select showSearch optionFilterProp="label" options={supervisorOptions} loading={supervisors.isLoading} />
          </Form.Item>
          <Form.Item label="Cluster" name="cluster_id" rules={[{ required: true, message: 'Pilih cluster' }]}>
            <Select showSearch optionFilterProp="label" options={clusterOptions} loading={clusters.isLoading} />
          </Form.Item>
          <Space style={{ width: '100%' }} size="middle">
            <Form.Item label="Tanggal Mulai" name="start_date" style={{ width: 180 }}>
              <DatePicker style={{ width: '100%' }} />
            </Form.Item>
            <Form.Item label="Tanggal Selesai (opsional)" name="end_date" style={{ width: 180 }}>
              <DatePicker style={{ width: '100%' }} />
            </Form.Item>
          </Space>
          <Form.Item label="Catatan" name="notes">
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
            Memindahkan cluster <strong>{reassignModal.cluster?.name || reassignModal.cluster_id}</strong> dari supervisor <strong>{reassignModal.supervisor?.name}</strong> ke supervisor lain.
          </p>
        ) : null}
        <Form form={reassignForm} layout="vertical" onFinish={(values) => reassign.mutate({ id: reassignModal.id, values })}>
          <Form.Item label="Supervisor Tujuan" name="new_supervisor_id" rules={[{ required: true, message: 'Pilih supervisor tujuan' }]}>
            <Select showSearch optionFilterProp="label" options={supervisorOptions.filter((o) => o.value !== reassignModal?.supervisor_id)} />
          </Form.Item>
          <Form.Item label="Catatan" name="notes">
            <Input.TextArea rows={2} />
          </Form.Item>
        </Form>
      </Modal>
    </section>
  );
}
