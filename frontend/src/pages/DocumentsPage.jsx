import { Button, Card, DatePicker, Form, Input, Space, Typography } from 'antd';
import { FilePdfOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import PageHeader from '../components/common/PageHeader.jsx';
import { api } from '../services/estateApi.js';

export default function DocumentsPage() {
  const [form] = Form.useForm();
  const values = Form.useWatch([], form) || {};
  const period = values.period || dayjs();
  const year = period.year();
  const month = period.month() + 1;

  const links = [
    { label: 'Rekap Billing', path: `/documents/billing-recap?year=${year}&month=${month}` },
    { label: 'Daftar Penghuni', path: '/documents/resident-list' },
    { label: 'Rekap Cluster', path: '/documents/cluster-recap' },
  ];

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
          {links.map((item) => <Button key={item.path} icon={<FilePdfOutlined />} href={api.documents.url(item.path)} target="_blank">{item.label}</Button>)}
        </Space>
      </Card>
      <Card className="section-row" title="Dokumen Berdasarkan Nomor">
        <Space wrap>
          <Button disabled={!values.receipt} icon={<FilePdfOutlined />} href={values.receipt ? api.documents.url(`/documents/spt/${values.receipt}`) : undefined} target="_blank">Cetak SPT</Button>
          <Button disabled={!values.billing} icon={<FilePdfOutlined />} href={values.billing ? api.documents.url(`/documents/spk/${values.billing}`) : undefined} target="_blank">Cetak SPK</Button>
        </Space>
        <Typography.Paragraph type="secondary" className="section-row">Tombol aktif setelah nomor receipt atau ID billing diisi.</Typography.Paragraph>
      </Card>
    </section>
  );
}
