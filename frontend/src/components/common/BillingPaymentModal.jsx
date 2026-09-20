import { Alert, Button, Checkbox, DatePicker, Descriptions, Form, Input, Modal, Select, Space, Spin, Statistic, Typography, Upload, message } from 'antd';
import { CloudUploadOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import dayjs from 'dayjs';
import { useEffect, useState } from 'react';
import { api } from '../../services/estateApi.js';
import { useAuth } from '../../state/AuthContext.jsx';
import { useDebounce } from '../../hooks/useDebounce.js';
import { formatCurrency, formatDateTime, formatPeriod } from '../../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../../utils/apiError.js';
import ResponsiveTable from '../tables/ResponsiveTable.jsx';
import MoneyInput from './MoneyInput.jsx';
import PaymentPrintMenu from './PaymentPrintMenu.jsx';

// Pembayaran loket langsung dari daftar tagihan: satu unit, satu atau beberapa tagihan yang sudah disetujui.
export default function BillingPaymentModal({ unitId, billingIds = [], open, onClose }) {
  const [form] = Form.useForm();
  const [receipt, setReceipt] = useState(null);
  const [transaction, setTransaction] = useState(null);
  const [transferSent, setTransferSent] = useState(null);
  const [selectedVia, setSelectedVia] = useState('loket');
  const queryClient = useQueryClient();
  const { can, user } = useAuth();

  const gatewayConfig = useQuery({ queryKey: ['payment-gateway-config'], queryFn: api.payments.gatewayConfig, enabled: open });
  const manualInfo = gatewayConfig.data?.data?.manual_payment || {};
  const manualAvailable = (gatewayConfig.data?.data?.available_methods || ['manual']).includes('manual');
  const viaOptions = [
    ...(can('payments.process') ? [{ value: 'loket', label: 'Loket' }] : []),
    ...(can('payments.create') && manualAvailable ? [{ value: 'transfer', label: 'Bank Transfer' }] : []),
  ];
  const via = viaOptions.some((option) => option.value === selectedVia) ? selectedVia : viaOptions[0]?.value;
  const isTransfer = via === 'transfer';

  const unitQuery = useQuery({
    queryKey: ['billing-payment', 'unit', unitId],
    queryFn: () => api.payments.search({ unit_id: unitId }),
    enabled: open && Boolean(unitId),
    staleTime: 0,
    gcTime: 0,
  });
  const unit = unitQuery.data?.data;
  const payable = unit?.billings || [];

  // Tagihan dalam satu skema pembayaran harus dibayar bersamaan, jadi ikut terpilih otomatis.
  const requested = payable.filter((billing) => billingIds.includes(billing.id));
  const schemeIds = new Set(requested.map((billing) => billing.payment_scheme_id).filter(Boolean));
  const selected = payable.filter((billing) => billingIds.includes(billing.id) || schemeIds.has(billing.payment_scheme_id));
  const selectedIds = selected.map((billing) => billing.id);
  const skipped = billingIds.length - requested.length;
  const total = selected.reduce((sum, billing) => sum + Number(billing.penalty_detail?.total_outstanding ?? 0), 0);
  const balance = Number(unit?.deposit_balance ?? 0);

  const watchedAmount = Form.useWatch('amount', form);
  const watchedUseBalance = Form.useWatch('use_balance', form);
  const debouncedAmount = useDebounce(watchedAmount, 400);
  const useBalance = watchedUseBalance ?? true;

  // Nominal tunai bawaan = sisa tagihan setelah saldo unit (bila dipakai); petugas tetap bisa mengubahnya.
  useEffect(() => {
    if (!open || !selectedIds.length) return;
    form.setFieldValue('amount', Math.max(0, total - (useBalance ? balance : 0)));
    // Transfer bank selalu melunasi seluruh tagihan terpilih, jadi nominal bawaannya total tagihan.
    form.setFieldValue('manual_amount', total);
  }, [open, total, balance, useBalance]); // eslint-disable-line react-hooks/exhaustive-deps

  const preview = useQuery({
    queryKey: ['billing-payment', 'preview', unitId, selectedIds, debouncedAmount, useBalance],
    queryFn: () => api.payments.preview({ unit_id: unitId, billing_ids: selectedIds, amount: Number(debouncedAmount) || 0, use_balance: useBalance }),
    enabled: open && !isTransfer && Boolean(unitId) && selectedIds.length > 0,
  });
  const summary = preview.data?.data;

  const pay = useMutation({
    mutationFn: (values) => api.payments.process({
      unit_id: unitId,
      billing_ids: selectedIds,
      amount: Number(values.amount) || 0,
      use_balance: values.use_balance ?? true,
      payment_method_id: values.payment_method_id,
      payment_channel_id: 'L',
      loket_code: values.loket_code,
      cashier_name: values.cashier_name,
      notes: values.notes,
    }),
    onSuccess: (response) => {
      setReceipt(response.data);
      message.success('Pembayaran berhasil diproses');
      ['billings', 'dashboard', 'payment-receipts', 'payment-schemes', 'units'].forEach((key) => queryClient.invalidateQueries({ queryKey: [key] }));
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  // Transfer: buat transaksi manual lalu unggah bukti; menunggu verifikasi petugas berwenang.
  // Transaksi disimpan agar bila unggah gagal, percobaan ulang tidak membuat transaksi ganda.
  const transfer = useMutation({
    mutationFn: async (values) => {
      let current = transaction;
      if (!current) {
        current = (await api.payments.createGateway({ unit_id: unitId, billing_ids: selectedIds, provider: 'manual' })).data;
        setTransaction(current);
      }
      const formData = new FormData();
      formData.append('proof', values.proof[0].originFileObj);
      formData.append('manual_transfer_date', values.manual_transfer_date.format('YYYY-MM-DD'));
      if (values.manual_amount) formData.append('amount', values.manual_amount);
      if (values.manual_notes) formData.append('manual_notes', values.manual_notes);
      return api.payments.uploadManualProof(current.id, formData);
    },
    onSuccess: (response) => {
      setTransferSent(response.data);
      message.success('Bukti transfer berhasil diunggah');
      ['billings', 'payment-transactions', 'dashboard'].forEach((key) => queryClient.invalidateQueries({ queryKey: [key] }));
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  function submit(values) {
    if (isTransfer) transfer.mutate(values);
    else pay.mutate(values);
  }

  function close() {
    setReceipt(null);
    setTransaction(null);
    setTransferSent(null);
    setSelectedVia('loket');
    form.resetFields();
    onClose();
  }

  const columns = [
    { title: 'Periode', render: (_, row) => formatPeriod(row.year, row.month) },
    { title: 'Nominal', render: (_, row) => formatCurrency(row.penalty_detail?.principal_amount ?? row.amount) },
    { title: 'Denda', render: (_, row) => formatCurrency(row.penalty_detail?.penalty_amount ?? 0) },
    { title: 'Sisa Tagihan', render: (_, row) => formatCurrency(row.penalty_detail?.total_outstanding ?? 0) },
  ];

  return (
    <Modal
      title={receipt || transferSent ? (receipt ? 'Pembayaran Berhasil' : 'Bukti Transfer Terkirim') : `Bayar Tagihan - Unit ${unitId ?? ''}`}
      open={open}
      onCancel={close}
      width={720}
      destroyOnHidden
      footer={receipt || transferSent ? [
        <Button key="close" onClick={close}>Tutup</Button>,
        ...(receipt ? [<PaymentPrintMenu key="print" type="primary" receiptNumber={receipt.number} label="Cetak Kuitansi" />] : []),
      ] : [
        <Button key="cancel" onClick={close}>Batal</Button>,
        <Button key="pay" type="primary" loading={pay.isPending || transfer.isPending} disabled={isTransfer ? !selected.length : !summary?.amount_allocated} onClick={() => form.submit()}>{isTransfer ? 'Kirim Bukti Transfer' : 'Proses Bayar'}</Button>,
      ]}
    >
      {transferSent ? (
        <Space direction="vertical" size={8} style={{ width: '100%' }}>
          <Typography.Title level={4} style={{ margin: 0 }}>{transferSent.invoice_number}</Typography.Title>
          <Typography.Text>Total transfer: <strong>{formatCurrency(transferSent.total)}</strong></Typography.Text>
          <Alert type="info" showIcon message="Bukti transfer sudah diunggah dan menunggu verifikasi. Tagihan akan lunas setelah pembayaran diverifikasi." />
        </Space>
      ) : receipt ? (
        <Space direction="vertical" size={8} style={{ width: '100%' }}>
          <Typography.Title level={4} style={{ margin: 0 }}>{receipt.number}</Typography.Title>
          <Typography.Text type="secondary">{formatDateTime(receipt.transaction_date)}</Typography.Text>
          <Typography.Text>{receipt.resident_name} — {receipt.cluster_name} {receipt.block}/{receipt.lot_number}</Typography.Text>
          <Typography.Text>Periode: {receipt.billing_periods} ({receipt.billing_count} tagihan)</Typography.Text>
          <Typography.Text strong>Grand Total: {formatCurrency(receipt.grand_total)}</Typography.Text>
          {Number(receipt.deposit_amount) > 0 ? <Alert type="success" showIcon message={`Kelebihan pembayaran ${formatCurrency(receipt.deposit_amount)} dicatat sebagai saldo unit.`} /> : null}
        </Space>
      ) : (
        <Spin spinning={unitQuery.isLoading}>
          {unitQuery.isError ? <Alert type="error" showIcon message={getApiErrorMessage(unitQuery.error, 'Gagal memuat tagihan unit')} /> : null}
          {unit ? (
            <>
              <Typography.Text strong>{unit.id} - {unit.resident?.name}</Typography.Text>
              <Space size="large" wrap style={{ display: 'flex', margin: '8px 0 12px' }}>
                <Statistic title="Saldo Unit" value={formatCurrency(balance)} />
                <Statistic title={`Total Dipilih (${selected.length} tagihan)`} value={formatCurrency(total)} />
              </Space>
              {skipped > 0 ? <Alert type="warning" showIcon style={{ marginBottom: 12 }} message={`${skipped} tagihan dilewati karena belum disetujui atau sudah lunas.`} /> : null}
              {selected.length > requested.length ? <Alert type="info" showIcon style={{ marginBottom: 12 }} message="Tagihan lain dalam skema pembayaran yang sama ikut dibayar bersamaan." /> : null}
              {selected.length === 0 ? <Alert type="warning" showIcon message="Tidak ada tagihan yang dapat dibayar. Pastikan tagihan sudah disetujui (approve)." /> : (
                <>
                  <ResponsiveTable data={selected} columns={columns} pagination={false} scrollX={500} size="small" />
                  <Space direction="vertical" size={4} style={{ width: '100%', marginTop: 16 }}>
                    <Typography.Text>Via</Typography.Text>
                    {viaOptions.length ? (
                      <Select value={via} onChange={setSelectedVia} options={viaOptions} style={{ width: 200 }} />
                    ) : <Alert type="warning" showIcon message="Anda tidak memiliki akses untuk memproses pembayaran." />}
                  </Space>
                  <Form
                    form={form}
                    layout="vertical"
                    onFinish={submit}
                    initialValues={{ payment_method_id: 'C', loket_code: 'L01', use_balance: true, cashier_name: user?.name?.slice(0, 50), manual_transfer_date: dayjs() }}
                    style={{ marginTop: 16 }}
                  >
                    {isTransfer ? (
                      <>
                        <Alert
                          type="info"
                          showIcon
                          style={{ marginBottom: 16 }}
                          message="Transfer melunasi seluruh tagihan yang dipilih"
                          description={`${manualInfo.bank_name || '-'} ${manualInfo.account_number || ''} a.n. ${manualInfo.account_name || '-'}. Pembayaran menunggu verifikasi setelah bukti diunggah.`}
                        />
                        <Space wrap align="start" style={{ width: '100%' }}>
                          <Form.Item label="Nominal Transfer" name="manual_amount">
                            <MoneyInput step={1000} style={{ width: 260 }} />
                          </Form.Item>
                          <Form.Item label="Tanggal Transfer" name="manual_transfer_date" rules={[{ required: true, message: 'Tanggal transfer wajib diisi' }]}>
                            <DatePicker style={{ width: 220 }} />
                          </Form.Item>
                        </Space>
                        <Form.Item
                          label="Bukti Transfer"
                          name="proof"
                          valuePropName="fileList"
                          getValueFromEvent={(event) => event?.fileList}
                          rules={[{ required: true, message: 'Bukti transfer wajib diunggah' }]}
                        >
                          <Upload.Dragger beforeUpload={() => false} maxCount={1} accept=".jpg,.jpeg,.png,.pdf">
                            <p className="ant-upload-drag-icon"><CloudUploadOutlined /></p>
                            <p>Tarik file ke sini atau klik untuk memilih</p>
                            <p className="ant-upload-hint">Format: JPG, PNG, PDF.</p>
                          </Upload.Dragger>
                        </Form.Item>
                        <Form.Item label="Catatan" name="manual_notes"><Input.TextArea rows={2} /></Form.Item>
                      </>
                    ) : (
                      <>
                        <Form.Item label="Nominal Pembayaran (tunai)" name="amount" rules={[{ type: 'number', min: 0, message: 'Nominal tidak boleh negatif' }]}>
                          <MoneyInput step={1000} />
                        </Form.Item>
                        <Form.Item name="use_balance" valuePropName="checked">
                          <Checkbox disabled={!balance}>Gunakan saldo unit ({formatCurrency(balance)})</Checkbox>
                        </Form.Item>
                        <Space wrap style={{ width: '100%' }} align="start">
                          <Form.Item label="Metode" name="payment_method_id" rules={[{ required: true }]}>
                            <Select style={{ width: 160 }} options={[{ value: 'C', label: 'Cash' }, { value: 'D', label: 'Debit' }]} />
                          </Form.Item>
                          <Form.Item label="Kode Loket" name="loket_code"><Input style={{ width: 120 }} /></Form.Item>
                          <Form.Item label="Nama Kasir" name="cashier_name"><Input style={{ width: 220 }} /></Form.Item>
                        </Space>
                        <Form.Item label="Catatan" name="notes"><Input.TextArea rows={2} /></Form.Item>
                      </>
                    )}
                  </Form>
                  {!isTransfer && summary ? (
                    <Descriptions size="small" column={2} bordered title="Ringkasan Alokasi">
                      <Descriptions.Item label="Total Tunggakan">{formatCurrency(summary.total_outstanding)}</Descriptions.Item>
                      <Descriptions.Item label="Nominal Pembayaran">{formatCurrency(summary.payment_amount)}</Descriptions.Item>
                      <Descriptions.Item label="Saldo Digunakan">{formatCurrency(summary.balance_used)}</Descriptions.Item>
                      <Descriptions.Item label="Teralokasi">{formatCurrency(summary.amount_allocated)}</Descriptions.Item>
                      <Descriptions.Item label="Sisa Tunggakan">{formatCurrency(summary.remaining_outstanding)}</Descriptions.Item>
                      <Descriptions.Item label="Saldo Baru">{formatCurrency(summary.new_balance)}</Descriptions.Item>
                    </Descriptions>
                  ) : null}
                  {!isTransfer && summary?.overpayment > 0 ? <Alert style={{ marginTop: 12 }} type="success" showIcon message={`Kelebihan pembayaran +${formatCurrency(summary.overpayment)} akan ditambahkan ke saldo unit.`} /> : null}
                </>
              )}
            </>
          ) : null}
        </Spin>
      )}
    </Modal>
  );
}
