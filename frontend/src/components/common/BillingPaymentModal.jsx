import { Alert, Button, Checkbox, Descriptions, Form, Input, InputNumber, Modal, Select, Space, Spin, Statistic, Typography, message } from 'antd';
import { PrinterOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { api } from '../../services/estateApi.js';
import { useDebounce } from '../../hooks/useDebounce.js';
import { formatCurrency, formatDateTime, formatPeriod } from '../../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../../utils/apiError.js';
import { printPdf } from '../../utils/download.js';
import ResponsiveTable from '../tables/ResponsiveTable.jsx';

// Pembayaran loket langsung dari daftar tagihan: satu unit, satu atau beberapa tagihan yang sudah disetujui.
export default function BillingPaymentModal({ unitId, billingIds = [], open, onClose }) {
  const [form] = Form.useForm();
  const [receipt, setReceipt] = useState(null);
  const queryClient = useQueryClient();

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
    if (open && selectedIds.length) form.setFieldValue('amount', Math.max(0, total - (useBalance ? balance : 0)));
  }, [open, total, balance, useBalance]); // eslint-disable-line react-hooks/exhaustive-deps

  const preview = useQuery({
    queryKey: ['billing-payment', 'preview', unitId, selectedIds, debouncedAmount, useBalance],
    queryFn: () => api.payments.preview({ unit_id: unitId, billing_ids: selectedIds, amount: Number(debouncedAmount) || 0, use_balance: useBalance }),
    enabled: open && Boolean(unitId) && selectedIds.length > 0,
  });
  const summary = preview.data?.data;

  const pay = useMutation({
    mutationFn: (values) => api.payments.process({
      unit_id: unitId,
      billing_ids: selectedIds,
      amount: Number(values.amount) || 0,
      use_balance: values.use_balance ?? true,
      payment_method_id: values.payment_method_id,
      payment_channel_id: values.payment_channel_id,
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

  async function printReceipt() {
    try {
      await printPdf(() => api.documents.receiptPdf(receipt.number), `kuitansi-${receipt.number}.pdf`);
    } catch (error) {
      message.error(getApiErrorMessage(error, 'Gagal memuat kuitansi'));
    }
  }

  function close() {
    setReceipt(null);
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
      title={receipt ? 'Pembayaran Berhasil' : `Bayar Tagihan - Unit ${unitId ?? ''}`}
      open={open}
      onCancel={close}
      width={720}
      destroyOnHidden
      footer={receipt ? [
        <Button key="close" onClick={close}>Tutup</Button>,
        <Button key="print" type="primary" icon={<PrinterOutlined />} onClick={printReceipt}>Cetak Kuitansi</Button>,
      ] : [
        <Button key="cancel" onClick={close}>Batal</Button>,
        <Button key="pay" type="primary" loading={pay.isPending} disabled={!summary?.amount_allocated} onClick={() => form.submit()}>Proses Bayar</Button>,
      ]}
    >
      {receipt ? (
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
                  <Form
                    form={form}
                    layout="vertical"
                    onFinish={pay.mutate}
                    initialValues={{ payment_method_id: 'C', loket_code: 'L01', use_balance: true }}
                    style={{ marginTop: 16 }}
                  >
                    <Form.Item label="Nominal Pembayaran (tunai)" name="amount" rules={[{ type: 'number', min: 0, message: 'Nominal tidak boleh negatif' }]}>
                      <InputNumber min={0} step={1000} style={{ width: '100%' }} />
                    </Form.Item>
                    <Form.Item name="use_balance" valuePropName="checked">
                      <Checkbox disabled={!balance}>Gunakan saldo unit ({formatCurrency(balance)})</Checkbox>
                    </Form.Item>
                    <Space wrap style={{ width: '100%' }} align="start">
                      <Form.Item label="Metode" name="payment_method_id" rules={[{ required: true }]}>
                        <Select style={{ width: 160 }} options={[{ value: 'C', label: 'Cash' }, { value: 'D', label: 'Debit/Transfer' }]} />
                      </Form.Item>
                      <Form.Item label="Channel" name="payment_channel_id">
                        <Select allowClear style={{ width: 160 }} options={[{ value: 'L', label: 'Loket' }, { value: 'M', label: 'Bank Transfer' }, { value: 'Q', label: 'QRIS' }]} />
                      </Form.Item>
                      <Form.Item label="Kode Loket" name="loket_code"><Input style={{ width: 120 }} /></Form.Item>
                      <Form.Item label="Nama Kasir" name="cashier_name"><Input style={{ width: 180 }} /></Form.Item>
                    </Space>
                    <Form.Item label="Catatan" name="notes"><Input.TextArea rows={2} /></Form.Item>
                  </Form>
                  {summary ? (
                    <Descriptions size="small" column={2} bordered title="Ringkasan Alokasi">
                      <Descriptions.Item label="Total Tunggakan">{formatCurrency(summary.total_outstanding)}</Descriptions.Item>
                      <Descriptions.Item label="Nominal Pembayaran">{formatCurrency(summary.payment_amount)}</Descriptions.Item>
                      <Descriptions.Item label="Saldo Digunakan">{formatCurrency(summary.balance_used)}</Descriptions.Item>
                      <Descriptions.Item label="Teralokasi">{formatCurrency(summary.amount_allocated)}</Descriptions.Item>
                      <Descriptions.Item label="Sisa Tunggakan">{formatCurrency(summary.remaining_outstanding)}</Descriptions.Item>
                      <Descriptions.Item label="Saldo Baru">{formatCurrency(summary.new_balance)}</Descriptions.Item>
                    </Descriptions>
                  ) : null}
                  {summary?.overpayment > 0 ? <Alert style={{ marginTop: 12 }} type="success" showIcon message={`Kelebihan pembayaran +${formatCurrency(summary.overpayment)} akan ditambahkan ke saldo unit.`} /> : null}
                </>
              )}
            </>
          ) : null}
        </Spin>
      )}
    </Modal>
  );
}
