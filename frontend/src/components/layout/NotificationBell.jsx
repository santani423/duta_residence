import { Badge, Button, Dropdown, Empty, Tooltip, Typography, message } from 'antd';
import { BellOutlined, CheckOutlined, NotificationOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useCallback, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { ErrorState, LoadingState } from '../common/ApiState.jsx';
import NotificationListItem from '../notifications/NotificationListItem.jsx';
import { bellSourceFor } from '../notifications/notificationSources.js';
import { useBrowserNotifications } from '../../hooks/useBrowserNotifications.js';
import { useAuth } from '../../state/AuthContext.jsx';
import { getApiErrorMessage } from '../../utils/apiError.js';

export default function NotificationBell() {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const location = useLocation();
  const { user, hasRole } = useAuth();
  const source = bellSourceFor(hasRole);
  const [open, setOpen] = useState(false);

  const query = useQuery({
    queryKey: [source.queryKey, 'header'],
    queryFn: () => source.api.list({ per_page: 8 }),
    // Polling keeps the badge (and, via the same rows, the mobile app's state) current;
    // react-query also refetches on window focus.
    refetchInterval: 60000,
  });
  const items = query.data?.data;
  const unread = query.data?.meta?.unread_count ?? (items || []).filter((item) => item.read_status === 'unread').length;

  const openNotification = useCallback((item) => {
    setOpen(false);
    // `from` lets the detail page's Back button return to wherever the user was.
    navigate(source.detailPath(item.id), { state: { from: { pathname: location.pathname, search: location.search } } });
  }, [navigate, source, location.pathname, location.search]);

  const browser = useBrowserNotifications({ items, userKey: user?.id, sourceKey: source.key, onOpen: openNotification });

  const readAll = useMutation({
    mutationFn: source.api.readAll,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: [source.queryKey] }),
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const overlay = (
    <div className="notification-menu" role="dialog" aria-label="Notifikasi">
      <div className="notification-menu-header">
        <Typography.Text strong>Notifikasi{unread ? ` (${unread} baru)` : ''}</Typography.Text>
        <Button size="small" type="link" icon={<CheckOutlined />} onClick={() => readAll.mutate()} loading={readAll.isPending} disabled={!unread}>
          Tandai semua
        </Button>
      </div>
      <div className="notification-menu-list">
        {query.isLoading ? <LoadingState /> : null}
        {query.isError ? <ErrorState error={query.error} onRetry={query.refetch} /> : null}
        {!query.isLoading && !query.isError && !items?.length ? <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Tidak ada notifikasi" /> : null}
        {(items || []).map((item) => <NotificationListItem key={item.id} item={item} onOpen={openNotification} compact />)}
      </div>
      {browser.supported && browser.permission === 'default' ? (
        <Button size="small" block icon={<NotificationOutlined />} onClick={browser.request}>Aktifkan notifikasi browser</Button>
      ) : null}
      <Link className="notification-footer" to={source.listPath} onClick={() => setOpen(false)}>Lihat semua</Link>
    </div>
  );

  return (
    <Dropdown popupRender={() => overlay} trigger={['click']} placement="bottomRight" open={open} onOpenChange={setOpen}>
      <Tooltip title="Notifikasi">
        <Badge count={unread} size="small" overflowCount={99}>
          <Button shape="circle" icon={<BellOutlined />} aria-label={unread ? `Notifikasi, ${unread} belum dibaca` : 'Notifikasi'} aria-haspopup="dialog" aria-expanded={open} />
        </Badge>
      </Tooltip>
    </Dropdown>
  );
}
