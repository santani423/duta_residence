import { Alert, Button, Empty, Result } from 'antd';

export function LogoSpinner({ size = 64 }) {
  return (
    <span className="logo-spinner" style={{ width: size, height: size }} role="status" aria-live="polite" aria-label="Memuat">
      <img src="/logo-app.png" alt="" className="logo-spinner__img" />
    </span>
  );
}

export function LoadingState() {
  return (
    <div className="loading-state">
      <LogoSpinner size={72} />
    </div>
  );
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
