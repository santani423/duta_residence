import { Button, Card, Input, Pagination, Select, message } from 'antd';
import { CheckOutlined, EyeInvisibleOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useLocation, useNavigate } from 'react-router-dom';
import PageHeader from '../common/PageHeader.jsx';
import FilterBar from '../common/FilterBar.jsx';
import { EmptyData, ErrorState, LoadingState } from '../common/ApiState.jsx';
import NotificationListItem from './NotificationListItem.jsx';
import { useTableState } from '../../hooks/useTableState.js';
import { getApiErrorMessage } from '../../utils/apiError.js';

// Full-page notification list used by the staff inbox and the resident portal: every row
// opens the detail page, and read/unread can be toggled without leaving the list.
export default function NotificationInbox({ source, title, subtitle, breadcrumbs, typeFilter = false }) {
  const table = useTableState();
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const location = useLocation();
  const query = useQuery({ queryKey: [source.queryKey, 'list', table.params], queryFn: () => source.api.list(table.params) });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: [source.queryKey] });
  const onError = (error) => message.error(getApiErrorMessage(error));
  const read = useMutation({ mutationFn: source.api.read, onSuccess: invalidate, onError });
  const unread = useMutation({ mutationFn: source.api.unread, onSuccess: invalidate, onError });
  const readAll = useMutation({
    mutationFn: source.api.readAll,
    onSuccess: () => {
      message.success('Semua notifikasi ditandai dibaca');
      invalidate();
    },
    onError,
  });

  const items = query.data?.data || [];
  const meta = query.data?.meta;

  function open(item) {
    navigate(source.detailPath(item.id), { state: { from: { pathname: location.pathname, search: location.search } } });
  }

  return (
    <section>
      <PageHeader
        title={title}
        subtitle={subtitle}
        breadcrumbs={breadcrumbs}
        onRefresh={query.refetch}
        loading={query.isFetching}
        extra={<Button icon={<CheckOutlined />} onClick={() => readAll.mutate()} loading={readAll.isPending} disabled={!meta?.unread_count}>Tandai Semua Dibaca</Button>}
      />
      <FilterBar>
        <Select
          allowClear
          placeholder="Status baca"
          value={table.filters.read_status}
          onChange={(value) => table.setFilters({ ...table.filters, read_status: value })}
          className="filter-input"
          options={[{ value: 'unread', label: 'Belum dibaca' }, { value: 'read', label: 'Dibaca' }]}
        />
        {typeFilter ? (
          <Input allowClear placeholder="Tipe notifikasi" value={table.filters.type} onChange={(event) => table.setFilters({ ...table.filters, type: event.target.value || undefined })} className="filter-input" />
        ) : null}
      </FilterBar>
      <Card>
        {query.isLoading ? <LoadingState /> : null}
        {query.isError ? <ErrorState error={query.error} onRetry={query.refetch} /> : null}
        {!query.isLoading && !query.isError && !items.length ? <EmptyData description="Tidak ada notifikasi" /> : null}
        {items.map((item) => (
          <NotificationListItem
            key={item.id}
            item={item}
            onOpen={open}
            actions={item.read_status === 'unread' ? (
              <Button size="small" icon={<CheckOutlined />} onClick={() => read.mutate(item.id)} loading={read.isPending && read.variables === item.id}>Tandai dibaca</Button>
            ) : (
              <Button size="small" type="text" icon={<EyeInvisibleOutlined />} onClick={() => unread.mutate(item.id)} loading={unread.isPending && unread.variables === item.id}>Belum dibaca</Button>
            )}
          />
        ))}
        {meta && meta.total > meta.per_page ? (
          <Pagination
            style={{ marginTop: 16, textAlign: 'right' }}
            current={meta.current_page}
            pageSize={meta.per_page}
            total={meta.total}
            showSizeChanger={false}
            onChange={(page, pageSize) => table.setPagination({ current: page, pageSize })}
          />
        ) : null}
      </Card>
    </section>
  );
}
