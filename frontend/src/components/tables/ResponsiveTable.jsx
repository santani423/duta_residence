import { Table } from 'antd';
import { EmptyData } from '../common/ApiState.jsx';

export default function ResponsiveTable({ query, data, meta, onChange, columns, rowKey = 'id', scrollX = 1100, ...props }) {
  const items = data || query?.data?.data || [];
  const paginationMeta = meta || query?.data?.meta;

  return (
    <Table
      rowKey={rowKey}
      loading={query?.isLoading || query?.isFetching}
      dataSource={items}
      columns={columns}
      scroll={{ x: scrollX }}
      locale={{ emptyText: <EmptyData /> }}
      onChange={onChange}
      pagination={paginationMeta ? {
        current: paginationMeta.current_page,
        pageSize: paginationMeta.per_page,
        total: paginationMeta.total,
        showSizeChanger: true,
        showTotal: (total) => `${total} data`,
      } : props.pagination}
      {...props}
    />
  );
}
