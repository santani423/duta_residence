import { Card, Col, DatePicker, Form, Row, Statistic, Table } from 'antd';
import { useQuery } from '@tanstack/react-query';
import dayjs from 'dayjs';
import PageHeader from '../components/common/PageHeader.jsx';
import { api } from '../services/estateApi.js';
import { formatCurrency } from '../utils/format.js';

export default function ReportsPage() {
  const [form] = Form.useForm();
  const values = Form.useWatch([], form) || {};
  const period = values.period || dayjs();
  const date = values.date || dayjs();
  const monthly = useQuery({ queryKey: ['report-monthly', period.year(), period.month() + 1], queryFn: () => api.reports.monthly({ year: period.year(), month: period.month() + 1 }) });
  const daily = useQuery({ queryKey: ['report-daily', date.format('YYYY-MM-DD')], queryFn: () => api.reports.dailyReceipt({ date: date.format('YYYY-MM-DD') }) });
  const reconciliation = useQuery({ queryKey: ['report-reconciliation', period.year(), period.month() + 1], queryFn: () => api.reports.reconciliation({ year: period.year(), month: period.month() + 1 }) });
  const collector = useQuery({ queryKey: ['report-collector', date.format('YYYY-MM-DD')], queryFn: () => api.reports.collector({ date: date.format('YYYY-MM-DD') }) });

  return (
    <section>
      <PageHeader title="Laporan" subtitle="Laporan bulanan, harian loket, rekonsiliasi, dan collector." breadcrumbs={[{ label: 'Laporan' }]} />
      <Card>
        <Form form={form} layout="inline" initialValues={{ period: dayjs(), date: dayjs() }}>
          <Form.Item label="Periode" name="period"><DatePicker picker="month" /></Form.Item>
          <Form.Item label="Tanggal" name="date"><DatePicker /></Form.Item>
        </Form>
      </Card>
      <Row gutter={[16, 16]} className="section-row">
        <Col xs={24} md={8}><Card><Statistic title="Total Billing" value={formatCurrency(reconciliation.data?.data?.total_billing)} /></Card></Col>
        <Col xs={24} md={8}><Card><Statistic title="Total Paid" value={formatCurrency(reconciliation.data?.data?.total_paid)} /></Card></Col>
        <Col xs={24} md={8}><Card><Statistic title="Outstanding" value={formatCurrency(reconciliation.data?.data?.total_outstanding)} /></Card></Col>
      </Row>
      <Row gutter={[16, 16]} className="section-row">
        <Col xs={24} xl={12}>
          <Card title="Rekap Bulanan per Cluster">
            <Table rowKey="cluster_id" loading={monthly.isLoading} dataSource={monthly.data?.data || []} pagination={false} columns={[
              { title: 'Cluster', dataIndex: 'cluster_id' },
              { title: 'Jumlah Tagihan', dataIndex: 'billing_count' },
              { title: 'Total', dataIndex: 'total_billing', render: formatCurrency },
              { title: 'Terbayar', dataIndex: 'total_paid', render: formatCurrency },
            ]} />
          </Card>
        </Col>
        <Col xs={24} xl={12}>
          <Card title="Harian Loket">
            <Statistic title="Transaksi" value={daily.data?.data?.total_transactions || 0} />
            <Statistic title="Total" value={formatCurrency(daily.data?.data?.grand_total)} />
          </Card>
          <Card title="Collector" className="section-row">
            <Table rowKey="cashier_name" loading={collector.isLoading} dataSource={collector.data?.data || []} pagination={false} columns={[
              { title: 'Kasir', dataIndex: 'cashier_name' },
              { title: 'Transaksi', dataIndex: 'transaction_count' },
              { title: 'Total', dataIndex: 'grand_total', render: formatCurrency },
            ]} />
          </Card>
        </Col>
      </Row>
    </section>
  );
}
