import { Button, Card, DatePicker, Drawer, Dropdown, Form, Image, Input, Modal, Select, Space, Upload, message } from 'antd';
import { DeleteOutlined, EditOutlined, EyeOutlined, MoreOutlined, PlusOutlined, StopOutlined, UploadOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import dayjs from 'dayjs';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import Can from '../components/common/Can.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { api, storageUrl } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';
import { useAuth } from '../state/AuthContext.jsx';

const ACCOUNT_STATUS_OPTIONS = [
  { value: 'active', label: 'Aktif' },
  { value: 'inactive', label: 'Nonaktif' },
  { value: 'leave', label: 'Cuti' },
  { value: 'suspended', label: 'Suspend' },
];

const EMPLOYMENT_STATUS_OPTIONS = [
  { value: 'tetap', label: 'Tetap' },
  { value: 'kontrak', label: 'Kontrak' },
  { value: 'harian', label: 'Harian' },
];

function toFormValues(record) {
  return {
    ...record,
    ...record.supervisor_profile,
    joined_at: record.supervisor_profile?.joined_at ? dayjs(record.supervisor_profile.joined_at) : undefined,
  };
}

function toSubmitValues(values) {
  return {
    ...values,
    joined_at: values.joined_at ? values.joined_at.format('YYYY-MM-DD') : undefined,
  };
}

export default function SupervisorsPage() {
  const table = useTableState();
  const [drawer, setDrawer] = useState({ type: null, record: null });
  const [statusModal, setStatusModal] = useState(null);
  const [statusForm] = Form.useForm();
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const { can } = useAuth();

  const supervisors = useQuery({ queryKey: ['supervisors', table.params], queryFn: () => api.supervisors.list(table.params) });

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ['supervisors'] });
  }

  const save = useMutation({
    mutationFn: (values) => {
      const payload = toSubmitValues(values);
      return drawer.type === 'edit' ? api.supervisors.update(drawer.record.id, payload) : api.supervisors.create(payload);
    },
    onSuccess: () => {
      message.success('Supervisor berhasil disimpan.');
      setDrawer({ type: null, record: null });
      form.resetFields();
      invalidate();
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const remove = useMutation({
    mutationFn: (id) => api.supervisors.remove(id),
    onSuccess: () => {
      message.success('Supervisor berhasil dihapus.');
      invalidate();
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const changeStatus = useMutation({
    mutationFn: ({ id, values }) => api.supervisors.updateStatus(id, values),
    onSuccess: () => {
      message.success('Status supervisor berhasil diperbarui.');
      setStatusModal(null);
      invalidate();
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const uploadPhoto = useMutation({
    mutationFn: ({ id, formData }) => api.supervisors.uploadPhoto(id, formData),
    onSuccess: () => {
      message.success('Foto profil berhasil diunggah.');
      invalidate();
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  function openCreate() {
    form.resetFields();
    form.setFieldsValue({ account_status: 'active', employment_status: 'tetap' });
    setDrawer({ type: 'create', record: null });
  }

  function openEdit(record) {
    form.setFieldsValue(toFormValues(record));
    setDrawer({ type: 'edit', record });
  }

  const editingPhoto = drawer.record?.supervisor_profile?.photos?.[0];

  return (
    <section>
      <PageHeader
        title="Data Supervisor"
        subtitle="Kelola akun, profil, dan status kepegawaian supervisor yang mengawasi kolektor."
        breadcrumbs={[{ label: 'Manajemen Supervisor' }, { label: 'Data Supervisor' }]}
        onRefresh={supervisors.refetch}
        extra={
          <Can permission="supervisor.create">
            <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Tambah Supervisor</Button>
          </Can>
        }
      />

      <FilterBar>
        <Input
          allowClear
          placeholder="Cari nama, username, kode supervisor"
          value={table.search}
          onChange={(event) => table.setSearch(event.target.value)}
          className="filter-input"
        />
        <Select
          allowClear
          placeholder="Status Akun"
          options={ACCOUNT_STATUS_OPTIONS}
          value={table.filters.account_status}
          onChange={(value) => table.setFilters({ ...table.filters, account_status: value })}
          className="filter-input"
        />
      </FilterBar>

      <Card>
        <ResponsiveTable
          query={supervisors}
          onChange={table.handleTableChange}
          scrollX={1000}
          columns={[
            { title: 'Kode', dataIndex: ['supervisor_profile', 'supervisor_code'], width: 110 },
            { title: 'Nama', dataIndex: 'name', width: 200 },
            { title: 'Kontak', render: (_, record) => record.phone || record.supervisor_profile?.whatsapp_number || '-' },
            {
              title: 'Status Akun',
              width: 120,
              render: (_, record) => <StatusBadge type="collectorAccount" value={record.supervisor_profile?.account_status} />,
            },
            {
              title: 'Kepegawaian',
              width: 110,
              render: (_, record) => EMPLOYMENT_STATUS_OPTIONS.find((o) => o.value === record.supervisor_profile?.employment_status)?.label || '-',
            },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 100,
              render: (_, record) => (
                <Dropdown
                  menu={{
                    items: [
                      { key: 'detail', label: 'Detail', icon: <EyeOutlined /> },
                      can('supervisor.update') ? { key: 'edit', label: 'Edit', icon: <EditOutlined /> } : null,
                      can('supervisor.activate') ? { key: 'status', label: 'Ubah Status', icon: <StopOutlined /> } : null,
                      can('supervisor.delete') ? { type: 'divider' } : null,
                      can('supervisor.delete') ? { key: 'delete', label: 'Hapus', icon: <DeleteOutlined />, danger: true } : null,
                    ].filter(Boolean),
                    onClick: ({ key }) => {
                      if (key === 'detail') navigate(`/admin/supervisors/list/${record.id}`);
                      if (key === 'edit') openEdit(record);
                      if (key === 'status') {
                        statusForm.setFieldsValue({ account_status: record.supervisor_profile?.account_status });
                        setStatusModal(record);
                      }
                      if (key === 'delete') {
                        Modal.confirm({
                          title: 'Hapus supervisor ini?',
                          content: record.name,
                          okText: 'Hapus',
                          okButtonProps: { danger: true },
                          onOk: () => remove.mutate(record.id),
                        });
                      }
                    },
                  }}
                >
                  <Button icon={<MoreOutlined />} />
                </Dropdown>
              ),
            },
          ]}
        />
      </Card>

      <Drawer
        title={drawer.type === 'edit' ? `Edit Supervisor — ${drawer.record?.name || ''}` : 'Tambah Supervisor'}
        open={drawer.type === 'create' || drawer.type === 'edit'}
        onClose={() => setDrawer({ type: null, record: null })}
        width={640}
        extra={
          <Space>
            <Button onClick={() => setDrawer({ type: null, record: null })}>Batal</Button>
            <Button type="primary" loading={save.isPending} onClick={() => form.submit()}>Simpan</Button>
          </Space>
        }
        destroyOnHidden
      >
        <Form form={form} layout="vertical" className="responsive-form" onFinish={save.mutate}>
          {drawer.type === 'edit' && (
            <Form.Item label="Foto Profil">
              <Space direction="vertical" size="small">
                {editingPhoto ? (
                  <Image src={storageUrl(editingPhoto.path)} width={100} height={100} style={{ objectFit: 'cover', borderRadius: 8 }} alt="" />
                ) : null}
                <Upload
                  accept="image/*"
                  showUploadList={false}
                  beforeUpload={(file) => {
                    const formData = new FormData();
                    formData.append('photo', file);
                    uploadPhoto.mutate({ id: drawer.record.id, formData });
                    return false;
                  }}
                >
                  <Button icon={<UploadOutlined />} loading={uploadPhoto.isPending} size="small">
                    {editingPhoto ? 'Ganti Foto' : 'Unggah Foto'}
                  </Button>
                </Upload>
              </Space>
            </Form.Item>
          )}
          <Form.Item label="Nama Lengkap" name="name" rules={[{ required: true, message: 'Nama wajib diisi' }]}>
            <Input />
          </Form.Item>
          <Form.Item label="Username" name="username" rules={[{ required: true, message: 'Username wajib diisi' }]}>
            <Input autoComplete="off" />
          </Form.Item>
          <Form.Item
            label="Password"
            name="password"
            tooltip={drawer.type === 'edit' ? 'Kosongkan jika tidak ingin mengubah password' : undefined}
            rules={[{ required: drawer.type !== 'edit', message: 'Password wajib diisi' }, { min: 8, message: 'Minimal 8 karakter' }]}
          >
            <Input.Password autoComplete="new-password" />
          </Form.Item>
          <Form.Item label="Kode Supervisor" name="supervisor_code" tooltip="Kosongkan untuk membuat kode otomatis (SPV-0001, dst.)">
            <Input placeholder="SPV-0001" />
          </Form.Item>
          <Form.Item label="Email" name="email" rules={[{ type: 'email', message: 'Format email tidak valid' }]}>
            <Input />
          </Form.Item>
          <Form.Item label="Nomor Telepon" name="phone">
            <Input />
          </Form.Item>
          <Form.Item label="Nomor WhatsApp" name="whatsapp_number">
            <Input />
          </Form.Item>
          <Form.Item label="Alamat" name="address">
            <Input.TextArea rows={2} />
          </Form.Item>
          <Form.Item label="Tanggal Bergabung" name="joined_at">
            <DatePicker style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Status Kepegawaian" name="employment_status">
            <Select options={EMPLOYMENT_STATUS_OPTIONS} />
          </Form.Item>
          <Form.Item label="Status Akun" name="account_status" rules={[{ required: true, message: 'Status akun wajib dipilih' }]}>
            <Select options={ACCOUNT_STATUS_OPTIONS} />
          </Form.Item>
          <Form.Item label="Catatan Admin" name="admin_notes">
            <Input.TextArea rows={2} />
          </Form.Item>
        </Form>
      </Drawer>

      <Modal
        title={`Ubah Status — ${statusModal?.name || ''}`}
        open={Boolean(statusModal)}
        onCancel={() => setStatusModal(null)}
        onOk={() => statusForm.submit()}
        confirmLoading={changeStatus.isPending}
        destroyOnHidden
      >
        <Form
          form={statusForm}
          layout="vertical"
          onFinish={(values) => changeStatus.mutate({ id: statusModal.id, values })}
        >
          <Form.Item label="Status Akun" name="account_status" rules={[{ required: true, message: 'Pilih status akun' }]}>
            <Select options={ACCOUNT_STATUS_OPTIONS} />
          </Form.Item>
          <Form.Item label="Alasan / Catatan" name="reason">
            <Input.TextArea rows={3} />
          </Form.Item>
        </Form>
      </Modal>
    </section>
  );
}
