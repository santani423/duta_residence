import { Card, Descriptions, List, Tabs, Tag, Typography } from 'antd';
import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import PageHeader from '../components/common/PageHeader.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { api } from '../services/estateApi.js';
import { permissionLabels } from '../constants/permissions.js';
import { formatDateTime } from '../utils/format.js';
import { ErrorState, LoadingState } from '../components/common/ApiState.jsx';
import Can from '../components/common/Can.jsx';

export default function UserDetailPage() {
  const { id } = useParams();
  const detail = useQuery({ queryKey: ['users', id], queryFn: () => api.users.detail(id) });
  const activities = useQuery({ queryKey: ['users', id, 'activities'], queryFn: () => api.users.activities(id), enabled: true });

  if (detail.isLoading) return <LoadingState rows={8} />;
  if (detail.isError) return <ErrorState error={detail.error} onRetry={detail.refetch} />;

  const payload = detail.data?.data || {};
  const user = payload.user || {};
  const permissions = payload.permissions || [];

  return (
    <section>
      <PageHeader title={user.name} subtitle="Detail user, role, permission, dan aktivitas." breadcrumbs={[{ label: 'User', to: '/users' }, { label: user.username }]} onRefresh={() => { detail.refetch(); activities.refetch(); }} />
      <Tabs
        items={[
          {
            key: 'info',
            label: 'Informasi',
            children: (
              <Card>
                <Descriptions bordered column={{ xs: 1, md: 2 }}>
                  <Descriptions.Item label="Nama">{user.name}</Descriptions.Item>
                  <Descriptions.Item label="Username">{user.username}</Descriptions.Item>
                  <Descriptions.Item label="Email">{user.email || '-'}</Descriptions.Item>
                  <Descriptions.Item label="Telepon">{user.phone || '-'}</Descriptions.Item>
                  <Descriptions.Item label="Status"><StatusBadge type="active" value={user.is_active} /></Descriptions.Item>
                  <Descriptions.Item label="Theme">{user.theme_preference}</Descriptions.Item>
                  <Descriptions.Item label="Login Terakhir">{formatDateTime(user.last_login_at)}</Descriptions.Item>
                  <Descriptions.Item label="IP Terakhir">{user.last_login_ip || '-'}</Descriptions.Item>
                  <Descriptions.Item label="Unit Terhubung">{user.unit?.id || '-'}</Descriptions.Item>
                  <Descriptions.Item label="Penghuni">{user.unit?.resident?.name || '-'}</Descriptions.Item>
                </Descriptions>
              </Card>
            ),
          },
          {
            key: 'roles',
            label: 'Role dan Permission',
            children: (
              <Card>
                <Typography.Title level={5}>Role</Typography.Title>
                {user.roles?.map((role) => <Tag key={role.id}>{role.name}</Tag>)}
                <Typography.Title level={5} className="section-row">Permission</Typography.Title>
                <List
                  dataSource={permissions}
                  grid={{ gutter: 8, xs: 1, sm: 2, md: 3 }}
                  renderItem={(permission) => <List.Item><Tag>{permissionLabels[permission] || permission}</Tag></List.Item>}
                />
              </Card>
            ),
          },
          {
            key: 'access',
            label: 'Akses Estate',
            children: <Card><Typography.Text>Akses estate mengikuti permission dan role global backend.</Typography.Text></Card>,
          },
          {
            key: 'login',
            label: 'Riwayat Login',
            children: <Card><Typography.Text>Login terakhir: {formatDateTime(user.last_login_at)} dari IP {user.last_login_ip || '-'}</Typography.Text></Card>,
          },
          {
            key: 'activity',
            label: 'Activity Log',
            children: (
              <Can permission="audit-logs.view" fallback={<Card>Activity log hanya tersedia untuk admin.</Card>}>
                <Card>
                  <ResponsiveTable
                    query={activities}
                    columns={[
                      { title: 'Waktu', dataIndex: 'created_at', render: formatDateTime },
                      { title: 'Modul', dataIndex: 'module' },
                      { title: 'Action', dataIndex: 'action' },
                      { title: 'Aktivitas', dataIndex: 'activity' },
                      { title: 'Status', dataIndex: 'status' },
                    ]}
                  />
                </Card>
              </Can>
            ),
          },
        ]}
      />
    </section>
  );
}
