import { Button, Card, DatePicker, Form, Input, Space, Typography, message } from 'antd';
import { FilePdfOutlined } from '@ant-design/icons';
import { useState } from 'react';
import dayjs from 'dayjs';
import PageHeader from '../components/common/PageHeader.jsx';
import { api } from '../services/estateApi.js';
import { getApiErrorMessage } from '../utils/apiError.js';
import { printPdf } from '../utils/download.js';

export default function DocumentsPage() {
  const [form] = Form.useForm();
  const [loadingKey, setLoadingKey] = useState(null);
  const values = Form.useWatch([], form) || {};
  const period = values.period || dayjs();
  const year = period.year();
  const month = period.month() + 1;
  const receipt = values.receipt?.trim();
  const billing = values.billing?.trim();

  const links = [
    { key: 'billing-recap', label: 'Rekap Billing', filename: 'billing-recap.pdf', request: () => api.documents.pdf(`/documents/billing-recap?year=${year}&month=${month}`) },
    { key: 'resident-list', label: 'Daftar Penghuni', filename: 'resident-list.pdf', request: () => api.documents.pdf('/documents/resident-list') },
    { key: 'cluster-recap', label: 'Rekap Cluster', filename: 'cluster-recap.pdf', request: () => api.documents.pdf('/documents/cluster-recap') },
  ];

  // A plain <a href> to the API would 401 - the Bearer token is only attached by axios - so every
  // document is fetched through the api client and then opened from a Blob.
  async function open({ key, request, filename }) {
    setLoadingKey(key);
    try {
      await printPdf(request, filename);
    } catch (error) {
      message.error(getApiErrorMessage(error, 'Gagal memuat dokumen'));
    } finally {
      setLoadingKey(null);
    }
  }

  return (
    <section>
      <PageHeader title="Dokumen PDF" subtitle="Generate dokumen PDF yang tersedia dari backend." breadcrumbs={[{ label: 'Dokumen PDF' }]} />
      <Card>
        <Form form={form} layout="inline" initialValues={{ period: dayjs() }}>
          <Form.Item label="Periode" name="period"><DatePicker picker="month" /></Form.Item>
          <Form.Item label="Nomor Receipt" name="receipt"><Input placeholder="Untuk SPT" /></Form.Item>
          <Form.Item label="ID Billing" name="billing"><Input placeholder="Untuk SPK" /></Form.Item>
        </Form>
      </Card>
      <Card className="section-row" title="Dokumen Umum">
        <Space wrap>
          {links.map((item) => (
            <Button key={item.key} icon={<FilePdfOutlined />} loading={loadingKey === item.key} disabled={Boolean(loadingKey)} onClick={() => open(item)}>{item.label}</Button>
          ))}
        </Space>
      </Card>
      <Card className="section-row" title="Dokumen Berdasarkan Nomor">
        <Space wrap>
          <Button
            disabled={!receipt || Boolean(loadingKey)}
            loading={loadingKey === 'spt'}
            icon={<FilePdfOutlined />}
            onClick={() => open({ key: 'spt', filename: `kuitansi-${receipt}.pdf`, request: () => api.documents.receiptPdf(receipt) })}
          >
            Cetak SPT
          </Button>
          <Button
            disabled={!billing || Boolean(loadingKey)}
            loading={loadingKey === 'spk'}
            icon={<FilePdfOutlined />}
            onClick={() => open({ key: 'spk', filename: `spk-${billing}.pdf`, request: () => api.documents.billingSpkPdf(billing) })}
          >
            Cetak SPK
          </Button>
        </Space>
        <Typography.Paragraph type="secondary" className="section-row">Tombol aktif setelah nomor receipt atau ID billing diisi.</Typography.Paragraph>
      </Card>
    </section>
  );
}
