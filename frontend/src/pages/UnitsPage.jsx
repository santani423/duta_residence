import { Button, Card, Descriptions, Drawer, Dropdown, Form, Input, Modal, Segmented, Select, Space, Tabs, Tag, message } from 'antd';
import { CreditCardOutlined, DeleteOutlined, EditOutlined, EyeOutlined, FileTextOutlined, HistoryOutlined, MoreOutlined, PlusOutlined, SwapOutlined, UserAddOutlined, UserOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import PageHeader from '../components/common/PageHeader.jsx';
import ExportPdfButton from '../components/common/ExportPdfButton.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import Can from '../components/common/Can.jsx';
import UnitForm, { VaSuffixInput, propertyTypeOptions, vaSuffixFromNumber, residentStatusOptions, unitOccupancyStatusOptions } from '../components/forms/UnitForm.jsx';
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
  const [assignForm] = Form.useForm();
  // 'new' = buat penghuni baru, 'existing' = pilih penghuni yang datanya sudah ada (hanya dari aksi unit)
  const [residentMode, setResidentMode] = useState('new');
  const queryClient = useQueryClient();
  const { can } = useAuth();
  const siteName = useSiteIdentity();

  const units = useQuery({ queryKey: ['units', table.params], queryFn: () => api.units.list(table.params) });
  const clusters = useQuery({ queryKey: ['clusters'], queryFn: () => api.clusters.list() });
  const residents = useQuery({ queryKey: ['residents-lookup'], queryFn: () => api.residents.list({ per_page: 1000 }) });
  const vaFormatQuery = useQuery({ queryKey: ['units-va-format'], queryFn: api.units.vaFormat });
  const vaFormat = vaFormatQuery.data?.data;
  const districts = useQuery({ queryKey: ['lookup-districts'], queryFn: () => api.lookup.districts() });
  const detail = useQuery({
    queryKey: ['units', drawer.record?.id],
    queryFn: () => api.units.detail(drawer.record.id),
    enabled: drawer.type === 'detail' && Boolean(drawer.record?.id),
  });

  const save = useMutation({
    mutationFn: (values) => drawer.type === 'edit' ? api.units.update(drawer.record.id, { ...values, resident_id: values.resident_id ?? null }) : api.units.create(values),
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

  const assignResident = useMutation({
    mutationFn: (values) => api.units.assignResident(drawer.record.id, values),
    onSuccess: () => {
      message.success('Penghuni berhasil ditautkan ke unit');
      setDrawer({ type: null, record: null });
      assignForm.resetFields();
      queryClient.invalidateQueries({ queryKey: ['units'] });
      queryClient.invalidateQueries({ queryKey: ['residents-lookup'] });
    },
    onError: (error) => {
      assignForm.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  function openCreate() {
    form.resetFields();
    form.setFieldsValue({ occupancy_id: '2' });
    setDrawer({ type: 'create', record: null });
  }

  function openEdit(record) {
    form.setFieldsValue({ ...record, va_suffix: vaSuffixFromNumber(record.va_number, vaFormat?.prefix) });
    setDrawer({ type: 'edit', record });
  }

  function openAddResident(record = null) {
    residentForm.resetFields();
    assignForm.resetFields();
    setResidentMode('new');
    if (record) residentForm.setFieldsValue({ unit_id: record.id, va_suffix: vaSuffixFromNumber(record.va_number, vaFormat?.prefix) });
    setDrawer({ type: 'add-resident', record });
  }

  const clusterOptions = (clusters.data?.data || []).map((item) => ({ value: item.id, label: item.name }));
  const existingMode = residentMode === 'existing' && Boolean(drawer.record);
  const residentOptions = (residents.data?.data || []).map((item) => ({ value: item.id, label: item.name }));
  const detailData = detail.data?.data;

  return (
    <section>
      <PageHeader
        title="Unit Properti"
        subtitle={`Master data unit dan kepemilikan di ${siteName}.`}
        breadcrumbs={[{ label: 'Unit Properti' }]}
        onRefresh={units.refetch}
        loading={units.isFetching}
        extra={
          <Space wrap>
            <Can permission="units.create"><Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Tambah Unit</Button></Can>
          </Space>
        }
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
                const items = [
                  { key: 'detail', label: 'Detail', icon: <EyeOutlined /> },
                  record.resident?.id
                    ? { key: 'resident', label: 'Detail Penghuni', icon: <UserOutlined /> }
                    : { key: 'add-resident', label: 'Masukan Penghuni', icon: <UserAddOutlined />, permission: 'residents.create' },
                  { key: 'billings', label: 'Tagihan', icon: <FileTextOutlined />, permission: 'billings.view' },
                  { key: 'billing-history', label: 'Riwayat Tagihan', icon: <HistoryOutlined />, permission: 'billings.view' },
                  { key: 'payments', label: 'Pembayaran', icon: <CreditCardOutlined />, permission: 'payments.view' },
                  { key: 'edit', label: 'Edit', icon: <EditOutlined />, permission: 'units.update' },
                  { key: 'convert', label: 'Konversi Properti', icon: <SwapOutlined />, disabled: record.property_type_id !== 'K', permission: 'units.convert-property' },
                  { type: 'divider' },
                  { key: 'delete', label: 'Hapus', icon: <DeleteOutlined />, danger: true, permission: 'units.delete' },
                ].filter((item) => item && (!item.permission || can(item.permission)));
                return (
                  <Dropdown menu={{ items, onClick: ({ key }) => {
                    if (key === 'detail') setDrawer({ type: 'detail', record });
                    if (key === 'resident') navigate(`/residents/${record.resident.id}`);
                    if (key === 'billings') navigate(`/billings?unit_id=${encodeURIComponent(record.id)}`);
                    if (key === 'billing-history') navigate(`/billings/history?unit_id=${encodeURIComponent(record.id)}`);
                    if (key === 'payments') navigate(`/payments?unit_id=${encodeURIComponent(record.id)}`);
                    if (key === 'add-resident') openAddResident(record);
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
        <UnitForm form={form} clusters={clusters.data?.data || []} residents={residents.data?.data || []} vaFormat={vaFormat} currentVaNumber={drawer.record?.va_number} onFinish={save.mutate} loading={save.isPending} isEdit={drawer.type === 'edit'} />
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
                          <Button size="small" type="link" icon={<UserAddOutlined />} onClick={() => openAddResident(detailData)}>Masukan Penghuni</Button>
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
                  <Descriptions.Item label="Status Penghuni">{detailData?.status?.name}</Descriptions.Item>
                  <Descriptions.Item label="Telepon Pemilik">{compactText(detailData?.resident?.phone)}</Descriptions.Item>
                  <Descriptions.Item label="Email Pemilik">{compactText(detailData?.resident?.email)}</Descriptions.Item>
                  <Descriptions.Item label="Luas">{compactText(detailData?.building_area)} / {compactText(detailData?.land_area)} m2</Descriptions.Item>
                  <Descriptions.Item label="Tanggal Aktif">{formatDate(detailData?.handover_date)}</Descriptions.Item>
                  <Descriptions.Item label="Catatan" span={2}>{compactText(detailData?.notes)}</Descriptions.Item>
                </Descriptions>
              ),
            },
            {
              key: 'billings',
              label: 'Tagihan',
              children: (
                <>
                <div style={{ textAlign: 'right', marginBottom: 8 }}>
                  <ExportPdfButton request={() => api.documents.billingRecapPdf({ unit_id: detailData?.id })} filename={`tagihan-${detailData?.id}.pdf`} permission="billings.view" label="Export PDF" />
                </div>
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
                </>
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
        title="Masukan Penghuni"
        open={drawer.type === 'add-resident'}
        onClose={() => setDrawer({ type: null, record: null })}
        width={620}
        extra={(
          <Space>
            <Button onClick={() => setDrawer({ type: null, record: null })}>Batal</Button>
            {existingMode ? (
              <Button type="primary" loading={assignResident.isPending} onClick={() => assignForm.submit()}>Simpan</Button>
            ) : (
              <Button type="primary" loading={createResident.isPending} onClick={() => residentForm.submit()}>Simpan</Button>
            )}
          </Space>
        )}
        destroyOnHidden
      >
        {drawer.record ? (
          <Segmented
            block
            style={{ marginBottom: 16 }}
            value={residentMode}
            onChange={setResidentMode}
            options={[{ value: 'new', label: 'Penghuni Baru' }, { value: 'existing', label: 'Penghuni yang Sudah Ada' }]}
          />
        ) : null}
        {existingMode ? (
          <Form form={assignForm} layout="vertical" onFinish={assignResident.mutate} disabled={assignResident.isPending} initialValues={{ va_suffix: vaSuffixFromNumber(drawer.record.va_number, vaFormat?.prefix) }}>
            <Form.Item label="Unit">
              <Input readOnly value={`${drawer.record.id} - ${drawer.record.cluster?.name || drawer.record.cluster_id} Blok ${drawer.record.block} No ${drawer.record.lot_number}`} />
            </Form.Item>
            <Form.Item label="Penghuni" name="resident_id" rules={[{ required: true, message: 'Penghuni wajib dipilih' }]}>
              <Select
                showSearch
                optionFilterProp="label"
                placeholder="Cari nama penghuni"
                loading={residents.isFetching}
                options={(residents.data?.data || []).map((item) => ({ value: item.id, label: [item.id, item.name, item.phone].filter(Boolean).join(' - ') }))}
              />
            </Form.Item>
            <VaSuffixInput
              vaFormat={vaFormat}
              required
              extraRules={[{
                validator: async (_, value) => {
                  if (!vaFormat?.prefix || !new RegExp(`^\\d{${vaFormat.suffix_length}}$`).test(value || '')) return;
                  const response = await api.residents.checkAvailability({ field: 'va_suffix', value, exclude_id: drawer.record.id }).catch(() => null);
                  if (response?.data?.taken) throw new Error('Nomor virtual account sudah terdaftar');
                },
              }]}
            />
          </Form>
        ) : (
          <ResidentForm
            form={residentForm}
            districts={districts.data?.data || []}
            clusters={clusters.data?.data || []}
            fixedUnit={drawer.record}
            onFinish={createResident.mutate}
            loading={createResident.isPending}
          />
        )}
      </Drawer>

      <Modal
        title="Konversi Kavling"
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
