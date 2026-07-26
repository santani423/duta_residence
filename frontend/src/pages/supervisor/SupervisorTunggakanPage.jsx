import { Card, Col, Row, Table, Tooltip } from 'antd';
import { useQuery } from '@tanstack/react-query';
import PageHeader from '../../components/common/PageHeader.jsx';
import { ErrorState, LoadingState } from '../../components/common/ApiState.jsx';
import { api } from '../../services/estateApi.js';
import { formatCurrency } from '../../utils/format.js';

// No geo-boundary data exists for clusters, so the "heatmap" is a color-graded grid
// (severity by percentage_unit_menunggak) rather than a literal map overlay - a
// deliberate scope decision documented in this feature's plan.
function severityColor(percentage) {
  if (percentage >= 50) return '#a8071a';
  if (percentage >= 30) return '#cf1322';
  if (percentage >= 15) return '#fa8c16';
  if (percentage >= 5) return '#fadb14';
  return '#52c41a';
}

export default function SupervisorTunggakanPage() {
  const clusters = useQuery({ queryKey: ['supervisor-tunggakan'], queryFn: () => api.tunggakan.clusters() });

  if (clusters.isLoading) return <LoadingState rows={8} />;
  if (clusters.isError) return <ErrorState error={clusters.error} onRetry={clusters.refetch} />;

  const rows = clusters.data?.data || [];

  return (
    <section>
      <PageHeader
        title="Analisis Tunggakan per Cluster"
        subtitle="Persentase Unit Menunggak = Jumlah Unit Menunggak / Total Unit Aktif × 100%."
        breadcrumbs={[{ label: 'Supervisor' }, { label: 'Analisis Tunggakan' }]}
        onRefresh={clusters.refetch}
      />

      <Card title="Peta Intensitas Tunggakan" style={{ marginBottom: 16 }}>
        <Row gutter={[12, 12]}>
          {rows.map((row) => (
            <Col key={row.cluster_id} xs={12} sm={8} md={6} lg={4}>
              <Tooltip title={`${row.unit_menunggak} dari ${row.total_unit_aktif} unit menunggak (${row.percentage_unit_menunggak}%)`}>
                <div
                  style={{
                    background: severityColor(row.percentage_unit_menunggak),
                    borderRadius: 8,
                    padding: '16px 12px',
                    color: '#fff',
                    textAlign: 'center',
                  }}
                >
                  <div style={{ fontWeight: 600 }}>{row.cluster_name}</div>
                  <div style={{ fontSize: 20, fontWeight: 700 }}>{row.percentage_unit_menunggak}%</div>
                </div>
              </Tooltip>
            </Col>
          ))}
        </Row>
      </Card>

      <Card>
        <Table
          rowKey="cluster_id"
          dataSource={rows}
          pagination={false}
          columns={[
            { title: 'Cluster', dataIndex: 'cluster_name' },
            { title: 'Unit Aktif', dataIndex: 'total_unit_aktif' },
            { title: 'Unit Menunggak', dataIndex: 'unit_menunggak' },
            { title: '% Menunggak', dataIndex: 'percentage_unit_menunggak', render: (v) => `${v}%` },
            { title: 'Total Tunggakan', dataIndex: 'total_tunggakan', render: formatCurrency },
            { title: 'Broken Promise', dataIndex: 'broken_promise_count' },
            { title: 'Pelanggaran', dataIndex: 'violation_count' },
            { title: 'Skor Prioritas', dataIndex: 'priority_score' },
          ]}
        />
      </Card>
    </section>
  );
}
