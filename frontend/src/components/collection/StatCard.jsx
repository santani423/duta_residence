import { ArrowDownOutlined, ArrowUpOutlined, InfoCircleOutlined, MinusOutlined } from '@ant-design/icons';
import { Card, Progress, Space, Statistic, Tooltip, Typography, theme } from 'antd';
import { formatCurrency } from '../../utils/format.js';

const numberFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const percentFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 });

function formatValue(value, format) {
  if (value === null || value === undefined || value === '') return '-';
  if (typeof format === 'function') return format(value);
  if (format === 'currency') return formatCurrency(value);
  if (format === 'percent') return `${percentFormatter.format(Number(value))}%`;
  if (format === 'number') return numberFormatter.format(Number(value));
  return value;
}

/**
 * Kartu KPI bersama.
 *
 * Props:
 * - title        : judul KPI.
 * - value        : nilai (number|string|null). null/undefined -> '-'.
 * - format       : 'currency' | 'number' | 'percent' | (value) => ReactNode. Default tanpa format.
 * - prefix/suffix: diteruskan ke Statistic.
 * - icon         : ReactNode kecil di samping judul.
 * - hint         : teks tooltip penjelasan (ikon info).
 * - delta        : angka perubahan (mis. 12.5 / -3). Tampil dengan panah + teks.
 * - deltaFormat  : sama seperti `format` untuk delta. Default 'percent'.
 * - deltaLabel   : teks pembanding, mis. 'vs bulan lalu'.
 * - inverse      : true bila nilai turun = baik (mis. tunggakan).
 * - progress     : 0..100+ (persen capaian) -> Progress bar; nilai di atas 100 ditampilkan apa adanya, bar dibatasi 100.
 * - status       : 'good' | 'warning' | 'critical' -> warna progress.
 * - footer       : ReactNode di bawah.
 * - loading      : skeleton (Card loading).
 * - onClick      : membuat kartu bisa diklik (hoverable).
 */
export default function StatCard({
  title,
  value,
  format,
  prefix,
  suffix,
  icon,
  hint,
  delta,
  deltaFormat = 'percent',
  deltaLabel,
  inverse = false,
  progress,
  status,
  footer,
  loading = false,
  onClick,
  className,
  style,
}) {
  const { token } = theme.useToken();
  const hasDelta = delta !== null && delta !== undefined && !Number.isNaN(Number(delta));
  const deltaNumber = Number(delta);
  const isUp = hasDelta && deltaNumber > 0;
  const isDown = hasDelta && deltaNumber < 0;
  const isGood = inverse ? isDown : isUp;
  const isBad = inverse ? isUp : isDown;
  const deltaColor = isGood ? token.colorSuccessText : (isBad ? token.colorErrorText : token.colorTextSecondary);
  const DeltaIcon = isUp ? ArrowUpOutlined : (isDown ? ArrowDownOutlined : MinusOutlined);
  const progressColor = { good: token.colorSuccess, warning: token.colorWarning, critical: token.colorError }[status];
  const progressValue = progress === null || progress === undefined ? null : Number(progress);

  return (
    <Card
      loading={loading}
      hoverable={Boolean(onClick)}
      onClick={onClick}
      className={className}
      style={{ height: '100%', ...style }}
    >
      <Statistic
        title={(
          <Space size={6}>
            {icon}
            <span>{title}</span>
            {hint ? (
              <Tooltip title={hint}>
                <InfoCircleOutlined aria-label={`Info ${title}`} style={{ color: token.colorTextTertiary }} />
              </Tooltip>
            ) : null}
          </Space>
        )}
        value={value ?? '-'}
        formatter={(raw) => formatValue(value === null || value === undefined ? null : raw, format)}
        prefix={prefix}
        suffix={suffix}
      />
      {hasDelta ? (
        <Typography.Text style={{ color: deltaColor, fontSize: token.fontSizeSM }}>
          <DeltaIcon aria-hidden />{' '}
          {formatValue(Math.abs(deltaNumber), deltaFormat)}
          {deltaLabel ? <Typography.Text type="secondary" style={{ fontSize: token.fontSizeSM }}> {deltaLabel}</Typography.Text> : null}
        </Typography.Text>
      ) : null}
      {progressValue !== null && !Number.isNaN(progressValue) ? (
        <Progress
          percent={Math.min(100, Math.max(0, progressValue))}
          format={() => `${percentFormatter.format(progressValue)}%`}
          strokeColor={progressColor}
          size="small"
        />
      ) : null}
      {footer ? <div style={{ marginTop: 8 }}>{footer}</div> : null}
    </Card>
  );
}
