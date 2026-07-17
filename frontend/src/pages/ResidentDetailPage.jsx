import {
  Alert,
  Avatar,
  Button,
  Card,
  Col,
  DatePicker,
  Descriptions,
  Drawer,
  Dropdown,
  Form,
  Input,
  InputNumber,
  Modal,
  Row,
  Segmented,
  Select,
  Space,
  Statistic,
  Table,
  Tabs,
  Tag,
  Typography,
  Upload,
  message,
} from 'antd';
import {
  ArrowLeftOutlined,
  BellOutlined,
  CloudUploadOutlined,
  DeleteOutlined,
  DownloadOutlined,
  EditOutlined,
  EnvironmentOutlined,
  EyeOutlined,
  MoreOutlined,
  PlusOutlined,
  SendOutlined,
  UserOutlined,
} from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import dayjs from 'dayjs';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import Can from '../components/common/Can.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { EmptyData, ErrorState, LoadingState } from '../components/common/ApiState.jsx';
import ResidentForm from '../components/forms/ResidentForm.jsx';
import { api, storageUrl } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { compactText, formatCurrency, formatDate, formatDateTime, formatPeriod } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';
import { useAuth } from '../state/AuthContext.jsx';

const vehicleTypeOptions = [
  { value: 'mobil', label: 'Mobil' },
  { value: 'motor', label: 'Motor' },
  { value: 'lainnya', label: 'Lainnya' },
];

const documentTypeOptions = [
  'KTP', 'Kartu Keluarga', 'Surat Kepemilikan Unit', 'Perjanjian Jual Beli', 'Perjanjian Sewa',
  'Surat Serah Terima Unit', 'Dokumen Kendaraan', 'Surat Kuasa', 'Bukti Pembayaran',
  'Surat Peringatan', 'Surat Pengantar', 'Dokumen Renovasi', 'Lainnya',
].map((value) => ({ value, label: value }));

const serviceCategoryOptions = [
  'Perbaikan', 'Penggantian Kartu Akses', 'Stiker Kendaraan', 'Surat Keterangan',
  'Perubahan Data Penghuni', 'Kebersihan', 'Teknisi', 'Penggunaan Fasilitas', 'Administrasi Lainnya',
].map((value) => ({ value, label: value }));

function unitLabel(unit) {
  return `${unit.id} - ${unit.cluster?.name || ''} ${unit.block}/${unit.lot_number}`;
}

function UnitScopeNote({ unitId, units }) {
  if (!unitId || units.length <= 1) return null;
  const unit = units.find((item) => item.id === unitId);
  return <Alert className="section-row" type="info" showIcon message={`Menampilkan data untuk unit ${unit ? unitLabel(unit) : unitId} saja. Ganti di tab Data Unit untuk melihat unit lain.`} />;
}

// ---------- Ringkasan ----------
function SummaryTab({ residentId, unitId, units }) {
  const query = useQuery({ queryKey: ['residents', residentId, 'summary', unitId], queryFn: () => api.residents.summary(residentId, { unit_id: unitId || undefined }) });
  const data = query.data?.data || {};

  if (query.isLoading) return <LoadingState rows={6} />;
  if (query.isError) return <ErrorState error={query.error} onRetry={query.refetch} />;

  const statusMap = {
    lancar: ['Lancar', 'green'],
    ada_tagihan_berjalan: ['Ada Tagihan Berjalan', 'blue'],
    menunggak: ['Menunggak', 'red'],
  };
  const [statusLabel, statusColor] = statusMap[data.financial_status] || ['-', 'default'];

  return (
    <section>
      <UnitScopeNote unitId={unitId} units={units} />
      <Row gutter={[16, 16]}>
        <Col xs={24} sm={12} lg={6}><Card><Statistic title="Total Tagihan Aktif" value={formatCurrency(data.active_billing_total)} /></Card></Col>
        <Col xs={24} sm={12} lg={6}><Card><Statistic title="Total Tunggakan" value={formatCurrency(data.overdue_total)} valueStyle={{ color: data.overdue_total > 0 ? '#cf1322' : undefined }} /></Card></Col>
        <Col xs={24} sm={12} lg={6}><Card><Statistic title="Total Denda" value={formatCurrency(data.penalty_total)} /></Card></Col>
        <Col xs={24} sm={12} lg={6}><Card><Statistic title="Total Pembayaran" value={formatCurrency(data.paid_total)} /></Card></Col>
        <Col xs={24} sm={12} lg={6}><Card><Statistic title="Sisa Tagihan" value={formatCurrency(data.outstanding_total)} /></Card></Col>
        <Col xs={24} sm={12} lg={6}><Card><Statistic title="Akan Jatuh Tempo" value={data.due_soon_count || 0} suffix="invoice" /><Typography.Text type="secondary">{formatCurrency(data.due_soon_total)}</Typography.Text></Card></Col>
        <Col xs={24} sm={12} lg={6}><Card><Statistic title="Menunggu Verifikasi" value={data.waiting_verification_count || 0} suffix="pembayaran" /></Card></Col>
        <Col xs={24} sm={12} lg={6}><Card><Statistic title="Status Keuangan" valueRender={() => <Tag color={statusColor}>{statusLabel}</Tag>} /></Card></Col>
      </Row>
    </section>
  );
}

// ---------- Data Penghuni ----------
function ResidentInfoTab({ resident, onEdit }) {
  const photo = resident.photos?.[0];
  const accounts = resident.users || [];

  return (
    <section>
      <Card
        className="section-row"
        extra={<Can permission="residents.update"><Button icon={<EditOutlined />} onClick={onEdit}>Edit Data Penghuni</Button></Can>}
      >
        <Space align="center" size="large">
          <Avatar size={80} icon={<UserOutlined />} src={photo ? storageUrl(photo.path) : undefined} />
          <Space direction="vertical" size={0}>
            <Typography.Title level={4} style={{ margin: 0 }}>{resident.name}</Typography.Title>
            <Typography.Text type="secondary">{resident.id}</Typography.Text>
          </Space>
        </Space>
      </Card>

      <Row gutter={[16, 16]}>
        <Col xs={24} md={12} lg={8}>
          <Card title="Kontak" size="small">
            <Descriptions column={1} size="small">
              <Descriptions.Item label="Telepon">{compactText(resident.phone)}</Descriptions.Item>
              <Descriptions.Item label="Telepon Rumah">{compactText(resident.telephone)}</Descriptions.Item>
              <Descriptions.Item label="Email">{compactText(resident.email)}</Descriptions.Item>
            </Descriptions>
          </Card>
        </Col>
        <Col xs={24} md={12} lg={8}>
          <Card title="Identitas & Domisili" size="small">
            <Descriptions column={1} size="small">
              <Descriptions.Item label="Jenis Identitas">{compactText(resident.identity_type)}</Descriptions.Item>
              <Descriptions.Item label="Nomor Identitas">{compactText(resident.identity_number)}</Descriptions.Item>
              <Descriptions.Item label="Kabupaten/Kota">{compactText(resident.district?.name)}</Descriptions.Item>
            </Descriptions>
          </Card>
        </Col>
        <Col xs={24} md={12} lg={8}>
          <Card title="Kontak Darurat" size="small">
            <Descriptions column={1} size="small">
              <Descriptions.Item label="Nama">{compactText(resident.emergency_contact_name)}</Descriptions.Item>
              <Descriptions.Item label="Telepon">{compactText(resident.emergency_contact_phone)}</Descriptions.Item>
            </Descriptions>
          </Card>
        </Col>
        <Col xs={24} lg={8}>
          <Card title="Akun Login (Portal Customer)" size="small">
            {accounts.length === 0 ? (
              <Typography.Text type="secondary">Belum ada akun login untuk penghuni ini.</Typography.Text>
            ) : (
              <Space direction="vertical" size="middle" style={{ width: '100%' }}>
                {accounts.map((account) => (
                  <div key={account.id}>
                    <Space wrap>
                      <Typography.Text strong>{account.username}</Typography.Text>
                      <StatusBadge type="active" value={account.is_active} />
                    </Space>
                    <div>
                      {account.unit_id ? (
                        <Typography.Text type="secondary">Unit: {account.unit_id}</Typography.Text>
                      ) : (
                        <Tag color="orange">Menunggu Unit</Tag>
                      )}
                    </div>
                  </div>
                ))}
              </Space>
            )}
          </Card>
        </Col>
        <Col xs={24} lg={16}>
          <Card title="Alamat & Catatan" size="small">
            <Descriptions column={1} size="small">
              <Descriptions.Item label="Alamat KTP">{compactText(resident.id_card_address)}</Descriptions.Item>
              <Descriptions.Item label="Catatan">{compactText(resident.notes)}</Descriptions.Item>
            </Descriptions>
          </Card>
        </Col>
      </Row>
    </section>
  );
}

// ---------- Data Unit ----------
function UnitInfoTab({ resident, units, unitId, onSelectUnit }) {
  const queryClient = useQueryClient();
  const [form] = Form.useForm();
  const [drawerUnit, setDrawerUnit] = useState(null);

  const update = useMutation({
    mutationFn: (values) => api.units.update(drawerUnit.id, { ...drawerUnit, ...values }),
    onSuccess: () => {
      message.success('Data unit berhasil diperbarui');
      setDrawerUnit(null);
      queryClient.invalidateQueries({ queryKey: ['residents', resident.id] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const shown = unitId ? units.filter((unit) => unit.id === unitId) : units;

  return (
    <section>
      {units.length > 1 ? (
        <Segmented
          className="section-row"
          value={unitId || 'all'}
          onChange={(value) => onSelectUnit(value === 'all' ? null : value)}
          options={[{ value: 'all', label: 'Semua Unit' }, ...units.map((unit) => ({ value: unit.id, label: unit.id }))]}
        />
      ) : null}
      <Row gutter={[16, 16]}>
        {shown.map((unit) => (
          <Col xs={24} lg={units.length > 1 && !unitId ? 12 : 24} key={unit.id}>
            <Card
              title={unitLabel(unit)}
              extra={<Can permission="units.update"><Button size="small" icon={<EditOutlined />} onClick={() => { form.setFieldsValue({ ...unit, tenancy_start_date: unit.tenancy_start_date ? dayjs(unit.tenancy_start_date) : null, tenancy_end_date: unit.tenancy_end_date ? dayjs(unit.tenancy_end_date) : null }); setDrawerUnit(unit); }}>Edit</Button></Can>}
            >
              <Descriptions bordered column={{ xs: 1, md: 2 }} size="small">
                <Descriptions.Item label="Cluster">{unit.cluster?.name}</Descriptions.Item>
                <Descriptions.Item label="Blok">{unit.block}</Descriptions.Item>
                <Descriptions.Item label="Nomor Unit">{unit.id}</Descriptions.Item>
                <Descriptions.Item label="Kavling">{unit.lot_number}</Descriptions.Item>
                <Descriptions.Item label="Jenis Unit">{unit.property_type?.name}</Descriptions.Item>
                <Descriptions.Item label="Status Kepemilikan">
                  <Tag>{{ pemilik: 'Pemilik', penyewa: 'Penyewa', keluarga: 'Keluarga', sementara: 'Sementara' }[unit.occupancy_role] || unit.occupancy_role}</Tag>
                </Descriptions.Item>
                <Descriptions.Item label="Status Hunian">{unit.occupancy?.name}</Descriptions.Item>
                <Descriptions.Item label="Luas Tanah">{compactText(unit.land_area)} m2</Descriptions.Item>
                <Descriptions.Item label="Luas Bangunan">{compactText(unit.building_area)} m2</Descriptions.Item>
                <Descriptions.Item label="Tarif Cluster">{formatCurrency(unit.cluster?.monthly_rate)}</Descriptions.Item>
                <Descriptions.Item label="Mulai Menempati">{formatDate(unit.tenancy_start_date || unit.handover_date)}</Descriptions.Item>
                {unit.occupancy_role === 'penyewa' ? <Descriptions.Item label="Akhir Sewa">{formatDate(unit.tenancy_end_date)}</Descriptions.Item> : null}
                <Descriptions.Item label="Status Unit">{unit.status?.name}</Descriptions.Item>
                <Descriptions.Item label="Penyewa">{unit.tenant_resident?.name || 'Tidak ada'}</Descriptions.Item>
                <Descriptions.Item label="Penanggung Jawab Tagihan">{unit.billing_payer === 'penyewa' ? 'Penyewa' : 'Pemilik'}</Descriptions.Item>
              </Descriptions>
              {units.length > 1 && unitId !== unit.id ? <Button className="section-row" onClick={() => onSelectUnit(unit.id)}>Pilih Unit Ini</Button> : null}
            </Card>
          </Col>
        ))}
      </Row>
      <Drawer title={`Edit Unit ${drawerUnit?.id || ''}`} open={Boolean(drawerUnit)} onClose={() => setDrawerUnit(null)} width={480} extra={<Space><Button onClick={() => setDrawerUnit(null)}>Batal</Button><Button type="primary" loading={update.isPending} onClick={() => form.submit()}>Simpan</Button></Space>} destroyOnHidden>
        <Form form={form} layout="vertical" onFinish={(values) => update.mutate({
          ...values,
          tenancy_start_date: values.tenancy_start_date?.format?.('YYYY-MM-DD') || null,
          tenancy_end_date: values.tenancy_end_date?.format?.('YYYY-MM-DD') || null,
        })}
        >
          <Form.Item label="Status Kepemilikan" name="occupancy_role" rules={[{ required: true }]}>
            <Select options={[{ value: 'pemilik', label: 'Pemilik' }, { value: 'penyewa', label: 'Penyewa' }, { value: 'keluarga', label: 'Keluarga' }, { value: 'sementara', label: 'Sementara' }]} />
          </Form.Item>
          <Form.Item label="Tanggal Mulai Menempati" name="tenancy_start_date">
            <DatePicker style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Tanggal Akhir Sewa" name="tenancy_end_date">
            <DatePicker style={{ width: '100%' }} />
          </Form.Item>
        </Form>
      </Drawer>
    </section>
  );
}

// ---------- Tagihan ----------
function BillingsTab({ residentId, unitId, units }) {
  const table = useTableState();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const { can } = useAuth();
  const query = useQuery({ queryKey: ['residents', residentId, 'billings', unitId, table.params], queryFn: () => api.residents.billings(residentId, { ...table.params, unit_id: unitId || undefined }) });

  const create = useMutation({
    mutationFn: (values) => api.billings.prepareSpecial({ unit_id: values.unit_id, year: values.period.year(), month: values.period.month() + 1, amount: values.amount }),
    onSuccess: () => {
      message.success('Tagihan khusus berhasil dibuat');
      setDrawerOpen(false);
      form.resetFields();
      queryClient.invalidateQueries({ queryKey: ['residents', residentId, 'billings'] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  return (
    <section>
      <UnitScopeNote unitId={unitId} units={units} />
      <FilterBar extra={can('billings.prepare-special') ? <Button type="primary" icon={<PlusOutlined />} onClick={() => setDrawerOpen(true)}>Buat Tagihan Khusus</Button> : null}>
        <InputNumber placeholder="Tahun" value={table.filters.year} onChange={(value) => table.setFilters({ ...table.filters, year: value })} className="filter-input" />
        <Select allowClear placeholder="Bulan" value={table.filters.month} onChange={(value) => table.setFilters({ ...table.filters, month: value })} className="filter-input" options={Array.from({ length: 12 }, (_, index) => ({ value: index + 1, label: dayjs().month(index).format('MMMM') }))} />
        <Select allowClear placeholder="Jenis Tagihan" value={table.filters.billing_type} onChange={(value) => table.setFilters({ ...table.filters, billing_type: value })} className="filter-input" options={[{ value: 'regular', label: 'Reguler' }, { value: 'special', label: 'Khusus' }, { value: 'back', label: 'Mundur' }]} />
        <Select allowClear placeholder="Status" value={table.filters.status_id} onChange={(value) => table.setFilters({ ...table.filters, status_id: value })} className="filter-input" options={[{ value: '01', label: 'Belum Bayar' }, { value: '02', label: 'Lunas' }, { value: '03', label: 'Sebagian' }, { value: '04', label: 'Dibatalkan' }]} />
      </FilterBar>
      <Card>
        <ResponsiveTable
          query={query}
          onChange={table.handleTableChange}
          scrollX={1530}
          columns={[
            { title: 'Invoice', render: (_, row) => `BIL-${row.id}`, width: 110, fixed: 'left' },
            { title: 'Unit', dataIndex: 'unit_id', width: 90 },
            { title: 'Jenis', dataIndex: 'billing_type', width: 100 },
            { title: 'Periode', render: (_, row) => formatPeriod(row.year, row.month), width: 130 },
            { title: 'Nominal', dataIndex: 'amount', render: formatCurrency, width: 130 },
            { title: 'Diskon', dataIndex: 'discount', render: formatCurrency, width: 120 },
            { title: 'Umur Tunggakan', render: (_, row) => `${row.penalty_detail?.overdue_months ?? 0} bulan`, width: 130 },
            { title: 'Denda', render: (_, row) => formatCurrency(row.penalty_detail?.penalty_amount ?? 0), width: 120 },
            { title: 'Total', render: (_, row) => formatCurrency(row.penalty_detail?.total_amount ?? row.amount), width: 130 },
            { title: 'Dibayar', render: (_, row) => formatCurrency(row.penalty_detail?.total_paid ?? 0), width: 130 },
            { title: 'Sisa', render: (_, row) => formatCurrency(row.penalty_detail?.total_outstanding ?? 0), width: 130 },
            { title: 'Status', dataIndex: 'status_id', render: (value) => <StatusBadge type="billing" value={value} />, width: 120 },
          ]}
        />
      </Card>
      <Drawer title="Buat Tagihan Khusus" open={drawerOpen} onClose={() => setDrawerOpen(false)} width={420} extra={<Space><Button onClick={() => setDrawerOpen(false)}>Batal</Button><Button type="primary" loading={create.isPending} onClick={() => form.submit()}>Simpan</Button></Space>} destroyOnHidden>
        <Form form={form} layout="vertical" onFinish={create.mutate} initialValues={{ unit_id: unitId || units[0]?.id, period: dayjs() }}>
          <Form.Item label="Unit" name="unit_id" rules={[{ required: true }]}>
            <Select options={units.map((unit) => ({ value: unit.id, label: unitLabel(unit) }))} />
          </Form.Item>
          <Form.Item label="Periode" name="period" rules={[{ required: true }]}>
            <DatePicker picker="month" style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Nominal" name="amount" rules={[{ required: true }]}>
            <InputNumber min={0} style={{ width: '100%' }} />
          </Form.Item>
        </Form>
      </Drawer>
    </section>
  );
}

// ---------- Transaksi ----------
function TransactionsTab({ residentId, unitId, units }) {
  const table = useTableState();
  const receiptTable = useTableState();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const transactions = useQuery({ queryKey: ['residents', residentId, 'transactions', unitId, table.params], queryFn: () => api.residents.transactions(residentId, { ...table.params, unit_id: unitId || undefined }) });
  const receipts = useQuery({ queryKey: ['residents', residentId, 'receipts', unitId, receiptTable.params], queryFn: () => api.residents.receipts(residentId, { ...receiptTable.params, unit_id: unitId || undefined }) });

  const send = useMutation({
    mutationFn: (receiptNumber) => api.residents.sendReceipt(residentId, receiptNumber),
    onSuccess: () => {
      message.success('Kuitansi berhasil dikirim ke penghuni');
      queryClient.invalidateQueries({ queryKey: ['residents', residentId, 'notifications'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  return (
    <section>
      <UnitScopeNote unitId={unitId} units={units} />
      <Card title="Transaksi Pembayaran" extra={<Button onClick={() => navigate('/payments')}>Proses Pembayaran</Button>}>
        <ResponsiveTable
          query={transactions}
          onChange={table.handleTableChange}
          scrollX={1500}
          columns={[
            { title: 'No. Transaksi', dataIndex: 'transaction_number', width: 190, fixed: 'left' },
            { title: 'Unit', dataIndex: 'unit_id', width: 90 },
            { title: 'Nominal', dataIndex: 'total', render: formatCurrency, width: 130 },
            { title: 'Metode', dataIndex: 'payment_method', width: 130 },
            { title: 'Petugas', render: (_, row) => row.creator?.name || '-', width: 150 },
            { title: 'Referensi', dataIndex: 'provider_reference', width: 180 },
            { title: 'Bukti', render: (_, row) => (row.manual_proof_path ? <a href={storageUrl(row.manual_proof_path)} target="_blank" rel="noreferrer">Lihat Bukti</a> : '-'), width: 110 },
            { title: 'Verifikasi', render: (_, row) => (row.verified_at ? `${row.verifier?.name || '-'} (${formatDateTime(row.verified_at)})` : '-'), width: 200 },
            { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge type="transaction" value={value} />, width: 160 },
            { title: 'Catatan', render: (_, row) => compactText(row.manual_notes || row.verification_notes), width: 200 },
          ]}
        />
      </Card>
      <Card className="section-row" title="Kuitansi (Loket)">
        <ResponsiveTable
          query={receipts}
          onChange={receiptTable.handleTableChange}
          rowKey="number"
          scrollX={1100}
          columns={[
            { title: 'Nomor', dataIndex: 'number', width: 190, fixed: 'left' },
            { title: 'Unit', dataIndex: 'unit_id', width: 90 },
            { title: 'Tanggal', dataIndex: 'transaction_date', render: formatDateTime, width: 170 },
            { title: 'Kasir', dataIndex: 'cashier_name', width: 140 },
            { title: 'Total', dataIndex: 'grand_total', render: formatCurrency, width: 140 },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 260,
              render: (_, row) => (
                <Space>
                  <Button size="small" icon={<EyeOutlined />} href={api.documents.url(`/documents/spt/${row.number}`)} target="_blank">Lihat/Cetak</Button>
                  <Button size="small" icon={<DownloadOutlined />} href={api.documents.url(`/documents/spt/${row.number}`)} target="_blank">Unduh</Button>
                  <Button size="small" icon={<SendOutlined />} loading={send.isPending} onClick={() => send.mutate(row.number)}>Kirim</Button>
                </Space>
              ),
            },
          ]}
        />
      </Card>
    </section>
  );
}

// ---------- Komplain ----------
function ComplaintsTab({ residentId, unitId, units }) {
  const table = useTableState();
  const [open, setOpen] = useState(false);
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ['residents', residentId, 'complaints', unitId, table.params], queryFn: () => api.residents.complaints(residentId, { ...table.params, unit_id: unitId || undefined }) });

  const create = useMutation({
    mutationFn: (values) => api.residents.createComplaint(residentId, values),
    onSuccess: () => {
      message.success('Komplain berhasil dibuat');
      setOpen(false);
      form.resetFields();
      queryClient.invalidateQueries({ queryKey: ['residents', residentId, 'complaints'] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  return (
    <section>
      <UnitScopeNote unitId={unitId} units={units} />
      <FilterBar extra={<Can permission="residents.update"><Button type="primary" icon={<PlusOutlined />} onClick={() => setOpen(true)}>Buat Komplain</Button></Can>}>
        <Select allowClear placeholder="Status" value={table.filters.status} onChange={(value) => table.setFilters({ ...table.filters, status: value })} className="filter-input" options={['submitted', 'in_review', 'in_progress', 'resolved', 'closed', 'rejected'].map((value) => ({ value, label: value }))} />
      </FilterBar>
      <Card>
        <ResponsiveTable
          query={query}
          onChange={table.handleTableChange}
          scrollX={1200}
          columns={[
            { title: 'Judul', dataIndex: 'title', width: 220, fixed: 'left' },
            { title: 'Kategori', dataIndex: 'category', width: 130 },
            { title: 'Prioritas', dataIndex: 'priority', width: 100 },
            { title: 'Divisi', dataIndex: 'division', width: 120 },
            { title: 'PIC', render: (_, row) => row.assignee?.name || '-', width: 150 },
            { title: 'Target Selesai', dataIndex: 'target_date', render: (value) => formatDate(value), width: 130 },
            { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge type="transaction" value={value} />, width: 140 },
            { title: 'Dibuat', dataIndex: 'created_at', render: formatDateTime, width: 170 },
          ]}
        />
      </Card>
      <Drawer title="Buat Komplain" open={open} onClose={() => setOpen(false)} width={480} extra={<Space><Button onClick={() => setOpen(false)}>Batal</Button><Button type="primary" loading={create.isPending} onClick={() => form.submit()}>Simpan</Button></Space>} destroyOnHidden>
        <Form form={form} layout="vertical" onFinish={create.mutate} initialValues={{ unit_id: unitId || units[0]?.id, priority: 'normal' }}>
          <Form.Item label="Unit" name="unit_id" rules={[{ required: true }]}>
            <Select options={units.map((unit) => ({ value: unit.id, label: unitLabel(unit) }))} />
          </Form.Item>
          <Form.Item label="Judul" name="title" rules={[{ required: true }]}><Input /></Form.Item>
          <Form.Item label="Kategori" name="category"><Input /></Form.Item>
          <Form.Item label="Prioritas" name="priority" rules={[{ required: true }]}>
            <Select options={['low', 'normal', 'high', 'urgent'].map((value) => ({ value, label: value }))} />
          </Form.Item>
          <Form.Item label="Divisi Tujuan" name="division"><Input placeholder="CS, Teknisi, Keamanan, dst" /></Form.Item>
          <Form.Item label="Target Selesai" name="target_date"><DatePicker style={{ width: '100%' }} /></Form.Item>
          <Form.Item label="Deskripsi" name="description" rules={[{ required: true }]}><Input.TextArea rows={4} /></Form.Item>
        </Form>
      </Drawer>
    </section>
  );
}

// ---------- Layanan ----------
function ServiceRequestsTab({ residentId, unitId, units }) {
  const table = useTableState();
  const [open, setOpen] = useState(false);
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ['residents', residentId, 'maintenance', unitId, table.params], queryFn: () => api.residents.maintenanceRequests(residentId, { ...table.params, unit_id: unitId || undefined }) });

  const create = useMutation({
    mutationFn: (values) => api.residents.createMaintenanceRequest(residentId, values),
    onSuccess: () => {
      message.success('Permintaan layanan berhasil dibuat');
      setOpen(false);
      form.resetFields();
      queryClient.invalidateQueries({ queryKey: ['residents', residentId, 'maintenance'] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  return (
    <section>
      <UnitScopeNote unitId={unitId} units={units} />
      <FilterBar extra={<Can permission="residents.update"><Button type="primary" icon={<PlusOutlined />} onClick={() => setOpen(true)}>Buat Permintaan Layanan</Button></Can>}>
        <Select allowClear placeholder="Status" value={table.filters.status} onChange={(value) => table.setFilters({ ...table.filters, status: value })} className="filter-input" options={['submitted', 'in_review', 'scheduled', 'in_progress', 'completed', 'closed', 'rejected'].map((value) => ({ value, label: value }))} />
      </FilterBar>
      <Card>
        <ResponsiveTable
          query={query}
          onChange={table.handleTableChange}
          scrollX={1200}
          columns={[
            { title: 'Kategori', dataIndex: 'category', width: 200, fixed: 'left' },
            { title: 'Urgensi', dataIndex: 'urgency', width: 110 },
            { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge type="transaction" value={value} />, width: 140 },
            { title: 'Petugas', dataIndex: 'technician_name', width: 150 },
            { title: 'Jadwal Teknisi', dataIndex: 'technician_schedule', render: formatDateTime, width: 170 },
            { title: 'Selesai', dataIndex: 'completed_at', render: formatDateTime, width: 170 },
            { title: 'Dibuat', dataIndex: 'created_at', render: formatDateTime, width: 170 },
          ]}
        />
      </Card>
      <Drawer title="Buat Permintaan Layanan" open={open} onClose={() => setOpen(false)} width={480} extra={<Space><Button onClick={() => setOpen(false)}>Batal</Button><Button type="primary" loading={create.isPending} onClick={() => form.submit()}>Simpan</Button></Space>} destroyOnHidden>
        <Form form={form} layout="vertical" onFinish={create.mutate} initialValues={{ unit_id: unitId || units[0]?.id, urgency: 'normal' }}>
          <Form.Item label="Unit" name="unit_id" rules={[{ required: true }]}>
            <Select options={units.map((unit) => ({ value: unit.id, label: unitLabel(unit) }))} />
          </Form.Item>
          <Form.Item label="Kategori" name="category" rules={[{ required: true }]}>
            <Select showSearch options={serviceCategoryOptions} />
          </Form.Item>
          <Form.Item label="Urgensi" name="urgency" rules={[{ required: true }]}>
            <Select options={['low', 'normal', 'high', 'emergency'].map((value) => ({ value, label: value }))} />
          </Form.Item>
          <Form.Item label="Jadwal Diinginkan" name="preferred_schedule"><DatePicker showTime style={{ width: '100%' }} /></Form.Item>
          <Form.Item label="Deskripsi" name="description" rules={[{ required: true }]}><Input.TextArea rows={4} /></Form.Item>
        </Form>
      </Drawer>
    </section>
  );
}

// ---------- Kunjungan Collector ----------
function VisitsTab({ residentId, unitId, units }) {
  const table = useTableState();
  const [drawer, setDrawer] = useState({ open: false, record: null });
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ['residents', residentId, 'visits', unitId, table.params], queryFn: () => api.residents.visits(residentId, { ...table.params, unit_id: unitId || undefined }) });

  const save = useMutation({
    mutationFn: (values) => (drawer.record ? api.visits.update(drawer.record.id, values) : api.visits.create(values.unit_id, values)),
    onSuccess: () => {
      message.success('Kunjungan berhasil disimpan');
      setDrawer({ open: false, record: null });
      form.resetFields();
      queryClient.invalidateQueries({ queryKey: ['residents', residentId, 'visits'] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  function useCurrentLocation() {
    if (!navigator.geolocation) {
      message.warning('Perangkat tidak mendukung lokasi.');
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (position) => form.setFieldsValue({ checkin_latitude: position.coords.latitude, checkin_longitude: position.coords.longitude }),
      () => message.error('Gagal mengambil lokasi.'),
    );
  }

  return (
    <section>
      <UnitScopeNote unitId={unitId} units={units} />
      <FilterBar extra={<Can permission="visits.create"><Button type="primary" icon={<PlusOutlined />} onClick={() => { form.resetFields(); setDrawer({ open: true, record: null }); }}>Jadwalkan/Catat Kunjungan</Button></Can>}>
        <Select allowClear placeholder="Status" value={table.filters.status} onChange={(value) => table.setFilters({ ...table.filters, status: value })} className="filter-input" options={['completed', 'no_answer', 'refused', 'rescheduled'].map((value) => ({ value, label: value }))} />
      </FilterBar>
      <Card>
        <ResponsiveTable
          query={query}
          onChange={table.handleTableChange}
          scrollX={1300}
          columns={[
            { title: 'Tanggal', dataIndex: 'visit_date', render: formatDateTime, width: 170, fixed: 'left' },
            { title: 'Unit', dataIndex: 'unit_id', width: 90 },
            { title: 'Tujuan', dataIndex: 'purpose', width: 150 },
            { title: 'Hasil', dataIndex: 'result', width: 220 },
            { title: 'Ditemui', dataIndex: 'met_with', width: 140 },
            { title: 'Collector', render: (_, row) => row.collector?.name || '-', width: 150 },
            { title: 'Lokasi', render: (_, row) => (row.checkin_latitude ? <a target="_blank" rel="noreferrer" href={`https://maps.google.com/?q=${row.checkin_latitude},${row.checkin_longitude}`}><EnvironmentOutlined /> Lihat</a> : '-'), width: 100 },
            { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge type="transaction" value={value} />, width: 130 },
            { title: 'Jadwal Berikutnya', dataIndex: 'next_visit_date', render: (value) => formatDate(value), width: 150 },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 90,
              render: (_, row) => (
                <Can permission="visits.update">
                  <Button size="small" icon={<EditOutlined />} onClick={() => {
                    form.setFieldsValue({ ...row, visit_date: dayjs(row.visit_date), next_visit_date: row.next_visit_date ? dayjs(row.next_visit_date) : null });
                    setDrawer({ open: true, record: row });
                  }}
                  />
                </Can>
              ),
            },
          ]}
        />
      </Card>
      <Drawer title={drawer.record ? 'Edit Kunjungan' : 'Catat Kunjungan'} open={drawer.open} onClose={() => setDrawer({ open: false, record: null })} width={480} extra={<Space><Button onClick={() => setDrawer({ open: false, record: null })}>Batal</Button><Button type="primary" loading={save.isPending} onClick={() => form.submit()}>Simpan</Button></Space>} destroyOnHidden>
        <Form form={form} layout="vertical" onFinish={(values) => save.mutate({ ...values, visit_date: values.visit_date.format('YYYY-MM-DD HH:mm:ss'), next_visit_date: values.next_visit_date?.format?.('YYYY-MM-DD') || null })} initialValues={{ unit_id: unitId || units[0]?.id, visit_date: dayjs(), status: 'completed' }}>
          {!drawer.record ? (
            <Form.Item label="Unit" name="unit_id" rules={[{ required: true }]}>
              <Select options={units.map((unit) => ({ value: unit.id, label: unitLabel(unit) }))} disabled={Boolean(drawer.record)} />
            </Form.Item>
          ) : null}
          <Form.Item label="Tanggal & Waktu Kunjungan" name="visit_date" rules={[{ required: true }]}><DatePicker showTime style={{ width: '100%' }} /></Form.Item>
          <Form.Item label="Tujuan Kunjungan" name="purpose" rules={[{ required: true }]}><Input placeholder="Penagihan, Survei, dst" /></Form.Item>
          <Form.Item label="Hasil Kunjungan" name="result"><Input.TextArea rows={2} /></Form.Item>
          <Form.Item label="Ditemui Dengan" name="met_with"><Input /></Form.Item>
          <Form.Item label="Status" name="status" rules={[{ required: true }]}>
            <Select options={['completed', 'no_answer', 'refused', 'rescheduled'].map((value) => ({ value, label: value }))} />
          </Form.Item>
          <Form.Item label="Jadwal Kunjungan Berikutnya" name="next_visit_date"><DatePicker style={{ width: '100%' }} /></Form.Item>
          <Form.Item label="Catatan" name="notes"><Input.TextArea rows={2} /></Form.Item>
          <Space className="section-row">
            <Button icon={<EnvironmentOutlined />} onClick={useCurrentLocation}>Ambil Lokasi Saat Ini</Button>
          </Space>
          <Form.Item label="Latitude" name="checkin_latitude"><InputNumber style={{ width: '100%' }} /></Form.Item>
          <Form.Item label="Longitude" name="checkin_longitude"><InputNumber style={{ width: '100%' }} /></Form.Item>
        </Form>
      </Drawer>
    </section>
  );
}

// ---------- Janji Pembayaran ----------
function PaymentPromisesTab({ residentId, unitId, units }) {
  const table = useTableState();
  const [drawer, setDrawer] = useState({ open: false, record: null });
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ['residents', residentId, 'promises', unitId, table.params], queryFn: () => api.residents.paymentPromises(residentId, { ...table.params, unit_id: unitId || undefined }) });

  const save = useMutation({
    mutationFn: (values) => (drawer.record ? api.paymentPromises.update(drawer.record.id, values) : api.paymentPromises.create(values.unit_id, values)),
    onSuccess: () => {
      message.success('Janji pembayaran berhasil disimpan');
      setDrawer({ open: false, record: null });
      form.resetFields();
      queryClient.invalidateQueries({ queryKey: ['residents', residentId, 'promises'] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  return (
    <section>
      <UnitScopeNote unitId={unitId} units={units} />
      <FilterBar extra={<Can permission="payment-promises.create"><Button type="primary" icon={<PlusOutlined />} onClick={() => { form.resetFields(); setDrawer({ open: true, record: null }); }}>Catat Janji Pembayaran</Button></Can>}>
        <Select allowClear placeholder="Status" value={table.filters.status} onChange={(value) => table.setFilters({ ...table.filters, status: value })} className="filter-input" options={['pending', 'fulfilled', 'broken', 'rescheduled'].map((value) => ({ value, label: value }))} />
      </FilterBar>
      <Card>
        <ResponsiveTable
          query={query}
          onChange={table.handleTableChange}
          scrollX={1250}
          columns={[
            { title: 'Tanggal Janji', dataIndex: 'promised_date', render: (value) => formatDate(value), width: 140, fixed: 'left' },
            { title: 'Unit', dataIndex: 'unit_id', width: 90 },
            { title: 'Nominal', dataIndex: 'promised_amount', render: formatCurrency, width: 140 },
            { title: 'Metode', dataIndex: 'payment_method', width: 120 },
            { title: 'Alasan', dataIndex: 'reason', width: 200 },
            { title: 'Follow-up', dataIndex: 'follow_up_date', render: (value) => formatDate(value), width: 130 },
            { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge type="transaction" value={value} />, width: 130 },
            { title: 'Dicatat Oleh', render: (_, row) => row.creator?.name || '-', width: 150 },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 90,
              render: (_, row) => (
                <Can permission="payment-promises.update">
                  <Button size="small" icon={<EditOutlined />} onClick={() => {
                    form.setFieldsValue({ ...row, promised_date: dayjs(row.promised_date), follow_up_date: row.follow_up_date ? dayjs(row.follow_up_date) : null });
                    setDrawer({ open: true, record: row });
                  }}
                  />
                </Can>
              ),
            },
          ]}
        />
      </Card>
      <Drawer title={drawer.record ? 'Edit Janji Pembayaran' : 'Catat Janji Pembayaran'} open={drawer.open} onClose={() => setDrawer({ open: false, record: null })} width={480} extra={<Space><Button onClick={() => setDrawer({ open: false, record: null })}>Batal</Button><Button type="primary" loading={save.isPending} onClick={() => form.submit()}>Simpan</Button></Space>} destroyOnHidden>
        <Form form={form} layout="vertical" onFinish={(values) => save.mutate({ ...values, promised_date: values.promised_date.format('YYYY-MM-DD'), follow_up_date: values.follow_up_date?.format?.('YYYY-MM-DD') || null })} initialValues={{ unit_id: unitId || units[0]?.id, status: 'pending' }}>
          {!drawer.record ? (
            <Form.Item label="Unit" name="unit_id" rules={[{ required: true }]}>
              <Select options={units.map((unit) => ({ value: unit.id, label: unitLabel(unit) }))} />
            </Form.Item>
          ) : null}
          <Form.Item label="Nominal Dijanjikan" name="promised_amount" rules={[{ required: true }]}><InputNumber min={0} style={{ width: '100%' }} /></Form.Item>
          <Form.Item label="Tanggal Janji" name="promised_date" rules={[{ required: true }]}><DatePicker style={{ width: '100%' }} /></Form.Item>
          <Form.Item label="Metode Pembayaran" name="payment_method"><Input placeholder="Transfer, Cash, dst" /></Form.Item>
          <Form.Item label="Alasan Penundaan" name="reason"><Input.TextArea rows={2} /></Form.Item>
          <Form.Item label="Tanggal Follow-up" name="follow_up_date"><DatePicker style={{ width: '100%' }} /></Form.Item>
          <Form.Item label="Status" name="status" rules={[{ required: true }]}>
            <Select options={['pending', 'fulfilled', 'broken', 'rescheduled'].map((value) => ({ value, label: value }))} />
          </Form.Item>
          <Form.Item label="Catatan" name="notes"><Input.TextArea rows={2} /></Form.Item>
        </Form>
      </Drawer>
    </section>
  );
}

// ---------- Dokumen ----------
function DocumentsTab({ residentId, unitId, units }) {
  const { can } = useAuth();
  const table = useTableState();
  const [open, setOpen] = useState(false);
  const [verifyRecord, setVerifyRecord] = useState(null);
  const [form] = Form.useForm();
  const [verifyForm] = Form.useForm();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ['residents', residentId, 'documents', unitId, table.params], queryFn: () => api.residents.documents(residentId, { ...table.params, unit_id: unitId || undefined }) });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['residents', residentId, 'documents'] });

  const create = useMutation({
    mutationFn: (values) => {
      const formData = new FormData();
      const dateFields = ['document_date', 'valid_from', 'valid_until'];
      Object.entries(values).forEach(([key, val]) => {
        if (key === 'file' && val?.[0]?.originFileObj) formData.append('file', val[0].originFileObj);
        else if (key === 'target') return;
        else if (dateFields.includes(key) && val) formData.append(key, val.format('YYYY-MM-DD'));
        else if (val !== undefined && val !== null) formData.append(key, val);
      });
      return values.target === 'resident' ? api.residents.createDocument(residentId, formData) : api.residentDocuments.createForUnit(values.target, formData);
    },
    onSuccess: () => {
      message.success('Dokumen berhasil diunggah');
      setOpen(false);
      form.resetFields();
      invalidate();
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const verify = useMutation({
    mutationFn: (values) => api.residentDocuments.update(verifyRecord.id, { document_type: verifyRecord.document_type, ...values }),
    onSuccess: () => {
      message.success('Verifikasi dokumen berhasil disimpan');
      setVerifyRecord(null);
      invalidate();
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const remove = useMutation({
    mutationFn: (id) => api.residentDocuments.remove(id),
    onSuccess: () => { message.success('Dokumen berhasil dihapus'); invalidate(); },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const verificationMap = { pending: ['Menunggu', 'gold'], verified: ['Terverifikasi', 'green'], rejected: ['Ditolak', 'red'] };

  return (
    <section>
      <UnitScopeNote unitId={unitId} units={units} />
      <FilterBar extra={<Can permission="resident-documents.create"><Button type="primary" icon={<PlusOutlined />} onClick={() => { form.resetFields(); setOpen(true); }}>Tambah Dokumen</Button></Can>}>
        <Select allowClear placeholder="Jenis Dokumen" value={table.filters.document_type} onChange={(value) => table.setFilters({ ...table.filters, document_type: value })} className="filter-input" options={documentTypeOptions} />
        <Select allowClear placeholder="Status Verifikasi" value={table.filters.verification_status} onChange={(value) => table.setFilters({ ...table.filters, verification_status: value })} className="filter-input" options={[{ value: 'pending', label: 'Menunggu' }, { value: 'verified', label: 'Terverifikasi' }, { value: 'rejected', label: 'Ditolak' }]} />
      </FilterBar>
      <Card>
        <ResponsiveTable
          query={query}
          onChange={table.handleTableChange}
          scrollX={1300}
          columns={[
            { title: 'Jenis Dokumen', dataIndex: 'document_type', width: 180, fixed: 'left' },
            { title: 'Untuk', render: (_, row) => (row.documentable_type?.endsWith('Resident') ? 'Penghuni' : row.documentable_id), width: 100 },
            { title: 'Nomor', dataIndex: 'document_number', width: 150 },
            { title: 'Tanggal Dokumen', dataIndex: 'document_date', render: (value) => formatDate(value), width: 140 },
            { title: 'Berlaku Sampai', dataIndex: 'valid_until', render: (value) => formatDate(value), width: 140 },
            { title: 'Status Verifikasi', dataIndex: 'verification_status', render: (value) => <Tag color={verificationMap[value]?.[1]}>{verificationMap[value]?.[0] || value}</Tag>, width: 140 },
            { title: 'Diunggah Oleh', render: (_, row) => row.creator?.name || '-', width: 150 },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 100,
              render: (_, row) => {
                const items = [
                  { key: 'preview', label: 'Preview / Unduh' },
                  can('resident-documents.verify') ? { key: 'verify', label: 'Verifikasi' } : null,
                  can('resident-documents.delete') ? { key: 'delete', label: 'Hapus', danger: true } : null,
                ].filter(Boolean);
                return (
                  <Dropdown menu={{ items, onClick: ({ key }) => {
                    if (key === 'preview') window.open(storageUrl(row.file_path), '_blank');
                    if (key === 'verify') { verifyForm.setFieldsValue({ verification_status: row.verification_status, notes: row.notes }); setVerifyRecord(row); }
                    if (key === 'delete') Modal.confirm({ title: 'Hapus dokumen?', content: row.document_type, okText: 'Hapus', okButtonProps: { danger: true }, onOk: () => remove.mutate(row.id) });
                  } }}
                  >
                    <Button icon={<MoreOutlined />} />
                  </Dropdown>
                );
              },
            },
          ]}
        />
      </Card>
      <Drawer title="Tambah Dokumen" open={open} onClose={() => setOpen(false)} width={480} extra={<Space><Button onClick={() => setOpen(false)}>Batal</Button><Button type="primary" loading={create.isPending} onClick={() => form.submit()}>Unggah</Button></Space>} destroyOnHidden>
        <Form form={form} layout="vertical" onFinish={create.mutate} initialValues={{ target: unitId || 'resident' }}>
          <Form.Item label="Untuk" name="target" rules={[{ required: true }]}>
            <Select options={[{ value: 'resident', label: 'Penghuni (data pribadi)' }, ...units.map((unit) => ({ value: unit.id, label: `Unit ${unitLabel(unit)}` }))]} />
          </Form.Item>
          <Form.Item label="Jenis Dokumen" name="document_type" rules={[{ required: true }]}>
            <Select showSearch options={documentTypeOptions} />
          </Form.Item>
          <Form.Item label="Nomor Dokumen" name="document_number"><Input /></Form.Item>
          <Form.Item label="Tanggal Dokumen" name="document_date"><DatePicker style={{ width: '100%' }} /></Form.Item>
          <Form.Item label="Berlaku Dari" name="valid_from"><DatePicker style={{ width: '100%' }} /></Form.Item>
          <Form.Item label="Berlaku Sampai" name="valid_until"><DatePicker style={{ width: '100%' }} /></Form.Item>
          <Form.Item label="Catatan" name="notes"><Input.TextArea rows={2} /></Form.Item>
          <Form.Item label="File" name="file" valuePropName="fileList" getValueFromEvent={(event) => event?.fileList} rules={[{ required: true, message: 'File wajib diunggah' }]}>
            <Upload.Dragger beforeUpload={() => false} maxCount={1}>
              <p className="ant-upload-drag-icon"><CloudUploadOutlined /></p>
              <p>Pilih atau tarik file dokumen</p>
            </Upload.Dragger>
          </Form.Item>
        </Form>
      </Drawer>
      <Modal title="Verifikasi Dokumen" open={Boolean(verifyRecord)} onCancel={() => setVerifyRecord(null)} onOk={() => verifyForm.submit()} confirmLoading={verify.isPending}>
        <Form form={verifyForm} layout="vertical" onFinish={verify.mutate}>
          <Form.Item label="Status Verifikasi" name="verification_status" rules={[{ required: true }]}>
            <Select options={[{ value: 'verified', label: 'Terverifikasi' }, { value: 'rejected', label: 'Ditolak' }, { value: 'pending', label: 'Menunggu' }]} />
          </Form.Item>
          <Form.Item label="Catatan" name="notes"><Input.TextArea rows={2} /></Form.Item>
        </Form>
      </Modal>
    </section>
  );
}

// ---------- Kendaraan ----------
function VehiclesTab({ residentId, unitId, units }) {
  const [drawer, setDrawer] = useState({ open: false, record: null });
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ['residents', residentId, 'vehicles'], queryFn: () => api.residents.vehicles(residentId, { per_page: 100 }) });
  const items = (query.data?.data || []).filter((item) => !unitId || item.unit_id === unitId);

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['residents', residentId, 'vehicles'] });

  const save = useMutation({
    mutationFn: (values) => (drawer.record ? api.vehicles.update(drawer.record.id, values) : api.vehicles.create(values.unit_id, values)),
    onSuccess: () => { message.success('Kendaraan berhasil disimpan'); setDrawer({ open: false, record: null }); form.resetFields(); invalidate(); },
    onError: (error) => { form.setFields(mapValidationErrors(error)); message.error(getApiErrorMessage(error)); },
  });

  const remove = useMutation({
    mutationFn: (id) => api.vehicles.remove(id),
    onSuccess: () => { message.success('Kendaraan berhasil dihapus'); invalidate(); },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  return (
    <section>
      <UnitScopeNote unitId={unitId} units={units} />
      <FilterBar extra={<Can permission="vehicles.create"><Button type="primary" icon={<PlusOutlined />} onClick={() => { form.resetFields(); setDrawer({ open: true, record: null }); }}>Tambah Kendaraan</Button></Can>} />
      <Card>
        <Table
          rowKey="id"
          loading={query.isLoading}
          dataSource={items}
          locale={{ emptyText: <EmptyData /> }}
          pagination={false}
          scroll={{ x: 1100 }}
          columns={[
            { title: 'Jenis', dataIndex: 'vehicle_type', width: 100 },
            { title: 'Merek/Model', render: (_, row) => `${row.brand || ''} ${row.model || ''}`.trim() || '-', width: 160 },
            { title: 'Warna', dataIndex: 'color', width: 100 },
            { title: 'Plat Nomor', dataIndex: 'plate_number', width: 130 },
            { title: 'Nomor Stiker', dataIndex: 'sticker_number', width: 130 },
            { title: 'Stiker Berlaku Sampai', dataIndex: 'sticker_valid_until', render: (value) => formatDate(value), width: 160 },
            { title: 'Unit', dataIndex: 'unit_id', width: 90 },
            { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge type="active" value={value === 'active'} />, width: 110 },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 90,
              render: (_, row) => (
                <Dropdown menu={{ items: [{ key: 'edit', label: 'Edit' }, { key: 'delete', label: 'Hapus', danger: true }], onClick: ({ key }) => {
                  if (key === 'edit') { form.setFieldsValue({ ...row, sticker_valid_until: row.sticker_valid_until ? dayjs(row.sticker_valid_until) : null }); setDrawer({ open: true, record: row }); }
                  if (key === 'delete') Modal.confirm({ title: 'Hapus kendaraan?', content: row.plate_number, okText: 'Hapus', okButtonProps: { danger: true }, onOk: () => remove.mutate(row.id) });
                } }}
                >
                  <Button size="small" icon={<MoreOutlined />} />
                </Dropdown>
              ),
            },
          ]}
        />
      </Card>
      <Drawer title={drawer.record ? 'Edit Kendaraan' : 'Tambah Kendaraan'} open={drawer.open} onClose={() => setDrawer({ open: false, record: null })} width={420} extra={<Space><Button onClick={() => setDrawer({ open: false, record: null })}>Batal</Button><Button type="primary" loading={save.isPending} onClick={() => form.submit()}>Simpan</Button></Space>} destroyOnHidden>
        <Form form={form} layout="vertical" onFinish={(values) => save.mutate({ ...values, sticker_valid_until: values.sticker_valid_until?.format?.('YYYY-MM-DD') || null })} initialValues={{ unit_id: unitId || units[0]?.id, vehicle_type: 'mobil', status: 'active' }}>
          {!drawer.record ? (
            <Form.Item label="Unit" name="unit_id" rules={[{ required: true }]}>
              <Select options={units.map((unit) => ({ value: unit.id, label: unitLabel(unit) }))} />
            </Form.Item>
          ) : null}
          <Form.Item label="Jenis Kendaraan" name="vehicle_type" rules={[{ required: true }]}>
            <Select options={vehicleTypeOptions} />
          </Form.Item>
          <Form.Item label="Merek" name="brand"><Input /></Form.Item>
          <Form.Item label="Model" name="model"><Input /></Form.Item>
          <Form.Item label="Warna" name="color"><Input /></Form.Item>
          <Form.Item label="Plat Nomor" name="plate_number" rules={[{ required: true }]}><Input /></Form.Item>
          <Form.Item label="Nomor Stiker" name="sticker_number"><Input /></Form.Item>
          <Form.Item label="Stiker Berlaku Sampai" name="sticker_valid_until"><DatePicker style={{ width: '100%' }} /></Form.Item>
          <Form.Item label="Nama Pemilik Kendaraan" name="owner_name"><Input /></Form.Item>
          <Form.Item label="Status" name="status" rules={[{ required: true }]}>
            <Select options={[{ value: 'active', label: 'Aktif' }, { value: 'inactive', label: 'Nonaktif' }]} />
          </Form.Item>
        </Form>
      </Drawer>
    </section>
  );
}

// ---------- Penghuni Tambahan & Petugas ----------
function OccupantsTab({ residentId, unitId, units }) {
  const [type, setType] = useState(null);
  const [drawer, setDrawer] = useState({ open: false, record: null });
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ['residents', residentId, 'occupants'], queryFn: () => api.residents.occupants(residentId, { per_page: 100 }) });
  const items = (query.data?.data || []).filter((item) => (!unitId || item.unit_id === unitId) && (!type || item.type === type));

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['residents', residentId, 'occupants'] });

  const save = useMutation({
    mutationFn: (values) => (drawer.record ? api.occupants.update(drawer.record.id, values) : api.occupants.create(values.unit_id, values)),
    onSuccess: () => { message.success('Data berhasil disimpan'); setDrawer({ open: false, record: null }); form.resetFields(); invalidate(); },
    onError: (error) => { form.setFields(mapValidationErrors(error)); message.error(getApiErrorMessage(error)); },
  });

  const remove = useMutation({
    mutationFn: (id) => api.occupants.remove(id),
    onSuccess: () => { message.success('Data berhasil dihapus'); invalidate(); },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  return (
    <section>
      <UnitScopeNote unitId={unitId} units={units} />
      <FilterBar extra={<Can permission="occupants.create"><Button type="primary" icon={<PlusOutlined />} onClick={() => { form.resetFields(); setDrawer({ open: true, record: null }); }}>Tambah Data</Button></Can>}>
        <Segmented value={type || 'all'} onChange={(value) => setType(value === 'all' ? null : value)} options={[{ value: 'all', label: 'Semua' }, { value: 'family', label: 'Keluarga' }, { value: 'staff', label: 'Petugas' }]} />
      </FilterBar>
      <Card>
        <Table
          rowKey="id"
          loading={query.isLoading}
          dataSource={items}
          locale={{ emptyText: <EmptyData /> }}
          pagination={false}
          scroll={{ x: 1200 }}
          columns={[
            { title: 'Tipe', dataIndex: 'type', render: (value) => <Tag color={value === 'family' ? 'blue' : 'purple'}>{value === 'family' ? 'Keluarga' : 'Petugas'}</Tag>, width: 110 },
            { title: 'Nama', dataIndex: 'name', width: 180 },
            { title: 'Hubungan/Peran', dataIndex: 'relationship', width: 150 },
            { title: 'Telepon', dataIndex: 'phone', width: 140 },
            { title: 'Nomor Identitas', dataIndex: 'identity_number', width: 150 },
            { title: 'Mulai Tinggal/Kerja', dataIndex: 'start_date', render: (value) => formatDate(value), width: 150 },
            { title: 'Akses Berlaku Sampai', dataIndex: 'access_valid_until', render: (value) => formatDate(value), width: 160 },
            { title: 'Unit', dataIndex: 'unit_id', width: 90 },
            { title: 'Status Akses', dataIndex: 'access_status', render: (value) => <StatusBadge type="active" value={value === 'active'} />, width: 120 },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 90,
              render: (_, row) => (
                <Dropdown menu={{ items: [{ key: 'edit', label: 'Edit' }, { key: 'delete', label: 'Hapus', danger: true }], onClick: ({ key }) => {
                  if (key === 'edit') { form.setFieldsValue({ ...row, start_date: row.start_date ? dayjs(row.start_date) : null, access_valid_until: row.access_valid_until ? dayjs(row.access_valid_until) : null }); setDrawer({ open: true, record: row }); }
                  if (key === 'delete') Modal.confirm({ title: 'Hapus data ini?', content: row.name, okText: 'Hapus', okButtonProps: { danger: true }, onOk: () => remove.mutate(row.id) });
                } }}
                >
                  <Button size="small" icon={<MoreOutlined />} />
                </Dropdown>
              ),
            },
          ]}
        />
      </Card>
      <Drawer title={drawer.record ? 'Edit Data' : 'Tambah Data'} open={drawer.open} onClose={() => setDrawer({ open: false, record: null })} width={420} extra={<Space><Button onClick={() => setDrawer({ open: false, record: null })}>Batal</Button><Button type="primary" loading={save.isPending} onClick={() => form.submit()}>Simpan</Button></Space>} destroyOnHidden>
        <Form form={form} layout="vertical" onFinish={(values) => save.mutate({ ...values, start_date: values.start_date?.format?.('YYYY-MM-DD') || null, access_valid_until: values.access_valid_until?.format?.('YYYY-MM-DD') || null })} initialValues={{ unit_id: unitId || units[0]?.id, type: 'family', access_status: 'active' }}>
          {!drawer.record ? (
            <Form.Item label="Unit" name="unit_id" rules={[{ required: true }]}>
              <Select options={units.map((unit) => ({ value: unit.id, label: unitLabel(unit) }))} />
            </Form.Item>
          ) : null}
          <Form.Item label="Tipe" name="type" rules={[{ required: true }]}>
            <Select options={[{ value: 'family', label: 'Keluarga / Penghuni Tambahan' }, { value: 'staff', label: 'Petugas Rumah Tangga' }]} />
          </Form.Item>
          <Form.Item label="Nama Lengkap" name="name" rules={[{ required: true }]}><Input /></Form.Item>
          <Form.Item label="Hubungan / Peran" name="relationship" placeholder="Anak, Istri, Asisten RT, Pengemudi"><Input /></Form.Item>
          <Form.Item label="Telepon" name="phone"><Input /></Form.Item>
          <Form.Item label="Nomor Identitas" name="identity_number"><Input /></Form.Item>
          <Form.Item label="Mulai Tinggal/Kerja" name="start_date"><DatePicker style={{ width: '100%' }} /></Form.Item>
          <Form.Item label="Akses Berlaku Sampai" name="access_valid_until"><DatePicker style={{ width: '100%' }} /></Form.Item>
          <Form.Item label="Kontak Darurat" name="emergency_contact"><Input /></Form.Item>
          <Form.Item label="Status Akses" name="access_status" rules={[{ required: true }]}>
            <Select options={[{ value: 'active', label: 'Aktif' }, { value: 'inactive', label: 'Nonaktif' }]} />
          </Form.Item>
        </Form>
      </Drawer>
    </section>
  );
}

// ---------- Surat & Notifikasi ----------
function NotificationsTab({ residentId, unitId, units }) {
  const table = useTableState();
  const query = useQuery({ queryKey: ['residents', residentId, 'notifications', unitId, table.params], queryFn: () => api.residents.notifications(residentId, { ...table.params, unit_id: unitId || undefined }) });

  return (
    <section>
      <UnitScopeNote unitId={unitId} units={units} />
      <Card>
        <ResponsiveTable
          query={query}
          onChange={table.handleTableChange}
          scrollX={1100}
          columns={[
            { title: 'Tanggal', dataIndex: 'created_at', render: formatDateTime, width: 170, fixed: 'left' },
            { title: 'Tipe', dataIndex: 'type', width: 180 },
            { title: 'Channel', dataIndex: 'channel', width: 100 },
            { title: 'Pesan', dataIndex: 'message' },
            { title: 'Status Terkirim', dataIndex: 'status', render: (value) => <Tag color={value === 'sent' ? 'green' : 'gold'}>{value}</Tag>, width: 130 },
            { title: 'Status Dibaca', dataIndex: 'read_status', render: (value) => <StatusBadge type="read" value={value} />, width: 130 },
          ]}
        />
      </Card>
    </section>
  );
}

// ---------- Riwayat Aktivitas ----------
function ActivityTab({ residentId, unitId, units }) {
  const table = useTableState();
  const query = useQuery({ queryKey: ['residents', residentId, 'activity', unitId, table.params], queryFn: () => api.residents.activity(residentId, { ...table.params, unit_id: unitId || undefined }) });

  return (
    <section>
      <UnitScopeNote unitId={unitId} units={units} />
      <Card>
        <ResponsiveTable
          query={query}
          onChange={table.handleTableChange}
          scrollX={1100}
          columns={[
            { title: 'Waktu', dataIndex: 'created_at', render: formatDateTime, width: 170, fixed: 'left' },
            { title: 'User', dataIndex: 'user_name', width: 160 },
            { title: 'Modul', dataIndex: 'module', width: 130 },
            { title: 'Action', dataIndex: 'action', width: 130 },
            { title: 'Aktivitas', dataIndex: 'activity', width: 220 },
            { title: 'Status', dataIndex: 'status', render: (value) => <Tag color={value === 'success' ? 'green' : 'red'}>{value}</Tag>, width: 100 },
          ]}
        />
      </Card>
    </section>
  );
}

export default function ResidentDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [unitId, setUnitId] = useState(null);
  const [editOpen, setEditOpen] = useState(false);
  const [notifyOpen, setNotifyOpen] = useState(false);
  const [editForm] = Form.useForm();
  const [notifyForm] = Form.useForm();
  const queryClient = useQueryClient();

  const detail = useQuery({ queryKey: ['residents', id], queryFn: () => api.residents.detail(id) });
  const districts = useQuery({ queryKey: ['lookup-districts'], queryFn: () => api.lookup.districts() });

  const updateResident = useMutation({
    mutationFn: (values) => api.residents.update(id, values),
    onSuccess: () => {
      message.success('Data penghuni berhasil diperbarui');
      setEditOpen(false);
      queryClient.invalidateQueries({ queryKey: ['residents', id] });
    },
    onError: (error) => {
      editForm.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const sendNotification = useMutation({
    mutationFn: (values) => api.residents.sendNotification(id, values),
    onSuccess: () => {
      message.success('Notifikasi berhasil dikirim');
      setNotifyOpen(false);
      notifyForm.resetFields();
      queryClient.invalidateQueries({ queryKey: ['residents', id, 'notifications'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  if (detail.isLoading) return <LoadingState rows={10} />;
  if (detail.isError) return <ErrorState error={detail.error} onRetry={detail.refetch} />;

  const resident = detail.data?.data || {};
  const units = resident.units || [];

  return (
    <section>
      <PageHeader
        title={resident.name || 'Detail Penghuni'}
        subtitle="Informasi lengkap penghuni, unit, keuangan, dan seluruh riwayat terkait."
        breadcrumbs={[{ label: 'Penghuni', to: '/residents' }, { label: resident.name }]}
        onRefresh={() => detail.refetch()}
        loading={detail.isFetching}
        extra={
          <Space wrap>
            <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/residents')}>Kembali</Button>
            <Can permission="residents.update">
              <Button icon={<BellOutlined />} onClick={() => { notifyForm.resetFields(); setNotifyOpen(true); }}>Kirim Notifikasi</Button>
            </Can>
            <Can permission="residents.update">
              <Button type="primary" icon={<EditOutlined />} onClick={() => { editForm.setFieldsValue(resident); setEditOpen(true); }}>Edit Penghuni</Button>
            </Can>
          </Space>
        }
      />

      <Tabs
        items={[
          { key: 'summary', label: 'Ringkasan', children: <SummaryTab residentId={id} unitId={unitId} units={units} /> },
          { key: 'resident', label: 'Data Penghuni', children: <ResidentInfoTab resident={resident} onEdit={() => { editForm.setFieldsValue(resident); setEditOpen(true); }} /> },
          { key: 'unit', label: 'Data Unit', children: <UnitInfoTab resident={resident} units={units} unitId={unitId} onSelectUnit={setUnitId} /> },
          { key: 'billings', label: 'Tagihan', children: <BillingsTab residentId={id} unitId={unitId} units={units} /> },
          { key: 'transactions', label: 'Transaksi', children: <TransactionsTab residentId={id} unitId={unitId} units={units} /> },
          { key: 'complaints', label: 'Komplain', children: <ComplaintsTab residentId={id} unitId={unitId} units={units} /> },
          { key: 'services', label: 'Layanan', children: <ServiceRequestsTab residentId={id} unitId={unitId} units={units} /> },
          { key: 'visits', label: 'Kunjungan Collector', children: <VisitsTab residentId={id} unitId={unitId} units={units} /> },
          { key: 'promises', label: 'Janji Pembayaran', children: <PaymentPromisesTab residentId={id} unitId={unitId} units={units} /> },
          { key: 'documents', label: 'Dokumen', children: <DocumentsTab residentId={id} unitId={unitId} units={units} /> },
          { key: 'vehicles', label: 'Kendaraan', children: <VehiclesTab residentId={id} unitId={unitId} units={units} /> },
          { key: 'occupants', label: 'Penghuni Tambahan & Petugas', children: <OccupantsTab residentId={id} unitId={unitId} units={units} /> },
          { key: 'notifications', label: 'Surat & Notifikasi', children: <NotificationsTab residentId={id} unitId={unitId} units={units} /> },
          { key: 'activity', label: 'Riwayat Aktivitas', children: <ActivityTab residentId={id} unitId={unitId} units={units} /> },
        ]}
      />

      <Drawer
        title="Edit Data Penghuni"
        open={editOpen}
        onClose={() => setEditOpen(false)}
        width={620}
        extra={<Space><Button onClick={() => setEditOpen(false)}>Batal</Button><Button type="primary" loading={updateResident.isPending} onClick={() => editForm.submit()}>Simpan</Button></Space>}
        destroyOnHidden
      >
        <ResidentForm form={editForm} districts={districts.data?.data || []} onFinish={updateResident.mutate} loading={updateResident.isPending} editing residentId={id} />
      </Drawer>

      <Modal title="Kirim Notifikasi ke Penghuni" open={notifyOpen} onCancel={() => setNotifyOpen(false)} onOk={() => notifyForm.submit()} confirmLoading={sendNotification.isPending}>
        <Form form={notifyForm} layout="vertical" onFinish={sendNotification.mutate} initialValues={{ unit_id: units[0]?.id }}>
          <Form.Item label="Unit Tujuan" name="unit_id" rules={[{ required: true }]}>
            <Select options={units.map((unit) => ({ value: unit.id, label: unitLabel(unit) }))} />
          </Form.Item>
          <Form.Item label="Pesan" name="message" rules={[{ required: true }]}>
            <Input.TextArea rows={4} placeholder="Isi notifikasi untuk penghuni" />
          </Form.Item>
        </Form>
      </Modal>
    </section>
  );
}
