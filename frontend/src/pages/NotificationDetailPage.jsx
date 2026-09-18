import { Alert, Button, Card, Descriptions, Result, Space, Tag, Typography, message } from 'antd';
import { ArrowLeftOutlined, CheckOutlined, EyeInvisibleOutlined, LinkOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef } from 'react';
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';
import PageHeader from '../components/common/PageHeader.jsx';
import { ErrorState, LoadingState } from '../components/common/ApiState.jsx';
import { notificationSources } from '../components/notifications/notificationSources.js';
import { api } from '../services/estateApi.js';
import { useAuth } from '../state/AuthContext.jsx';
import { formatDateTime } from '../utils/format.js';
import { getApiErrorMessage } from '../utils/apiError.js';
import { resolveNotificationTarget } from '../utils/notificationRouting.js';

const PRIORITY_COLORS = { low: 'default', normal: 'blue', high: 'orange', critical: 'red' };
const HANDLED_LABELS = { open: 'Terbuka', in_progress: 'Diproses', handled: 'Selesai', escalated: 'Dieskalasi' };

export default function NotificationDetailPage({ source: sourceKey }) {
  const source = notificationSources[sourceKey];
  const { id } = useParams();
  const navigate = useNavigate();
  const location = useLocation();
  const queryClient = useQueryClient();
  const { can } = useAuth();
  const autoReadFor = useRef(null);

  const detailKey = [source.queryKey, 'detail', id];
  const query = useQuery({ queryKey: detailKey, queryFn: () => source.api.get(id), retry: false });
  const notification = query.data?.data;

  function applyUpdate(response) {
    queryClient.setQueryData(detailKey, (previous) => ({ ...previous, data: response.data }));
    // Lists and the bell badge live under the same prefix; refetch them so read state
    // stays in step here, in the bell and (on next fetch) on the mobile app.
    queryClient.invalidateQueries({ queryKey: [source.queryKey], predicate: (q) => q.queryKey[1] !== 'detail' });
  }

  const markRead = useMutation({ mutationFn: () => source.api.read(id), onSuccess: applyUpdate });
  const markUnread = useMutation({
    mutationFn: () => source.api.unread(id),
    onSuccess: (response) => {
      applyUpdate(response);
      message.success('Notifikasi ditandai belum dibaca');
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });
  const markHandled = useMutation({
    mutationFn: () => api.supervisorNotifications.markHandled(id),
    onSuccess: () => {
      message.success('Notifikasi ditandai selesai.');
      queryClient.invalidateQueries({ queryKey: [source.queryKey] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  // Opening a notification marks it read - once per opened id, so a deliberate
  // "mark as unread" while staying on the page isn't immediately undone.
  useEffect(() => {
    if (notification && notification.read_status === 'unread' && autoReadFor.current !== id) {
      autoReadFor.current = id;
      markRead.mutate();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [notification?.id, notification?.read_status, id]);

  const listPath = source.listPath;
  const backTo = location.state?.from?.pathname ? `${location.state.from.pathname}${location.state.from.search || ''}` : listPath;
  const breadcrumbs = [{ label: 'Notifikasi', to: listPath }, { label: 'Detail' }];

  if (query.isLoading) {
    return <section><PageHeader title="Detail Notifikasi" breadcrumbs={breadcrumbs} /><LoadingState /></section>;
  }

  if (query.isError) {
    const notFound = query.error?.status === 404;
    return (
      <section>
        <PageHeader title="Detail Notifikasi" breadcrumbs={breadcrumbs} />
        {notFound ? (
          <Result
            status="404"
            title="Notifikasi tidak ditemukan"
            subTitle="Notifikasi ini tidak ada atau bukan milik akun Anda."
            extra={<Button type="primary" onClick={() => navigate(listPath)}>Kembali ke daftar notifikasi</Button>}
          />
        ) : <ErrorState error={query.error} onRetry={query.refetch} />}
      </section>
    );
  }

  const target = resolveNotificationTarget(notification, source.key, can);
  const reference = notification.reference;
  const unread = notification.read_status === 'unread';
  const isSupervisor = source.key === 'supervisor';

  const details = [
    { key: 'category', label: 'Kategori', children: notification.category_label || '-' },
    { key: 'time', label: 'Tanggal & waktu', children: formatDateTime(notification.created_at) },
    notification.sender ? { key: 'sender', label: 'Pengirim', children: notification.sender.name } : null,
    notification.related_collector ? { key: 'collector', label: 'Kolektor terkait', children: notification.related_collector.name } : null,
    notification.unit_id ? { key: 'unit', label: 'Unit', children: notification.unit_id } : null,
    isSupervisor ? { key: 'priority', label: 'Prioritas', children: <Tag color={PRIORITY_COLORS[notification.priority]}>{notification.priority}</Tag> } : null,
    isSupervisor ? { key: 'handled', label: 'Status penanganan', children: HANDLED_LABELS[notification.handled_status] || notification.handled_status } : null,
    isSupervisor && notification.handling_deadline ? { key: 'deadline', label: 'Batas penanganan', children: formatDateTime(notification.handling_deadline) } : null,
    notification.data?.periods ? { key: 'periods', label: 'Periode tagihan', children: notification.data.periods } : null,
    reference ? { key: 'reference', label: 'Terkait dengan', children: `${reference.resource_label} #${reference.id}` } : null,
  ].filter(Boolean);

  return (
    <section>
      <PageHeader
        title="Detail Notifikasi"
        breadcrumbs={breadcrumbs}
        onRefresh={query.refetch}
        loading={query.isFetching}
        extra={<Button icon={<ArrowLeftOutlined />} onClick={() => navigate(backTo)}>Kembali</Button>}
      />
      <Card className="notification-detail" aria-live="polite">
        <Space direction="vertical" size="middle" style={{ width: '100%' }}>
          <Space wrap>
            <Typography.Title level={3} style={{ margin: 0 }}>{notification.title}</Typography.Title>
            <Tag color={unread ? 'blue' : 'default'}>{unread ? 'Belum dibaca' : 'Sudah dibaca'}</Tag>
          </Space>

          <Typography.Paragraph style={{ whiteSpace: 'pre-wrap', marginBottom: 0 }}>{notification.message}</Typography.Paragraph>

          {reference && !reference.available ? (
            <Alert type="warning" showIcon message={reference.message} />
          ) : null}

          <Space wrap>
            {target ? <Link to={target.to}><Button type="primary" icon={<LinkOutlined />}>{target.label}</Button></Link> : null}
            {isSupervisor && notification.handled_status !== 'handled' ? (
              <Button icon={<CheckOutlined />} onClick={() => markHandled.mutate()} loading={markHandled.isPending}>Tandai Selesai</Button>
            ) : null}
            {unread ? (
              <Button icon={<CheckOutlined />} onClick={() => markRead.mutate()} loading={markRead.isPending}>Tandai dibaca</Button>
            ) : (
              <Button icon={<EyeInvisibleOutlined />} onClick={() => markUnread.mutate()} loading={markUnread.isPending}>Tandai belum dibaca</Button>
            )}
          </Space>
        </Space>

        <Descriptions className="notification-detail-meta" size="small" bordered column={{ xs: 1, sm: 1, md: 2 }} items={details} />
      </Card>
    </section>
  );
}
