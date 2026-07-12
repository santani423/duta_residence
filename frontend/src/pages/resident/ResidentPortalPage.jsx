import {
  Alert,
  Button,
  Card,
  DatePicker,
  Descriptions,
  Drawer,
  Form,
  Grid,
  Input,
  Modal,
  Select,
  Space,
  Statistic,
  Tabs,
  Timeline,
  Typography,
  Upload,
  message,
} from 'antd';
import {
  CheckOutlined,
  CloudUploadOutlined,
  CopyOutlined,
  DownloadOutlined,
  EyeOutlined,
  LinkOutlined,
  PlusOutlined,
  ReloadOutlined,
} from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import dayjs from 'dayjs';
import PageHeader from '../../components/common/PageHeader.jsx';
import FilterBar from '../../components/common/FilterBar.jsx';
import { EmptyData, ErrorState, LoadingState } from '../../components/common/ApiState.jsx';
import StatusBadge from '../../components/common/StatusBadge.jsx';
import ResponsiveTable from '../../components/tables/ResponsiveTable.jsx';
import { api } from '../../services/estateApi.js';
import { useTableState } from '../../hooks/useTableState.js';
import { compactText, formatCurrency, formatDate, formatDateTime } from '../../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../../utils/apiError.js';
import { useThemeMode } from '../../state/ThemeContext.jsx';

const invoiceStatuses = ['unpaid', 'pending', 'partially_paid', 'paid', 'overdue', 'cancelled'];
const paymentStatuses = ['pending', 'waiting_verification', 'paid', 'failed', 'expired', 'cancelled', 'rejected', 'refunded'];
const gateways = [
  { value: 'xendit', label: 'Xendit' },
  { value: 'midtrans', label: 'Midtrans' },
  { value: 'manual', label: 'Manual Transfer' },
];

function unwrapQuery(query) {
  return query.data?.data;
}

function asList(value) {
  return Array.isArray(value) ? value : [];
}

function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  URL.revokeObjectURL(url);
}

function StatCard({ title, value, suffix, loading }) {
  return (
    <Card>
      <Statistic title={title} value={loading ? 0 : value || 0} suffix={suffix} />
    </Card>
  );
}

function InfoList({ data = {} }) {
  return (
    <Descriptions bordered column={{ xs: 1, md: 2 }} size="small">
      {Object.entries(data).map(([label, value]) => (
        <Descriptions.Item key={label} label={label}>{compactText(value)}</Descriptions.Item>
      ))}
    </Descriptions>
  );
}

function PaymentConfigCard({ config, total, invoiceNumber, onProvider }) {
  const available = config?.available_methods || config?.enabled_gateways || [config?.active_gateway].filter(Boolean);
  const manual = config?.manual_payment;

  return (
    <Card title="Metode Pembayaran">
      <Space direction="vertical" style={{ width: '100%' }} size="middle">
        <Alert
          type={config?.is_active === false ? 'warning' : 'info'}
          showIcon
          message={`Gateway aktif: ${config?.active_gateway || '-'}`}
          description="Pilihan pembayaran mengikuti konfigurasi Admin dan diambil dari API."
        />
        {manual ? (
          <Descriptions bordered column={1} size="small">
            <Descriptions.Item label="Bank">{manual.bank_name}</Descriptions.Item>
            <Descriptions.Item label="Nomor rekening">
              <Space>
                <Typography.Text copyable>{manual.account_number}</Typography.Text>
                <Button size="small" icon={<CopyOutlined />} onClick={() => navigator.clipboard?.writeText(manual.account_number || '')}>Salin</Button>
              </Space>
            </Descriptions.Item>
            <Descriptions.Item label="Nama rekening">{manual.account_name}</Descriptions.Item>
            <Descriptions.Item label="Instruksi">{manual.instructions}</Descriptions.Item>
          </Descriptions>
        ) : null}
        {invoiceNumber ? <Typography.Text>Total {invoiceNumber}: {formatCurrency(total)}</Typography.Text> : null}
        {onProvider ? (
          <Space wrap>
            {available.map((provider) => (
              <Button key={provider} type={provider === config?.active_gateway ? 'primary' : 'default'} onClick={() => onProvider(provider)}>
                Bayar via {gateways.find((item) => item.value === provider)?.label || provider}
              </Button>
            ))}
          </Space>
        ) : null}
      </Space>
    </Card>
  );
}

function ManualProofDrawer({ payment, open, onClose }) {
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const upload = useMutation({
    mutationFn: (values) => {
      const formData = new FormData();
      formData.append('sender_name', values.sender_name);
      formData.append('sender_bank', values.sender_bank);
      if (values.sender_account_number) formData.append('sender_account_number', values.sender_account_number);
      formData.append('amount', values.amount);
      formData.append('manual_transfer_date', values.manual_transfer_date.format('YYYY-MM-DD'));
      formData.append('proof', values.proof[0].originFileObj);
      if (values.manual_notes) formData.append('manual_notes', values.manual_notes);
      return api.resident.uploadManualProof(payment.id, formData);
    },
    onSuccess: () => {
      message.success('Bukti pembayaran dikirim dan menunggu verifikasi.');
      queryClient.invalidateQueries({ queryKey: ['resident-payments'] });
      queryClient.invalidateQueries({ queryKey: ['resident-payment', payment?.id] });
      form.resetFields();
      onClose();
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  return (
    <Drawer title="Upload Bukti Pembayaran" open={open} onClose={onClose} width={560} extra={<Button type="primary" loading={upload.isPending} onClick={() => form.submit()}>Kirim</Button>} destroyOnHidden>
      <Alert type="info" showIcon message={payment?.invoice_number} description={`Total transfer: ${formatCurrency(payment?.total)}`} />
      <Form form={form} layout="vertical" className="section-row" initialValues={{ amount: payment?.total, manual_transfer_date: dayjs() }} onFinish={upload.mutate}>
        <Form.Item label="Nama pengirim" name="sender_name" rules={[{ required: true }]}><Input /></Form.Item>
        <Form.Item label="Bank pengirim" name="sender_bank" rules={[{ required: true }]}><Input /></Form.Item>
        <Form.Item label="Nomor rekening pengirim" name="sender_account_number"><Input /></Form.Item>
        <Form.Item label="Nominal transfer" name="amount" rules={[{ required: true }]}><Input type="number" /></Form.Item>
        <Form.Item label="Tanggal transfer" name="manual_transfer_date" rules={[{ required: true }]}><DatePicker style={{ width: '100%' }} /></Form.Item>
        <Form.Item label="Bukti pembayaran" name="proof" valuePropName="fileList" getValueFromEvent={(event) => event?.fileList} rules={[{ required: true }]}>
          <Upload.Dragger beforeUpload={() => false} maxCount={1} accept=".jpg,.jpeg,.png,.pdf">
            <p className="ant-upload-drag-icon"><CloudUploadOutlined /></p>
            <p>Pilih atau tarik file bukti pembayaran</p>
          </Upload.Dragger>
        </Form.Item>
        <Form.Item label="Catatan" name="manual_notes"><Input.TextArea rows={3} /></Form.Item>
      </Form>
    </Drawer>
  );
}

function useResidentPaymentActions() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ invoiceId, provider }) => api.resident.createPayment(invoiceId, { provider }),
    onSuccess: (response) => {
      const payment = response.data;
      queryClient.invalidateQueries({ queryKey: ['resident-dashboard'] });
      queryClient.invalidateQueries({ queryKey: ['resident-bills'] });
      queryClient.invalidateQueries({ queryKey: ['resident-payments'] });
      message.success('Transaksi pembayaran berhasil dibuat.');
      if (payment.payment_url) {
        window.location.assign(payment.payment_url);
      } else {
        navigate(`/resident/payments/${payment.id}`);
      }
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });
}

function ResidentDashboard() {
  const navigate = useNavigate();
  const query = useQuery({ queryKey: ['resident-dashboard'], queryFn: api.resident.dashboard });
  const data = unwrapQuery(query) || {};
  const billing = data.billing_summary || {};
  const payment = data.payment_summary || {};
  const services = data.service_summary || {};

  if (query.isLoading) return <LoadingState rows={10} />;
  if (query.isError) return <ErrorState error={query.error} onRetry={query.refetch} />;

  return (
    <section>
      <PageHeader title="Dashboard Penghuni" subtitle="Ringkasan akun, unit, tagihan, pembayaran, layanan, dan aktivitas Anda." breadcrumbs={[{ label: 'Penghuni' }, { label: 'Dashboard' }]} onRefresh={query.refetch} />
      <div className="resident-grid resident-grid-4">
        <StatCard title="Total Tagihan Aktif" value={billing.active_total} loading={query.isFetching} />
        <StatCard title="Belum Dibayar" value={billing.unpaid_count} suffix="invoice" />
        <StatCard title="Jatuh Tempo" value={billing.overdue_count} suffix="invoice" />
        <StatCard title="Pembayaran Berhasil" value={payment.successful_total} />
        <StatCard title="Sedang Diproses" value={payment.processing_count} suffix="transaksi" />
        <StatCard title="Menunggu Verifikasi" value={payment.manual_waiting_count} suffix="manual" />
        <StatCard title="Komplain Aktif" value={services.active_complaints} />
        <StatCard title="Maintenance Aktif" value={services.active_maintenance_requests} />
      </div>
      <div className="resident-grid resident-grid-2 section-row">
        <Card title="Informasi Penghuni"><InfoList data={{ Nama: data.resident?.name, Email: data.resident?.email, Telepon: data.resident?.phone, 'Nomor Penghuni': data.resident?.resident_number, Status: data.resident?.status }} /></Card>
        <Card title="Estate dan Unit"><InfoList data={{ Estate: data.estate?.name, Cluster: data.estate?.cluster, Unit: data.property?.unit_label, 'Tipe Properti': data.property?.property_type, Hunian: data.property?.occupancy }} /></Card>
      </div>
      <Card className="section-row">
        <Space wrap>
          <Button type="primary" onClick={() => navigate('/resident/bills')}>Pembayaran Cepat</Button>
          <Button onClick={() => navigate('/resident/complaints')} icon={<PlusOutlined />}>Buat Komplain</Button>
          <Button onClick={() => navigate('/resident/maintenance-requests')} icon={<PlusOutlined />}>Buat Maintenance</Button>
          <Button onClick={() => navigate('/resident/bills')}>Lihat Tagihan</Button>
          <Button onClick={() => navigate('/resident/payments')}>Riwayat Pembayaran</Button>
        </Space>
      </Card>
      <div className="resident-grid resident-grid-3 section-row">
        <Card title="Tagihan Terbaru">{asList(billing.latest).map((item) => <p key={item.id}><Button type="link" onClick={() => navigate(`/resident/invoices/${item.id}`)}>{item.invoice_number}</Button> {formatCurrency(item.total)} <StatusBadge type="transaction" value={item.status} /></p>) || <EmptyData />}</Card>
        <Card title="Pembayaran Terbaru">{asList(payment.latest).map((item) => <p key={item.id}><Button type="link" onClick={() => navigate(`/resident/payments/${item.id}`)}>{item.transaction_number}</Button> {formatCurrency(item.total)} <StatusBadge type="transaction" value={item.status} /></p>)}</Card>
        <Card title="Penggunaan Layanan">{asList(services.usage).map((item) => <p key={item.label}>{item.label}: <strong>{item.value}</strong></p>)}</Card>
        <Card title="Dokumen Terbaru">{asList(data.latest_documents).map((item) => <p key={item.id}>{item.name}<br /><Typography.Text type="secondary">{formatDate(item.created_at)}</Typography.Text></p>)}</Card>
        <Card title="Notifikasi Terbaru">{asList(data.latest_notifications).map((item) => <p key={item.id}>{item.title || item.subject || item.type}<br /><Typography.Text type="secondary">{formatDateTime(item.created_at)}</Typography.Text></p>)}</Card>
        <Card title="Aktivitas Terbaru">{asList(data.latest_activity).map((item) => <p key={item.id}>{item.action || item.event}<br /><Typography.Text type="secondary">{formatDateTime(item.created_at)}</Typography.Text></p>)}</Card>
      </div>
    </section>
  );
}

function ProfileForm({ initial, onSaved }) {
  const [form] = Form.useForm();
  const { setMode } = useThemeMode();
  const queryClient = useQueryClient();
  useEffect(() => form.setFieldsValue(initial), [form, initial]);

  const update = useMutation({
    mutationFn: (values) => {
      const formData = new FormData();
      ['name', 'phone', 'email', 'id_card_address', 'language_preference', 'theme_preference'].forEach((key) => {
        if (values[key] !== undefined && values[key] !== null) formData.append(key, values[key]);
      });
      if (values.photo?.[0]?.originFileObj) formData.append('photo', values.photo[0].originFileObj);
      return api.resident.updateProfile(formData);
    },
    onSuccess: (response) => {
      message.success('Profil berhasil diperbarui.');
      if (response.data?.theme_preference) setMode(response.data.theme_preference);
      queryClient.invalidateQueries({ queryKey: ['resident-account'] });
      queryClient.invalidateQueries({ queryKey: ['resident-profile'] });
      onSaved?.();
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  return (
    <Form form={form} layout="vertical" className="responsive-form" onFinish={update.mutate}>
      <Form.Item label="Nama" name="name" rules={[{ required: true }]}><Input /></Form.Item>
      <Form.Item label="Email" name="email" rules={[{ type: 'email' }]}><Input /></Form.Item>
      <Form.Item label="Nomor telepon" name="phone"><Input /></Form.Item>
      <Form.Item label="Alamat" name="id_card_address"><Input /></Form.Item>
      <Form.Item label="Bahasa" name="language_preference"><Select options={[{ value: 'id', label: 'Indonesia' }, { value: 'en', label: 'English' }]} /></Form.Item>
      <Form.Item label="Tema" name="theme_preference"><Select options={[{ value: 'light', label: 'Light' }, { value: 'dark', label: 'Dark' }, { value: 'system', label: 'System' }]} /></Form.Item>
      <Form.Item label="Foto profil" name="photo" valuePropName="fileList" getValueFromEvent={(event) => event?.fileList} className="full-span">
        <Upload beforeUpload={() => false} maxCount={1} accept=".jpg,.jpeg,.png"><Button icon={<CloudUploadOutlined />}>Pilih Foto</Button></Upload>
      </Form.Item>
      <Form.Item className="full-span"><Button type="primary" htmlType="submit" loading={update.isPending}>Simpan Profil</Button></Form.Item>
    </Form>
  );
}

function ResidentAccount({ mode = 'account' }) {
  const query = useQuery({ queryKey: ['resident-account'], queryFn: api.resident.account });
  const data = unwrapQuery(query) || {};

  if (query.isLoading) return <LoadingState rows={10} />;
  if (query.isError) return <ErrorState error={query.error} onRetry={query.refetch} />;

  if (mode === 'profile') {
    return (
      <section>
        <PageHeader title="Profil Penghuni" breadcrumbs={[{ label: 'Penghuni' }, { label: 'Profil' }]} onRefresh={query.refetch} />
        <Card><ProfileForm initial={data.account} /></Card>
      </section>
    );
  }

  return (
    <section>
      <PageHeader title="Akun Penghuni" subtitle="Informasi akun, profil, unit, tagihan, pembayaran, dokumen, keamanan, notifikasi, dan aktivitas." breadcrumbs={[{ label: 'Penghuni' }, { label: 'Akun' }]} onRefresh={query.refetch} />
      <Tabs
        items={[
          { key: 'account', label: 'Informasi Akun', children: <Card><InfoList data={{ Nama: data.account?.name, Email: data.account?.email, Telepon: data.account?.phone, 'Nomor Penghuni': data.account?.resident_number, Status: data.account?.status, Estate: data.account?.estate, Unit: data.account?.unit, Bergabung: formatDate(data.account?.joined_at), 'Login Terakhir': formatDateTime(data.account?.last_login_at) }} /></Card> },
          { key: 'profile', label: 'Profil Penghuni', children: <Card><ProfileForm initial={data.account} /></Card> },
          { key: 'property', label: 'Properti atau Unit', children: <Card><InfoList data={{ Estate: data.property?.estate, Cluster: data.property?.cluster, Unit: data.property?.unit_label, Blok: data.property?.block, Kavling: data.property?.lot_number, 'Tipe Properti': data.property?.property_type, Hunian: data.property?.occupancy, 'Luas Bangunan': data.property?.building_area, 'Luas Tanah': data.property?.land_area }} /></Card> },
          { key: 'bills', label: 'Tagihan', children: <InvoiceTable data={data.billings} /> },
          { key: 'payments', label: 'Riwayat Pembayaran', children: <PaymentTable data={data.payments} /> },
          { key: 'methods', label: 'Metode Pembayaran', children: <PaymentConfigCard config={data.payment_methods} /> },
          { key: 'documents', label: 'Dokumen', children: <DocumentList documents={data.documents} /> },
          { key: 'security', label: 'Keamanan Akun', children: <Card><InfoList data={{ 'Login terakhir': formatDateTime(data.security?.last_login_at), 'IP terakhir': data.security?.last_login_ip, 'Tindakan sensitif': 'Memerlukan konfirmasi password dari backend' }} /></Card> },
          { key: 'notifications', label: 'Notifikasi', children: <NotificationList data={data.notifications} /> },
          { key: 'activity', label: 'Aktivitas Akun', children: <ActivityList data={data.activity} /> },
        ]}
      />
    </section>
  );
}

function ResidentProperty() {
  const query = useQuery({ queryKey: ['resident-property'], queryFn: api.resident.property });
  const properties = asList(unwrapQuery(query));
  return (
    <section>
      <PageHeader title="Properti atau Unit" breadcrumbs={[{ label: 'Penghuni' }, { label: 'Properti' }]} onRefresh={query.refetch} />
      {query.isLoading ? <LoadingState /> : null}
      {!query.isLoading && properties.length === 0 ? (
        <Card><Typography.Text type="secondary">Belum ada unit yang terhubung dengan akun ini.</Typography.Text></Card>
      ) : null}
      <Space direction="vertical" size="middle" style={{ width: '100%' }}>
        {properties.map((data) => (
          <Card key={data.unit_id} title={data.unit_label}>
            <InfoList data={{ Estate: data.estate, Cluster: data.cluster, Unit: data.unit_label, Blok: data.block, Kavling: data.lot_number, 'Tipe Properti': data.property_type, Hunian: data.occupancy, 'Luas Bangunan': data.building_area, 'Luas Tanah': data.land_area, 'Serah Terima': formatDate(data.handover_date), Status: data.status }} />
          </Card>
        ))}
      </Space>
    </section>
  );
}

function InvoiceTable({ query, data, onChange, onPay }) {
  const navigate = useNavigate();
  return (
    <Card>
      <ResponsiveTable
        query={query}
        data={data}
        onChange={onChange}
        scrollX={1280}
        columns={[
          { title: 'Invoice', dataIndex: 'invoice_number', width: 150, fixed: 'left' },
          { title: 'Jenis', dataIndex: 'billing_type', width: 130 },
          { title: 'Periode', dataIndex: 'period', width: 110 },
          { title: 'Terbit', dataIndex: 'issued_at', render: formatDate, width: 130 },
          { title: 'Jatuh Tempo', dataIndex: 'due_date', render: formatDate, width: 130 },
          { title: 'Subtotal', dataIndex: 'subtotal', render: formatCurrency, width: 130 },
          { title: 'Pajak', dataIndex: 'tax', render: formatCurrency, width: 120 },
          { title: 'Admin', dataIndex: 'admin_fee', render: formatCurrency, width: 120 },
          { title: 'Total', dataIndex: 'total', render: formatCurrency, width: 140 },
          { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge type="transaction" value={value} />, width: 150 },
          {
            title: 'Aksi',
            fixed: 'right',
            width: 260,
            render: (_, row) => (
              <Space>
                <Button size="small" icon={<EyeOutlined />} onClick={() => navigate(`/resident/invoices/${row.id}`)}>Detail</Button>
                <Button size="small" type="primary" disabled={!['unpaid', 'overdue'].includes(row.status)} onClick={() => onPay?.(row)}>Bayar</Button>
                <Button size="small" icon={<DownloadOutlined />} onClick={async () => downloadBlob(await api.resident.downloadInvoice(row.id), `${row.invoice_number}.pdf`)}>PDF</Button>
              </Space>
            ),
          },
        ]}
      />
    </Card>
  );
}

function ResidentBills() {
  const table = useTableState();
  const query = useQuery({ queryKey: ['resident-bills', table.params], queryFn: () => api.resident.bills(table.params) });
  const config = useQuery({ queryKey: ['resident-payment-config'], queryFn: api.resident.paymentConfig });
  const createPayment = useResidentPaymentActions();
  const [paying, setPaying] = useState(null);
  const configData = unwrapQuery(config);

  function pay(provider) {
    createPayment.mutate({ invoiceId: paying.id, provider });
    setPaying(null);
  }

  return (
    <section>
      <PageHeader title="Tagihan Penghuni" subtitle="Cari, filter, sortir, dan bayar invoice milik Anda." breadcrumbs={[{ label: 'Penghuni' }, { label: 'Tagihan' }]} onRefresh={query.refetch} />
      <FilterBar>
        <Input allowClear placeholder="Cari invoice atau jenis" value={table.search} onChange={(event) => table.setSearch(event.target.value)} className="filter-input" />
        <Select allowClear placeholder="Status" value={table.filters.status} onChange={(value) => table.setFilters({ ...table.filters, status: value })} options={invoiceStatuses.map((value) => ({ value, label: value }))} className="filter-input" />
        <Input placeholder="Periode YYYY-MM" value={table.filters.period} onChange={(event) => table.setFilters({ ...table.filters, period: event.target.value || undefined })} className="filter-input" />
        <Select allowClear placeholder="Sorting" value={table.filters.sort} onChange={(value) => table.setFilters({ ...table.filters, sort: value })} options={[{ value: 'due_date', label: 'Jatuh Tempo' }]} className="filter-input" />
      </FilterBar>
      <InvoiceTable query={query} onChange={table.handleTableChange} onPay={setPaying} />
      <Modal title="Pilih Metode Pembayaran" open={Boolean(paying)} onCancel={() => setPaying(null)} footer={null}>
        <PaymentConfigCard config={configData} total={paying?.total} invoiceNumber={paying?.invoice_number} onProvider={pay} />
      </Modal>
    </section>
  );
}

function ResidentInvoiceDetail() {
  const { invoiceId } = useParams();
  const query = useQuery({ queryKey: ['resident-invoice', invoiceId], queryFn: () => api.resident.invoice(invoiceId), enabled: Boolean(invoiceId) });
  const config = useQuery({ queryKey: ['resident-payment-config'], queryFn: api.resident.paymentConfig });
  const createPayment = useResidentPaymentActions();
  const data = unwrapQuery(query) || {};
  const configData = unwrapQuery(config);

  return (
    <section>
      <PageHeader title="Detail Invoice" breadcrumbs={[{ label: 'Penghuni' }, { label: 'Invoice' }, { label: data.invoice_number || invoiceId }]} onRefresh={query.refetch} />
      {query.isLoading ? <LoadingState rows={8} /> : query.isError ? <ErrorState error={query.error} onRetry={query.refetch} /> : (
        <div className="resident-grid resident-grid-2">
          <Card title={data.invoice_number}>
            <InfoList data={{ Penghuni: data.resident?.name, Estate: data.estate?.name, Unit: data.unit?.unit_label, Periode: data.period, 'Jatuh Tempo': formatDate(data.due_date), Subtotal: formatCurrency(data.subtotal), Pajak: formatCurrency(data.tax), Admin: formatCurrency(data.admin_fee), Diskon: formatCurrency(data.discount), Total: formatCurrency(data.total), Dibayar: formatCurrency(data.total_paid), Status: data.status }} />
            <Space className="action-row" wrap>
              <Button icon={<DownloadOutlined />} onClick={async () => downloadBlob(await api.resident.downloadInvoice(data.id), `${data.invoice_number}.pdf`)}>Download Invoice</Button>
              <Button type="primary" disabled={!['unpaid', 'overdue'].includes(data.status)} onClick={() => createPayment.mutate({ invoiceId: data.id, provider: configData?.active_gateway })}>Bayar Sekarang</Button>
            </Space>
          </Card>
          <PaymentConfigCard config={configData} total={data.total} invoiceNumber={data.invoice_number} onProvider={(provider) => createPayment.mutate({ invoiceId: data.id, provider })} />
          <Card title="Riwayat Pembayaran" className="resident-wide"><PaymentTable data={data.payment_history} /></Card>
        </div>
      )}
    </section>
  );
}

function PaymentTable({ query, data, onChange }) {
  const navigate = useNavigate();
  const [proof, setProof] = useState(null);
  return (
    <>
      <Card>
        <ResponsiveTable
          query={query}
          data={data}
          onChange={onChange}
          scrollX={1260}
          columns={[
            { title: 'Transaksi', dataIndex: 'transaction_number', width: 190, fixed: 'left' },
            { title: 'Invoice', dataIndex: 'invoice_number', width: 180 },
            { title: 'Gateway', dataIndex: 'payment_gateway', width: 110 },
            { title: 'Metode', dataIndex: 'payment_method', width: 130 },
            { title: 'Nominal', dataIndex: 'total', render: formatCurrency, width: 140 },
            { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge type="transaction" value={value} />, width: 170 },
            { title: 'Transaksi', dataIndex: 'created_at', render: formatDateTime, width: 170 },
            { title: 'Dibayar', dataIndex: 'paid_at', render: formatDateTime, width: 170 },
            { title: 'Kedaluwarsa', dataIndex: 'expired_at', render: formatDateTime, width: 170 },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 280,
              render: (_, row) => (
                <Space>
                  <Button size="small" icon={<EyeOutlined />} onClick={() => navigate(`/resident/payments/${row.id}`)}>Detail</Button>
                  {row.status === 'paid' ? <Button size="small" icon={<DownloadOutlined />} onClick={async () => downloadBlob(await api.resident.downloadPaymentReceipt(row.id), `${row.transaction_number}.pdf`)}>Receipt</Button> : null}
                  {row.payment_url && row.status === 'pending' ? <Button size="small" icon={<LinkOutlined />} href={row.payment_url} target="_blank">Lanjut</Button> : null}
                  {row.payment_gateway === 'manual' && ['pending', 'rejected'].includes(row.status) ? <Button size="small" icon={<CloudUploadOutlined />} onClick={() => setProof(row)}>Upload</Button> : null}
                </Space>
              ),
            },
          ]}
        />
      </Card>
      <ManualProofDrawer payment={proof} open={Boolean(proof)} onClose={() => setProof(null)} />
    </>
  );
}

function ResidentPayments() {
  const table = useTableState();
  const query = useQuery({ queryKey: ['resident-payments', table.params], queryFn: () => api.resident.payments(table.params) });
  return (
    <section>
      <PageHeader title="Riwayat Pembayaran" breadcrumbs={[{ label: 'Penghuni' }, { label: 'Pembayaran' }]} onRefresh={query.refetch} />
      <FilterBar>
        <Input allowClear placeholder="Cari transaksi atau invoice" value={table.search} onChange={(event) => table.setSearch(event.target.value)} className="filter-input" />
        <Select allowClear placeholder="Gateway" value={table.filters.provider} onChange={(value) => table.setFilters({ ...table.filters, provider: value })} options={gateways} className="filter-input" />
        <Select allowClear placeholder="Status" value={table.filters.status} onChange={(value) => table.setFilters({ ...table.filters, status: value })} options={paymentStatuses.map((value) => ({ value, label: value }))} className="filter-input" />
      </FilterBar>
      <PaymentTable query={query} onChange={table.handleTableChange} />
    </section>
  );
}

function ResidentPaymentDetail() {
  const { paymentId } = useParams();
  const query = useQuery({ queryKey: ['resident-payment', paymentId], queryFn: () => api.resident.payment(paymentId), enabled: Boolean(paymentId) });
  const [proofOpen, setProofOpen] = useState(false);
  const data = unwrapQuery(query) || {};
  return (
    <section>
      <PageHeader title="Detail Pembayaran" breadcrumbs={[{ label: 'Penghuni' }, { label: 'Pembayaran' }, { label: data.transaction_number || paymentId }]} onRefresh={query.refetch} />
      {query.isLoading ? <LoadingState rows={8} /> : query.isError ? <ErrorState error={query.error} onRetry={query.refetch} /> : (
        <div className="resident-grid resident-grid-2">
          <Card title={data.transaction_number}>
            <InfoList data={{ Invoice: data.invoice_number, Penghuni: data.resident?.name, Gateway: data.payment_gateway, Metode: data.payment_method, Subtotal: formatCurrency(data.fee_breakdown?.subtotal), Pajak: formatCurrency(data.fee_breakdown?.tax), Admin: formatCurrency(data.fee_breakdown?.admin_fee), Total: formatCurrency(data.fee_breakdown?.total), Status: data.status, 'Waktu transaksi': formatDateTime(data.created_at), 'Waktu pembayaran': formatDateTime(data.paid_at), 'Kedaluwarsa': formatDateTime(data.expired_at), 'Bukti manual': data.manual_proof_path, 'Catatan verifikator': data.verification_notes }} />
            <Space className="action-row" wrap>
              {data.status === 'paid' ? <Button icon={<DownloadOutlined />} onClick={async () => downloadBlob(await api.resident.downloadPaymentReceipt(data.id), `${data.transaction_number}.pdf`)}>Download Receipt</Button> : null}
              {data.payment_url && data.status === 'pending' ? <Button icon={<LinkOutlined />} href={data.payment_url} target="_blank">Lanjutkan Pembayaran</Button> : null}
              {data.payment_gateway === 'manual' && ['pending', 'rejected'].includes(data.status) ? <Button icon={<CloudUploadOutlined />} onClick={() => setProofOpen(true)}>Upload Bukti</Button> : null}
            </Space>
          </Card>
          <Card title="Riwayat Status">
            <Timeline items={asList(data.status_history).map((item) => ({ children: <span><StatusBadge type="transaction" value={item.status} /> {formatDateTime(item.changed_at)}<br />{item.notes}</span> }))} />
          </Card>
        </div>
      )}
      <ManualProofDrawer payment={data} open={proofOpen} onClose={() => setProofOpen(false)} />
    </section>
  );
}

function ResidentPaymentMethods() {
  const query = useQuery({ queryKey: ['resident-payment-config'], queryFn: api.resident.paymentConfig });
  return (
    <section>
      <PageHeader title="Metode Pembayaran" breadcrumbs={[{ label: 'Penghuni' }, { label: 'Metode Bayar' }]} onRefresh={query.refetch} />
      {query.isLoading ? <LoadingState /> : <PaymentConfigCard config={unwrapQuery(query)} />}
    </section>
  );
}

function ComplaintMaintenancePage({ kind }) {
  const isComplaint = kind === 'complaints';
  const table = useTableState();
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [detail, setDetail] = useState(null);
  const [form] = Form.useForm();
  const [commentForm] = Form.useForm();
  const endpoint = isComplaint ? api.resident.complaints : api.resident.maintenanceRequests;
  const createEndpoint = isComplaint ? api.resident.createComplaint : api.resident.createMaintenanceRequest;
  const detailEndpoint = isComplaint ? api.resident.complaint : api.resident.maintenanceRequest;
  const query = useQuery({ queryKey: [`resident-${kind}`, table.params], queryFn: () => endpoint(table.params) });
  const detailQuery = useQuery({ queryKey: [`resident-${kind}-detail`, detail?.id], queryFn: () => detailEndpoint(detail.id), enabled: Boolean(detail?.id) });
  const detailData = unwrapQuery(detailQuery) || {};

  const create = useMutation({
    mutationFn: (values) => {
      const formData = new FormData();
      Object.entries(values).forEach(([key, value]) => {
        if (key === 'attachment' && value?.[0]?.originFileObj) formData.append(key, value[0].originFileObj);
        else if (key === 'preferred_schedule' && value) formData.append(key, value.format('YYYY-MM-DD HH:mm:ss'));
        else if (value !== undefined && value !== null) formData.append(key, value);
      });
      return createEndpoint(formData);
    },
    onSuccess: () => {
      message.success(isComplaint ? 'Komplain berhasil dibuat.' : 'Permintaan maintenance berhasil dibuat.');
      setOpen(false);
      form.resetFields();
      queryClient.invalidateQueries({ queryKey: [`resident-${kind}`] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const addComment = useMutation({
    mutationFn: (values) => {
      const formData = new FormData();
      formData.append('body', values.body);
      if (values.attachment?.[0]?.originFileObj) formData.append('attachment', values.attachment[0].originFileObj);
      return api.resident.addComplaintComment(detail.id, formData);
    },
    onSuccess: () => {
      message.success('Komentar dikirim.');
      commentForm.resetFields();
      queryClient.invalidateQueries({ queryKey: [`resident-${kind}-detail`, detail?.id] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const closeComplaint = useMutation({
    mutationFn: () => api.resident.closeComplaint(detail.id),
    onSuccess: () => {
      message.success('Komplain ditutup.');
      setDetail(null);
      queryClient.invalidateQueries({ queryKey: [`resident-${kind}`] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const rate = useMutation({
    mutationFn: (values) => api.resident.rateMaintenanceRequest(detail.id, values),
    onSuccess: () => {
      message.success('Rating dikirim.');
      queryClient.invalidateQueries({ queryKey: [`resident-${kind}-detail`, detail?.id] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  return (
    <section>
      <PageHeader
        title={isComplaint ? 'Komplain Penghuni' : 'Maintenance Request'}
        breadcrumbs={[{ label: 'Penghuni' }, { label: isComplaint ? 'Komplain' : 'Maintenance' }]}
        actions={<Button type="primary" icon={<PlusOutlined />} onClick={() => setOpen(true)}>Buat Baru</Button>}
        onRefresh={query.refetch}
      />
      <FilterBar>
        <Input allowClear placeholder="Cari" value={table.search} onChange={(event) => table.setSearch(event.target.value)} className="filter-input" />
        <Select allowClear placeholder="Status" value={table.filters.status} onChange={(value) => table.setFilters({ ...table.filters, status: value })} className="filter-input" options={(isComplaint ? ['submitted', 'in_review', 'in_progress', 'resolved', 'closed', 'rejected'] : ['submitted', 'in_review', 'scheduled', 'in_progress', 'completed', 'closed', 'rejected']).map((value) => ({ value, label: value }))} />
      </FilterBar>
      <Card>
        <ResponsiveTable
          query={query}
          onChange={table.handleTableChange}
          scrollX={920}
          columns={[
            { title: isComplaint ? 'Judul' : 'Kategori', dataIndex: isComplaint ? 'title' : 'category', width: 220, fixed: 'left' },
            { title: 'Prioritas', dataIndex: isComplaint ? 'priority' : 'urgency', width: 120 },
            { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge type="transaction" value={value} />, width: 150 },
            { title: 'Dibuat', dataIndex: 'created_at', render: formatDateTime, width: 170 },
            { title: 'Aksi', fixed: 'right', width: 130, render: (_, row) => <Button size="small" icon={<EyeOutlined />} onClick={() => setDetail(row)}>Detail</Button> },
          ]}
        />
      </Card>
      <Drawer title={isComplaint ? 'Buat Komplain' : 'Buat Maintenance Request'} open={open} onClose={() => setOpen(false)} width={560} extra={<Button type="primary" loading={create.isPending} onClick={() => form.submit()}>Simpan</Button>} destroyOnHidden>
        <Form form={form} layout="vertical" onFinish={create.mutate} initialValues={{ priority: 'normal', urgency: 'normal' }}>
          {isComplaint ? <Form.Item label="Judul" name="title" rules={[{ required: true }]}><Input /></Form.Item> : null}
          <Form.Item label="Kategori" name="category" rules={[{ required: !isComplaint }]}><Input /></Form.Item>
          <Form.Item label={isComplaint ? 'Prioritas' : 'Urgensi'} name={isComplaint ? 'priority' : 'urgency'} rules={[{ required: true }]}>
            <Select options={(isComplaint ? ['low', 'normal', 'high', 'urgent'] : ['low', 'normal', 'high', 'emergency']).map((value) => ({ value, label: value }))} />
          </Form.Item>
          {!isComplaint ? <Form.Item label="Jadwal yang diinginkan" name="preferred_schedule"><DatePicker showTime style={{ width: '100%' }} /></Form.Item> : null}
          <Form.Item label="Deskripsi" name="description" rules={[{ required: true }]}><Input.TextArea rows={5} /></Form.Item>
          <Form.Item label="Attachment" name="attachment" valuePropName="fileList" getValueFromEvent={(event) => event?.fileList}>
            <Upload beforeUpload={() => false} maxCount={1} accept=".jpg,.jpeg,.png,.pdf"><Button icon={<CloudUploadOutlined />}>Pilih File</Button></Upload>
          </Form.Item>
        </Form>
      </Drawer>
      <Drawer title="Detail" open={Boolean(detail)} onClose={() => setDetail(null)} width={640} destroyOnHidden>
        {detailQuery.isLoading ? <LoadingState /> : (
          <Space direction="vertical" style={{ width: '100%' }} size="middle">
            <InfoList data={{ Judul: detailData.title, Kategori: detailData.category, Deskripsi: detailData.description, Status: detailData.status, 'Jadwal teknisi': formatDateTime(detailData.scheduled_at), 'Catatan teknisi': detailData.technician_notes, Rating: detailData.rating }} />
            {isComplaint ? (
              <>
                <Card title="Komentar">
                  {asList(detailData.comments).map((item) => <p key={item.id}><strong>{item.user?.name}</strong>: {item.body}<br /><Typography.Text type="secondary">{formatDateTime(item.created_at)}</Typography.Text></p>)}
                  <Form form={commentForm} layout="vertical" onFinish={addComment.mutate}>
                    <Form.Item name="body" rules={[{ required: true }]}><Input.TextArea rows={3} placeholder="Tambahkan komentar" /></Form.Item>
                    <Form.Item name="attachment" valuePropName="fileList" getValueFromEvent={(event) => event?.fileList}><Upload beforeUpload={() => false} maxCount={1}><Button icon={<CloudUploadOutlined />}>Attachment</Button></Upload></Form.Item>
                    <Button type="primary" htmlType="submit" loading={addComment.isPending}>Kirim Komentar</Button>
                  </Form>
                </Card>
                <Button danger icon={<CheckOutlined />} disabled={['closed', 'rejected'].includes(detailData.status)} onClick={() => Modal.confirm({ title: 'Tutup komplain?', onOk: closeComplaint.mutate })}>Tutup Komplain</Button>
              </>
            ) : (
              <Card title="Rating Setelah Selesai">
                <Form layout="vertical" onFinish={rate.mutate}>
                  <Form.Item label="Rating" name="rating" rules={[{ required: true }]}><Select options={[1, 2, 3, 4, 5].map((value) => ({ value, label: `${value}` }))} /></Form.Item>
                  <Form.Item label="Catatan" name="rating_notes"><Input.TextArea rows={3} /></Form.Item>
                  <Button type="primary" htmlType="submit" loading={rate.isPending}>Kirim Rating</Button>
                </Form>
              </Card>
            )}
          </Space>
        )}
      </Drawer>
    </section>
  );
}

function DocumentList({ documents, query }) {
  const items = documents || unwrapQuery(query) || [];
  async function download(row) {
    const blob = row.download_type === 'payment_receipt'
      ? await api.resident.downloadPaymentReceipt(row.download_id)
      : row.download_type === 'receipt'
        ? await api.resident.downloadCashReceipt(row.download_id)
        : await api.resident.downloadInvoice(row.download_id);
    downloadBlob(blob, `${row.reference || row.name}.pdf`);
  }
  return (
    <Card>
      <ResponsiveTable
        data={items}
        rowKey="id"
        scrollX={820}
        columns={[
          { title: 'Nama', dataIndex: 'name', fixed: 'left', width: 260 },
          { title: 'Jenis', dataIndex: 'type', width: 120 },
          { title: 'Referensi', dataIndex: 'reference', width: 180 },
          { title: 'Tanggal', dataIndex: 'created_at', render: formatDateTime, width: 180 },
          { title: 'Aksi', fixed: 'right', width: 120, render: (_, row) => <Button size="small" icon={<DownloadOutlined />} onClick={() => download(row)}>Download</Button> },
        ]}
      />
    </Card>
  );
}

function ResidentDocuments() {
  const query = useQuery({ queryKey: ['resident-documents'], queryFn: api.resident.documents });
  return (
    <section>
      <PageHeader title="Dokumen Penghuni" breadcrumbs={[{ label: 'Penghuni' }, { label: 'Dokumen' }]} onRefresh={query.refetch} />
      {query.isLoading ? <LoadingState /> : query.isError ? <ErrorState error={query.error} onRetry={query.refetch} /> : <DocumentList query={query} />}
    </section>
  );
}

function NotificationList({ data, query, onRead }) {
  const items = data || query?.data?.data || [];
  return (
    <Card>
      {items.length ? items.map((item) => (
        <div className="resident-list-item" key={item.id}>
          <div>
            <Typography.Text strong>{item.title || item.subject || item.type}</Typography.Text>
            <div>{item.message || item.body || item.description}</div>
            <Typography.Text type="secondary">{formatDateTime(item.created_at)}</Typography.Text>
          </div>
          <Space>
            <StatusBadge type="read" value={item.read_status || (item.read_at ? 'read' : 'unread')} />
            {onRead ? <Button size="small" onClick={() => onRead(item.id)}>Dibaca</Button> : null}
          </Space>
        </div>
      )) : <EmptyData />}
    </Card>
  );
}

function ResidentNotifications() {
  const table = useTableState();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ['resident-notifications', table.params], queryFn: () => api.resident.notifications(table.params) });
  const read = useMutation({ mutationFn: api.resident.readNotification, onSuccess: () => queryClient.invalidateQueries({ queryKey: ['resident-notifications'] }) });
  const readAll = useMutation({ mutationFn: api.resident.readAllNotifications, onSuccess: () => queryClient.invalidateQueries({ queryKey: ['resident-notifications'] }) });
  return (
    <section>
      <PageHeader title="Notifikasi Penghuni" breadcrumbs={[{ label: 'Penghuni' }, { label: 'Notifikasi' }]} actions={<Button icon={<CheckOutlined />} onClick={() => readAll.mutate()} loading={readAll.isPending}>Tandai Semua Dibaca</Button>} onRefresh={query.refetch} />
      <FilterBar>
        <Select allowClear placeholder="Status" value={table.filters.read_status} onChange={(value) => table.setFilters({ ...table.filters, read_status: value })} className="filter-input" options={[{ value: 'read', label: 'Dibaca' }, { value: 'unread', label: 'Belum Dibaca' }]} />
        <Input allowClear placeholder="Tipe notifikasi" value={table.filters.type} onChange={(event) => table.setFilters({ ...table.filters, type: event.target.value || undefined })} className="filter-input" />
      </FilterBar>
      {query.isLoading ? <LoadingState /> : <NotificationList query={query} onRead={(id) => read.mutate(id)} />}
    </section>
  );
}

function ActivityList({ data, query }) {
  const items = data || query?.data?.data || [];
  return (
    <Card>
      {items.length ? <Timeline items={items.map((item) => ({ children: <span><strong>{item.action || item.event}</strong> {item.module ? `di ${item.module}` : ''}<br /><Typography.Text type="secondary">{formatDateTime(item.created_at)} {item.ip_address || ''}</Typography.Text></span> }))} /> : <EmptyData />}
    </Card>
  );
}

function ResidentActivity() {
  const table = useTableState();
  const query = useQuery({ queryKey: ['resident-activity', table.params], queryFn: () => api.resident.activity(table.params) });
  return (
    <section>
      <PageHeader title="Aktivitas Penghuni" breadcrumbs={[{ label: 'Penghuni' }, { label: 'Aktivitas' }]} onRefresh={query.refetch} />
      {query.isLoading ? <LoadingState /> : <ActivityList query={query} />}
    </section>
  );
}

function ResidentSettings() {
  const queryClient = useQueryClient();
  const { setMode } = useThemeMode();
  const query = useQuery({ queryKey: ['resident-settings'], queryFn: api.resident.settings });
  const data = unwrapQuery(query) || {};
  const [form] = Form.useForm();
  useEffect(() => {
    form.setFieldsValue({
      theme_preference: data.theme_preference || 'system',
      language_preference: data.language_preference || 'id',
      ...Object.fromEntries(Object.entries(data.notification_preferences || {}).map(([key, value]) => [`notification_${key}`, value])),
    });
  }, [data, form]);

  const update = useMutation({
    mutationFn: (values) => api.resident.updateSettings({
      theme_preference: values.theme_preference,
      language_preference: values.language_preference,
      notification_preferences: Object.fromEntries(Object.entries(values).filter(([key]) => key.startsWith('notification_')).map(([key, value]) => [key.replace('notification_', ''), Boolean(value)])),
    }),
    onSuccess: (_, values) => {
      setMode(values.theme_preference);
      message.success('Pengaturan disimpan.');
      queryClient.invalidateQueries({ queryKey: ['resident-settings'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  return (
    <section>
      <PageHeader title="Pengaturan Penghuni" breadcrumbs={[{ label: 'Penghuni' }, { label: 'Pengaturan' }]} onRefresh={query.refetch} />
      <Card>
        {query.isLoading ? <LoadingState /> : (
          <Form form={form} layout="vertical" className="responsive-form" onFinish={update.mutate}>
            <Form.Item label="Tema" name="theme_preference" rules={[{ required: true }]}><Select options={[{ value: 'light', label: 'Light' }, { value: 'dark', label: 'Dark' }, { value: 'system', label: 'System' }]} /></Form.Item>
            <Form.Item label="Bahasa" name="language_preference" rules={[{ required: true }]}><Select options={[{ value: 'id', label: 'Indonesia' }, { value: 'en', label: 'English' }]} /></Form.Item>
            {Object.keys(data.notification_preferences || {}).map((key) => (
              <Form.Item key={key} label={`Notifikasi ${key}`} name={`notification_${key}`}><Select options={[{ value: true, label: 'Aktif' }, { value: false, label: 'Nonaktif' }]} /></Form.Item>
            ))}
            <Form.Item className="full-span"><Button type="primary" htmlType="submit" loading={update.isPending}>Simpan Pengaturan</Button></Form.Item>
          </Form>
        )}
      </Card>
    </section>
  );
}

export default function ResidentPortalPage({ page }) {
  const screens = Grid.useBreakpoint();
  const current = useMemo(() => page, [page]);
  useEffect(() => {
    document.body.classList.toggle('resident-mobile', !screens.md);
    return () => document.body.classList.remove('resident-mobile');
  }, [screens.md]);

  if (current === 'dashboard') return <ResidentDashboard />;
  if (current === 'account') return <ResidentAccount />;
  if (current === 'profile') return <ResidentAccount mode="profile" />;
  if (current === 'property') return <ResidentProperty />;
  if (current === 'bills' || current === 'invoices') return <ResidentBills />;
  if (current === 'invoice-detail') return <ResidentInvoiceDetail />;
  if (current === 'payments') return <ResidentPayments />;
  if (current === 'payment-detail') return <ResidentPaymentDetail />;
  if (current === 'payment-methods') return <ResidentPaymentMethods />;
  if (current === 'complaints') return <ComplaintMaintenancePage kind="complaints" />;
  if (current === 'maintenance') return <ComplaintMaintenancePage kind="maintenance" />;
  if (current === 'documents') return <ResidentDocuments />;
  if (current === 'notifications') return <ResidentNotifications />;
  if (current === 'activity') return <ResidentActivity />;
  if (current === 'settings') return <ResidentSettings />;
  return <EmptyData description="Halaman penghuni tidak ditemukan." />;
}
