import { Alert, Button, Card, DatePicker, Drawer, Form, Input, Modal, Select, Space, Tabs, Upload, message, Typography } from 'antd';
import { CheckOutlined, CloudUploadOutlined, CloseOutlined, LinkOutlined, SearchOutlined } from '@ant-design/icons';
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
import { formatCurrency, formatDate, formatDateTime, formatPeriod } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';

export default function PaymentsPage() {
  const [unit, setUnit] = useState(null);
  const [selected, setSelected] = useState([]);
  const [preview, setPreview] = useState(null);
  const [transaction, setTransaction] = useState(null);
  const [proofOpen, setProofOpen] = useState(null);
  const [searchForm] = Form.useForm();
  const [loketForm] = Form.useForm();
  const [gatewayForm] = Form.useForm();
  const [proofForm] = Form.useForm();
  const [verifyForm] = Form.useForm();
  const queryClient = useQueryClient();
  const transactionTable = useTableState();
  const receiptTable = useTableState();

  const config = useQuery({ queryKey: ['payment-gateway-config'], queryFn: api.payments.gatewayConfig });
  const transactions = useQuery({ queryKey: ['payment-transactions', transactionTable.params], queryFn: () => api.payments.gatewayTransactions(transactionTable.params) });
  const receipts = useQuery({ queryKey: ['payment-receipts', receiptTable.params], queryFn: () => api.payments.receipts(receiptTable.params) });

  const search = useMutation({
    mutationFn: (values) => api.payments.search({ unit_id: values.unit_id }),
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

  function resetPaymentWorkspace() {
    setUnit(null);
    setSelected([]);
    setPreview(null);
    setTransaction(null);
    searchForm.resetFields();
    loketForm.resetFields();
    gatewayForm.resetFields();
  }

  const unpaidBillings = unit?.billings || [];
  const activeGateway = config.data?.data?.active_gateway || 'manual';
  const manualInfo = config.data?.data?.manual_payment || {};

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
                <Card>
                  <Form form={searchForm} layout="inline" onFinish={search.mutate}>
                    <Form.Item label="ID Unit" name="unit_id" rules={[{ required: true }]}>
                      <Input placeholder="GA001" />
                    </Form.Item>
                    <Button htmlType="submit" icon={<SearchOutlined />} loading={search.isPending}>Cari Tagihan</Button>
                    <Button onClick={resetPaymentWorkspace}>Reset</Button>
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
                                  <Select allowClear options={[{ value: 'B', label: 'Bank Transfer' }, { value: 'Q', label: 'QRIS' }, { value: 'E', label: 'EDC' }]} />
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
                <FilterBar>
                  <Input allowClear placeholder="Cari invoice, transaksi, penghuni" value={transactionTable.search} onChange={(event) => transactionTable.setSearch(event.target.value)} className="filter-input" />
                  <Select allowClear placeholder="Provider" value={transactionTable.filters.provider} onChange={(value) => transactionTable.setFilters({ ...transactionTable.filters, provider: value })} className="filter-input" options={[{ value: 'manual', label: 'Manual' }, { value: 'xendit', label: 'Xendit' }, { value: 'midtrans', label: 'Midtrans' }]} />
                  <Select allowClear placeholder="Status" value={transactionTable.filters.status} onChange={(value) => transactionTable.setFilters({ ...transactionTable.filters, status: value })} className="filter-input" options={['pending', 'waiting_verification', 'paid', 'rejected', 'failed', 'expired'].map((value) => ({ value, label: value }))} />
                </FilterBar>
                <Card>
                  <ResponsiveTable
                    query={transactions}
                    onChange={transactionTable.handleTableChange}
                    scrollX={1320}
                    columns={[
                      { title: 'Invoice', dataIndex: 'invoice_number', width: 190, fixed: 'left' },
                      { title: 'Penghuni', dataIndex: ['unit', 'resident', 'name'], width: 200 },
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
                <FilterBar>
                  <Input allowClear placeholder="ID unit" value={receiptTable.filters.unit_id} onChange={(event) => receiptTable.setFilters({ ...receiptTable.filters, unit_id: event.target.value || undefined })} className="filter-input" />
                  <DatePicker placeholder="Tanggal" onChange={(value) => receiptTable.setFilters({ ...receiptTable.filters, date: value?.format('YYYY-MM-DD') })} className="filter-input" />
                </FilterBar>
                <Card>
                  <ResponsiveTable
                    query={receipts}
                    onChange={receiptTable.handleTableChange}
                    scrollX={1060}
                    rowKey="number"
                    columns={[
                      { title: 'Nomor', dataIndex: 'number', width: 190, fixed: 'left' },
                      { title: 'Penghuni', dataIndex: 'resident_name', width: 220 },
                      { title: 'Tanggal', dataIndex: 'transaction_date', render: formatDateTime, width: 170 },
                      { title: 'Periode', dataIndex: 'billing_periods' },
                      { title: 'Total', dataIndex: 'grand_total', render: formatCurrency, width: 150 },
                      { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge type="transaction" value={value} />, width: 120 },
                      { title: 'Kasir', dataIndex: 'cashier_name', width: 130 },
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
