import { Badge, Button, Dropdown, List, Space, Typography, message } from 'antd';
import { BellOutlined, CheckOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { api } from '../../services/estateApi.js';
import { formatDateTime, formatNotificationType } from '../../utils/format.js';
import { getApiErrorMessage } from '../../utils/apiError.js';

export default function NotificationBell() {
  const queryClient = useQueryClient();
  const query = useQuery({
    queryKey: ['notifications', 'header'],
    queryFn: () => api.notifications.list({ per_page: 8 }),
    refetchInterval: 60000,
  });
  const readAll = useMutation({
    mutationFn: api.notifications.readAll,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['notifications'] }),
    onError: (error) => message.error(getApiErrorMessage(error)),
  });
  const unread = (query.data?.data || []).filter((item) => item.read_status === 'unread').length;

  const overlay = (
    <div className="notification-menu">
      <div className="notification-menu-header">
        <Typography.Text strong>Notifikasi</Typography.Text>
        <Button size="small" type="link" icon={<CheckOutlined />} onClick={() => readAll.mutate()} loading={readAll.isPending}>
          Tandai semua
        </Button>
      </div>
      <List
        size="small"
        loading={query.isLoading}
        dataSource={query.data?.data || []}
        locale={{ emptyText: 'Tidak ada notifikasi' }}
        renderItem={(item) => (
          <List.Item>
            <List.Item.Meta
              title={<Space><Typography.Text strong={item.read_status === 'unread'}>{formatNotificationType(item.type)}</Typography.Text></Space>}
              description={<><div>{item.message}</div><Typography.Text type="secondary">{formatDateTime(item.created_at)}</Typography.Text></>}
            />
          </List.Item>
        )}
      />
      <Link className="notification-footer" to="/notifications">Lihat semua</Link>
    </div>
  );

  return (
    <Dropdown dropdownRender={() => overlay} trigger={['click']} placement="bottomRight">
      <Badge count={unread} size="small">
        <Button shape="circle" icon={<BellOutlined />} />
      </Badge>
    </Dropdown>
  );
}
