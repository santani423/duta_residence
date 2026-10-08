import {
  AlertOutlined,
  ClockCircleOutlined,
  FieldTimeOutlined,
  PhoneOutlined,
  TeamOutlined,
  WalletOutlined,
} from '@ant-design/icons';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import {
  Button,
  Card,
  Col,
  Empty,
  Grid,
  Input,
  Pagination,
  Row,
  Segmented,
  Select,
  Skeleton,
  Space,
  Table,
  Tag,
  Typography,
  theme,
} from 'antd';
import { useMemo, useState } from 'react';
import { Bar, BarChart, CartesianGrid, Cell, Tooltip, XAxis, YAxis } from 'recharts';
import ChartCard from '../../components/collection/ChartCard.jsx';
import CollectorSelect from '../../components/collection/CollectorSelect.jsx';
import StatCard from '../../components/collection/StatCard.jsx';
import { useChartPalette } from '../../components/collection/useChartPalette.js';
import { collectorOptionLabel, useCollectorOptions } from '../../components/collection/useCollectorOptions.js';
import { EmptyData, ErrorState } from '../../components/common/ApiState.jsx';
import FilterBar from '../../components/common/FilterBar.jsx';
import MoneyInput from '../../components/common/MoneyInput.jsx';
import PageHeader from '../../components/common/PageHeader.jsx';
import StatusBadge, { statusOptions } from '../../components/common/StatusBadge.jsx';
import { UnitFilterFields } from '../../components/common/UnitFilters.jsx';
import ResponsiveTable from '../../components/tables/ResponsiveTable.jsx';
import { useDebounce } from '../../hooks/useDebounce.js';
import { useTableState } from '../../hooks/useTableState.js';
import { api } from '../../services/estateApi.js';
import { formatCurrency, formatDate, formatDateTime } from '../../utils/format.js';

const UNASSIGNED = '__unassigned';
const EMPTY_TEXT = 'Tidak ada akun yang cocok dengan filter.';
const DEFAULT_SORT = { field: 'priority_score', direction: 'desc' };

// Kolom yang boleh di-sort server (GET /collection/accounts?sort=...).
const SORT_OPTIONS = [
  { value: 'priority_score', label: 'Prioritas' },
  { value: 'outstanding_total', label: 'Tunggakan' },
  { value: 'aging_days', label: 'Umur tunggakan' },
  { value: 'oldest_due_date', label: 'Jatuh tempo tertua' },
  { value: 'last_contact_at', label: 'Kontak terakhir' },
  { value: 'next_follow_up_at', label: 'Tindak lanjut berikut' },
  { value: 'unit_id', label: 'Unit' },
];

const PRIORITY_ORDER = ['critical', 'high', 'medium', 'normal'];
const STATUS_ORDER = ['overdue', 'escalated', 'disputed', 'promise_to_pay', 'partially_paid', 'due_today', 'due_soon', 'current', 'paid'];
const OVERDUE_BUCKETS = ['1_30', '31_60', '61_90', '91_180', '180_plus'];

const CONTACT_RESULT_LABELS = {
  not_home: 'Tidak di rumah',
  refused: 'Menolak',
  address_not_found: 'Alamat tidak ditemukan',
  no_answer: 'Tidak menjawab',
  promise_to_pay: 'Janji bayar',
  paid: 'Bayar',
  partial_payment: 'Bayar sebagian',
  met: 'Bertemu',
  delivered: 'Terkirim',
  read: 'Dibaca',
  failed: 'Gagal',
};

const numberFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

function formatContactResult(value) {
  if (!value) return null;
  if (CONTACT_RESULT_LABELS[value]) return CONTACT_RESULT_LABELS[value];
  const text = String(value).replace(/_/g, ' ');
  return text.charAt(0).toUpperCase() + text.slice(1);
}

function unitLabel(unit) {
  if (!unit) return '-';
  const parts = [unit.cluster_name || unit.cluster_id, unit.block, unit.lot_number].filter((part) => part !== null && part !== undefined && part !== '');
  return parts.length ? parts.join(' · ') : unit.id;
}

function compactCurrency(value) {
  const number = Number(value) || 0;
  if (Math.abs(number) >= 1e9) return `${numberFormatter.format(number / 1e9)} M`;
  if (Math.abs(number) >= 1e6) return `${numberFormatter.format(number / 1e6)} jt`;
  if (Math.abs(number) >= 1e3) return `${numberFormatter.format(number / 1e3)} rb`;
  return numberFormatter.format(number);
}

function toggleValue(list, value) {
  const current = Array.isArray(list) ? list : [];
  return current.includes(value) ? current.filter((item) => item !== value) : [...current, value];
}

function nonEmptyArray(value) {
  return Array.isArray(value) && value.length ? value : undefined;
}

function AgingTooltip({ active, payload, palette }) {
  if (!active || !payload?.length) return null;
  const row = payload[0].payload;
  return (
    <div style={{ ...palette.tooltip.contentStyle, padding: '8px 12px' }}>
      <div style={palette.tooltip.labelStyle}>{row.label}</div>
      <div style={palette.tooltip.itemStyle}>Tunggakan: {formatCurrency(row.outstanding_amount)}</div>
      <div style={palette.tooltip.itemStyle}>Akun: {numberFormatter.format(row.account_count)}</div>
      <div style={palette.tooltip.itemStyle}>Porsi: {row.percentage ?? 0}%</div>
    </div>
  );
}

function DistributionChips({ title, type, order, counts, selected, onToggle }) {
  const { token } = theme.useToken();
  const labels = Object.fromEntries(statusOptions(type).map((option) => [option.value, option.label]));
  const entries = order
    .concat(Object.keys(counts || {}).filter((key) => !order.includes(key)))
    .map((key) => [key, Number(counts?.[key] || 0)])
    .filter(([key, count]) => count > 0 || selected.includes(key));

  return (
    <div>
      <Typography.Text type="secondary" style={{ display: 'block', marginBottom: 8 }}>{title}</Typography.Text>
      {entries.length ? (
        <Space size={[8, 8]} wrap>
          {entries.map(([key, count]) => (
            <Tag.CheckableTag
              key={key}
              checked={selected.includes(key)}
              onChange={() => onToggle(key)}
              style={{ border: `1px solid ${token.colorBorder}`, padding: '2px 8px' }}
            >
              {labels[key] || key} <strong>{numberFormatter.format(count)}</strong>
            </Tag.CheckableTag>
          ))}
        </Space>
      ) : (
        <Typography.Text type="secondary">Belum ada data.</Typography.Text>
      )}
    </div>
  );
}

function AccountCard({ account }) {
  const { token } = theme.useToken();
  return (
    <Card size="small">
      <Space orientation="vertical" size={6} style={{ width: '100%' }}>
        <Space style={{ width: '100%', justifyContent: 'space-between' }} align="start">
          <div>
            <Typography.Text strong>{unitLabel(account.unit)}</Typography.Text>
            <div><Typography.Text type="secondary" style={{ fontSize: token.fontSizeSM }}>{account.unit?.id}</Typography.Text></div>
          </div>
          <StatusBadge type="priorityLevel" value={account.priority_level} />
        </Space>
        <div>
          <Typography.Text>{account.customer?.name || 'Tanpa customer'}</Typography.Text>
          {account.customer?.phone ? (
            <div>
              <Typography.Link href={`tel:${account.customer.phone}`} style={{ fontSize: token.fontSizeSM }}>
                <PhoneOutlined /> {account.customer.phone}
              </Typography.Link>
            </div>
          ) : null}
        </div>
        <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
          <Typography.Text strong>{formatCurrency(account.outstanding_total)}</Typography.Text>
          <StatusBadge type="collectionAccount" value={account.status} />
        </Space>
        <Space size={[8, 4]} wrap>
          <Typography.Text type="secondary" style={{ fontSize: token.fontSizeSM }}>
            Umur {numberFormatter.format(account.aging_days || 0)} hari
          </Typography.Text>
          <StatusBadge type="agingBucket" value={account.aging_bucket} />
        </Space>
        <Typography.Text type="secondary" style={{ fontSize: token.fontSizeSM }}>
          Kolektor: {account.collector?.name || 'Belum ditugaskan'}
        </Typography.Text>
        <Typography.Text type="secondary" style={{ fontSize: token.fontSizeSM }}>
          Kontak terakhir: {formatDateTime(account.last_contact_at)}
          {account.last_contact_result ? ` (${formatContactResult(account.last_contact_result)})` : ''}
        </Typography.Text>
        <Typography.Text type="secondary" style={{ fontSize: token.fontSizeSM }}>
          Tindak lanjut: {formatDate(account.next_follow_up_at)}
        </Typography.Text>
      </Space>
    </Card>
  );
}

export default function CollectionMonitoringPage() {
  const screens = Grid.useBreakpoint();
  const isMobile = Object.keys(screens).length > 0 && !screens.md;
  const { token } = theme.useToken();
  const palette = useChartPalette();
  const table = useTableState({});
  const [sort, setSort] = useState(DEFAULT_SORT);
  const [range, setRange] = useState({ min: undefined, max: undefined });
  const debouncedRange = useDebounce(range, 500);
  const { collectors } = useCollectorOptions({ includeInactive: true });

  const { filters } = table;
  const params = useMemo(() => {
    const { collector, ...rest } = table.params;
    const min = debouncedRange.min ?? undefined;
    const max = debouncedRange.max ?? undefined;
    return {
      ...rest,
      collector_id: collector && collector !== UNASSIGNED ? collector : undefined,
      unassigned: collector === UNASSIGNED ? 1 : undefined,
      status: nonEmptyArray(rest.status),
      aging_bucket: nonEmptyArray(rest.aging_bucket),
      priority: nonEmptyArray(rest.priority),
      min_outstanding: min === null || min === '' ? undefined : min,
      max_outstanding: max === null || max === '' ? undefined : max,
      sort: sort.field,
      direction: sort.direction,
    };
  }, [table.params, debouncedRange, sort]);

  const list = useQuery({
    queryKey: ['collection-accounts', params],
    queryFn: () => api.collection.accounts(params),
    placeholderData: keepPreviousData,
  });

  const meta = list.data?.meta;
  const summary = meta?.summary ?? list.data?.summary;
  const aging = useMemo(() => {
    const rows = meta?.aging ?? list.data?.aging;
    return Array.isArray(rows) ? rows : [];
  }, [meta, list.data]);
  const items = Array.isArray(list.data?.data) ? list.data.data : [];
  const hasSnapshot = Boolean(list.data);

  const overdueAccounts = summary?.overdue_accounts
    ?? aging.filter((row) => row.bucket !== 'current').reduce((sum, row) => sum + Number(row.account_count || 0), 0);
  const criticalAccounts = summary?.critical_accounts ?? summary?.by_priority?.critical ?? 0;
  const agingEmpty = !aging.some((row) => Number(row.outstanding_amount) > 0);

  const selectedStatus = Array.isArray(filters.status) ? filters.status : [];
  const selectedPriority = Array.isArray(filters.priority) ? filters.priority : [];
  const selectedAging = Array.isArray(filters.aging_bucket) ? filters.aging_bucket : [];

  const collectorOptions = useMemo(() => [
    { value: UNASSIGNED, label: 'Belum ditugaskan' },
    ...collectors.map((collector) => ({ value: collector.id, label: collectorOptionLabel(collector), collector })),
  ], [collectors]);

  const hasActiveFilters = Boolean(
    table.search
    || (range.min !== undefined && range.min !== null)
    || (range.max !== undefined && range.max !== null)
    || Object.values(filters).some((value) => (Array.isArray(value) ? value.length > 0 : value !== undefined && value !== null && value !== '')),
  );

  function patchFilters(patch) {
    table.setFilters({ ...filters, ...patch });
  }

  function updateRange(patch) {
    setRange((current) => ({ ...current, ...patch }));
    table.setPagination((current) => ({ ...current, current: 1 }));
  }

  function resetFilters() {
    table.setFilters({});
    table.setSearch('');
    setRange({ min: undefined, max: undefined });
    setSort(DEFAULT_SORT);
  }

  function changeSort(field, direction) {
    setSort(field ? { field, direction: direction || 'desc' } : DEFAULT_SORT);
    table.setPagination((current) => ({ ...current, current: 1 }));
  }

  function handleTableChange(pagination, _tableFilters, sorter, extra) {
    if (extra?.action === 'sort') {
      const active = Array.isArray(sorter) ? sorter[0] : sorter;
      const field = active?.order ? active.columnKey : null;
      changeSort(field, active?.order === 'ascend' ? 'asc' : 'desc');
      return;
    }
    table.handleTableChange(pagination);
  }

  const sortOrderFor = (key) => (sort.field === key ? (sort.direction === 'asc' ? 'ascend' : 'descend') : null);
  const sortable = (key) => ({ key, sorter: true, sortOrder: sortOrderFor(key), sortDirections: ['descend', 'ascend'] });

  const columns = [
    {
      title: 'Prioritas',
      ...sortable('priority_score'),
      width: 120,
      render: (_, row) => (
        <Space orientation="vertical" size={0}>
          <StatusBadge type="priorityLevel" value={row.priority_level} />
          {row.priority_score !== null && row.priority_score !== undefined ? (
            <Typography.Text type="secondary" style={{ fontSize: token.fontSizeSM }}>Skor {numberFormatter.format(row.priority_score)}</Typography.Text>
          ) : null}
        </Space>
      ),
    },
    {
      title: 'Unit',
      ...sortable('unit_id'),
      width: 180,
      render: (_, row) => (
        <Space orientation="vertical" size={0}>
          <Typography.Text strong>{unitLabel(row.unit)}</Typography.Text>
          <Typography.Text type="secondary" style={{ fontSize: token.fontSizeSM }}>{row.unit?.id}</Typography.Text>
        </Space>
      ),
    },
    {
      title: 'Customer',
      key: 'customer',
      width: 180,
      render: (_, row) => (row.customer ? (
        <Space orientation="vertical" size={0}>
          <Typography.Text>{row.customer.name}</Typography.Text>
          {row.customer.phone ? (
            <Typography.Link href={`tel:${row.customer.phone}`} style={{ fontSize: token.fontSizeSM }}>{row.customer.phone}</Typography.Link>
          ) : null}
        </Space>
      ) : <Typography.Text type="secondary">-</Typography.Text>),
    },
    {
      title: 'Kolektor',
      key: 'collector',
      width: 150,
      render: (_, row) => (row.collector?.name
        ? row.collector.name
        : <Tag>Belum ditugaskan</Tag>),
    },
    {
      title: 'Tunggakan',
      ...sortable('outstanding_total'),
      width: 150,
      align: 'right',
      render: (_, row) => (
        <Space orientation="vertical" size={0} style={{ alignItems: 'flex-end' }}>
          <Typography.Text strong>{formatCurrency(row.outstanding_total)}</Typography.Text>
          {row.open_invoice_count ? (
            <Typography.Text type="secondary" style={{ fontSize: token.fontSizeSM }}>{row.open_invoice_count} tagihan</Typography.Text>
          ) : null}
        </Space>
      ),
    },
    {
      title: 'Jatuh Tempo Tertua',
      ...sortable('oldest_due_date'),
      width: 140,
      render: (_, row) => formatDate(row.oldest_due_date),
    },
    {
      title: 'Umur',
      ...sortable('aging_days'),
      width: 150,
      render: (_, row) => (
        <Space orientation="vertical" size={2}>
          <Typography.Text>{numberFormatter.format(row.aging_days || 0)} hari</Typography.Text>
          <StatusBadge type="agingBucket" value={row.aging_bucket} />
        </Space>
      ),
    },
    {
      title: 'Kontak Terakhir',
      ...sortable('last_contact_at'),
      width: 170,
      render: (_, row) => (row.last_contact_at ? (
        <Space orientation="vertical" size={0}>
          <Typography.Text>{formatDateTime(row.last_contact_at)}</Typography.Text>
          {row.last_contact_result ? (
            <Typography.Text type="secondary" style={{ fontSize: token.fontSizeSM }}>{formatContactResult(row.last_contact_result)}</Typography.Text>
          ) : null}
        </Space>
      ) : <Typography.Text type="secondary">Belum pernah</Typography.Text>),
    },
    {
      title: 'Tindak Lanjut',
      ...sortable('next_follow_up_at'),
      width: 140,
      render: (_, row) => formatDate(row.next_follow_up_at),
    },
    {
      title: 'Status',
      key: 'status',
      width: 160,
      render: (_, row) => <StatusBadge type="collectionAccount" value={row.status} />,
    },
  ];

  const agingTable = (
    <Table
      size="small"
      rowKey="bucket"
      pagination={false}
      dataSource={aging}
      scroll={{ x: 480 }}
      columns={[
        { title: 'Umur', dataIndex: 'label', render: (label, row) => <StatusBadge type="agingBucket" value={row.bucket}>{label}</StatusBadge> },
        { title: 'Akun', dataIndex: 'account_count', align: 'right', render: (value) => numberFormatter.format(value || 0) },
        { title: 'Tunggakan', dataIndex: 'outstanding_amount', align: 'right', render: formatCurrency },
        { title: 'Porsi', dataIndex: 'percentage', align: 'right', render: (value) => `${value ?? 0}%` },
      ]}
    />
  );

  const showOverview = !(list.isError && !hasSnapshot);

  return (
    <section>
      <PageHeader
        title="Monitoring Penagihan"
        subtitle="Pantau akun penagihan, tunggakan, dan prioritas tindak lanjut lintas kolektor."
        breadcrumbs={[{ label: 'Manajemen Kolektor' }, { label: 'Monitoring Penagihan' }]}
        onRefresh={() => list.refetch()}
        loading={list.isFetching}
      />

      <FilterBar
        extra={hasActiveFilters ? <Button onClick={resetFilters}>Reset filter</Button> : null}
      >
        <Input
          allowClear
          placeholder="Cari unit / customer / telepon"
          value={table.search}
          onChange={(event) => table.setSearch(event.target.value)}
          className="filter-input"
        />
        <UnitFilterFields value={filters} onChange={table.setFilters} />
        <CollectorSelect
          includeInactive
          placeholder="Kolektor"
          value={filters.collector}
          onChange={(value) => patchFilters({ collector: value ?? undefined })}
          options={collectorOptions}
          className="filter-input"
        />
        <Select
          mode="multiple"
          allowClear
          maxTagCount="responsive"
          placeholder="Status akun"
          options={statusOptions('collectionAccount')}
          value={selectedStatus}
          onChange={(value) => patchFilters({ status: value })}
          className="filter-input"
        />
        <Select
          mode="multiple"
          allowClear
          maxTagCount="responsive"
          placeholder="Umur tunggakan"
          options={statusOptions('agingBucket')}
          value={selectedAging}
          onChange={(value) => patchFilters({ aging_bucket: value })}
          className="filter-input"
        />
        <Select
          mode="multiple"
          allowClear
          maxTagCount="responsive"
          placeholder="Prioritas"
          options={statusOptions('priorityLevel')}
          value={selectedPriority}
          onChange={(value) => patchFilters({ priority: value })}
          className="filter-input"
        />
        <div className="filter-input">
          <MoneyInput
            placeholder="Tunggakan min."
            value={range.min}
            max={range.max ?? undefined}
            step={100000}
            onChange={(value) => updateRange({ min: value ?? undefined })}
            aria-label="Tunggakan minimum"
          />
        </div>
        <div className="filter-input">
          <MoneyInput
            placeholder="Tunggakan maks."
            value={range.max}
            min={range.min ?? 0}
            step={100000}
            onChange={(value) => updateRange({ max: value ?? undefined })}
            aria-label="Tunggakan maksimum"
          />
        </div>
      </FilterBar>

      {showOverview ? (
        <div className="stack" style={{ marginBottom: 16 }}>
          <Row gutter={[16, 16]}>
            <Col xs={12} lg={6}>
              <StatCard
                title="Jumlah Akun"
                icon={<TeamOutlined />}
                value={summary?.account_count ?? 0}
                format="number"
                loading={list.isLoading}
                hint="Akun yang cocok dengan filter saat ini."
              />
            </Col>
            <Col xs={12} lg={6}>
              <StatCard
                title="Total Tunggakan"
                icon={<WalletOutlined />}
                value={summary?.outstanding_total ?? 0}
                format="currency"
                loading={list.isLoading}
                hint="Pokok + denda dari akun yang cocok dengan filter."
              />
            </Col>
            <Col xs={12} lg={6}>
              <StatCard
                title="Akun Kritis"
                icon={<AlertOutlined />}
                value={criticalAccounts}
                format="number"
                loading={list.isLoading}
                hint="Klik untuk menampilkan hanya akun berprioritas kritis."
                onClick={() => patchFilters({ priority: ['critical'] })}
              />
            </Col>
            <Col xs={12} lg={6}>
              <StatCard
                title="Akun Menunggak"
                icon={<ClockCircleOutlined />}
                value={overdueAccounts}
                format="number"
                loading={list.isLoading}
                hint="Akun dengan tunggakan yang sudah lewat jatuh tempo (umur > 0 hari). Klik untuk memfilter."
                onClick={() => patchFilters({ aging_bucket: OVERDUE_BUCKETS })}
              />
            </Col>
          </Row>

          <Row gutter={[16, 16]}>
            <Col xs={24} lg={14}>
              <ChartCard
                title="Umur Tunggakan"
                loading={list.isLoading}
                empty={!list.isLoading && agingEmpty}
                emptyText="Tidak ada tunggakan pada filter ini."
                table={agingTable}
                footer={(
                  <Typography.Text type="secondary" style={{ fontSize: token.fontSizeSM }}>
                    Klik batang untuk memfilter berdasarkan umur tunggakan.
                  </Typography.Text>
                )}
              >
                <BarChart data={aging} margin={{ top: 8, right: 8, left: 8, bottom: 0 }}>
                  <CartesianGrid vertical={false} stroke={palette.grid} />
                  <XAxis dataKey="label" tick={palette.axisTick} tickLine={false} axisLine={{ stroke: palette.grid }} interval={0} />
                  <YAxis tick={palette.axisTick} tickLine={false} axisLine={false} tickFormatter={compactCurrency} width={56} />
                  <Tooltip cursor={palette.tooltip.cursor} content={<AgingTooltip palette={palette} />} />
                  <Bar
                    dataKey="outstanding_amount"
                    name="Tunggakan"
                    radius={[4, 4, 0, 0]}
                    maxBarSize={48}
                    style={{ cursor: 'pointer' }}
                    onClick={(entry) => {
                      const bucket = entry?.bucket ?? entry?.payload?.bucket;
                      if (bucket) patchFilters({ aging_bucket: toggleValue(selectedAging, bucket) });
                    }}
                  >
                    {aging.map((row) => (
                      <Cell
                        key={row.bucket}
                        fill={palette.aging[row.bucket] || palette.series[0]}
                        fillOpacity={selectedAging.length && !selectedAging.includes(row.bucket) ? 0.35 : 1}
                      />
                    ))}
                  </Bar>
                </BarChart>
              </ChartCard>
            </Col>
            <Col xs={24} lg={10}>
              <Card title="Distribusi Akun" loading={list.isLoading} style={{ height: '100%' }}>
                <div className="stack">
                  <DistributionChips
                    title="Prioritas (klik untuk filter)"
                    type="priorityLevel"
                    order={PRIORITY_ORDER}
                    counts={summary?.by_priority}
                    selected={selectedPriority}
                    onToggle={(key) => patchFilters({ priority: toggleValue(selectedPriority, key) })}
                  />
                  <DistributionChips
                    title="Status (klik untuk filter)"
                    type="collectionAccount"
                    order={STATUS_ORDER}
                    counts={summary?.by_status}
                    selected={selectedStatus}
                    onToggle={(key) => patchFilters({ status: toggleValue(selectedStatus, key) })}
                  />
                </div>
              </Card>
            </Col>
          </Row>
        </div>
      ) : null}

      {isMobile ? (
        <div className="stack">
          <Space wrap>
            <Select
              value={sort.field}
              options={SORT_OPTIONS}
              onChange={(field) => changeSort(field, sort.direction)}
              style={{ minWidth: 180 }}
              aria-label="Urutkan berdasarkan"
            />
            <Segmented
              value={sort.direction}
              onChange={(direction) => changeSort(sort.field, direction)}
              options={[{ value: 'desc', label: 'Terbesar' }, { value: 'asc', label: 'Terkecil' }]}
            />
          </Space>
          {list.isError ? <ErrorState error={list.error} onRetry={() => list.refetch()} /> : null}
          {list.isLoading ? (
            [0, 1, 2].map((key) => <Card key={key} size="small"><Skeleton active paragraph={{ rows: 3 }} /></Card>)
          ) : null}
          {!list.isLoading && !list.isError && items.length === 0 ? (
            <Card><Empty description={EMPTY_TEXT} /></Card>
          ) : null}
          {items.map((account) => <AccountCard key={account.unit?.id ?? account.unit_id} account={account} />)}
          {meta?.total ? (
            <Pagination
              size="small"
              align="center"
              current={meta.current_page}
              pageSize={meta.per_page}
              total={meta.total}
              showSizeChanger={false}
              showTotal={(total) => `${total} akun`}
              onChange={(current, pageSize) => table.handleTableChange({ current, pageSize })}
            />
          ) : null}
          {list.isFetching && !list.isLoading ? (
            <Typography.Text type="secondary" style={{ textAlign: 'center' }}>
              <FieldTimeOutlined /> Memperbarui…
            </Typography.Text>
          ) : null}
        </div>
      ) : (
        <ResponsiveTable
          query={list}
          columns={columns}
          rowKey={(row) => row.unit?.id ?? row.unit_id}
          scrollX={1600}
          onChange={handleTableChange}
          locale={{
            emptyText: list.isError
              ? <ErrorState error={list.error} onRetry={() => list.refetch()} />
              : <EmptyData description={EMPTY_TEXT} />,
          }}
        />
      )}
    </section>
  );
}
