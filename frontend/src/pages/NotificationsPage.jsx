import { Button, Card, List, Select, Space, Typography, message } from 'antd';
import { CheckOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import { api } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { formatDateTime } from '../utils/format.js';
import { getApiErrorMessage } from '../utils/apiError.js';

export default function NotificationsPage() {
  const table = useTableState();
  const queryClient = useQueryClient();
  const notifications = useQuery({ queryKey: ['notifications', table.params], queryFn: () => api.notifications.list(table.params) });
  const read = useMutation({
    mutationFn: api.notifications.read,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['notifications'] }),
    onError: (error) => message.error(getApiErrorMessage(error)),
  });
  const readAll = useMutation({
    mutationFn: api.notifications.readAll,
    onSuccess: () => {
      message.success('Semua notifikasi ditandai dibaca');
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });

  return (
    <section>
      <PageHeader title="Notifikasi" subtitle="Daftar notifikasi operasional dan sistem." breadcrumbs={[{ label: 'Notifikasi' }]} onRefresh={notifications.refetch} extra={<Button icon={<CheckOutlined />} onClick={() => readAll.mutate()} loading={readAll.isPending}>Tandai Semua Dibaca</Button>} />
      <FilterBar>
        <Select allowClear placeholder="Status baca" value={table.filters.read_status} onChange={(value) => table.setFilters({ ...table.filters, read_status: value })} className="filter-input" options={[{ value: 'unread', label: 'Belum dibaca' }, { value: 'read', label: 'Dibaca' }]} />
      </FilterBar>
      <Card>
        <List
          loading={notifications.isLoading}
          dataSource={notifications.data?.data || []}
          pagination={notifications.data?.meta ? {
            current: notifications.data.meta.current_page,
            pageSize: notifications.data.meta.per_page,
            total: notifications.data.meta.total,
            onChange: (page, pageSize) => table.setPagination({ current: page, pageSize }),
          } : false}
          locale={{ emptyText: 'Tidak ada notifikasi' }}
          renderItem={(item) => (
            <List.Item
              actions={[
                item.read_status === 'unread' ? <Button size="small" onClick={() => read.mutate(item.id)} loading={read.isPending}>Tandai dibaca</Button> : null,
              ]}
            >
              <List.Item.Meta
                title={<Space><Typography.Text strong={item.read_status === 'unread'}>{item.type}</Typography.Text><StatusBadge type="read" value={item.read_status} /></Space>}
                description={<><div>{item.message}</div><Typography.Text type="secondary">{formatDateTime(item.created_at)}</Typography.Text></>}
              />
            </List.Item>
          )}
        />
      </Card>
    </section>
  );
}
