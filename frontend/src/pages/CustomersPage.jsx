import { Button, Card, Descriptions, Drawer, Dropdown, Form, Input, Modal, Space, Tabs, Tag, message } from 'antd';
import { DeleteOutlined, EditOutlined, EyeOutlined, MoreOutlined, PlusOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import Can from '../components/common/Can.jsx';
import CustomerForm from '../components/forms/CustomerForm.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { api } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { compactText } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';
import { useAuth } from '../state/AuthContext.jsx';

export default function CustomersPage() {
  const table = useTableState();
  const [drawer, setDrawer] = useState({ type: null, record: null });
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const { can } = useAuth();

  const customers = useQuery({ queryKey: ['customers', table.params], queryFn: () => api.customers.list(table.params) });
  const districts = useQuery({ queryKey: ['lookup-districts'], queryFn: () => api.lookup.districts() });
  const detail = useQuery({
    queryKey: ['customers', drawer.record?.id],
    queryFn: () => api.customers.detail(drawer.record.id),
    enabled: drawer.type === 'detail' && Boolean(drawer.record?.id),
  });

  const save = useMutation({
    mutationFn: (values) => drawer.type === 'edit' ? api.customers.update(drawer.record.id, values) : api.customers.create(values),
    onSuccess: () => {
      message.success('Pelanggan berhasil disimpan');
      setDrawer({ type: null, record: null });
      form.resetFields();
      queryClient.invalidateQueries({ queryKey: ['customers'] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const remove = useMutation({
    mutationFn: api.customers.remove,
    onSuccess: () => {
      message.success('Pelanggan berhasil dihapus');
      queryClient.invalidateQueries({ queryKey: ['customers'] });
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

  const detailData = detail.data?.data;

  return (
    <section>
      <PageHeader
        title="Pelanggan"
        subtitle="Master data pemilik unit Grand Duta."
        breadcrumbs={[{ label: 'Pelanggan' }]}
        onRefresh={customers.refetch}
        loading={customers.isFetching}
        extra={<Can permission="customers.create"><Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Tambah Pelanggan</Button></Can>}
      />

      <FilterBar>
        <Input allowClear placeholder="Cari nama, ID, telepon" value={table.search} onChange={(event) => table.setSearch(event.target.value)} className="filter-input" />
      </FilterBar>

      <Card>
        <ResponsiveTable
          query={customers}
          onChange={table.handleTableChange}
          scrollX={760}
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
                  { key: 'edit', label: 'Edit', icon: <EditOutlined />, permission: 'customers.update' },
                  { type: 'divider' },
                  { key: 'delete', label: 'Hapus', icon: <DeleteOutlined />, danger: true, permission: 'customers.delete' },
                ].filter((item) => !item.permission || can(item.permission));
                return (
                  <Dropdown menu={{ items, onClick: ({ key }) => {
                    if (key === 'detail') setDrawer({ type: 'detail', record });
                    if (key === 'edit') openEdit(record);
                    if (key === 'delete') {
                      Modal.confirm({
                        title: 'Hapus pelanggan?',
                        content: `${record.id} - ${record.name}`,
                        okText: 'Hapus',
                        okButtonProps: { danger: true },
                        onOk: () => remove.mutate(record.id),
                      });
                    }
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
        title={drawer.type === 'edit' ? 'Edit Pelanggan' : 'Tambah Pelanggan'}
        open={drawer.type === 'create' || drawer.type === 'edit'}
        onClose={() => setDrawer({ type: null, record: null })}
        width={620}
        extra={<Space><Button onClick={() => setDrawer({ type: null, record: null })}>Batal</Button><Button type="primary" loading={save.isPending} onClick={() => form.submit()}>Simpan</Button></Space>}
        destroyOnHidden
      >
        <CustomerForm form={form} districts={districts.data?.data || []} onFinish={save.mutate} loading={save.isPending} />
      </Drawer>

      <Drawer title="Detail Pelanggan" open={drawer.type === 'detail'} onClose={() => setDrawer({ type: null, record: null })} width={840}>
        <Tabs
          items={[
            {
              key: 'info',
              label: 'Informasi',
              children: (
                <Descriptions bordered column={{ xs: 1, md: 2 }}>
                  <Descriptions.Item label="ID">{detailData?.id}</Descriptions.Item>
                  <Descriptions.Item label="Nama">{detailData?.name}</Descriptions.Item>
                  <Descriptions.Item label="Telepon">{compactText(detailData?.phone)}</Descriptions.Item>
                  <Descriptions.Item label="Telepon Rumah">{compactText(detailData?.telephone)}</Descriptions.Item>
                  <Descriptions.Item label="Email">{compactText(detailData?.email)}</Descriptions.Item>
                  <Descriptions.Item label="Kabupaten/Kota">{detailData?.district?.name}</Descriptions.Item>
                  <Descriptions.Item label="Alamat" span={2}>{compactText(detailData?.id_card_address)}</Descriptions.Item>
                </Descriptions>
              ),
            },
            {
              key: 'units',
              label: 'Unit-unit',
              children: (
                <ResponsiveTable
                  data={detailData?.units || []}
                  pagination={false}
                  columns={[
                    { title: 'ID', dataIndex: 'id' },
                    { title: 'Cluster', dataIndex: ['cluster', 'name'] },
                    { title: 'Blok', dataIndex: 'block' },
                    { title: 'Kavling', dataIndex: 'lot_number' },
                    { title: 'Status', render: (_, row) => <Tag color={row.status_id === 'AK' ? 'green' : 'default'}>{row.status?.name || row.status_id}</Tag> },
                  ]}
                />
              ),
            },
          ]}
        />
      </Drawer>
    </section>
  );
}
