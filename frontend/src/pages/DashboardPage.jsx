import { Card, Col, List, Row, Select, Space, Statistic, Typography } from 'antd';
import { useQuery } from '@tanstack/react-query';
import { Bar, BarChart, CartesianGrid, Legend, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import dayjs from 'dayjs';
import PageHeader from '../components/common/PageHeader.jsx';
import { ErrorState, LoadingState } from '../components/common/ApiState.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import { api } from '../services/estateApi.js';
import { formatCurrency, formatDateTime } from '../utils/format.js';
import { useAuth } from '../state/AuthContext.jsx';

export default function DashboardPage() {
  const { can } = useAuth();
  const now = dayjs();
  const dashboard = useQuery({ queryKey: ['dashboard'], queryFn: api.dashboard.summary });
  const monthly = useQuery({
    queryKey: ['dashboard-monthly', now.year(), now.month() + 1],
    queryFn: () => api.dashboard.monthly({ year: now.year(), month: now.month() + 1 }),
    enabled: can('reports.view'),
  });
  const aging = useQuery({
    queryKey: ['receivable-aging'],
    queryFn: api.dashboard.aging,
    enabled: can('reports.view'),
  });

  if (dashboard.isLoading) return <LoadingState rows={8} />;
  if (dashboard.isError) return <ErrorState error={dashboard.error} onRetry={dashboard.refetch} />;

  const data = dashboard.data?.data || {};
  const monthlyData = monthly.data?.data || [];
  const agingData = aging.data?.data || {};
  const pieData = [
    { name: '< 30 hari', value: Number(agingData.lt_30 || 0) },
    { name: '30-60 hari', value: Number(agingData.d30_60 || 0) },
    { name: '60-90 hari', value: Number(agingData.d60_90 || 0) },
    { name: '> 90 hari', value: Number(agingData.gt_90 || 0) },
  ];

  return (
    <section>
      <PageHeader
        title="Dashboard"
        subtitle="Ringkasan operasional estate, tagihan, pembayaran, dan aktivitas terbaru."
        breadcrumbs={[{ label: 'Dashboard' }]}
        onRefresh={() => {
          dashboard.refetch();
          monthly.refetch();
          aging.refetch();
        }}
        extra={<Select value="bulan-ini" options={[{ value: 'bulan-ini', label: 'Bulan ini' }]} />}
      />

      <Row gutter={[16, 16]}>
        <Col xs={24} sm={12} lg={6}><Card><Statistic title="Total Cluster" value={data.total_clusters || 0} /></Card></Col>
        <Col xs={24} sm={12} lg={6}><Card><Statistic title="Total Penghuni" value={data.total_residents || 0} /></Card></Col>
        <Col xs={24} sm={12} lg={6}><Card><Statistic title="Tagihan" value={data.total_billings || 0} /></Card></Col>
        <Col xs={24} sm={12} lg={6}><Card><Statistic title="Penerimaan Hari Ini" value={formatCurrency(data.today_receipts_total)} /></Card></Col>
      </Row>

      <Row gutter={[16, 16]} className="section-row">
        <Col xs={24} xl={15}>
          <Card title="Pemasukan per Cluster" loading={monthly.isLoading}>
            <div className="chart-box">
              <ResponsiveContainer>
                <BarChart data={monthlyData}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="cluster_id" />
                  <YAxis tickFormatter={(value) => `${Number(value) / 1000000} jt`} />
                  <Tooltip formatter={(value) => formatCurrency(value)} />
                  <Legend />
                  <Bar dataKey="total_billing" name="Tagihan" fill="#0f766e" />
                  <Bar dataKey="total_paid" name="Terbayar" fill="#2563eb" />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </Card>
        </Col>
        <Col xs={24} xl={9}>
          <Card title="Aging Piutang" loading={aging.isLoading}>
            <div className="chart-box">
              <ResponsiveContainer>
                <PieChart>
                  <Pie data={pieData} dataKey="value" nameKey="name" outerRadius={90} fill="#0f766e" label />
                  <Tooltip formatter={(value) => formatCurrency(value)} />
                </PieChart>
              </ResponsiveContainer>
            </div>
          </Card>
        </Col>
      </Row>

      <Row gutter={[16, 16]} className="section-row">
        <Col xs={24} lg={12}>
          <Card title="Status Tagihan">
            <Space size="large" wrap>
              <Statistic title="Belum Bayar" value={data.unpaid_billings || 0} />
              <Statistic title="Lunas" value={data.paid_billings || 0} />
            </Space>
          </Card>
        </Col>
        <Col xs={24} lg={12}>
          <Card title="Aktivitas Pembayaran Terbaru">
            <List
              dataSource={data.recent_receipts || []}
              locale={{ emptyText: 'Belum ada pembayaran terbaru' }}
              renderItem={(item) => (
                <List.Item>
                  <List.Item.Meta
                    title={<Space><Typography.Text strong>{item.number}</Typography.Text><StatusBadge type="transaction" value={item.status} /></Space>}
                    description={`${item.unit?.resident?.name || item.resident_name} - ${formatDateTime(item.transaction_date)}`}
                  />
                  <Typography.Text strong>{formatCurrency(item.grand_total)}</Typography.Text>
                </List.Item>
              )}
            />
          </Card>
        </Col>
      </Row>
    </section>
  );
}
