import { Card, Col, Row, Statistic, Table, Tabs, Tag } from 'antd';
import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import PageHeader from '../../components/common/PageHeader.jsx';
import { ErrorState, LoadingState } from '../../components/common/ApiState.jsx';
import { api } from '../../services/estateApi.js';
import { formatCurrency, formatDate, formatDateTime } from '../../utils/format.js';

export default function SupervisorCollectorDetailPage() {
  const { id } = useParams();
  const detail = useQuery({ queryKey: ['supervisor-collector', id], queryFn: () => api.supervisorMonitoring.collectorDetail(id) });

  if (detail.isLoading) return <LoadingState rows={8} />;
  if (detail.isError) return <ErrorState error={detail.error} onRetry={detail.refetch} />;

  const payload = detail.data?.data || {};
  const collector = payload.collector || {};
  const profile = collector.collector_profile || {};
  const summary = payload.summary || {};

  return (
    <section>
      <PageHeader
        title={collector.name}
        subtitle={`Kode Kolektor: ${profile.collector_code || '-'}`}
        breadcrumbs={[{ label: 'Supervisor' }, { label: 'Monitoring Kolektor', to: '/supervisor/collectors' }, { label: collector.name }]}
        onRefresh={detail.refetch}
      />

      <Row gutter={16} style={{ marginBottom: 16 }}>
        <Col xs={24} md={6}><Card><Statistic title="Total Unit" value={summary.total_units ?? 0} /></Card></Col>
        <Col xs={24} md={6}><Card><Statistic title="Total Tunggakan" value={formatCurrency(summary.total_outstanding ?? 0)} /></Card></Col>
        <Col xs={24} md={6}><Card><Statistic title="Pembayaran Terkumpul" value={formatCurrency(summary.total_payments_collected ?? 0)} /></Card></Col>
        <Col xs={24} md={6}><Card><Statistic title="Broken Promise" value={summary.broken_promises ?? 0} /></Card></Col>
      </Row>

      <Tabs
        items={[
          {
            key: 'assignments',
            label: 'Penugasan',
            children: (
              <Card>
                <Table
                  rowKey="id"
                  dataSource={payload.assignments || []}
                  pagination={false}
                  columns={[
                    { title: 'Cakupan', dataIndex: 'scope_type', render: (v) => <Tag>{v}</Tag> },
                    { title: 'Cluster', render: (_, r) => r.cluster?.name || r.cluster_id || '-' },
                    { title: 'Prioritas', dataIndex: 'priority' },
                    { title: 'Status', dataIndex: 'status' },
                  ]}
                />
              </Card>
            ),
          },
          {
            key: 'visits',
            label: 'Kunjungan Terbaru',
            children: (
              <Card>
                <Table
                  rowKey="id"
                  dataSource={payload.recent_visits || []}
                  pagination={false}
                  columns={[
                    { title: 'Tanggal', dataIndex: 'visit_date', render: formatDateTime },
                    { title: 'Unit', dataIndex: 'unit_id' },
                    { title: 'Tujuan', dataIndex: 'purpose' },
                    { title: 'Status', dataIndex: 'status' },
                  ]}
                />
              </Card>
            ),
          },
          {
            key: 'promises',
            label: 'Janji Bayar (PTP)',
            children: (
              <Card>
                <Table
                  rowKey="id"
                  dataSource={payload.recent_payment_promises || []}
                  pagination={false}
                  columns={[
                    { title: 'Unit', dataIndex: 'unit_id' },
                    { title: 'Nominal', dataIndex: 'promised_amount', render: formatCurrency },
                    { title: 'Tanggal Janji', dataIndex: 'promised_date', render: formatDate },
                    { title: 'Status', dataIndex: 'status', render: (v) => <Tag color={v === 'broken' ? 'red' : v === 'fulfilled' ? 'green' : 'gold'}>{v}</Tag> },
                  ]}
                />
              </Card>
            ),
          },
          {
            key: 'complaints',
            label: 'Komplain Terkait',
            children: (
              <Card>
                <Table
                  rowKey="id"
                  dataSource={payload.recent_complaints || []}
                  pagination={false}
                  columns={[
                    { title: 'Unit', dataIndex: 'unit_id' },
                    { title: 'Judul', dataIndex: 'title' },
                    { title: 'Prioritas', dataIndex: 'priority' },
                    { title: 'Status', dataIndex: 'status' },
                  ]}
                />
              </Card>
            ),
          },
        ]}
      />
    </section>
  );
}
