import { Button, Card, Drawer, Dropdown, Form, Input, Modal, Space, message } from 'antd';
import { DeleteOutlined, EditOutlined, EyeOutlined, MoreOutlined, PlusOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import Can from '../components/common/Can.jsx';
import ResidentForm from '../components/forms/ResidentForm.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { api } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';
import { useAuth } from '../state/AuthContext.jsx';

export default function ResidentsPage() {
  const table = useTableState();
  const [drawer, setDrawer] = useState({ type: null, record: null });
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const { can } = useAuth();

  const residents = useQuery({ queryKey: ['residents', table.params], queryFn: () => api.residents.list(table.params) });
  const districts = useQuery({ queryKey: ['lookup-districts'], queryFn: () => api.lookup.districts() });

  const save = useMutation({
    mutationFn: (values) => drawer.type === 'edit' ? api.residents.update(drawer.record.id, values) : api.residents.create(values),
    onSuccess: (response) => {
      message.success('Penghuni berhasil disimpan');
      setDrawer({ type: null, record: null });
      form.resetFields();
      queryClient.invalidateQueries({ queryKey: ['residents'] });

      const account = response?.data?.login_account;
      if (account) {
        Modal.success({
          title: 'Akun login penghuni dibuat',
          width: 480,
          content: (
            <div>
              <p>Akun customer otomatis dibuat untuk penghuni ini. Sampaikan detail berikut ke penghuni (password ini hanya ditampilkan sekali):</p>
              <p><strong>Username:</strong> {account.username}</p>
              <p><strong>Password sementara:</strong> {account.temporary_password}</p>
              <p>Akun baru bisa login penuh setelah Unit penghuni dibuat dan ditautkan lewat menu User Management.</p>
            </div>
          ),
        });
      }
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const remove = useMutation({
    mutationFn: api.residents.remove,
    onSuccess: () => {
      message.success('Penghuni berhasil dihapus');
      queryClient.invalidateQueries({ queryKey: ['residents'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  function openCreate() {
    form.resetFields();
    setDrawer({ type: 'create', record: null });
  }

  function openEdit(record) {
    form.setFieldsValue(record);
    setDrawer({ type: 'edit', record });
  }

  return (
    <section>
      <PageHeader
        title="Penghuni"
        subtitle="Master data pemilik unit Grand Duta."
        breadcrumbs={[{ label: 'Penghuni' }]}
        onRefresh={residents.refetch}
        loading={residents.isFetching}
        extra={<Can permission="residents.create"><Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Tambah Penghuni</Button></Can>}
      />

      <FilterBar>
        <Input allowClear placeholder="Cari nama, ID, telepon" value={table.search} onChange={(event) => table.setSearch(event.target.value)} className="filter-input" />
      </FilterBar>

      <Card>
        <ResponsiveTable
          query={residents}
          onChange={table.handleTableChange}
          scrollX={760}
          onRow={(record) => ({ onClick: () => navigate(`/residents/${record.id}`), style: { cursor: 'pointer' } })}
          columns={[
            { title: 'ID', dataIndex: 'id', fixed: 'left', width: 110 },
            { title: 'Nama', dataIndex: 'name', width: 220 },
            { title: 'Telepon', dataIndex: 'phone' },
            { title: 'Email', dataIndex: 'email' },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 92,
              render: (_, record) => {
                const items = [
                  { key: 'detail', label: 'Detail', icon: <EyeOutlined /> },
                  { key: 'edit', label: 'Edit', icon: <EditOutlined />, permission: 'residents.update' },
                  { type: 'divider' },
                  { key: 'delete', label: 'Hapus', icon: <DeleteOutlined />, danger: true, permission: 'residents.delete' },
                ].filter((item) => !item.permission || can(item.permission));
                return (
                  <Dropdown menu={{ items, onClick: ({ key, domEvent }) => {
                    domEvent.stopPropagation();
                    if (key === 'detail') navigate(`/residents/${record.id}`);
                    if (key === 'edit') openEdit(record);
                    if (key === 'delete') {
                      Modal.confirm({
                        title: 'Hapus penghuni?',
                        content: `${record.id} - ${record.name}`,
                        okText: 'Hapus',
                        okButtonProps: { danger: true },
                        onOk: () => remove.mutate(record.id),
                      });
                    }
                  } }}>
                    <Button icon={<MoreOutlined />} onClick={(event) => event.stopPropagation()} />
                  </Dropdown>
                );
              },
            },
          ]}
        />
      </Card>

      <Drawer
        title={drawer.type === 'edit' ? 'Edit Penghuni' : 'Tambah Penghuni'}
        open={drawer.type === 'create' || drawer.type === 'edit'}
        onClose={() => setDrawer({ type: null, record: null })}
        width={620}
        extra={<Space><Button onClick={() => setDrawer({ type: null, record: null })}>Batal</Button><Button type="primary" loading={save.isPending} onClick={() => form.submit()}>Simpan</Button></Space>}
        destroyOnHidden
      >
        <ResidentForm
          form={form}
          districts={districts.data?.data || []}
          onFinish={save.mutate}
          loading={save.isPending}
          editing={drawer.type === 'edit'}
          residentId={drawer.record?.id}
        />
      </Drawer>
    </section>
  );
}
