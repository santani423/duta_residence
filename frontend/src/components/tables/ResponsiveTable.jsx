import { Table } from 'antd';
import { EmptyData, ErrorState, LogoSpinner } from '../common/ApiState.jsx';

export default function ResponsiveTable({ query, data, meta, onChange, columns, rowKey = 'id', scrollX = 1100, ...props }) {
  const items = data || query?.data?.data || [];
  const paginationMeta = meta || query?.data?.meta;

  // Saat request gagal (403/500/jaringan) tampilkan error + tombol coba lagi, bukan "Belum ada data".
  // Bila masih ada data lama (refetch gagal), error ditampilkan di atas tabel.
  const isError = Boolean(query?.isError);
  const errorState = isError
    ? <ErrorState error={query.error} onRetry={query.refetch ? () => query.refetch() : undefined} />
    : null;
  const showErrorAbove = isError && items.length > 0;

  const table = (
    <Table
      rowKey={rowKey}
      loading={{ spinning: Boolean(query?.isLoading || query?.isFetching), indicator: <LogoSpinner size={40} /> }}
      dataSource={items}
      columns={columns}
      scroll={{ x: scrollX }}
      locale={{ emptyText: isError ? errorState : <EmptyData /> }}
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

  if (!showErrorAbove) return table;

  return (
    <div className="stack">
      {errorState}
      {table}
    </div>
  );
}
