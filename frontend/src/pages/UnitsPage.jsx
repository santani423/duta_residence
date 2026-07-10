import { Button, Card, Descriptions, Drawer, Dropdown, Form, Input, Modal, Select, Space, Tabs, Tag, message } from 'antd';
import { DeleteOutlined, EditOutlined, EyeOutlined, MoreOutlined, PlusOutlined, SwapOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import Can from '../components/common/Can.jsx';
import UnitForm, { customerStatusOptions, propertyTypeOptions } from '../components/forms/UnitForm.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { api } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { compactText, formatCurrency, formatDate, formatPeriod } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';
import { useAuth } from '../state/AuthContext.jsx';

export default function UnitsPage() {
  const table = useTableState();
  const [drawer, setDrawer] = useState({ type: null, record: null });
  const [form] = Form.useForm();
  const [convertForm] = Form.useForm();
  const queryClient = useQueryClient();
  const { can } = useAuth();

  const units = useQuery({ queryKey: ['units', table.params], queryFn: () => api.units.list(table.params) });
  const clusters = useQuery({ queryKey: ['clusters'], queryFn: () => api.clusters.list() });
  const customers = useQuery({ queryKey: ['customers-lookup'], queryFn: () => api.customers.list({ per_page: 1000 }) });
  const detail = useQuery({
    queryKey: ['units', drawer.record?.id],
    queryFn: () => api.units.detail(drawer.record.id),
    enabled: drawer.type === 'detail' && Boolean(drawer.record?.id),
  });

  const save = useMutation({
    mutationFn: (values) => drawer.type === 'edit' ? api.units.update(drawer.record.id, values) : api.units.create(values),
    onSuccess: () => {
      message.success('Unit berhasil disimpan');
      setDrawer({ type: null, record: null });
      form.resetFields();
      queryClient.invalidateQueries({ queryKey: ['units'] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const remove = useMutation({
    mutationFn: api.units.remove,
    onSuccess: () => {
      message.success('Unit berhasil dihapus');
      queryClient.invalidateQueries({ queryKey: ['units'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const convert = useMutation({
    mutationFn: (values) => api.units.convert(drawer.record.id, values),
    onSuccess: () => {
      message.success('Properti berhasil dikonversi');
      setDrawer({ type: null, record: null });
      queryClient.invalidateQueries({ queryKey: ['units'] });
    },
    onError: (error) => {
      convertForm.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  function openCreate() {
    form.resetFields();
    setDrawer({ type: 'create', record: null });
  }

  function openEdit(record) {
    form.setFieldsValue(record);
    setDrawer({ type: 'edit', record });
  }

  const clusterOptions = (clusters.data?.data || []).map((item) => ({ value: item.id, label: item.name }));
  const customerOptions = (customers.data?.data || []).map((item) => ({ value: item.id, label: item.name }));
  const detailData = detail.data?.data;

  return (
    <section>
      <PageHeader
        title="Unit Rumah"
        subtitle="Master data unit dan kepemilikan di Grand Duta."
        breadcrumbs={[{ label: 'Unit Rumah' }]}
        onRefresh={units.refetch}
        loading={units.isFetching}
        extra={<Can permission="units.create"><Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Tambah Unit</Button></Can>}
      />

      <FilterBar>
        <Input allowClear placeholder="Cari ID, blok, nama pemilik" value={table.search} onChange={(event) => table.setSearch(event.target.value)} className="filter-input" />
        <Select allowClear placeholder="Cluster" options={clusterOptions} value={table.filters.cluster_id} onChange={(value) => table.setFilters({ ...table.filters, cluster_id: value })} className="filter-input" />
        <Select allowClear placeholder="Status" options={customerStatusOptions} value={table.filters.status_id} onChange={(value) => table.setFilters({ ...table.filters, status_id: value })} className="filter-input" />
        <Select allowClear placeholder="Tipe" options={propertyTypeOptions} value={table.filters.property_type_id} onChange={(value) => table.setFilters({ ...table.filters, property_type_id: value })} className="filter-input" />
      </FilterBar>

      <Card>
        <ResponsiveTable
          query={units}
          onChange={table.handleTableChange}
          scrollX={1180}
          columns={[
            { title: 'ID', dataIndex: 'id', fixed: 'left', width: 90 },
            { title: 'Pemilik', dataIndex: ['customer', 'name'], width: 220 },
            { title: 'Cluster', dataIndex: ['cluster', 'name'], width: 160 },
            { title: 'Blok', dataIndex: 'block', width: 80 },
            { title: 'Kavling', dataIndex: 'lot_number', width: 90 },
            { title: 'Tipe', render: (_, row) => row.property_type?.name || row.propertyType?.name || row.property_type_id },
            { title: 'Status', render: (_, row) => <Tag color={row.status_id === 'AK' ? 'green' : 'default'}>{row.status?.name || row.status_id}</Tag> },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 92,
              render: (_, record) => {
                const items = [
                  { key: 'detail', label: 'Detail', icon: <EyeOutlined /> },
                  { key: 'edit', label: 'Edit', icon: <EditOutlined />, permission: 'units.update' },
                  { key: 'convert', label: 'Konversi Properti', icon: <SwapOutlined />, disabled: record.property_type_id !== 'K', permission: 'units.convert-property' },
                  { type: 'divider' },
                  { key: 'delete', label: 'Hapus', icon: <DeleteOutlined />, danger: true, permission: 'units.delete' },
                ].filter((item) => !item.permission || can(item.permission));
                return (
                  <Dropdown menu={{ items, onClick: ({ key }) => {
                    if (key === 'detail') setDrawer({ type: 'detail', record });
                    if (key === 'edit') openEdit(record);
                    if (key === 'convert') setDrawer({ type: 'convert', record });
                    if (key === 'delete') {
                      Modal.confirm({
                        title: 'Hapus unit?',
                        content: `${record.id}`,
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
        title={drawer.type === 'edit' ? 'Edit Unit' : 'Tambah Unit'}
        open={drawer.type === 'create' || drawer.type === 'edit'}
        onClose={() => setDrawer({ type: null, record: null })}
        width={760}
        extra={<Space><Button onClick={() => setDrawer({ type: null, record: null })}>Batal</Button><Button type="primary" loading={save.isPending} onClick={() => form.submit()}>Simpan</Button></Space>}
        destroyOnHidden
      >
        <UnitForm form={form} clusters={clusters.data?.data || []} customers={customers.data?.data || []} disabledId={drawer.type === 'edit'} onFinish={save.mutate} loading={save.isPending} />
      </Drawer>

      <Drawer title="Detail Unit" open={drawer.type === 'detail'} onClose={() => setDrawer({ type: null, record: null })} width={840}>
        <Tabs
          items={[
            {
              key: 'info',
              label: 'Informasi',
              children: (
                <Descriptions bordered column={{ xs: 1, md: 2 }}>
                  <Descriptions.Item label="ID">{detailData?.id}</Descriptions.Item>
                  <Descriptions.Item label="Pemilik">{detailData?.customer?.name}</Descriptions.Item>
                  <Descriptions.Item label="Cluster">{detailData?.cluster?.name}</Descriptions.Item>
                  <Descriptions.Item label="Unit">{detailData?.block}-{detailData?.lot_number}</Descriptions.Item>
                  <Descriptions.Item label="Tipe">{detailData?.property_type?.name || detailData?.propertyType?.name}</Descriptions.Item>
                  <Descriptions.Item label="Status">{detailData?.status?.name}</Descriptions.Item>
                  <Descriptions.Item label="Telepon Pemilik">{compactText(detailData?.customer?.phone)}</Descriptions.Item>
                  <Descriptions.Item label="Email Pemilik">{compactText(detailData?.customer?.email)}</Descriptions.Item>
                  <Descriptions.Item label="Luas">{compactText(detailData?.building_area)} / {compactText(detailData?.land_area)} m2</Descriptions.Item>
                  <Descriptions.Item label="Serah Terima">{formatDate(detailData?.handover_date)}</Descriptions.Item>
                  <Descriptions.Item label="Catatan" span={2}>{compactText(detailData?.notes)}</Descriptions.Item>
                </Descriptions>
              ),
            },
            {
              key: 'billings',
              label: 'Tagihan',
              children: (
                <ResponsiveTable
                  data={detailData?.billings || []}
                  pagination={false}
                  columns={[
                    { title: 'Periode', render: (_, row) => formatPeriod(row.year, row.month) },
                    { title: 'Nominal', dataIndex: 'amount', render: formatCurrency },
                    { title: 'Denda', dataIndex: 'penalty', render: formatCurrency },
                    { title: 'Status', dataIndex: 'status_id', render: (value) => <StatusBadge type="billing" value={value} /> },
                  ]}
                />
              ),
            },
          ]}
        />
      </Drawer>

      <Modal
        title="Konversi Kavling Developer"
        open={drawer.type === 'convert'}
        onCancel={() => setDrawer({ type: null, record: null })}
        onOk={() => convertForm.submit()}
        confirmLoading={convert.isPending}
      >
        <Form form={convertForm} layout="vertical" initialValues={{ property_type_id: 'B' }} onFinish={convert.mutate}>
          <Form.Item label="Tipe tujuan" name="property_type_id" rules={[{ required: true }]}>
            <Select options={[{ value: 'B', label: 'Bangunan' }]} />
          </Form.Item>
          <Form.Item label="Catatan" name="notes">
            <Input.TextArea rows={3} />
          </Form.Item>
        </Form>
      </Modal>
    </section>
  );
}
