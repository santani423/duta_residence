import { Card, Descriptions, Tag } from 'antd';
import PageHeader from '../../components/common/PageHeader.jsx';
import { useAuth } from '../../state/AuthContext.jsx';

export default function ProfilePage() {
  const { user, permissions } = useAuth();

  return (
    <section>
      <PageHeader title="Profil Pengguna" breadcrumbs={[{ label: 'Profil' }]} />
      <Card>
        <Descriptions bordered column={{ xs: 1, md: 2 }}>
          <Descriptions.Item label="Nama">{user?.name}</Descriptions.Item>
          <Descriptions.Item label="Username">{user?.username}</Descriptions.Item>
          <Descriptions.Item label="Email">{user?.email || '-'}</Descriptions.Item>
          <Descriptions.Item label="Role">{user?.roles?.map((role) => <Tag key={role.name || role}>{role.name || role}</Tag>)}</Descriptions.Item>
          <Descriptions.Item label="Permission" span={2}>{permissions.map((permission) => <Tag key={permission}>{permission}</Tag>)}</Descriptions.Item>
        </Descriptions>
      </Card>
    </section>
  );
}
