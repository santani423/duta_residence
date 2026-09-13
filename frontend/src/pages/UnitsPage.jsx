import { Button, Card, DatePicker, Descriptions, Drawer, Dropdown, Form, Input, Modal, Select, Space, Tabs, Tag, message } from 'antd';
import { DeleteOutlined, EditOutlined, EyeOutlined, KeyOutlined, MoreOutlined, PlusOutlined, SwapOutlined, UserAddOutlined, UserOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import dayjs from 'dayjs';
import { useNavigate } from 'react-router-dom';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import Can from '../components/common/Can.jsx';
import UnitForm, { residentStatusOptions, propertyTypeOptions, unitOccupancyStatusOptions } from '../components/forms/UnitForm.jsx';
import ResidentForm from '../components/forms/ResidentForm.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { api } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { useSiteIdentity } from '../hooks/useSiteIdentity.js';
import { compactText, formatCurrency, formatDate, formatDateTime, formatPeriod } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';
import { useAuth } from '../state/AuthContext.jsx';

export default function UnitsPage() {
  const navigate = useNavigate();
  const table = useTableState();
  const [drawer, setDrawer] = useState({ type: null, record: null });
  const [form] = Form.useForm();
  const [convertForm] = Form.useForm();
  const [residentForm] = Form.useForm();
  const [handoverForm] = Form.useForm();
  const queryClient = useQueryClient();
  const { can } = useAuth();
  const siteName = useSiteIdentity();

  const units = useQuery({ queryKey: ['units', table.params], queryFn: () => api.units.list(table.params) });
  const clusters = useQuery({ queryKey: ['clusters'], queryFn: () => api.clusters.list() });
  const residents = useQuery({ queryKey: ['residents-lookup'], queryFn: () => api.residents.list({ per_page: 1000 }) });
  const districts = useQuery({ queryKey: ['lookup-districts'], queryFn: () => api.lookup.districts() });
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

  const createResident = useMutation({
    mutationFn: (values) => api.residents.create(values),
    onSuccess: (response) => {
      message.success('Penghuni berhasil ditambahkan');
      setDrawer({ type: null, record: null });
      residentForm.resetFields();
      queryClient.invalidateQueries({ queryKey: ['units'] });
      queryClient.invalidateQueries({ queryKey: ['residents-lookup'] });

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
            </div>
          ),
        });
      }
    },
    onError: (error) => {
      residentForm.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const handover = useMutation({
    mutationFn: ({ unit, va_number, handover_date }) => api.units.update(unit.id, {
      va_number,
      resident_id: unit.resident_id ?? unit.resident?.id,
      cluster_id: unit.cluster_id,
      block: unit.block,
      lot_number: unit.lot_number,
      property_type_id: unit.property_type_id,
      building_area: unit.building_area,
      land_area: unit.land_area,
      handover_date,
      occupancy_id: unit.occupancy_id,
      status_id: 'AK',
      occupancy_role: unit.occupancy_role,
      tenancy_start_date: unit.tenancy_start_date,
      tenancy_end_date: unit.tenancy_end_date,
      is_penalty_eligible: unit.is_penalty_eligible,
      is_discount_eligible: unit.is_discount_eligible,
      discount_rule_id: unit.discount_rule_id,
      notes: unit.notes,
    }),
    onSuccess: () => {
      message.success('Serah terima kunci berhasil, unit sekarang aktif');
      setDrawer({ type: null, record: null });
      handoverForm.resetFields();
      queryClient.invalidateQueries({ queryKey: ['units'] });
    },
    onError: (error) => {
      handoverForm.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  function openHandover(unit) {
    handoverForm.resetFields();
    handoverForm.setFieldsValue({
      va_number: unit.va_number || undefined,
      handover_date: dayjs(),
    });
    setDrawer({ type: 'handover', record: unit });
  }

  function openCreate() {
    form.resetFields();
    setDrawer({ type: 'create', record: null });
  }

  function openEdit(record) {
    form.setFieldsValue(record);
    setDrawer({ type: 'edit', record });
  }

  function openAddResident(record) {
    residentForm.resetFields();
    residentForm.setFieldsValue({ unit_id: record.id });
    setDrawer({ type: 'add-resident', record });
  }

  const clusterOptions = (clusters.data?.data || []).map((item) => ({ value: item.id, label: item.name }));
  const residentOptions = (residents.data?.data || []).map((item) => ({ value: item.id, label: item.name }));
  const detailData = detail.data?.data;

  return (
    <section>
      <PageHeader
        title="Unit Rumah"
        subtitle={`Master data unit dan kepemilikan di ${siteName}.`}
        breadcrumbs={[{ label: 'Unit Rumah' }]}
        onRefresh={units.refetch}
        loading={units.isFetching}
        extra={<Can permission="units.create"><Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Tambah Unit</Button></Can>}
      />

      <FilterBar>
        <Input allowClear placeholder="Cari ID, blok, nama pemilik" value={table.search} onChange={(event) => table.setSearch(event.target.value)} className="filter-input" />
        <Select allowClear placeholder="Cluster" options={clusterOptions} value={table.filters.cluster_id} onChange={(value) => table.setFilters({ ...table.filters, cluster_id: value })} className="filter-input" />
        <Input allowClear placeholder="No Unit" value={table.filters.lot_number} onChange={(event) => table.setFilters({ ...table.filters, lot_number: event.target.value || undefined })} className="filter-input" />
        <Input allowClear placeholder="Blok" value={table.filters.block} onChange={(event) => table.setFilters({ ...table.filters, block: event.target.value || undefined })} className="filter-input" />
        <Select allowClear placeholder="Status Unit" options={unitOccupancyStatusOptions} value={table.filters.occupancy_status} onChange={(value) => table.setFilters({ ...table.filters, occupancy_status: value })} className="filter-input" />
        <Select allowClear placeholder="Status Penghuni" options={residentStatusOptions} value={table.filters.status_id} onChange={(value) => table.setFilters({ ...table.filters, status_id: value })} className="filter-input" />
        <Select allowClear placeholder="Tipe" options={propertyTypeOptions} value={table.filters.property_type_id} onChange={(value) => table.setFilters({ ...table.filters, property_type_id: value })} className="filter-input" />
      </FilterBar>

      <Card>
        <ResponsiveTable
          query={units}
          onChange={table.handleTableChange}
          scrollX={1180}
          columns={[
            { title: 'ID', dataIndex: 'id', fixed: 'left', width: 90 },
            { title: 'Nomor VA', dataIndex: 'va_number', width: 140, render: (value) => value || <Tag color="default">Belum ada</Tag> },
            { title: 'Pemilik', dataIndex: ['resident', 'name'], width: 220, render: (value) => value || <Tag color="default">Belum ada penghuni</Tag> },
            { title: 'Cluster', dataIndex: ['cluster', 'name'], width: 160 },
            { title: 'Blok', dataIndex: 'block', width: 80 },
            { title: 'No Unit', dataIndex: 'lot_number', width: 90 },
            { title: 'Tipe', render: (_, row) => row.property_type?.name || row.propertyType?.name || row.property_type_id },
            { title: 'Status Unit', width: 130, render: (_, row) => <StatusBadge type="unitOccupancy" value={row.occupancy_status} /> },
            { title: 'Status Penghuni', render: (_, row) => <Tag color={row.status_id === 'AK' ? 'green' : 'default'}>{row.status?.name || row.status_id}</Tag> },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 92,
              render: (_, record) => {
                const canHandover = record.property_type_id === 'B' && Boolean(record.resident?.id) && record.status_id !== 'AK';
                const items = [
                  { key: 'detail', label: 'Detail', icon: <EyeOutlined /> },
                  record.resident?.id
                    ? { key: 'resident', label: 'Detail Penghuni', icon: <UserOutlined /> }
                    : { key: 'add-resident', label: 'Tambah Penghuni', icon: <UserAddOutlined />, permission: 'residents.create' },
                  canHandover
                    ? { key: 'handover', label: 'Serah Terima Kunci', icon: <KeyOutlined />, permission: 'units.update' }
                    : null,
                  { key: 'edit', label: 'Edit', icon: <EditOutlined />, permission: 'units.update' },
                  { key: 'convert', label: 'Konversi Properti', icon: <SwapOutlined />, disabled: record.property_type_id !== 'K', permission: 'units.convert-property' },
                  { type: 'divider' },
                  { key: 'delete', label: 'Hapus', icon: <DeleteOutlined />, danger: true, permission: 'units.delete' },
                ].filter((item) => item && (!item.permission || can(item.permission)));
                return (
                  <Dropdown menu={{ items, onClick: ({ key }) => {
                    if (key === 'detail') setDrawer({ type: 'detail', record });
                    if (key === 'resident') navigate(`/residents/${record.resident.id}`);
                    if (key === 'add-resident') openAddResident(record);
                    if (key === 'handover') openHandover(record);
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
        <UnitForm form={form} clusters={clusters.data?.data || []} residents={residents.data?.data || []} isEdit={drawer.type === 'edit'} onFinish={save.mutate} loading={save.isPending} />
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
                  <Descriptions.Item label="Nomor VA">{detailData?.va_number || <Tag color="default">Belum ada</Tag>}</Descriptions.Item>
                  <Descriptions.Item label="Pemilik">
                    {detailData?.resident?.name || (
                      <Space>
                        <Tag color="default">Belum ada penghuni</Tag>
                        <Can permission="residents.create">
                          <Button size="small" type="link" icon={<UserAddOutlined />} onClick={() => openAddResident(detailData)}>Tambah Penghuni</Button>
                        </Can>
                      </Space>
                    )}
                  </Descriptions.Item>
                  <Descriptions.Item label="Cluster">{detailData?.cluster?.name}</Descriptions.Item>
                  <Descriptions.Item label="Unit">{detailData?.block}-{detailData?.lot_number}</Descriptions.Item>
                  <Descriptions.Item label="Tipe">{detailData?.property_type?.name || detailData?.propertyType?.name}</Descriptions.Item>
                  <Descriptions.Item label="Status Unit">
                    <StatusBadge type="unitOccupancy" value={detailData?.occupancy_status} />
                  </Descriptions.Item>
                  <Descriptions.Item label="Status Penghuni">
                    <Space>
                      {detailData?.status?.name}
                      {detailData?.property_type_id === 'B' && detailData?.resident?.id && detailData?.status_id !== 'AK' ? (
                        <Can permission="units.update">
                          <Button size="small" type="link" icon={<KeyOutlined />} onClick={() => openHandover(detailData)}>
                            Serah Terima Kunci
                          </Button>
                        </Can>
                      ) : null}
                    </Space>
                  </Descriptions.Item>
                  <Descriptions.Item label="Telepon Pemilik">{compactText(detailData?.resident?.phone)}</Descriptions.Item>
                  <Descriptions.Item label="Email Pemilik">{compactText(detailData?.resident?.email)}</Descriptions.Item>
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
                    { title: 'Umur Tunggakan', render: (_, row) => `${row.penalty_detail?.overdue_months ?? 0} bulan` },
                    { title: 'Denda', render: (_, row) => formatCurrency(row.penalty_detail?.penalty_amount ?? 0) },
                    { title: 'Sisa Tagihan', render: (_, row) => formatCurrency(row.penalty_detail?.total_outstanding ?? 0) },
                    { title: 'Status', dataIndex: 'status_id', render: (value) => <StatusBadge type="billing" value={value} /> },
                  ]}
                />
              ),
            },
            {
              key: 'portal',
              label: 'Akun Portal',
              children: (
                <ResponsiveTable
                  data={detailData?.users || []}
                  pagination={false}
                  columns={[
                    { title: 'Username', dataIndex: 'username' },
                    { title: 'Nama', dataIndex: 'name' },
                    { title: 'Role', render: (_, row) => row.roles?.map((role) => <Tag key={role.id}>{role.name}</Tag>) },
                    { title: 'Status', dataIndex: 'is_active', render: (value) => <StatusBadge type="active" value={value} /> },
                    { title: 'Login Terakhir', dataIndex: 'last_login_at', render: formatDateTime },
                  ]}
                />
              ),
            },
          ]}
        />
      </Drawer>

      <Drawer
        title="Tambah Penghuni"
        open={drawer.type === 'add-resident'}
        onClose={() => setDrawer({ type: null, record: null })}
        width={620}
        extra={<Space><Button onClick={() => setDrawer({ type: null, record: null })}>Batal</Button><Button type="primary" loading={createResident.isPending} onClick={() => residentForm.submit()}>Simpan</Button></Space>}
        destroyOnHidden
      >
        <ResidentForm
          form={residentForm}
          districts={districts.data?.data || []}
          clusters={clusters.data?.data || []}
          onFinish={createResident.mutate}
          loading={createResident.isPending}
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

      <Modal
        title="Serah Terima Kunci"
        open={drawer.type === 'handover'}
        onCancel={() => setDrawer({ type: null, record: null })}
        onOk={() => handoverForm.submit()}
        confirmLoading={handover.isPending}
        okText="Aktifkan Unit"
        destroyOnHidden
      >
        <p>
          Unit <strong>{drawer.record?.id}</strong> akan diaktifkan setelah serah terima kunci dan dapat mulai ditagih biaya IPL sejak tanggal serah terima.
        </p>
        <Form
          form={handoverForm}
          layout="vertical"
          onFinish={(values) => handover.mutate({
            unit: drawer.record,
            va_number: values.va_number,
            handover_date: values.handover_date.format('YYYY-MM-DD'),
          })}
        >
          <Form.Item label="Nomor Virtual Account" name="va_number" rules={[{ required: true, message: 'Nomor virtual account wajib diisi' }]}>
            <Input placeholder="Masukkan nomor virtual account" />
          </Form.Item>
          <Form.Item label="Tanggal Serah Terima Kunci" name="handover_date" rules={[{ required: true, message: 'Pilih tanggal serah terima kunci' }]}>
            <DatePicker style={{ width: '100%' }} />
          </Form.Item>
        </Form>
      </Modal>
    </section>
  );
}
