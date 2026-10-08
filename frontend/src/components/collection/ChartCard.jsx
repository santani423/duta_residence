import { Card, Segmented } from 'antd';
import { useState } from 'react';
import { ResponsiveContainer } from 'recharts';
import { EmptyData, ErrorState } from '../common/ApiState.jsx';

/**
 * Pembungkus chart bersama (Card + state loading/error/empty + ResponsiveContainer).
 * Warna chart ambil dari `useChartPalette()` (./useChartPalette.js), jangan hex hardcode.
 *
 * Props:
 * - title, extra          : header Card.
 * - loading               : skeleton Card.
 * - error, onRetry        : bila `error` truthy -> ErrorState + tombol "Coba lagi".
 * - empty, emptyText      : bila `empty` true -> EmptyData.
 * - height                : tinggi area chart (px). Default memakai kelas `.chart-box` (320px, 260px di mobile).
 * - table                 : ReactNode opsional (tabel data yang sama). Bila ada, muncul toggle Grafik/Tabel (aksesibilitas).
 * - responsive            : default true -> children dibungkus <ResponsiveContainer> (children = SATU elemen chart recharts).
 *                           false -> children dirender apa adanya di dalam .chart-box.
 * - footer                : ReactNode di bawah chart (mis. legend kustom / catatan).
 * - children              : elemen chart recharts.
 */
export default function ChartCard({
  title,
  extra,
  loading = false,
  error,
  onRetry,
  empty = false,
  emptyText = 'Belum ada data untuk ditampilkan.',
  height,
  table,
  responsive = true,
  footer,
  children,
  className,
  style,
}) {
  const [view, setView] = useState('chart');
  const showTable = Boolean(table) && view === 'table';
  const toggle = table ? (
    <Segmented
      size="small"
      value={view}
      onChange={setView}
      options={[{ value: 'chart', label: 'Grafik' }, { value: 'table', label: 'Tabel' }]}
    />
  ) : null;

  let body;
  if (error) {
    body = <ErrorState error={error} onRetry={onRetry} />;
  } else if (empty) {
    body = <EmptyData description={emptyText} />;
  } else if (showTable) {
    body = table;
  } else {
    body = (
      <div className="chart-box" style={height ? { height } : undefined}>
        {responsive ? <ResponsiveContainer width="100%" height="100%">{children}</ResponsiveContainer> : children}
      </div>
    );
  }

  return (
    <Card
      title={title}
      extra={(toggle || extra) ? <span style={{ display: 'inline-flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>{extra}{toggle}</span> : null}
      loading={loading}
      className={className}
      style={style}
    >
      {body}
      {footer && !error && !empty ? <div style={{ marginTop: 12 }}>{footer}</div> : null}
    </Card>
  );
}
