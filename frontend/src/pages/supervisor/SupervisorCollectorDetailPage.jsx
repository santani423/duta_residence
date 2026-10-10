import { Button, Card, Col, Row, Statistic, Table, Tabs, Tag } from 'antd';
import { PaperClipOutlined } from '@ant-design/icons';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import PageHeader from '../../components/common/PageHeader.jsx';
import { ErrorState, LoadingState } from '../../components/common/ApiState.jsx';
import StatusBadge, { VisitSignatureBadge } from '../../components/common/StatusBadge.jsx';
import VisitEvidenceModal, { VISIT_EVIDENCE_PERMISSIONS } from '../../components/collection/VisitEvidenceModal.jsx';
import { api } from '../../services/estateApi.js';
import { useAuth } from '../../state/AuthContext.jsx';
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
                <RecentVisitsTable visits={payload.recent_visits || []} collector={collector} onStale={detail.refetch} />
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

// Kunjungan terbaru kolektor. Kunjungan "Selesai" baru final setelah penghuni tanda tangan di HP
// collector (kolom Tanda Tangan); bukti (tanda tangan/foto/GPS) dibuka lewat modal. onStale memuat
// ulang detail kolektor bila modal menemukan bukti yang lebih baru dari baris tabel.
function RecentVisitsTable({ visits, collector, onStale }) {
  const { can, canAny } = useAuth();
  const [evidenceVisit, setEvidenceVisit] = useState(null);
  const canViewEvidence = canAny(VISIT_EVIDENCE_PERMISSIONS);

  return (
    <>
      <Table
        rowKey="id"
        dataSource={visits}
        pagination={false}
        scroll={{ x: 760 }}
        columns={[
          { title: 'Tanggal', dataIndex: 'visit_date', render: formatDateTime },
          {
            title: 'Unit',
            dataIndex: 'unit_id',
            render: (value, record) => {
              const residentId = record.unit?.resident_id ?? record.resident_id;
              return residentId && can('residents.view') ? <Link to={`/residents/${residentId}`}>{value}</Link> : value;
            },
          },
          { title: 'Tujuan', dataIndex: 'purpose' },
          { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge type="visitStatus" value={value} /> },
          { title: 'Tanda Tangan', key: 'signature', render: (_, record) => <VisitSignatureBadge visit={record} /> },
          canViewEvidence ? {
            title: 'Aksi',
            key: 'evidence',
            fixed: 'right',
            render: (_, record) => (
              <Button size="small" icon={<PaperClipOutlined />} onClick={() => setEvidenceVisit({ ...record, collector: record.collector || collector })}>
                Bukti
              </Button>
            ),
          } : null,
        ].filter(Boolean)}
      />
      <VisitEvidenceModal visit={evidenceVisit} onClose={() => setEvidenceVisit(null)} onStale={onStale} />
    </>
  );
}
