import { Alert, Card, Col, DatePicker, Grid, Row, Segmented, Select, Space, Table, Tag, Typography, theme } from 'antd';
import { TrophyOutlined } from '@ant-design/icons';
import { useIsFetching, useQuery, useQueryClient } from '@tanstack/react-query';
import dayjs from 'dayjs';
import { useMemo, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { Bar, BarChart, CartesianGrid, Cell, Tooltip, XAxis, YAxis } from 'recharts';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import { EmptyData, ErrorState } from '../components/common/ApiState.jsx';
import { UnitFilterFields } from '../components/common/UnitFilters.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import CollectorSelect from '../components/collection/CollectorSelect.jsx';
import StatCard from '../components/collection/StatCard.jsx';
import ChartCard from '../components/collection/ChartCard.jsx';
import { useChartPalette } from '../components/collection/useChartPalette.js';
import { useAuth } from '../state/AuthContext.jsx';
import { api } from '../services/estateApi.js';
import { formatCurrency, formatDate } from '../utils/format.js';

const PERIOD_LABELS = { daily: 'Harian', weekly: 'Mingguan', monthly: 'Bulanan' };
const PERIOD_OPTIONS = Object.entries(PERIOD_LABELS).map(([value, label]) => ({ value, label }));
const PICKER_BY_PERIOD = { daily: 'date', weekly: 'week', monthly: 'month' };
const CLUSTER_ONLY = ['block', 'unit_id', 'customer', 'address'];
const CHART_LIMIT = 15;

const METRICS = [
  { value: 'collected_amount', label: 'Nominal tertagih', format: 'currency' },
  { value: 'collection_rate', label: 'Collection rate', format: 'percent' },
  { value: 'achievement_percent_raw', label: 'Pencapaian target', format: 'percent' },
  { value: 'visit_count', label: 'Kunjungan', format: 'number' },
  { value: 'successful_visit_rate', label: 'Kunjungan berhasil %', format: 'percent' },
  { value: 'ptp_fulfilled', label: 'PTP terpenuhi', format: 'number' },
  { value: 'ptp_fulfillment_rate', label: 'PTP fulfillment %', format: 'percent' },
];
const METRIC_BY_KEY = Object.fromEntries(METRICS.map((metric) => [metric.value, metric]));

const numberFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const percentFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 });
const compactFormatter = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 });

function isBlank(value) {
  return value === null || value === undefined || value === '';
}

function formatMetric(value, format) {
  if (isBlank(value)) return '-';
  if (format === 'currency') return formatCurrency(value);
  if (format === 'percent') return `${percentFormatter.format(Number(value))}%`;
  return numberFormatter.format(Number(value));
}

function formatAxis(value, format) {
  if (format === 'currency') return `Rp${compactFormatter.format(Number(value))}`;
  if (format === 'percent') return `${numberFormatter.format(Number(value))}%`;
  return compactFormatter.format(Number(value));
}

// Selaras dengan CollectorPerformanceService::resolvePeriod: harian = hari itu, mingguan = Senin,
// bulanan = tanggal 1. Dihitung manual supaya tidak bergantung pada locale dayjs (awal minggu).
function alignPeriodStart(type, value) {
  const date = dayjs(value || undefined).startOf('day');
  if (type === 'monthly') return date.startOf('month');
  if (type === 'weekly') return date.subtract((date.day() + 6) % 7, 'day');
  return date;
}

function describePeriod(type, start) {
  if (!start) return '-';
  const aligned = alignPeriodStart(type, start);
  if (type === 'monthly') return aligned.format('MMMM YYYY');
  if (type === 'weekly') return `${aligned.format('DD MMM')} – ${aligned.add(6, 'day').format('DD MMM YYYY')}`;
  return aligned.format('DD MMM YYYY');
}

function periodRangeLabel(period, fallbackType, fallbackStart) {
  if (period?.start) {
    return period.end && period.end !== period.start
      ? `${formatDate(period.start)} – ${formatDate(period.end)}`
      : formatDate(period.start);
  }
  return describePeriod(fallbackType, fallbackStart);
}

function usePeriodState(initialType = 'monthly') {
  const [periodType, setPeriodType] = useState(initialType);
  const [periodStart, setPeriodStart] = useState(() => alignPeriodStart(initialType));

  return {
    periodType,
    periodStart,
    // Ganti jenis periode -> kembali ke awal periode berjalan untuk jenis itu.
    changeType: (type) => {
      setPeriodType(type);
      setPeriodStart(alignPeriodStart(type));
    },
    changeStart: (value) => {
      if (value) setPeriodStart(alignPeriodStart(periodType, value));
    },
  };
}

function PeriodControls({ period }) {
  return (
    <>
      <Segmented options={PERIOD_OPTIONS} value={period.periodType} onChange={period.changeType} />
      <DatePicker
        picker={PICKER_BY_PERIOD[period.periodType]}
        value={period.periodStart}
        allowClear={false}
        inputReadOnly
        format={(value) => describePeriod(period.periodType, value)}
        onChange={period.changeStart}
        className="filter-input"
        aria-label="Periode"
      />
    </>
  );
}

function RankBadge({ rank }) {
  const medal = { 1: 'gold', 2: 'default', 3: 'orange' }[rank];
  if (!medal) return <Typography.Text type="secondary">#{rank ?? '-'}</Typography.Text>;
  return <Tag color={medal} icon={<TrophyOutlined />}>#{rank}</Tag>;
}

function RankingView() {
  const { token } = theme.useToken();
  const palette = useChartPalette();
  const screens = Grid.useBreakpoint();
  const period = usePeriodState('monthly');
  const [metric, setMetric] = useState('collected_amount');
  const [filters, setFilters] = useState({});

  const params = {
    period_type: period.periodType,
    period_start: period.periodStart.format('YYYY-MM-DD'),
    metric,
    cluster_id: filters.cluster_id || undefined,
  };

  const performance = useQuery({
    queryKey: ['collection', 'performance', params],
    queryFn: () => api.collection.performance(params),
    placeholderData: (previous) => previous,
  });

  const payload = performance.data?.data;
  const summary = payload?.summary || {};
  const ranking = useMemo(() => (Array.isArray(payload?.ranking) ? payload.ranking : []), [payload]);
  const metricInfo = METRIC_BY_KEY[metric];
  const periodLabel = periodRangeLabel(payload?.period, period.periodType, period.periodStart);

  const chartData = useMemo(
    () => ranking
      .filter((row) => !isBlank(row[metric]))
      .slice(0, CHART_LIMIT)
      .map((row) => ({ name: row.collector?.name || '-', rank: row.rank, value: Number(row[metric]) })),
    [ranking, metric],
  );
  const missingCount = ranking.filter((row) => isBlank(row[metric])).length;

  const highlight = (key) => (key === metric ? { style: { background: token.colorPrimaryBg } } : {});

  const columns = [
    { title: '#', dataIndex: 'rank', width: 80, fixed: 'left', render: (rank) => <RankBadge rank={rank} /> },
    {
      title: 'Kolektor',
      key: 'collector',
      fixed: 'left',
      width: 200,
      render: (_, row) => (
        <Space orientation="vertical" size={0}>
          <Typography.Text strong={row.rank <= 3}>{row.collector?.name || '-'}</Typography.Text>
          {row.collector?.collector_code ? <Typography.Text type="secondary">{row.collector.collector_code}</Typography.Text> : null}
        </Space>
      ),
    },
    {
      title: 'Nominal Tertagih',
      dataIndex: 'collected_amount',
      align: 'right',
      onCell: () => highlight('collected_amount'),
      render: (value) => formatMetric(value, 'currency'),
    },
    {
      title: 'Collection Rate',
      dataIndex: 'collection_rate',
      align: 'right',
      onCell: () => highlight('collection_rate'),
      render: (value) => formatMetric(value, 'percent'),
    },
    {
      title: 'Pencapaian Target',
      dataIndex: 'achievement_percent_raw',
      align: 'right',
      onCell: () => highlight('achievement_percent_raw'),
      render: (value, row) => (isBlank(value)
        ? <Typography.Text type="secondary">{row.target_amount ? '-' : 'Tanpa target'}</Typography.Text>
        : formatMetric(value, 'percent')),
    },
    {
      title: 'Kunjungan',
      dataIndex: 'visit_count',
      align: 'right',
      onCell: () => highlight('visit_count'),
      render: (value, row) => (
        <span>
          {formatMetric(value, 'number')}
          {!isBlank(row.successful_visit_count) ? <Typography.Text type="secondary"> ({formatMetric(row.successful_visit_count, 'number')} berhasil)</Typography.Text> : null}
        </span>
      ),
    },
    {
      title: 'Kunjungan Berhasil',
      dataIndex: 'successful_visit_rate',
      align: 'right',
      onCell: () => highlight('successful_visit_rate'),
      render: (value) => formatMetric(value, 'percent'),
    },
    {
      title: 'PTP Terpenuhi',
      dataIndex: 'ptp_fulfilled',
      align: 'right',
      onCell: () => highlight('ptp_fulfilled'),
      render: (value, row) => (
        <span>
          {formatMetric(value, 'number')}
          {!isBlank(row.ptp_created) ? <Typography.Text type="secondary"> / {formatMetric(row.ptp_created, 'number')} dibuat</Typography.Text> : null}
        </span>
      ),
    },
    {
      title: 'PTP Fulfillment',
      dataIndex: 'ptp_fulfillment_rate',
      align: 'right',
      onCell: () => highlight('ptp_fulfillment_rate'),
      render: (value) => formatMetric(value, 'percent'),
    },
  ];

  const metricPicker = screens.md ? (
    <div style={{ overflowX: 'auto', maxWidth: '100%' }}>
      <Segmented options={METRICS.map(({ value, label }) => ({ value, label }))} value={metric} onChange={setMetric} />
    </div>
  ) : (
    <Select
      options={METRICS.map(({ value, label }) => ({ value, label }))}
      value={metric}
      onChange={setMetric}
      style={{ width: '100%' }}
      aria-label="Metrik peringkat"
    />
  );

  const chartTable = (
    <Table
      size="small"
      rowKey="rank"
      pagination={false}
      dataSource={chartData}
      columns={[
        { title: '#', dataIndex: 'rank', width: 60 },
        { title: 'Kolektor', dataIndex: 'name' },
        { title: metricInfo.label, dataIndex: 'value', align: 'right', render: (value) => formatMetric(value, metricInfo.format) },
      ]}
    />
  );

  return (
    <div className="stack">
      <FilterBar>
        <PeriodControls period={period} />
        <UnitFilterFields value={filters} onChange={setFilters} hide={CLUSTER_ONLY} />
      </FilterBar>

      {performance.isError && !payload ? (
        <ErrorState error={performance.error} onRetry={() => performance.refetch()} />
      ) : (
        <>
          <Row gutter={[16, 16]}>
            <Col xs={24} sm={12} lg={6}>
              <StatCard
                title="Total Tertagih"
                value={summary.collected_amount}
                format="currency"
                loading={performance.isLoading}
                footer={<Typography.Text type="secondary">dari {formatMetric(summary.collector_count, 'number')} kolektor</Typography.Text>}
              />
            </Col>
            <Col xs={24} sm={12} lg={6}>
              <StatCard
                title="Rata-rata Collection Rate"
                value={summary.average_collection_rate}
                format="percent"
                loading={performance.isLoading}
                hint="Perkiraan: tertagih ÷ (tertagih + tunggakan saat ini), dirata-rata per kolektor."
              />
            </Col>
            <Col xs={24} sm={12} lg={6}>
              <StatCard title="Total Kunjungan" value={summary.visit_count} format="number" loading={performance.isLoading} />
            </Col>
            <Col xs={24} sm={12} lg={6}>
              <StatCard title="PTP Terpenuhi" value={summary.ptp_fulfilled} format="number" loading={performance.isLoading} />
            </Col>
          </Row>

          <Card size="small">
            <Space orientation="vertical" size={8} style={{ width: '100%' }}>
              <Typography.Text type="secondary">Urutkan peringkat berdasarkan</Typography.Text>
              {metricPicker}
            </Space>
          </Card>

          <Row gutter={[16, 16]}>
            <Col xs={24} xl={10}>
              <ChartCard
                title={`${metricInfo.label} per kolektor`}
                loading={performance.isLoading}
                error={performance.isError ? performance.error : null}
                onRetry={() => performance.refetch()}
                empty={!chartData.length}
                emptyText={ranking.length
                  ? 'Belum ada nilai untuk metrik ini pada periode terpilih (mis. kolektor belum punya target).'
                  : 'Belum ada kolektor dalam cakupan Anda untuk periode ini.'}
                height={Math.max(260, chartData.length * 34 + 40)}
                table={chartTable}
                footer={(ranking.length > CHART_LIMIT || missingCount) ? (
                  <Typography.Text type="secondary">
                    {ranking.length > CHART_LIMIT ? `Menampilkan ${CHART_LIMIT} teratas. ` : ''}
                    {missingCount ? `${missingCount} kolektor tanpa nilai tidak ditampilkan.` : ''}
                  </Typography.Text>
                ) : null}
              >
                <BarChart data={chartData} layout="vertical" margin={{ top: 4, right: 24, bottom: 4, left: 8 }}>
                  <CartesianGrid horizontal={false} stroke={palette.grid} />
                  <XAxis
                    type="number"
                    tick={palette.axisTick}
                    stroke={palette.grid}
                    tickFormatter={(value) => formatAxis(value, metricInfo.format)}
                  />
                  <YAxis type="category" dataKey="name" width={screens.md ? 130 : 90} tick={palette.axisTick} stroke={palette.grid} interval={0} />
                  <Tooltip
                    {...palette.tooltip}
                    formatter={(value) => [formatMetric(value, metricInfo.format), metricInfo.label]}
                    labelFormatter={(label, items) => {
                      const rank = items?.[0]?.payload?.rank;
                      return rank ? `#${rank} ${label}` : label;
                    }}
                  />
                  <Bar dataKey="value" name={metricInfo.label} radius={[0, 4, 4, 0]} maxBarSize={24}>
                    {chartData.map((entry) => (
                      <Cell key={entry.rank} fill={palette.series[0]} fillOpacity={entry.rank <= 3 ? 1 : 0.55} />
                    ))}
                  </Bar>
                </BarChart>
              </ChartCard>
            </Col>
            <Col xs={24} xl={14}>
              <Card
                title={`Peringkat · ${periodLabel}`}
                extra={<Typography.Text type="secondary">{PERIOD_LABELS[period.periodType]}</Typography.Text>}
              >
                <ResponsiveTable
                  query={performance}
                  data={ranking}
                  rowKey={(row) => row.collector?.id ?? row.rank}
                  scrollX={1250}
                  pagination={{ pageSize: 20, showSizeChanger: true, showTotal: (total) => `${total} kolektor` }}
                  onRow={(row) => (row.rank <= 3 ? { style: { background: token.colorFillAlter } } : {})}
                  {...(performance.isError ? {} : {
                    locale: { emptyText: <EmptyData description="Belum ada kolektor dalam cakupan Anda. Pastikan kolektor sudah ditugaskan ke cluster Anda." /> },
                  })}
                  columns={columns}
                />
              </Card>
            </Col>
          </Row>
        </>
      )}
    </div>
  );
}

function SingleCollectorView({ isCollector }) {
  const period = usePeriodState('monthly');
  const [collectorId, setCollectorId] = useState();

  const params = { period_type: period.periodType, period_start: period.periodStart.format('YYYY-MM-DD') };
  const enabled = isCollector || Boolean(collectorId);

  const performance = useQuery({
    queryKey: ['collector-performance', isCollector ? 'me' : collectorId, params],
    queryFn: () => (isCollector
      ? api.collectorPerformance.mine(params)
      : api.collectorPerformance.forCollector({ ...params, collector_id: collectorId })),
    enabled,
  });

  const data = performance.data?.data;
  // Backend (achievementFor) selalu mengirim target_amount angka (0 bila tanpa target, demi Flutter);
  // keberadaan target dibaca dari target_id. achievement_percent_raw = null bila tanpa target.
  const hasTarget = Boolean(data?.target_id) || (data?.target_id === undefined && Number(data?.target_amount) > 0);
  const achievement = hasTarget
    ? (data?.achievement_percent_raw !== undefined ? data.achievement_percent_raw : data?.achievement_percent)
    : null;
  const achievementStatus = isBlank(achievement) ? undefined : (Number(achievement) >= 100 ? 'good' : (Number(achievement) >= 70 ? undefined : 'warning'));
  const visitPercent = data?.visit_achievement_percent
    ?? (data?.target_visit_count ? (Number(data.visit_count || 0) / Number(data.target_visit_count)) * 100 : null);
  const periodLabel = data?.period_start
    ? periodRangeLabel({ start: data.period_start, end: data.period_end }, period.periodType, period.periodStart)
    : describePeriod(period.periodType, period.periodStart);

  let body;
  if (!enabled) {
    body = <Alert type="info" showIcon title="Pilih kolektor terlebih dahulu untuk melihat performanya." />;
  } else if (performance.isError) {
    body = <ErrorState error={performance.error} onRetry={() => performance.refetch()} />;
  } else {
    body = (
      <div className="stack">
        <Typography.Text type="secondary">Periode {periodLabel}</Typography.Text>
        <Row gutter={[16, 16]}>
          <Col xs={24} md={8}>
            <StatCard
              title="Terkumpul"
              value={data?.collected_amount}
              format="currency"
              loading={performance.isLoading}
              footer={<Typography.Text type="secondary">Target {hasTarget ? formatCurrency(data.target_amount) : 'belum ditetapkan'}</Typography.Text>}
            />
          </Col>
          <Col xs={24} md={8}>
            <StatCard
              title="Pencapaian Target"
              value={achievement}
              format="percent"
              loading={performance.isLoading}
              progress={isBlank(achievement) ? null : achievement}
              status={achievementStatus}
              footer={isBlank(achievement) ? <Typography.Text type="secondary">Belum ada target nominal untuk periode ini.</Typography.Text> : null}
            />
          </Col>
          <Col xs={24} md={8}>
            <StatCard
              title="Jumlah Kunjungan"
              value={data?.visit_count}
              format="number"
              loading={performance.isLoading}
              progress={isBlank(visitPercent) ? null : visitPercent}
              footer={<Typography.Text type="secondary">Target kunjungan {isBlank(data?.target_visit_count) ? '-' : formatMetric(data.target_visit_count, 'number')}</Typography.Text>}
            />
          </Col>
          {!isBlank(data?.collection_rate) ? (
            <Col xs={24} md={8}>
              <StatCard title="Collection Rate" value={data.collection_rate} format="percent" hint="Perkiraan: tertagih ÷ (tertagih + tunggakan saat ini)." />
            </Col>
          ) : null}
          {!isBlank(data?.successful_visit_rate) ? (
            <Col xs={24} md={8}>
              <StatCard title="Kunjungan Berhasil" value={data.successful_visit_rate} format="percent" />
            </Col>
          ) : null}
          {!isBlank(data?.ptp_fulfilled) ? (
            <Col xs={24} md={8}>
              <StatCard
                title="PTP Terpenuhi"
                value={data.ptp_fulfilled}
                format="number"
                footer={!isBlank(data?.ptp_fulfillment_rate) ? <Typography.Text type="secondary">Fulfillment {formatMetric(data.ptp_fulfillment_rate, 'percent')}</Typography.Text> : null}
              />
            </Col>
          ) : null}
          {!isBlank(data?.assigned_accounts) ? (
            <Col xs={24} md={8}>
              <StatCard
                title="Akun Ditangani"
                value={data.assigned_accounts}
                format="number"
                progress={isBlank(data?.account_achievement_percent) ? null : data.account_achievement_percent}
                hint="Jumlah akun penagihan yang saat ini menjadi tanggung jawab kolektor (bukan per periode)."
                footer={(
                  <Typography.Text type="secondary">
                    Tunggakan saat ini {formatCurrency(data.outstanding_total || 0)}
                    {!isBlank(data?.overdue_accounts) ? ` · ${formatMetric(data.overdue_accounts, 'number')} menunggak` : ''}
                  </Typography.Text>
                )}
              />
            </Col>
          ) : null}
        </Row>
      </div>
    );
  }

  return (
    <div className="stack">
      <FilterBar>
        {!isCollector ? (
          <CollectorSelect includeInactive value={collectorId} onChange={setCollectorId} className="filter-input" />
        ) : null}
        <PeriodControls period={period} />
      </FilterBar>
      {body}
    </div>
  );
}

export default function CollectorPerformancePage() {
  const { roles, can } = useAuth();
  const location = useLocation();
  // Mode "milik sendiri": rute /collector/performance, atau akun yang hanya ber-role collector
  // (backend menolak collector melihat performa collector lain). User multi-role (mis.
  // supervisor + collector) di /admin/collectors/performance tetap melihat tampilan admin.
  const isCollector = location.pathname.startsWith('/collector/')
    || (roles.length > 0 && roles.every((role) => role === 'collector'));
  const showRanking = !isCollector && can('collector-monitoring.view');
  const queryClient = useQueryClient();
  const queryKey = showRanking ? ['collection', 'performance'] : ['collector-performance'];
  const fetchingCount = useIsFetching({ queryKey });

  return (
    <section>
      <PageHeader
        title={isCollector ? 'Target & Performa Saya' : 'Performa Kolektor'}
        subtitle={showRanking
          ? 'Bandingkan hasil penagihan antar kolektor dan lihat peringkat berdasarkan metrik pilihan.'
          : 'Bandingkan hasil penagihan kolektor terhadap target yang ditetapkan.'}
        breadcrumbs={isCollector ? [{ label: 'Target & Performa' }] : [{ label: 'Manajemen Kolektor' }, { label: 'Performa' }]}
        onRefresh={() => queryClient.invalidateQueries({ queryKey })}
        loading={fetchingCount > 0}
      />
      {showRanking ? <RankingView /> : <SingleCollectorView isCollector={isCollector} />}
    </section>
  );
}
