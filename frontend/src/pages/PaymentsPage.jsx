import { Alert, Button, Card, DatePicker, Drawer, Form, Input, Modal, Select, Space, Tabs, Upload, message, Typography } from 'antd';
import { CheckOutlined, CloudUploadOutlined, CloseOutlined, FileExcelOutlined, LinkOutlined, PrinterOutlined, SearchOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import dayjs from 'dayjs';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import Can from '../components/common/Can.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { api } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { useDebounce } from '../hooks/useDebounce.js';
import { formatCurrency, formatDate, formatDateTime, formatPeriod } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';
import { downloadBlob, openBlobInWindow } from '../utils/download.js';

export default function PaymentsPage() {
  const [unit, setUnit] = useState(null);
  const [selected, setSelected] = useState([]);
  const [preview, setPreview] = useState(null);
  const [transaction, setTransaction] = useState(null);
  const [proofOpen, setProofOpen] = useState(null);
  const [unitQuery, setUnitQuery] = useState('');
  const [unitFilters, setUnitFilters] = useState({});
  const [exportingTransactions, setExportingTransactions] = useState(null);
  const [exportingReceipts, setExportingReceipts] = useState(null);
  const [searchForm] = Form.useForm();
  const [loketForm] = Form.useForm();
  const [gatewayForm] = Form.useForm();
  const [proofForm] = Form.useForm();
  const [verifyForm] = Form.useForm();
  const queryClient = useQueryClient();
  const transactionTable = useTableState();
  const receiptTable = useTableState();
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
    onSuccess: (response) => {
      setUnit(response.data);
      setSelected([]);
      setPreview(null);
      setTransaction(null);
    },
    onError: (error) => message.error(getApiErrorMessage(error, 'Unit tidak ditemukan')),
  });

  const previewMutation = useMutation({
    mutationFn: () => api.payments.preview({ unit_id: unit.id, billing_ids: selected }),
    onSuccess: (response) => setPreview(response.data),
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const processLoket = useMutation({
    mutationFn: (values) => api.payments.process({ ...values, unit_id: unit.id, billing_ids: selected }),
    onSuccess: () => {
      message.success('Pembayaran loket berhasil diproses');
      resetPaymentWorkspace();
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      queryClient.invalidateQueries({ queryKey: ['payment-receipts'] });
    },
    onError: (error) => {
      loketForm.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const createGateway = useMutation({
    mutationFn: (values) => api.payments.createGateway({ ...values, unit_id: unit.id, billing_ids: selected }),
    onSuccess: (response) => {
      message.success('Transaksi gateway berhasil dibuat');
      setTransaction(response.data);
      queryClient.invalidateQueries({ queryKey: ['payment-transactions'] });
    },
    onError: (error) => {
      gatewayForm.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const uploadProof = useMutation({
    mutationFn: (values) => {
      const formData = new FormData();
      formData.append('proof', values.proof[0].originFileObj);
      formData.append('manual_transfer_date', values.manual_transfer_date.format('YYYY-MM-DD'));
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
    onSuccess: () => {
      message.success('Status pembayaran manual diperbarui');
      verifyForm.resetFields();
      queryClient.invalidateQueries({ queryKey: ['payment-transactions'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  async function printReceipt(number) {
    const printWindow = window.open('', '_blank');
    try {
      const blob = await api.documents.receiptPdf(number);
      openBlobInWindow(printWindow, blob);
    } catch (error) {
      printWindow?.close();
      message.error(getApiErrorMessage(error, 'Gagal memuat kuitansi'));
    }
  }

  async function printTransactions() {
    const printWindow = window.open('', '_blank');
    const { page: _page, per_page: _perPage, ...filters } = transactionTable.params;
    setExportingTransactions('pdf');
    try {
      const blob = await api.documents.paymentTransactionsPdf(filters);
      openBlobInWindow(printWindow, blob);
    } catch (error) {
      printWindow?.close();
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

  async function printReceipts() {
    const printWindow = window.open('', '_blank');
    const { page: _page, per_page: _perPage, ...filters } = receiptTable.params;
    setExportingReceipts('pdf');
    try {
      const blob = await api.documents.paymentReceiptsPdf(filters);
      openBlobInWindow(printWindow, blob);
    } catch (error) {
      printWindow?.close();
      message.error(getApiErrorMessage(error, 'Gagal memuat PDF kuitansi'));
    } finally {
      setExportingReceipts(null);
    }
  }

  async function exportReceiptsExcel() {
    setExportingReceipts('excel');
    try {
      const { page: _page, per_page: _perPage, ...filters } = receiptTable.params;
      const blob = await api.documents.paymentReceiptsExcel(filters);
      downloadBlob(blob, 'riwayat-kuitansi.csv');
    } catch (error) {
      message.error(getApiErrorMessage(error, 'Gagal mengunduh data kuitansi'));
    } finally {
      setExportingReceipts(null);
    }
  }

  function updateUnitFilters(patch) {
    setUnitFilters((previous) => ({ ...previous, ...patch }));
    searchForm.setFieldValue('unit_id', undefined);
    setUnitQuery('');
  }

  function resetPaymentWorkspace() {
    setUnit(null);
    setSelected([]);
    setPreview(null);
    setTransaction(null);
    setUnitQuery('');
    setUnitFilters({});
    searchForm.resetFields();
    loketForm.resetFields();
    gatewayForm.resetFields();
  }

  const unpaidBillings = unit?.billings || [];
  const activeGateway = config.data?.data?.active_gateway || 'manual';
  const manualInfo = config.data?.data?.manual_payment || {};
  const unitOptions = (unitLookup.data?.data || []).map((item) => ({
    value: item.id,
    label: `${item.id} — ${item.cluster?.name || ''} ${item.block || ''}/${item.lot_number || ''} — ${item.resident?.name || ''}`,
  }));

  const billingColumns = [
    { title: 'Periode', render: (_, row) => formatPeriod(row.year, row.month) },
    { title: 'Nominal', dataIndex: 'amount', render: formatCurrency },
    { title: 'Umur Tunggakan', render: (_, row) => `${row.penalty_detail?.overdue_months ?? 0} bulan` },
    { title: 'Denda', render: (_, row) => formatCurrency(row.penalty_detail?.penalty_amount ?? 0) },
    { title: 'Sisa Tagihan', render: (_, row) => formatCurrency(row.penalty_detail?.total_outstanding ?? 0) },
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

      <Tabs
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
                    <Form.Item label="Periode Tagihan" name="billing_range">
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
                  <Card title={`${unit.id} - ${unit.resident?.name}`} extra={unit.cluster?.name}>
                    <ResponsiveTable
                      data={unpaidBillings}
                      columns={billingColumns}
                      pagination={false}
                      rowSelection={{ selectedRowKeys: selected, onChange: setSelected }}
                      scrollX={1000}
                    />
                    <Space className="action-row" wrap>
                      <Button disabled={!selected.length} onClick={() => previewMutation.mutate()} loading={previewMutation.isPending}>Preview Total</Button>
                      {preview ? <Typography.Text strong>Total bayar: {formatCurrency(preview.grand_total)}</Typography.Text> : null}
                    </Space>

                    <Tabs
                      items={[
                        {
                          key: 'loket',
                          label: 'Loket',
                          children: (
                            <Can permission="payments.process" fallback={<Alert type="warning" showIcon message="Anda tidak memiliki akses proses loket." />}>
                              <Form form={loketForm} layout="vertical" onFinish={processLoket.mutate} initialValues={{ payment_method_id: 'C', loket_code: 'L01' }} className="responsive-form">
                                <Form.Item label="Metode" name="payment_method_id" rules={[{ required: true }]}>
                                  <Select options={[{ value: 'C', label: 'Cash' }, { value: 'D', label: 'Debit/Transfer' }]} />
                                </Form.Item>
                                <Form.Item label="Channel" name="payment_channel_id">
                                  <Select allowClear options={[{ value: 'L', label: 'Loket' }, { value: 'M', label: 'Bank Transfer' }, { value: 'Q', label: 'QRIS' }]} />
                                </Form.Item>
                                <Form.Item label="Kode Loket" name="loket_code"><Input /></Form.Item>
                                <Form.Item label="Nama Kasir" name="cashier_name"><Input /></Form.Item>
                                <Form.Item label="Catatan" name="notes" className="full-span"><Input.TextArea rows={2} /></Form.Item>
                                <Form.Item className="full-span">
                                  <Button type="primary" htmlType="submit" disabled={!preview} loading={processLoket.isPending}>Proses Bayar Loket</Button>
                                </Form.Item>
                              </Form>
                            </Can>
                          ),
                        },
                        {
                          key: 'gateway',
                          label: 'Gateway / Manual',
                          children: (
                            <Can permission="payments.create" fallback={<Alert type="warning" showIcon message="Anda tidak memiliki akses membuat transaksi gateway." />}>
                              <Alert
                                type="info"
                                showIcon
                                message={`Gateway aktif: ${activeGateway}`}
                                description={activeGateway === 'manual' ? `${manualInfo.bank_name || '-'} ${manualInfo.account_number || ''} a.n. ${manualInfo.account_name || '-'}` : 'Transaksi akan menghasilkan payment URL jika provider aktif.'}
                              />
                              <Form form={gatewayForm} layout="vertical" onFinish={createGateway.mutate} initialValues={{ provider: activeGateway }} className="section-row">
                                <Form.Item label="Provider" name="provider" rules={[{ required: true }]}>
                                  <Select options={[
                                    { value: 'manual', label: 'Manual Transfer' },
                                    { value: 'xendit', label: 'Xendit' },
                                    { value: 'midtrans', label: 'Midtrans' },
                                  ]} />
                                </Form.Item>
                                <Button type="primary" htmlType="submit" disabled={!selected.length} loading={createGateway.isPending}>Buat Transaksi</Button>
                              </Form>
                              {transaction ? (
                                <Card className="section-row" title={transaction.invoice_number}>
                                  <Space direction="vertical">
                                    <StatusBadge type="transaction" value={transaction.status} />
                                    <Typography.Text>Total: {formatCurrency(transaction.total)}</Typography.Text>
                                    {transaction.payment_url ? <Button icon={<LinkOutlined />} href={transaction.payment_url} target="_blank">Buka Payment URL</Button> : null}
                                    {transaction.payment_provider === 'manual' ? <Button icon={<CloudUploadOutlined />} onClick={() => setProofOpen(transaction)}>Upload Bukti Manual</Button> : null}
                                  </Space>
                                </Card>
                              ) : null}
                            </Can>
                          ),
                        },
                      ]}
                    />
                  </Card>
                ) : null}
              </div>
            ),
          },
          {
            key: 'transactions',
            label: 'Transaksi Gateway',
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
                  <Select allowClear placeholder="Provider" value={transactionTable.filters.provider} onChange={(value) => transactionTable.setFilters({ ...transactionTable.filters, provider: value })} className="filter-input" options={[{ value: 'manual', label: 'Manual' }, { value: 'xendit', label: 'Xendit' }, { value: 'midtrans', label: 'Midtrans' }]} />
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
                    scrollX={1320}
                    columns={[
                      { title: 'Invoice', dataIndex: 'invoice_number', width: 190, fixed: 'left' },
                      { title: 'Penghuni', dataIndex: ['unit', 'resident', 'name'], width: 200 },
                      { title: 'Alamat Unit', render: (_, row) => `${row.unit?.cluster?.name || ''} ${row.unit?.block || ''}/${row.unit?.lot_number || ''}`, width: 180 },
                      { title: 'Provider', dataIndex: 'payment_provider', width: 110 },
                      { title: 'Total', dataIndex: 'total', render: formatCurrency, width: 140 },
                      { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge type="transaction" value={value} />, width: 170 },
                      { title: 'Dibuat', dataIndex: 'created_at', render: formatDateTime, width: 170 },
                      {
                        title: 'Aksi',
                        fixed: 'right',
                        width: 240,
                        render: (_, row) => (
                          <Space>
                            {row.payment_provider === 'manual' ? <Button size="small" icon={<CloudUploadOutlined />} onClick={() => setProofOpen(row)}>Upload</Button> : null}
                            <Can permission="payments.verify">
                              <Button size="small" icon={<CheckOutlined />} disabled={row.status !== 'waiting_verification'} onClick={() => Modal.confirm({
                                title: 'Verifikasi pembayaran manual?',
                                content: <Form form={verifyForm} layout="vertical"><Form.Item label="Catatan" name="notes"><Input.TextArea rows={3} /></Form.Item></Form>,
                                onOk: () => verify.mutate({ id: row.id, status: 'paid', notes: verifyForm.getFieldValue('notes') }),
                              })}>Verifikasi</Button>
                              <Button size="small" danger icon={<CloseOutlined />} disabled={row.status !== 'waiting_verification'} onClick={() => Modal.confirm({
                                title: 'Tolak pembayaran manual?',
                                content: <Form form={verifyForm} layout="vertical"><Form.Item label="Alasan" name="notes" rules={[{ required: true }]}><Input.TextArea rows={3} /></Form.Item></Form>,
                                onOk: () => verify.mutate({ id: row.id, status: 'rejected', notes: verifyForm.getFieldValue('notes') }),
                              })}>Tolak</Button>
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
                        <Button icon={<PrinterOutlined />} loading={exportingReceipts === 'pdf'} disabled={Boolean(exportingReceipts)} onClick={printReceipts}>Cetak PDF</Button>
                        <Button icon={<FileExcelOutlined />} loading={exportingReceipts === 'excel'} disabled={Boolean(exportingReceipts)} onClick={exportReceiptsExcel}>Export Excel</Button>
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
                <Card>
                  <ResponsiveTable
                    query={receipts}
                    onChange={receiptTable.handleTableChange}
                    scrollX={1390}
                    rowKey="number"
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
                            <Button size="small" icon={<PrinterOutlined />} onClick={() => printReceipt(row.number)}>Cetak Kuitansi</Button>
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
        <Form form={proofForm} layout="vertical" className="section-row" onFinish={uploadProof.mutate} initialValues={{ manual_transfer_date: dayjs() }}>
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
    </section>
  );
}
