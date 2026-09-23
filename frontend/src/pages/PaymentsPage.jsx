import { Alert, Badge, Button, Card, Checkbox, DatePicker, Descriptions, Drawer, Form, Input, Modal, Select, Space, Statistic, Tabs, Tag, Upload, message, Typography } from 'antd';
import { CheckOutlined, CloudUploadOutlined, CloseOutlined, FileExcelOutlined, LinkOutlined, PrinterOutlined, SearchOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import dayjs from 'dayjs';
import PageHeader from '../components/common/PageHeader.jsx';
import ExportPdfButton from '../components/common/ExportPdfButton.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import Can from '../components/common/Can.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { api, storageUrl } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { useDebounce } from '../hooks/useDebounce.js';
import { usePendingPaymentVerificationCount } from '../hooks/usePendingPaymentVerificationCount.js';
import { formatCurrency, formatDate, formatDateTime, formatPaymentMethod, formatPeriod } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';
import { downloadBlob, printPdf } from '../utils/download.js';
import MoneyInput from '../components/common/MoneyInput.jsx';
import PaymentPrintMenu from '../components/common/PaymentPrintMenu.jsx';
import { useAuth } from '../state/AuthContext.jsx';

const PAYMENT_METHOD_LABELS = { C: 'Cash', D: 'Debit/Transfer' };
const PROVIDER_LABELS = { manual: 'Transfer', xendit: 'Xendit', midtrans: 'Midtrans' };
const PAYMENT_CHANNEL_LABELS = { L: 'Loket', M: 'Bank Transfer', Q: 'QRIS' };

export default function PaymentsPage() {
  const { user } = useAuth();
  const [unit, setUnit] = useState(null);
  const [selectedBillingIds, setSelectedBillingIds] = useState([]);
  const [transaction, setTransaction] = useState(null);
  const [selectedVia, setVia] = useState('loket');
  const [proofOpen, setProofOpen] = useState(null);
  const [detailOpen, setDetailOpen] = useState(null);
  const [verifyTarget, setVerifyTarget] = useState(null);
  const [successReceipt, setSuccessReceipt] = useState(null);
  const [unitQuery, setUnitQuery] = useState('');
  const [unitFilters, setUnitFilters] = useState({});
  // Rentang periode yang dipakai pada pencarian tagihan aktif, supaya PDF-nya sama dengan tabel di layar.
  const [searchRange, setSearchRange] = useState({});
  const [exportingTransactions, setExportingTransactions] = useState(null);
  const [exportingReceipts, setExportingReceipts] = useState(null);
  const [selectedReceiptNumbers, setSelectedReceiptNumbers] = useState([]);
  const [searchForm] = Form.useForm();
  const [loketForm] = Form.useForm();
  const [proofForm] = Form.useForm();
  const [verifyForm] = Form.useForm();
  const pendingVerificationCount = usePendingPaymentVerificationCount();
  const queryClient = useQueryClient();
  // Dari aksi "Pembayaran" di halaman Unit: /payments?unit_id=... membatasi transaksi & kuitansi ke unit itu saja.
  const [searchParams, setSearchParams] = useSearchParams();
  const urlUnitId = searchParams.get('unit_id') || undefined;
  const transactionTable = useTableState({ unit_id: urlUnitId });
  const receiptTable = useTableState({ unit_id: urlUnitId });
  const debouncedUnitQuery = useDebounce(unitQuery);

  const config = useQuery({ queryKey: ['payment-gateway-config'], queryFn: api.payments.gatewayConfig });
  const clusters = useQuery({ queryKey: ['clusters'], queryFn: () => api.clusters.list() });
  const transactions = useQuery({ queryKey: ['payment-transactions', transactionTable.params], queryFn: () => api.payments.gatewayTransactions(transactionTable.params) });
  const receipts = useQuery({ queryKey: ['payment-receipts', receiptTable.params], queryFn: () => api.payments.receipts(receiptTable.params) });
  const unitLookup = useQuery({
    queryKey: ['units', 'payment-search', debouncedUnitQuery, unitFilters],
    queryFn: () => api.units.list({
      search: debouncedUnitQuery || undefined,
      cluster_id: unitFilters.cluster_id,
      customer: unitFilters.customer || undefined,
      address: unitFilters.address || undefined,
      per_page: 20,
    }),
  });
  const clusterOptions = (clusters.data?.data || []).map((item) => ({ value: item.id, label: item.name }));

  const search = useMutation({
    mutationFn: (values) => api.payments.search({
      unit_id: values.unit_id,
      date_from: values.billing_range?.[0]?.format('YYYY-MM-DD'),
      date_to: values.billing_range?.[1]?.format('YYYY-MM-DD'),
    }),
    onSuccess: (response, variables) => {
      const found = response.data?.billings || [];
      setUnit(response.data);
      setSearchRange({ date_from: variables?.billing_range?.[0]?.format('YYYY-MM-DD'), date_to: variables?.billing_range?.[1]?.format('YYYY-MM-DD') });
      setSelectedBillingIds(found.map((billing) => billing.id));
      setTransaction(null);
      loketForm.setFieldsValue({ amount: undefined, use_balance: true });
    },
    onError: (error) => message.error(getApiErrorMessage(error, 'Unit tidak ditemukan')),
  });

  const watchedAmount = Form.useWatch('amount', loketForm);
  const watchedUseBalance = Form.useWatch('use_balance', loketForm);
  const debouncedAmount = useDebounce(watchedAmount, 400);

  const previewQuery = useQuery({
    queryKey: ['payment-preview', unit?.id, selectedBillingIds, debouncedAmount, watchedUseBalance ?? true],
    queryFn: () => api.payments.preview({
      unit_id: unit.id,
      billing_ids: selectedBillingIds,
      amount: Number(debouncedAmount) || 0,
      use_balance: watchedUseBalance ?? true,
    }),
    enabled: Boolean(unit) && selectedBillingIds.length > 0,
  });
  const preview = selectedBillingIds.length ? previewQuery.data?.data : undefined;

  const processLoket = useMutation({
    mutationFn: (values) => api.payments.process({
      unit_id: unit.id,
      billing_ids: selectedBillingIds,
      amount: Number(values.amount) || 0,
      use_balance: values.use_balance ?? true,
      payment_method_id: values.payment_method_id,
      payment_channel_id: values.payment_channel_id,
      loket_code: values.loket_code,
      cashier_name: values.cashier_name,
      notes: values.notes,
    }),
    onSuccess: (response) => {
      const depositAmount = Number(response.data?.deposit_amount || 0);
      message.success(depositAmount > 0
        ? `Pembayaran loket berhasil diproses. Kelebihan ${formatCurrency(depositAmount)} dicatat sebagai saldo unit.`
        : 'Pembayaran loket berhasil diproses');
      setSuccessReceipt(response.data);
      resetPaymentWorkspace();
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      queryClient.invalidateQueries({ queryKey: ['payment-receipts'] });
      queryClient.invalidateQueries({ queryKey: ['payment-schemes'] });
    },
    onError: (error) => {
      loketForm.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const createGateway = useMutation({
    mutationFn: (values) => api.payments.createGateway({
      ...values,
      unit_id: unit.id,
      billing_ids: selectedBillingIds,
    }),
    onSuccess: (response) => {
      message.success('Transaksi gateway berhasil dibuat');
      setTransaction(response.data);
      queryClient.invalidateQueries({ queryKey: ['payment-transactions'] });
    },
    onError: (error) => {
      message.error(getApiErrorMessage(error));
    },
  });

  const uploadProof = useMutation({
    mutationFn: (values) => {
      const formData = new FormData();
      formData.append('proof', values.proof[0].originFileObj);
      formData.append('manual_transfer_date', values.manual_transfer_date.format('YYYY-MM-DD'));
      if (values.amount) formData.append('amount', values.amount);
      if (values.manual_notes) formData.append('manual_notes', values.manual_notes);
      return api.payments.uploadManualProof(proofOpen.id, formData);
    },
    onSuccess: () => {
      message.success('Bukti pembayaran berhasil diunggah');
      setProofOpen(null);
      proofForm.resetFields();
      queryClient.invalidateQueries({ queryKey: ['payment-transactions'] });
    },
    onError: (error) => {
      proofForm.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const verify = useMutation({
    mutationFn: ({ id, status, notes }) => status === 'paid'
      ? api.payments.verifyManual(id, { verification_notes: notes })
      : api.payments.rejectManual(id, { verification_notes: notes }),
    onSuccess: (_response, { status }) => {
      message.success(status === 'paid' ? 'Pembayaran berhasil diverifikasi' : 'Pembayaran ditolak');
      closeVerifyModal();
      setDetailOpen(null);
      queryClient.invalidateQueries({ queryKey: ['payment-transactions'] });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
    },
    onError: (error) => {
      verifyForm.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  function openVerifyModal(row, status) {
    setVerifyTarget({ row, status });
  }

  function closeVerifyModal() {
    setVerifyTarget(null);
  }

  async function submitVerify() {
    const { notes } = await verifyForm.validateFields();
    verify.mutate({ id: verifyTarget.row.id, status: verifyTarget.status, notes });
  }

  async function printTransactions() {
    const { page: _page, per_page: _perPage, ...filters } = transactionTable.params;
    setExportingTransactions('pdf');
    try {
      await printPdf(() => api.documents.paymentTransactionsPdf(filters), 'transaksi-gateway.pdf');
    } catch (error) {
      message.error(getApiErrorMessage(error, 'Gagal memuat PDF transaksi'));
    } finally {
      setExportingTransactions(null);
    }
  }

  async function exportTransactionsExcel() {
    setExportingTransactions('excel');
    try {
      const { page: _page, per_page: _perPage, ...filters } = transactionTable.params;
      const blob = await api.documents.paymentTransactionsExcel(filters);
      downloadBlob(blob, 'transaksi-gateway.csv');
    } catch (error) {
      message.error(getApiErrorMessage(error, 'Gagal mengunduh data transaksi'));
    } finally {
      setExportingTransactions(null);
    }
  }

  // Bila ada kuitansi yang dicentang, hanya itu yang diekspor; jika tidak, semua hasil sesuai filter.
  function receiptExportFilters() {
    if (selectedReceiptNumbers.length) return { numbers: selectedReceiptNumbers };
    const { page: _page, per_page: _perPage, ...filters } = receiptTable.params;
    return filters;
  }

  async function printReceipts() {
    const filters = receiptExportFilters();
    setExportingReceipts('pdf');
    try {
      await printPdf(() => api.documents.paymentReceiptsPdf(filters), 'riwayat-kuitansi.pdf');
    } catch (error) {
      message.error(getApiErrorMessage(error, 'Gagal memuat PDF kuitansi'));
    } finally {
      setExportingReceipts(null);
    }
  }

  async function exportReceiptsExcel() {
    setExportingReceipts('excel');
    try {
      const blob = await api.documents.paymentReceiptsExcel(receiptExportFilters());
      downloadBlob(blob, 'riwayat-kuitansi.csv');
    } catch (error) {
      message.error(getApiErrorMessage(error, 'Gagal mengunduh data kuitansi'));
    } finally {
      setExportingReceipts(null);
    }
  }

  useEffect(() => {
    if ((transactionTable.filters.unit_id || undefined) !== urlUnitId) transactionTable.setFilters({ ...transactionTable.filters, unit_id: urlUnitId });
    if ((receiptTable.filters.unit_id || undefined) !== urlUnitId) receiptTable.setFilters({ ...receiptTable.filters, unit_id: urlUnitId });
  }, [urlUnitId]); // eslint-disable-line react-hooks/exhaustive-deps

  function updateUnitFilters(patch) {
    setUnitFilters((previous) => ({ ...previous, ...patch }));
    searchForm.setFieldValue('unit_id', undefined);
    setUnitQuery('');
  }

  // Tagihan dalam satu skema pembayaran adalah satu kewajiban: dipilih dan dilepas bersamaan.
  function changeSelection(keys) {
    const bills = unit?.billings || [];
    const schemeOf = (id) => bills.find((billing) => billing.id === id)?.payment_scheme_id;
    const next = new Set(keys);
    keys.filter((id) => !selectedBillingIds.includes(id)).forEach((id) => {
      const scheme = schemeOf(id);
      if (scheme) bills.filter((billing) => billing.payment_scheme_id === scheme).forEach((billing) => next.add(billing.id));
    });
    selectedBillingIds.filter((id) => !keys.includes(id)).forEach((id) => {
      const scheme = schemeOf(id);
      if (scheme) bills.filter((billing) => billing.payment_scheme_id === scheme).forEach((billing) => next.delete(billing.id));
    });
    setSelectedBillingIds([...next]);
    setTransaction(null);
  }

  function resetPaymentWorkspace() {
    setUnit(null);
    setSelectedBillingIds([]);
    setTransaction(null);
    setUnitQuery('');
    setUnitFilters({});
    searchForm.resetFields();
    loketForm.resetFields();
  }

  const unpaidBillings = unit?.billings || [];
  const selectedTotal = unpaidBillings
    .filter((billing) => selectedBillingIds.includes(billing.id))
    .reduce((sum, billing) => sum + Number(billing.penalty_detail?.total_outstanding ?? 0), 0);
  const balance = Number(unit?.deposit_balance ?? 0);
  const useBalance = watchedUseBalance ?? true;
  // Nominal tunai tidak boleh kurang dari sisa tagihan setelah saldo unit (bila dipakai); lebih dari itu boleh (kelebihan jadi saldo).
  const cashMin = Math.max(0, selectedTotal - (useBalance ? balance : 0));

  // Nominal tunai bawaan = sisa tagihan setelah saldo unit (bila dipakai); petugas tetap bisa menambah lebih.
  useEffect(() => {
    if (!unit || !selectedBillingIds.length) return;
    loketForm.setFieldValue('amount', cashMin);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [unit?.id, selectedBillingIds, balance, useBalance]);

  // Loket and Transfer are always offered; Xendit/Midtrans only when the backend reports them enabled.
  const availableGateways = config.data?.data?.available_methods || ['manual'];
  const viaOptions = [
    { value: 'loket', label: 'Loket' },
    { value: 'manual', label: 'Transfer' },
    ...['xendit', 'midtrans'].filter((value) => availableGateways.includes(value)).map((value) => ({ value, label: PROVIDER_LABELS[value] })),
  ];
  const via = viaOptions.some((option) => option.value === selectedVia) ? selectedVia : 'loket';
  const viaLabel = viaOptions.find((option) => option.value === via)?.label;
  const manualInfo = config.data?.data?.manual_payment || {};
  const unitOptions = (unitLookup.data?.data || []).map((item) => ({
    value: item.id,
    label: `${item.id} — ${item.cluster?.name || ''} ${item.block || ''}/${item.lot_number || ''} — ${item.resident?.name || ''}`,
  }));

  const schemeGroups = Object.values(unpaidBillings.reduce((groups, billing) => {
    if (!billing.payment_scheme_id) return groups;
    const group = groups[billing.payment_scheme_id] || { id: billing.payment_scheme_id, count: 0, total: 0 };
    return { ...groups, [billing.payment_scheme_id]: { ...group, count: group.count + 1, total: group.total + Number(billing.penalty_detail?.total_outstanding ?? 0) } };
  }, {}));

  const billingColumns = [
    { title: 'Periode', render: (_, row) => formatPeriod(row.year, row.month) },
    { title: 'Jatuh Tempo', render: (_, row) => formatDate(row.penalty_detail?.due_date) },
    { title: 'Nominal', render: (_, row) => formatCurrency(row.penalty_detail?.principal_amount ?? row.amount) },
    { title: 'Denda', render: (_, row) => formatCurrency(row.penalty_detail?.penalty_amount ?? 0) },
    { title: 'Tagihan', render: (_, row) => formatCurrency(row.penalty_detail?.total_amount ?? 0) },
    { title: 'Terbayar', render: (_, row) => formatCurrency(row.penalty_detail?.total_paid ?? 0) },
    { title: 'Sisa Tagihan', render: (_, row) => formatCurrency(row.penalty_detail?.total_outstanding ?? 0) },
    { title: 'Status', render: (_, row) => <StatusBadge type="billing" value={row.status_id} /> },
    ...(schemeGroups.length ? [{ title: 'Skema', render: (_, row) => (row.payment_scheme_id ? <Tag color="green">Skema #{row.payment_scheme_id}</Tag> : '-') }] : []),
  ];

  return (
    <section>
      <PageHeader
        title="Pembayaran"
        subtitle="Pembayaran loket, transaksi gateway, verifikasi manual, dan riwayat kuitansi."
        breadcrumbs={[{ label: 'Pembayaran' }]}
        onRefresh={() => {
          config.refetch();
          transactions.refetch();
          receipts.refetch();
        }}
      />

      {urlUnitId ? (
        <Alert
          type="info"
          showIcon
          className="section-row"
          message={`Menampilkan transaksi dan kuitansi pembayaran untuk unit ${urlUnitId}`}
          action={<Button size="small" onClick={() => setSearchParams({})}>Tampilkan semua unit</Button>}
        />
      ) : null}

      <Tabs
        defaultActiveKey={urlUnitId ? 'receipts' : 'workspace'}
        items={[
          {
            key: 'workspace',
            label: 'Proses Pembayaran',
            children: (
              <div className="stack">
                <FilterBar>
                  <Select allowClear showSearch placeholder="Cluster" value={unitFilters.cluster_id} onChange={(value) => updateUnitFilters({ cluster_id: value })} options={clusterOptions} optionFilterProp="label" loading={clusters.isFetching} className="filter-input" />
                  <Input allowClear placeholder="Nama customer" value={unitFilters.customer} onChange={(event) => updateUnitFilters({ customer: event.target.value || undefined })} className="filter-input" />
                  <Input allowClear placeholder="Alamat (blok/kavling)" value={unitFilters.address} onChange={(event) => updateUnitFilters({ address: event.target.value || undefined })} className="filter-input" />
                </FilterBar>

                <Card>
                  <Form form={searchForm} layout="vertical" onFinish={search.mutate} className="responsive-form">
                    <Form.Item label="Unit" name="unit_id" rules={[{ required: true, message: 'Pilih unit' }]}>
                      <Select
                        showSearch
                        filterOption={false}
                        onSearch={setUnitQuery}
                        options={unitOptions}
                        loading={unitLookup.isFetching}
                        notFoundContent={unitLookup.isFetching ? 'Mencari...' : 'Tidak ditemukan'}
                        placeholder="Cari ID unit, alamat (cluster/blok/kavling), atau nama penghuni"
                      />
                    </Form.Item>
                    <Form.Item label="Periode Tagihan (opsional)" name="billing_range">
                      <DatePicker.RangePicker picker="month" placeholder={['Tanggal awal', 'Tanggal akhir']} style={{ width: '100%' }} />
                    </Form.Item>
                    <Form.Item className="full-span">
                      <Space>
                        <Button type="primary" htmlType="submit" icon={<SearchOutlined />} loading={search.isPending}>Cari Tagihan</Button>
                        <Button onClick={resetPaymentWorkspace}>Reset</Button>
                      </Space>
                    </Form.Item>
                  </Form>
                </Card>

                {unit ? (
                  <Card
                    title={`${unit.id} - ${unit.resident?.name}`}
                    extra={(
                      <Space>
                        {unit.cluster?.name}
                        <ExportPdfButton dataset="unit-outstanding" params={{ unit_id: unit.id, ...searchRange }} filename={`tagihan-${unit.id}.pdf`} permission="billings.view" label="Cetak Tagihan Unit" />
                        <ExportPdfButton dataset="unit-billing-history" params={{ unit_id: unit.id, ...searchRange }} filename={`riwayat-tagihan-${unit.id}.pdf`} permission="billings.view" label="Cetak Riwayat Tagihan" />
                      </Space>
                    )}
                  >
                    <Space size="large" wrap className="section-row">
                      <Statistic title="Saldo Unit" value={formatCurrency(unit.deposit_balance)} />
                      <Statistic title="Total Tunggakan" value={formatCurrency(unit.total_outstanding)} />
                      <Statistic title="Tagihan Mendatang" value={formatCurrency(unit.total_upcoming)} />
                    </Space>

                    {schemeGroups.map((group) => (
                      <Alert
                        key={group.id}
                        className="section-row"
                        type="success"
                        showIcon
                        message={`Skema Pembayaran #${group.id} — ${group.count} tagihan, total ${formatCurrency(group.total)}`}
                        description="Diskon dan keringanan denda dari skema yang disetujui sudah diterapkan pada tagihan bertanda Skema. Tagihan dalam satu skema dibayar bersamaan."
                      />
                    ))}

                    <ResponsiveTable
                      data={unpaidBillings}
                      columns={billingColumns}
                      pagination={false}
                      scrollX={1300}
                      rowSelection={{
                        selectedRowKeys: selectedBillingIds,
                        onChange: changeSelection,
                      }}
                    />
                    <Typography.Text className="section-row" style={{ display: 'block' }}>
                      Dipilih: <strong>{selectedBillingIds.length}</strong> dari {unpaidBillings.length} tagihan — Total: <strong>{formatCurrency(selectedTotal)}</strong>
                    </Typography.Text>

                    <Space className="section-row" direction="vertical" style={{ width: '100%' }}>
                      <Typography.Text strong>Via</Typography.Text>
                      <Select value={via} onChange={(value) => { setVia(value); setTransaction(null); }} options={viaOptions} style={{ width: '100%', maxWidth: 320 }} />
                    </Space>
                    {via === 'loket' ? (
                            <Can permission="payments.process" fallback={<Alert type="warning" showIcon message="Anda tidak memiliki akses proses loket." />}>
                              <Form form={loketForm} layout="vertical" onFinish={processLoket.mutate} initialValues={{ payment_method_id: 'C', loket_code: 'L01', use_balance: true, cashier_name: user?.name }} className="responsive-form">
                                <Form.Item label="Nominal Pembayaran (tunai)" name="amount" rules={[{ type: 'number', min: cashMin, message: `Nominal minimal ${formatCurrency(cashMin)}` }]}>
                                  <MoneyInput step={1000} min={cashMin} />
                                </Form.Item>
                                <Form.Item name="use_balance" valuePropName="checked" className="full-span">
                                  <Checkbox disabled={!unit.deposit_balance}>Gunakan saldo unit ({formatCurrency(unit.deposit_balance)})</Checkbox>
                                </Form.Item>
                                <Form.Item label="Metode" name="payment_method_id" rules={[{ required: true }]}>
                                  <Select options={[{ value: 'C', label: 'Cash' }, { value: 'D', label: 'Debit/Transfer' }]} />
                                </Form.Item>
                                <Form.Item label="Channel" name="payment_channel_id">
                                  <Select allowClear options={[{ value: 'L', label: 'Loket' }, { value: 'M', label: 'Bank Transfer' }, { value: 'Q', label: 'QRIS' }]} />
                                </Form.Item>
                                <Form.Item label="Kode Loket" name="loket_code"><Input /></Form.Item>
                                <Form.Item label="Nama Kasir" name="cashier_name" tooltip="Otomatis sesuai akun yang login, tidak dapat diubah.">
                                  <Input disabled />
                                </Form.Item>
                                <Form.Item label="Catatan" name="notes" className="full-span"><Input.TextArea rows={2} /></Form.Item>

                                {preview ? (
                                  <Card size="small" type="inner" title="Ringkasan Alokasi" className="full-span">
                                    <Descriptions size="small" column={2} bordered>
                                      <Descriptions.Item label="Total Tunggakan">{formatCurrency(preview.total_outstanding)}</Descriptions.Item>
                                      <Descriptions.Item label="Nominal Pembayaran">{formatCurrency(preview.payment_amount)}</Descriptions.Item>
                                      <Descriptions.Item label="Saldo Digunakan">{formatCurrency(preview.balance_used)}</Descriptions.Item>
                                      <Descriptions.Item label="Teralokasi">{formatCurrency(preview.amount_allocated)}</Descriptions.Item>
                                      <Descriptions.Item label="Sisa Tunggakan">{formatCurrency(preview.remaining_outstanding)}</Descriptions.Item>
                                      <Descriptions.Item label="Saldo Baru">{formatCurrency(preview.new_balance)}</Descriptions.Item>
                                    </Descriptions>
                                    {preview.overpayment > 0 ? (
                                      <Alert style={{ marginTop: 12 }} type="success" showIcon message={`Kelebihan pembayaran +${formatCurrency(preview.overpayment)} akan ditambahkan ke saldo unit.`} />
                                    ) : null}
                                  </Card>
                                ) : null}

                                <Form.Item className="full-span">
                                  <Button type="primary" htmlType="submit" disabled={!preview?.amount_allocated} loading={processLoket.isPending}>Proses Bayar Loket</Button>
                                </Form.Item>
                              </Form>
                            </Can>
                    ) : (
                            <Can permission="payments.create" fallback={<Alert type="warning" showIcon message="Anda tidak memiliki akses membuat transaksi gateway." />}>
                              <Alert
                                type="info"
                                showIcon
                                message={`Via: ${viaLabel}`}
                                description={via === 'manual' ? `${manualInfo.bank_name || '-'} ${manualInfo.account_number || ''} a.n. ${manualInfo.account_name || '-'}` : 'Transaksi akan menghasilkan payment URL. Transaksi gateway melunasi seluruh tagihan yang dipilih.'}
                              />
                              <Button className="section-row" type="primary" disabled={!selectedBillingIds.length} loading={createGateway.isPending} onClick={() => createGateway.mutate({ provider: via })}>Buat Transaksi</Button>
                              {transaction ? (
                                <Card className="section-row" title={transaction.invoice_number}>
                                  <Space direction="vertical">
                                    <StatusBadge type="transaction" value={transaction.status} />
                                    <Typography.Text>Total: {formatCurrency(transaction.total)}</Typography.Text>
                                    {transaction.payment_url ? <Button icon={<LinkOutlined />} href={transaction.payment_url} target="_blank">Buka Payment URL</Button> : null}
                                    {transaction.payment_provider === 'manual' ? <Button icon={<CloudUploadOutlined />} onClick={() => setProofOpen(transaction)}>Upload Bukti Transfer</Button> : null}
                                  </Space>
                                </Card>
                              ) : null}
                            </Can>
                    )}
                  </Card>
                ) : null}
              </div>
            ),
          },
          {
            key: 'transactions',
            label: pendingVerificationCount ? (
              <Space size={6}>
                Transaksi Gateway
                <Badge count={pendingVerificationCount} size="small" />
              </Space>
            ) : 'Transaksi Gateway',
            children: (
              <>
                <FilterBar
                  extra={
                    <Can permission="documents.generate">
                      <Space wrap>
                        <Button icon={<PrinterOutlined />} loading={exportingTransactions === 'pdf'} disabled={Boolean(exportingTransactions)} onClick={printTransactions}>Cetak PDF</Button>
                        <Button icon={<FileExcelOutlined />} loading={exportingTransactions === 'excel'} disabled={Boolean(exportingTransactions)} onClick={exportTransactionsExcel}>Export Excel</Button>
                      </Space>
                    </Can>
                  }
                >
                  <Input allowClear placeholder="Cari invoice, transaksi, penghuni" value={transactionTable.search} onChange={(event) => transactionTable.setSearch(event.target.value)} className="filter-input" />
                  <Select allowClear showSearch placeholder="Cluster" value={transactionTable.filters.cluster_id} onChange={(value) => transactionTable.setFilters({ ...transactionTable.filters, cluster_id: value })} className="filter-input" options={clusterOptions} optionFilterProp="label" loading={clusters.isFetching} />
                  <Input allowClear placeholder="Nama penghuni/customer" value={transactionTable.filters.customer} onChange={(event) => transactionTable.setFilters({ ...transactionTable.filters, customer: event.target.value || undefined })} className="filter-input" />
                  <Input allowClear placeholder="Alamat unit (cluster/blok/kavling)" value={transactionTable.filters.address} onChange={(event) => transactionTable.setFilters({ ...transactionTable.filters, address: event.target.value || undefined })} className="filter-input" />
                  <Select allowClear placeholder="Via" value={transactionTable.filters.provider} onChange={(value) => transactionTable.setFilters({ ...transactionTable.filters, provider: value })} className="filter-input" options={viaOptions.filter((option) => option.value !== 'loket')} />
                  <Select allowClear placeholder="Status" value={transactionTable.filters.status} onChange={(value) => transactionTable.setFilters({ ...transactionTable.filters, status: value })} className="filter-input" options={['pending', 'waiting_verification', 'paid', 'rejected', 'failed', 'expired'].map((value) => ({ value, label: value }))} />
                  <DatePicker.RangePicker
                    allowClear
                    placeholder={['Tanggal awal', 'Tanggal akhir']}
                    value={[
                      transactionTable.filters.date_from ? dayjs(transactionTable.filters.date_from) : null,
                      transactionTable.filters.date_to ? dayjs(transactionTable.filters.date_to) : null,
                    ]}
                    onChange={(dates) => transactionTable.setFilters({
                      ...transactionTable.filters,
                      date_from: dates?.[0]?.format('YYYY-MM-DD'),
                      date_to: dates?.[1]?.format('YYYY-MM-DD'),
                    })}
                    className="filter-input"
                  />
                </FilterBar>
                <Card>
                  <ResponsiveTable
                    query={transactions}
                    onChange={transactionTable.handleTableChange}
                    scrollX={2100}
                    columns={[
                      { title: 'Invoice', dataIndex: 'invoice_number', width: 190, fixed: 'left' },
                      { title: 'Penghuni', dataIndex: ['unit', 'resident', 'name'], width: 200 },
                      { title: 'Alamat Unit', render: (_, row) => `${row.unit?.cluster?.name || ''} ${row.unit?.block || ''}/${row.unit?.lot_number || ''}`, width: 180 },
                      { title: 'Via', dataIndex: 'payment_provider', width: 110 },
                      { title: 'Metode', dataIndex: 'payment_method', render: (value) => formatPaymentMethod(value), width: 110 },
                      { title: 'Nominal Tagihan', dataIndex: 'total', render: formatCurrency, width: 140 },
                      { title: 'Nominal Dibayar', dataIndex: 'manual_amount', render: (value) => (value ? formatCurrency(value) : '-'), width: 140 },
                      { title: 'Tgl Bayar', dataIndex: 'manual_transfer_date', render: (value) => (value ? formatDate(value) : '-'), width: 120 },
                      { title: 'Bukti', render: (_, row) => (row.manual_proof_path ? <a href={storageUrl(row.manual_proof_path)} target="_blank" rel="noreferrer">Lihat Bukti</a> : '-'), width: 100 },
                      { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge type="transaction" value={value} />, width: 170 },
                      { title: 'Waktu Upload', dataIndex: 'manual_proof_uploaded_at', render: (value) => (value ? formatDateTime(value) : '-'), width: 170 },
                      {
                        title: 'Aksi',
                        fixed: 'right',
                        width: 470,
                        render: (_, row) => (
                          <Space size={[8, 8]} wrap>
                            <Button size="small" onClick={() => setDetailOpen(row)}>Detail</Button>
                            <Can permission="documents.generate">
                              <PaymentPrintMenu size="small" transaction={row} />
                            </Can>
                            {row.payment_provider === 'manual' && row.status !== 'paid' ? <Button size="small" icon={<CloudUploadOutlined />} onClick={() => setProofOpen(row)}>Upload</Button> : null}
                            <Can permission="payments.verify">
                              <Button size="small" icon={<CheckOutlined />} disabled={row.status !== 'waiting_verification'} onClick={() => openVerifyModal(row, 'paid')}>Verifikasi</Button>
                              <Button size="small" danger icon={<CloseOutlined />} disabled={row.status !== 'waiting_verification'} onClick={() => openVerifyModal(row, 'rejected')}>Tolak</Button>
                            </Can>
                          </Space>
                        ),
                      },
                    ]}
                  />
                </Card>
              </>
            ),
          },
          {
            key: 'receipts',
            label: 'Riwayat Kuitansi',
            children: (
              <>
                <FilterBar
                  extra={
                    <Can permission="documents.generate">
                      <Space wrap>
                        <Button icon={<PrinterOutlined />} loading={exportingReceipts === 'pdf'} disabled={Boolean(exportingReceipts)} onClick={printReceipts}>{selectedReceiptNumbers.length ? `Cetak PDF (${selectedReceiptNumbers.length})` : 'Cetak PDF'}</Button>
                        <Button icon={<FileExcelOutlined />} loading={exportingReceipts === 'excel'} disabled={Boolean(exportingReceipts)} onClick={exportReceiptsExcel}>{selectedReceiptNumbers.length ? `Export Excel (${selectedReceiptNumbers.length})` : 'Export Excel'}</Button>
                      </Space>
                    </Can>
                  }
                >
                  <Input allowClear placeholder="Cari nomor kuitansi, penghuni, ID unit" value={receiptTable.search} onChange={(event) => receiptTable.setSearch(event.target.value)} className="filter-input" />
                  <Select allowClear showSearch placeholder="Cluster" value={receiptTable.filters.cluster_id} onChange={(value) => receiptTable.setFilters({ ...receiptTable.filters, cluster_id: value })} className="filter-input" options={clusterOptions} optionFilterProp="label" loading={clusters.isFetching} />
                  <Input allowClear placeholder="Nama penghuni/customer" value={receiptTable.filters.customer} onChange={(event) => receiptTable.setFilters({ ...receiptTable.filters, customer: event.target.value || undefined })} className="filter-input" />
                  <Input allowClear placeholder="Alamat unit (cluster/blok/kavling)" value={receiptTable.filters.address} onChange={(event) => receiptTable.setFilters({ ...receiptTable.filters, address: event.target.value || undefined })} className="filter-input" />
                  <DatePicker.RangePicker
                    allowClear
                    placeholder={['Tanggal awal', 'Tanggal akhir']}
                    value={[
                      receiptTable.filters.date_from ? dayjs(receiptTable.filters.date_from) : null,
                      receiptTable.filters.date_to ? dayjs(receiptTable.filters.date_to) : null,
                    ]}
                    onChange={(dates) => receiptTable.setFilters({
                      ...receiptTable.filters,
                      date_from: dates?.[0]?.format('YYYY-MM-DD'),
                      date_to: dates?.[1]?.format('YYYY-MM-DD'),
                    })}
                    className="filter-input"
                  />
                </FilterBar>
                {selectedReceiptNumbers.length ? (
                  <Alert
                    type="info"
                    showIcon
                    style={{ marginBottom: 12 }}
                    message={`${selectedReceiptNumbers.length} kuitansi dipilih — Cetak PDF dan Export Excel hanya memuat kuitansi yang dipilih.`}
                    action={<Button size="small" onClick={() => setSelectedReceiptNumbers([])}>Hapus pilihan</Button>}
                  />
                ) : null}
                <Card>
                  <ResponsiveTable
                    query={receipts}
                    onChange={receiptTable.handleTableChange}
                    scrollX={1440}
                    rowKey="number"
                    rowSelection={{
                      selectedRowKeys: selectedReceiptNumbers,
                      onChange: setSelectedReceiptNumbers,
                      preserveSelectedRowKeys: true,
                    }}
                    columns={[
                      { title: 'Nomor', dataIndex: 'number', width: 190, fixed: 'left' },
                      { title: 'Penghuni', dataIndex: 'resident_name', width: 220 },
                      { title: 'Alamat Unit', render: (_, row) => `${row.cluster_name || ''} ${row.block || ''}/${row.lot_number || ''}`, width: 180 },
                      { title: 'Tanggal', dataIndex: 'transaction_date', render: formatDateTime, width: 170 },
                      { title: 'Periode', dataIndex: 'billing_periods', width: 150 },
                      { title: 'Total', dataIndex: 'grand_total', render: formatCurrency, width: 150 },
                      { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge type="transaction" value={value} />, width: 120 },
                      { title: 'Kasir', dataIndex: 'cashier_name', width: 130 },
                      {
                        title: 'Aksi',
                        fixed: 'right',
                        width: 150,
                        render: (_, row) => (
                          <Can permission="documents.generate">
                            <PaymentPrintMenu size="small" receiptNumber={row.number} label="Cetak Kuitansi" />
                          </Can>
                        ),
                      },
                    ]}
                  />
                </Card>
              </>
            ),
          },
        ]}
      />

      <Drawer title="Upload Bukti Pembayaran Manual" open={Boolean(proofOpen)} onClose={() => setProofOpen(null)} width={520} extra={<Button type="primary" onClick={() => proofForm.submit()} loading={uploadProof.isPending}>Upload</Button>} destroyOnHidden>
        <Alert type="info" showIcon message={proofOpen?.invoice_number} description={`Total transfer: ${formatCurrency(proofOpen?.total)}`} />
        <Form form={proofForm} layout="vertical" className="section-row" onFinish={uploadProof.mutate} initialValues={{ manual_transfer_date: dayjs(), amount: proofOpen?.total }}>
          <Form.Item label="Nominal Dibayar" name="amount" rules={[{ type: 'number', min: Number(proofOpen?.total) || 0, message: `Nominal minimal ${formatCurrency(proofOpen?.total)}` }]}>
            <MoneyInput step={1000} min={Number(proofOpen?.total) || 0} />
          </Form.Item>
          <Form.Item label="Tanggal Transfer" name="manual_transfer_date" rules={[{ required: true }]}>
            <DatePicker style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item
            label="Bukti Pembayaran"
            name="proof"
            valuePropName="fileList"
            getValueFromEvent={(event) => event?.fileList}
            rules={[{ required: true, message: 'Bukti pembayaran wajib diunggah' }]}
          >
            <Upload.Dragger beforeUpload={() => false} maxCount={1} accept=".jpg,.jpeg,.png,.pdf">
              <p className="ant-upload-drag-icon"><CloudUploadOutlined /></p>
              <p>Tarik file ke sini atau klik untuk memilih</p>
              <p className="ant-upload-hint">Format: JPG, PNG, PDF. Maksimal mengikuti konfigurasi backend.</p>
            </Upload.Dragger>
          </Form.Item>
          <Form.Item label="Catatan" name="manual_notes">
            <Input.TextArea rows={3} />
          </Form.Item>
        </Form>
      </Drawer>

      <Modal
        title="Detail Pembayaran"
        open={Boolean(detailOpen)}
        onCancel={() => setDetailOpen(null)}
        footer={(
          <Space wrap>
            <Can permission="payments.verify">
              {detailOpen?.status === 'waiting_verification' ? (
                <>
                  <Button danger icon={<CloseOutlined />} onClick={() => openVerifyModal(detailOpen, 'rejected')}>Tolak</Button>
                  <Button type="primary" icon={<CheckOutlined />} onClick={() => openVerifyModal(detailOpen, 'paid')}>Verifikasi</Button>
                </>
              ) : null}
            </Can>
            <Can permission="documents.generate">
              {detailOpen ? <PaymentPrintMenu transaction={detailOpen} /> : null}
            </Can>
            <Button onClick={() => setDetailOpen(null)}>Tutup</Button>
          </Space>
        )}
        width={640}
      >
        {detailOpen ? (
          <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Descriptions bordered size="small" column={2}>
              <Descriptions.Item label="Penghuni">{detailOpen.unit?.resident?.name || '-'}</Descriptions.Item>
              <Descriptions.Item label="Unit">{detailOpen.unit_id}</Descriptions.Item>
              <Descriptions.Item label="Nomor Tagihan" span={2}>
                {(detailOpen.billings || []).map((billing) => `BIL-${billing.id}`).join(', ') || '-'}
              </Descriptions.Item>
              <Descriptions.Item label="Periode" span={2}>
                {(detailOpen.billings || []).map((billing) => formatPeriod(billing.year, billing.month)).join(', ') || '-'}
              </Descriptions.Item>
              <Descriptions.Item label="Jenis Tagihan" span={2}>
                {[...new Set((detailOpen.billings || []).map((billing) => billing.billing_type))].join(', ') || '-'}
              </Descriptions.Item>
              <Descriptions.Item label="Metode">{formatPaymentMethod(detailOpen.payment_method)}</Descriptions.Item>
              <Descriptions.Item label="Nominal Tagihan">{formatCurrency(detailOpen.total)}</Descriptions.Item>
              <Descriptions.Item label="Nominal Dibayar">{detailOpen.manual_amount ? formatCurrency(detailOpen.manual_amount) : '-'}</Descriptions.Item>
              <Descriptions.Item label="Tanggal Pembayaran">{detailOpen.manual_transfer_date ? formatDate(detailOpen.manual_transfer_date) : '-'}</Descriptions.Item>
              <Descriptions.Item label="Waktu Upload">{detailOpen.manual_proof_uploaded_at ? formatDateTime(detailOpen.manual_proof_uploaded_at) : '-'}</Descriptions.Item>
              <Descriptions.Item label="Catatan Penghuni" span={2}>{detailOpen.manual_notes || '-'}</Descriptions.Item>
              <Descriptions.Item label="Status" span={2}><StatusBadge type="transaction" value={detailOpen.status} /></Descriptions.Item>
              {detailOpen.verified_by || detailOpen.verifier?.name ? (
                <Descriptions.Item label="Diverifikasi Oleh">{detailOpen.verifier?.name || detailOpen.verified_by}</Descriptions.Item>
              ) : null}
              {detailOpen.verification_notes ? (
                <Descriptions.Item label="Catatan Verifikasi" span={2}>{detailOpen.verification_notes}</Descriptions.Item>
              ) : null}
            </Descriptions>
            {detailOpen.manual_proof_path ? (
              <div>
                <Typography.Text strong>Bukti Pembayaran</Typography.Text>
                <div style={{ marginTop: 8 }}>
                  {/\.pdf$/i.test(detailOpen.manual_proof_path) ? (
                    <a href={storageUrl(detailOpen.manual_proof_path)} target="_blank" rel="noreferrer">Buka PDF Bukti Pembayaran</a>
                  ) : (
                    <a href={storageUrl(detailOpen.manual_proof_path)} target="_blank" rel="noreferrer">
                      <img src={storageUrl(detailOpen.manual_proof_path)} alt="Bukti pembayaran" style={{ maxWidth: '100%', maxHeight: 420, borderRadius: 8, border: '1px solid #d9d9d9' }} />
                    </a>
                  )}
                </div>
              </div>
            ) : (
              <Alert type="warning" showIcon message="Bukti pembayaran belum diunggah." />
            )}
          </Space>
        ) : null}
      </Modal>

      <Modal
        title={verifyTarget?.status === 'paid' ? 'Verifikasi pembayaran manual?' : 'Tolak pembayaran manual?'}
        open={Boolean(verifyTarget)}
        onCancel={closeVerifyModal}
        onOk={submitVerify}
        okText={verifyTarget?.status === 'paid' ? 'Verifikasi' : 'Tolak'}
        okButtonProps={{ danger: verifyTarget?.status === 'rejected', loading: verify.isPending }}
        cancelText="Batal"
        destroyOnHidden
      >
        {verifyTarget ? (
          <Space direction="vertical" size={12} style={{ width: '100%' }}>
            <Alert
              type={verifyTarget.status === 'paid' ? 'info' : 'warning'}
              showIcon
              message={verifyTarget.row.invoice_number}
              description={`${verifyTarget.row.unit?.resident?.name || '-'} - Unit ${verifyTarget.row.unit_id} - ${formatCurrency(verifyTarget.row.manual_amount || verifyTarget.row.total)}`}
            />
            <Form form={verifyForm} layout="vertical" preserve={false}>
              {verifyTarget.status === 'paid' ? (
                <Form.Item label="Catatan" name="notes"><Input.TextArea rows={3} /></Form.Item>
              ) : (
                <Form.Item label="Alasan" name="notes" rules={[{ required: true, whitespace: true, message: 'Alasan penolakan wajib diisi' }]}>
                  <Input.TextArea rows={3} />
                </Form.Item>
              )}
            </Form>
          </Space>
        ) : null}
      </Modal>

      <Modal
        title="Pembayaran Berhasil"
        open={Boolean(successReceipt)}
        onCancel={() => setSuccessReceipt(null)}
        width={520}
        footer={[
          <Button key="close" onClick={() => setSuccessReceipt(null)}>Tutup</Button>,
          <PaymentPrintMenu key="print" type="primary" receiptNumber={successReceipt?.number} label="Cetak Kuitansi" />,
        ]}
        destroyOnHidden
      >
        {successReceipt ? (
          <Space direction="vertical" size={10} style={{ width: '100%' }}>
            <div>
              <Typography.Title level={4} style={{ margin: 0 }}>{successReceipt.number}</Typography.Title>
              <Typography.Text type="secondary">{formatDateTime(successReceipt.transaction_date)}</Typography.Text>
            </div>
            <div>
              <Typography.Text strong>{successReceipt.resident_name}</Typography.Text><br />
              <Typography.Text>{successReceipt.cluster_name} {successReceipt.block}/{successReceipt.lot_number}</Typography.Text>
            </div>
            <div>
              <Typography.Text>Periode: {successReceipt.billing_periods}</Typography.Text><br />
              <Typography.Text>Jumlah Tagihan: {successReceipt.billing_count}</Typography.Text>
            </div>
            <div>
              <Typography.Text>Total Tagihan: {formatCurrency(successReceipt.total_billing)}</Typography.Text><br />
              <Typography.Text>Total Denda: {formatCurrency(successReceipt.total_penalty)}</Typography.Text><br />
              <Typography.Text strong>Grand Total: {formatCurrency(successReceipt.grand_total)}</Typography.Text>
            </div>
            {Number(successReceipt.balance_used) > 0 ? (
              <Alert type="info" showIcon message={`Saldo unit digunakan: ${formatCurrency(successReceipt.balance_used)}`} />
            ) : null}
            {Number(successReceipt.deposit_amount) > 0 ? (
              <Alert type="success" showIcon message={`Kelebihan pembayaran ${formatCurrency(successReceipt.deposit_amount)} dicatat sebagai saldo unit.`} />
            ) : null}
            <div>
              <Typography.Text>Metode: {PAYMENT_METHOD_LABELS[successReceipt.payment_method_id] || successReceipt.payment_method_id}</Typography.Text><br />
              {successReceipt.payment_channel_id ? <><Typography.Text>Channel: {PAYMENT_CHANNEL_LABELS[successReceipt.payment_channel_id] || successReceipt.payment_channel_id}</Typography.Text><br /></> : null}
              {successReceipt.loket_code ? <><Typography.Text>Kode Loket: {successReceipt.loket_code}</Typography.Text><br /></> : null}
              {successReceipt.cashier_name ? <Typography.Text>Kasir: {successReceipt.cashier_name}</Typography.Text> : null}
            </div>
            {successReceipt.notes ? <Typography.Text type="secondary">Catatan: {successReceipt.notes}</Typography.Text> : null}
          </Space>
        ) : null}
      </Modal>
    </section>
  );
}
