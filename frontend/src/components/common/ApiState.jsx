import { Alert, Button, Empty, Result, Skeleton } from 'antd';

export function LoadingState({ rows = 4 }) {
  return <Skeleton active paragraph={{ rows }} />;
}

export function ErrorState({ error, onRetry }) {
  return (
    <Alert
      type="error"
      showIcon
      message={error?.message || 'Data gagal dimuat.'}
      description={onRetry ? <Button onClick={onRetry}>Coba lagi</Button> : null}
    />
  );
}

export function EmptyData({ description = 'Belum ada data.' }) {
  return <Empty description={description} />;
}

export function HttpErrorPage({ status = '404', title = 'Halaman tidak ditemukan', subTitle, action }) {
  return <Result status={status} title={title} subTitle={subTitle} extra={action} />;
}
