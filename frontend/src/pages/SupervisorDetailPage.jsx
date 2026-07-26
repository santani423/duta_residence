import { Card, Col, Descriptions, Row, Statistic, Table, Tabs, Tag } from 'antd';
import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import PageHeader from '../components/common/PageHeader.jsx';
import { ErrorState, LoadingState } from '../components/common/ApiState.jsx';
import { api } from '../services/estateApi.js';
import { formatDateTime } from '../utils/format.js';

export default function SupervisorDetailPage() {
  const { id } = useParams();
  const detail = useQuery({ queryKey: ['supervisors', id], queryFn: () => api.supervisors.detail(id) });
  const history = useQuery({ queryKey: ['supervisors', id, 'activity-history'], queryFn: () => api.supervisors.activityHistory(id) });

  if (detail.isLoading) return <LoadingState rows={8} />;
  if (detail.isError) return <ErrorState error={detail.error} onRetry={detail.refetch} />;

  const payload = detail.data?.data || {};
  const supervisor = payload.supervisor || {};
  const profile = supervisor.supervisor_profile || {};
  const summary = payload.summary || {};

  return (
    <section>
      <PageHeader
        title={supervisor.name}
        subtitle={`Kode Supervisor: ${profile.supervisor_code || '-'}`}
        breadcrumbs={[{ label: 'Manajemen Supervisor' }, { label: 'Data Supervisor', to: '/admin/supervisors/list' }, { label: supervisor.name }]}
        onRefresh={() => { detail.refetch(); history.refetch(); }}
      />

      <Row gutter={16} style={{ marginBottom: 16 }}>
        <Col xs={24} md={8}><Card><Statistic title="Cluster Diawasi" value={summary.total_clusters ?? 0} /></Card></Col>
        <Col xs={24} md={8}><Card><Statistic title="Kolektor Diawasi" value={summary.total_collectors ?? 0} /></Card></Col>
        <Col xs={24} md={8}><Card><Statistic title="Persetujuan Menunggu" value={summary.pending_approvals ?? 0} /></Card></Col>
      </Row>

      <Tabs
        items={[
          {
            key: 'profile',
            label: 'Profil',
            children: (
              <Card>
                <Descriptions column={2} bordered size="small">
                  <Descriptions.Item label="Nama">{supervisor.name}</Descriptions.Item>
                  <Descriptions.Item label="Username">{supervisor.username}</Descriptions.Item>
                  <Descriptions.Item label="Email">{supervisor.email || '-'}</Descriptions.Item>
                  <Descriptions.Item label="Telepon">{supervisor.phone || '-'}</Descriptions.Item>
                  <Descriptions.Item label="WhatsApp">{profile.whatsapp_number || '-'}</Descriptions.Item>
                  <Descriptions.Item label="Status Akun">{profile.account_status || '-'}</Descriptions.Item>
                  <Descriptions.Item label="Status Kepegawaian">{profile.employment_status || '-'}</Descriptions.Item>
                  <Descriptions.Item label="Bergabung">{profile.joined_at || '-'}</Descriptions.Item>
                  <Descriptions.Item label="Alamat" span={2}>{profile.address || '-'}</Descriptions.Item>
                  <Descriptions.Item label="Catatan Admin" span={2}>{profile.admin_notes || '-'}</Descriptions.Item>
                </Descriptions>
              </Card>
            ),
          },
          {
            key: 'assignments',
            label: 'Wilayah Cluster',
            children: (
              <Card>
                <Table
                  rowKey="id"
                  dataSource={payload.assignments || []}
                  pagination={false}
                  columns={[
                    { title: 'Cluster', render: (_, r) => r.cluster?.name || r.cluster_id },
                    { title: 'Status', dataIndex: 'status', render: (v) => <Tag>{v}</Tag> },
                    { title: 'Mulai', dataIndex: 'start_date', render: (v) => v || '-' },
                    { title: 'Selesai', dataIndex: 'end_date', render: (v) => v || 'sekarang' },
                  ]}
                />
              </Card>
            ),
          },
          {
            key: 'collectors',
            label: 'Kolektor Diawasi',
            children: (
              <Card>
                <Table
                  rowKey="id"
                  dataSource={payload.supervised_collectors || []}
                  pagination={false}
                  columns={[
                    { title: 'Kode', render: (_, r) => r.collector_profile?.collector_code || '-' },
                    { title: 'Nama', dataIndex: 'name' },
                    { title: 'Status Akun', render: (_, r) => r.collector_profile?.account_status || '-' },
                  ]}
                />
              </Card>
            ),
          },
          {
            key: 'history',
            label: 'Riwayat Aktivitas',
            children: (
              <Card>
                <Table
                  rowKey="id"
                  loading={history.isLoading}
                  dataSource={history.data?.data?.data || []}
                  pagination={false}
                  columns={[
                    { title: 'Waktu', dataIndex: 'created_at', render: formatDateTime, width: 170 },
                    { title: 'Aktivitas', dataIndex: 'activity' },
                    { title: 'Modul', dataIndex: 'module' },
                    { title: 'Deskripsi', dataIndex: 'description' },
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
