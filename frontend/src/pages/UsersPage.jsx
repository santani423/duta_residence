import { Button, Card, Drawer, Dropdown, Form, Input, Modal, Select, Space, Tag, message } from 'antd';
import { DeleteOutlined, EditOutlined, EyeOutlined, KeyOutlined, MoreOutlined, PlusOutlined, PoweroffOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import Can from '../components/common/Can.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import UserForm from '../components/forms/UserForm.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { api } from '../services/estateApi.js';
import { roles } from '../constants/permissions.js';
import { useTableState } from '../hooks/useTableState.js';
import { formatDateTime } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';
import { useAuth } from '../state/AuthContext.jsx';

export default function UsersPage() {
  const table = useTableState();
  const [drawer, setDrawer] = useState({ type: null, record: null });
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const { can } = useAuth();
  const users = useQuery({ queryKey: ['users', table.params], queryFn: () => api.users.list(table.params) });
  const save = useMutation({
    mutationFn: (values) => drawer.type === 'edit' ? api.users.update(drawer.record.id, values) : api.users.create(values),
    onSuccess: () => {
      message.success('User berhasil disimpan');
      setDrawer({ type: null, record: null });
      form.resetFields();
      queryClient.invalidateQueries({ queryKey: ['users'] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });
  const remove = useMutation({
    mutationFn: api.users.remove,
    onSuccess: () => {
      message.success('User berhasil dihapus');
      queryClient.invalidateQueries({ queryKey: ['users'] });
    },
  });
  const reset = useMutation({
    mutationFn: api.users.resetPassword,
    onSuccess: (response) => {
      Modal.info({ title: 'Password sementara', content: response.data.temporary_password });
      queryClient.invalidateQueries({ queryKey: ['users'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });
  const toggle = useMutation({
    mutationFn: api.users.toggleStatus,
    onSuccess: () => {
      message.success('Status user diperbarui');
      queryClient.invalidateQueries({ queryKey: ['users'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  function openCreate() {
    form.resetFields();
    setDrawer({ type: 'create', record: null });
  }

  function openEdit(record) {
    form.setFieldsValue({
      ...record,
      role: record.roles?.[0]?.name,
    });
    setDrawer({ type: 'edit', record });
  }

  return (
    <section>
      <PageHeader
        title="User Management"
        subtitle="Kelola akun internal, role, status, dan akses."
        breadcrumbs={[{ label: 'User' }]}
        onRefresh={users.refetch}
        extra={<Can permission="users.create"><Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Tambah User</Button></Can>}
      />

      <FilterBar>
        <Input allowClear placeholder="Cari nama, username, email, telepon" value={table.search} onChange={(event) => table.setSearch(event.target.value)} className="filter-input" />
        <Select allowClear placeholder="Role" value={table.filters.role} onChange={(value) => table.setFilters({ ...table.filters, role: value })} options={roles.map((role) => ({ value: role, label: role }))} className="filter-input" />
        <Select allowClear placeholder="Status" value={table.filters.is_active} onChange={(value) => table.setFilters({ ...table.filters, is_active: value })} options={[{ value: true, label: 'Aktif' }, { value: false, label: 'Nonaktif' }]} className="filter-input" />
      </FilterBar>

      <Card>
        <ResponsiveTable
          query={users}
          onChange={table.handleTableChange}
          scrollX={1120}
          columns={[
            { title: 'Nama', dataIndex: 'name', width: 220, fixed: 'left' },
            { title: 'Username', dataIndex: 'username', width: 150 },
            { title: 'Email', dataIndex: 'email', width: 220 },
            { title: 'Role', dataIndex: 'roles', render: (items) => items?.map((role) => <Tag key={role.id}>{role.name}</Tag>) },
            { title: 'Status', dataIndex: 'is_active', render: (value) => <StatusBadge type="active" value={value} /> },
            { title: 'Login Terakhir', dataIndex: 'last_login_at', render: formatDateTime, width: 170 },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 90,
              render: (_, record) => {
                const items = [
                  { key: 'detail', label: 'Detail', icon: <EyeOutlined /> },
                  can('users.update') ? { key: 'edit', label: 'Edit', icon: <EditOutlined /> } : null,
                  can('users.activate') ? { key: 'toggle', label: record.is_active ? 'Nonaktifkan' : 'Aktifkan', icon: <PoweroffOutlined /> } : null,
                  can('users.reset-password') ? { key: 'reset', label: 'Reset Password', icon: <KeyOutlined /> } : null,
                  can('users.delete') ? { type: 'divider' } : null,
                  can('users.delete') ? { key: 'delete', label: 'Hapus', icon: <DeleteOutlined />, danger: true } : null,
                ].filter(Boolean);
                return (
                  <Dropdown menu={{ items, onClick: ({ key }) => {
                    if (key === 'detail') navigate(`/users/${record.id}`);
                    if (key === 'edit') openEdit(record);
                    if (key === 'toggle') Modal.confirm({ title: 'Ubah status user?', onOk: () => toggle.mutate(record.id) });
                    if (key === 'reset') Modal.confirm({ title: 'Reset password user?', onOk: () => reset.mutate(record.id) });
                    if (key === 'delete') Modal.confirm({ title: 'Hapus user?', okButtonProps: { danger: true }, onOk: () => remove.mutate(record.id) });
                  } }}>
                    <Button icon={<MoreOutlined />} />
                  </Dropdown>
                );
              },
            },
          ]}
        />
      </Card>

      <Drawer
        title={drawer.type === 'edit' ? 'Edit User' : 'Tambah User'}
        open={drawer.type === 'create' || drawer.type === 'edit'}
        onClose={() => setDrawer({ type: null, record: null })}
        width={680}
        extra={<Space><Button onClick={() => setDrawer({ type: null, record: null })}>Batal</Button><Button type="primary" loading={save.isPending} onClick={() => form.submit()}>Simpan</Button></Space>}
        destroyOnHidden
      >
        <UserForm form={form} editing={drawer.type === 'edit'} onFinish={save.mutate} loading={save.isPending} />
      </Drawer>
    </section>
  );
}
