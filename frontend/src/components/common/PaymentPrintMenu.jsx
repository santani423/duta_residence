import { Button, Dropdown, message } from 'antd';
import { DownOutlined, PrinterOutlined } from '@ant-design/icons';
import { useState } from 'react';
import { api } from '../../services/estateApi.js';
import { getApiErrorMessage } from '../../utils/apiError.js';
import { printPdf } from '../../utils/download.js';

/**
 * Tombol "Cetak" untuk dokumen pembayaran. Semua dokumen dibuat backend dari data pembayaran yang
 * tersimpan (jadi angkanya selalu sama dengan yang tampil di aplikasi):
 * - Detail transaksi (A4) - bila `transaction` diberikan.
 * - Kuitansi A4 / struk thermal 80mm - dari `receiptNumber` (kuitansi loket) atau `transaction`
 *   yang sudah dibayar (transfer/gateway).
 */
export default function PaymentPrintMenu({ transaction, receiptNumber, label = 'Cetak', size, type, block }) {
  const [loading, setLoading] = useState(false);
  const canPrintReceipt = Boolean(receiptNumber) || transaction?.status === 'paid';

  async function run(request, filename, failure) {
    setLoading(true);
    try {
      await printPdf(request, filename);
    } catch (error) {
      message.error(getApiErrorMessage(error, failure));
    } finally {
      setLoading(false);
    }
  }

  function printReceipt(format) {
    const suffix = format === 'thermal' ? '-struk' : '';
    if (receiptNumber) {
      return run(() => api.documents.receiptPdf(receiptNumber, format), `kuitansi-${receiptNumber}${suffix}.pdf`, 'Gagal memuat kuitansi');
    }
    return run(() => api.documents.paymentTransactionReceiptPdf(transaction.id, format), `kuitansi-${transaction.invoice_number}${suffix}.pdf`, 'Gagal memuat kuitansi');
  }

  const items = [
    ...(transaction ? [{
      key: 'detail',
      label: 'Cetak Detail Transaksi (A4)',
      onClick: () => run(() => api.documents.paymentTransactionPdf(transaction.id), `transaksi-${transaction.invoice_number}.pdf`, 'Gagal memuat detail transaksi'),
    }] : []),
    { key: 'receipt-a4', label: 'Cetak Kuitansi (A4)', disabled: !canPrintReceipt, onClick: () => printReceipt('a4') },
    { key: 'receipt-thermal', label: 'Cetak Struk (Thermal 80mm)', disabled: !canPrintReceipt, onClick: () => printReceipt('thermal') },
  ];

  return (
    <Dropdown menu={{ items }} trigger={['click']} disabled={loading}>
      <Button size={size} type={type} block={block} icon={<PrinterOutlined />} loading={loading}>
        {label} <DownOutlined />
      </Button>
    </Dropdown>
  );
}
