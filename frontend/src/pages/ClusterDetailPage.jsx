import { Button, Card, Col, DatePicker, Descriptions, Drawer, Dropdown, Empty, Form, Input, Modal, Row, Select, Space, Statistic, Switch, Tag, message } from 'antd';
import { DeleteOutlined, EditOutlined, EnvironmentOutlined, MoreOutlined, PlusOutlined, PrinterOutlined } from '@ant-design/icons';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import dayjs from 'dayjs';
import { CartesianGrid, Cell, Legend, Line, LineChart, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import PageHeader from '../components/common/PageHeader.jsx';
import DashboardRangePicker from '../components/common/DashboardRangePicker.jsx';
import Can from '../components/common/Can.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { ErrorState, LoadingState } from '../components/common/ApiState.jsx';
import { api } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { formatCurrency, formatDate, formatDateTime, formatPeriod } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';
import StatusBadge from '../components/common/StatusBadge.jsx';
import { propertyTypeOptions, residentStatusOptions, unitOccupancyStatusOptions } from '../components/forms/UnitForm.jsx';
import MoneyInput from '../components/common/MoneyInput.jsx';

const DONUT_COLORS = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100'];
const MONTHLY_RATE_MONTHS_PAST = 6;
const MONTHLY_RATE_MONTHS_FUTURE = 6;
const INCOME_LINE_COLOR = '#2563eb';
const INCOME_MAX_RANGE_MONTHS = 60; // harus sama dengan ClusterController::MAX_INCOME_RANGE_MONTHS
// Palet grafik penghasilan: layar memakai warna semula; cetak memakai garis lebih tebal dan
// kontras tinggi (latar putih, label & sumbu gelap) agar terbaca jelas di kertas.
const SCREEN_CHART_PALETTE = { line: INCOME_LINE_COLOR, lineWidth: 2, dot: { r: 4 }, grid: '#ccc', axis: '#666', tick: '#666' };
const PRINT_CHART_PALETTE = {
  line: '#1e40af',
  lineWidth: 2.5,
  dot: { r: 4, fill: '#ffffff', stroke: '#1e40af', strokeWidth: 2 },
  grid: '#9ca3af',
  axis: '#111827',
  tick: '#111827',
};
// Ukuran tetap saat cetak agar grafik tidak terpotong oleh lebar layar (A4 portrait).
const PRINT_CHART_WIDTH = 640;
const PRINT_CHART_HEIGHT = 300;
const ID_MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

function shortMonthLabel(month) {
  const [year, monthNumber] = String(month).split('-').map(Number);
  return `${ID_MONTHS_SHORT[(monthNumber || 1) - 1]} ${year}`;
}

function IncomeTooltip({ active, payload }) {
  if (!active || !payload?.length) return null;
  const row = payload[0].payload;
  return (
    <div className="income-chart-tooltip">
      <div className="income-chart-tooltip__label">{row.label}</div>
      <div className="income-chart-tooltip__value">{formatCurrency(row.income)}</div>
    </div>
  );
}

// Default: 12 bulan terakhir (dari awal bulan 11 bulan lalu sampai hari ini).
function defaultIncomeRange() {
  const today = dayjs().startOf('day');
  return [today.subtract(11, 'month').startOf('month'), today];
}

function incomeRangePresets() {
  const today = dayjs().startOf('day');
  const lastYear = today.subtract(1, 'year');
  return [
    { label: '12 bulan terakhir', value: defaultIncomeRange() },
    { label: 'Tahun ini', value: [today.startOf('year'), today] },
    { label: 'Tahun lalu', value: [lastYear.startOf('year'), lastYear.endOf('year').startOf('day')] },
  ];
}

function ClusterIncomeChart({ query, range, onRangeChange, clusterName, printing, onPrint }) {
  const payload = query.data?.data;
  const monthlyIncome = (payload?.monthly_income || []).map((row) => ({ ...row, shortLabel: shortMonthLabel(row.month) }));
  const hasIncome = monthlyIncome.some((row) => Number(row.income) > 0);
  const palette = printing ? PRINT_CHART_PALETTE : SCREEN_CHART_PALETTE;
  const periodLabel = `${formatDate(range[0])} s/d ${formatDate(range[1])}`;

  // Definisi grafik yang sama untuk layar (ikut lebar kontainer) dan cetak (ukuran tetap).
  const lineChart = (size) => (
    <LineChart data={monthlyIncome} margin={{ top: 8, right: 16, left: 8, bottom: 24 }} {...size}>
      <CartesianGrid strokeDasharray="3 3" vertical={false} stroke={palette.grid} />
      <XAxis dataKey="shortLabel" tick={{ fontSize: 12, fill: palette.tick }} stroke={palette.axis} angle={-35} textAnchor="end" height={56} />
      <YAxis tickFormatter={(value) => `${(Number(value) / 1000000).toLocaleString('id-ID')} jt`} width={70} tick={{ fontSize: 12, fill: palette.tick }} stroke={palette.axis} />
      <Tooltip content={<IncomeTooltip />} />
      <Line
        type="monotone"
        dataKey="income"
        name="Penghasilan"
        stroke={palette.line}
        strokeWidth={palette.lineWidth}
        dot={palette.dot}
        activeDot={{ r: 6 }}
        isAnimationActive={!printing}
      />
    </LineChart>
  );

  return (
    <Card
      className="section-row print-income"
      title="Penghasilan Cluster"
      loading={query.isLoading}
      extra={(
        <Space wrap className="no-print">
          <DashboardRangePicker value={range} onChange={onRangeChange} presets={incomeRangePresets()} />
          <Button icon={<PrinterOutlined />} onClick={onPrint}>Cetak</Button>
        </Space>
      )}
    >
      <div className="print-only income-print-meta">{clusterName} · dicetak {formatDate(dayjs())}</div>
      {query.isError ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        // Saat pindah rentang, data lama ditampilkan redup sampai data rentang baru tiba.
        <div style={{ opacity: query.isPlaceholderData ? 0.5 : 1, transition: 'opacity 0.2s' }}>
          {payload && (
            <div className="income-summary">
              <Statistic title="Total Penghasilan" value={formatCurrency(payload.total_income)} valueStyle={{ fontSize: 18 }} />
              <div className="muted">Periode {periodLabel}</div>
            </div>
          )}
          {!hasIncome ? (
            <Empty description="Belum ada transaksi penghasilan yang tercatat." image={Empty.PRESENTED_IMAGE_SIMPLE} />
          ) : printing ? (
            <div className="income-chart-print">{lineChart({ width: PRINT_CHART_WIDTH, height: PRINT_CHART_HEIGHT })}</div>
          ) : (
            <div className="chart-box">
              <ResponsiveContainer>{lineChart()}</ResponsiveContainer>
            </div>
          )}
        </div>
      )}
    </Card>
  );
}

// Mirrors ClusterRateScheduleService::rateForPeriod on the backend: the rate for a month
// is whichever active schedule has the latest effective_date on/before that month.
function buildMonthlyRateRows(schedules, fallbackRate) {
  const activeSchedules = schedules
    .filter((item) => item.is_active)
    .map((item) => ({ ...item, effective_date: dayjs(item.effective_date) }))
    .sort((a, b) => b.effective_date.valueOf() - a.effective_date.valueOf());

  const startMonth = dayjs().startOf('month').subtract(MONTHLY_RATE_MONTHS_PAST, 'month');
  const totalMonths = MONTHLY_RATE_MONTHS_PAST + MONTHLY_RATE_MONTHS_FUTURE + 1;

  return Array.from({ length: totalMonths }, (_, index) => {
    const month = startMonth.add(index, 'month');
    const applicable = activeSchedules.find((item) => !item.effective_date.isAfter(month, 'month'));
    const rate = applicable ? Number(applicable.rate) : Number(fallbackRate || 0);
    const status = month.isSame(dayjs(), 'month') ? 'current' : month.isBefore(dayjs(), 'month') ? 'past' : 'future';
    return { key: month.format('YYYY-MM'), year: month.year(), month: month.month() + 1, rate, status };
  });
}

function DonutChart({ title, data, loading }) {
  const total = data.reduce((sum, item) => sum + item.value, 0);
  return (
    <Card size="small" type="inner" title={`${title} (${total} unit)`} loading={loading}>
      {total === 0 ? (
        <Empty description="Belum ada data" image={Empty.PRESENTED_IMAGE_SIMPLE} />
      ) : (
        <div className="chart-box">
          <ResponsiveContainer>
            <PieChart>
              <Pie
                data={data}
                dataKey="value"
                nameKey="name"
                outerRadius={95}
                paddingAngle={2}
                cornerRadius={4}
                label={({ percent, value }) => (value > 0 ? `${Math.round(percent * 100)}%` : '')}
              >
                {data.map((entry, index) => (
                  <Cell key={entry.name} fill={DONUT_COLORS[index % DONUT_COLORS.length]} />
                ))}
              </Pie>
              <Tooltip formatter={(value, name) => [`${value} unit`, name]} />
              <Legend verticalAlign="bottom" formatter={(value, entry) => `${value} (${entry.payload.value})`} />
            </PieChart>
          </ResponsiveContainer>
        </div>
      )}
    </Card>
  );
}

export default function ClusterDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [drawer, setDrawer] = useState({ mode: null, record: null });
  const [printTarget, setPrintTarget] = useState(null);
  const [incomeRange, setIncomeRange] = useState(defaultIncomeRange);
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const unitsTable = useTableState();
  const incomeStart = incomeRange[0].format('YYYY-MM-DD');
  const incomeEnd = incomeRange[1].format('YYYY-MM-DD');

  useEffect(() => {
    function handleAfterPrint() {
      setPrintTarget(null);
    }
    window.addEventListener('afterprint', handleAfterPrint);
    return () => window.removeEventListener('afterprint', handleAfterPrint);
  }, []);

  // Cetak satu bagian halaman: hanya kartu target yang tampil (lihat aturan @media print di styles.css).
  function printSection(target) {
    setPrintTarget(target);
    setTimeout(() => window.print(), 50);
  }

  function changeIncomeRange(next) {
    const months = next[1].startOf('month').diff(next[0].startOf('month'), 'month') + 1;
    if (months > INCOME_MAX_RANGE_MONTHS) {
      message.warning(`Rentang maksimal ${INCOME_MAX_RANGE_MONTHS} bulan.`);
      return;
    }
    setIncomeRange(next);
  }

  const detail = useQuery({ queryKey: ['clusters', id], queryFn: () => api.clusters.detail(id) });
  const incomeStatistics = useQuery({
    queryKey: ['clusters', id, 'income-statistics', incomeStart, incomeEnd],
    queryFn: () => api.clusters.incomeStatistics(id, { start: incomeStart, end: incomeEnd }),
    placeholderData: keepPreviousData,
  });
  const schedules = useQuery({ queryKey: ['clusters', id, 'rate-schedules'], queryFn: () => api.clusters.rateSchedules(id) });
  const units = useQuery({
    queryKey: ['clusters', id, 'units', unitsTable.params],
    queryFn: () => api.units.list({ ...unitsTable.params, cluster_id: id, per_page: unitsTable.params.per_page || 20 }),
  });
  const unitsAll = useQuery({
    queryKey: ['clusters', id, 'units-all'],
    queryFn: () => api.units.list({ cluster_id: id, per_page: 1000 }),
  });

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['clusters', id] });
    queryClient.invalidateQueries({ queryKey: ['clusters', id, 'rate-schedules'] });
    queryClient.invalidateQueries({ queryKey: ['clusters'] });
  };

  const save = useMutation({
    mutationFn: (values) => drawer.mode === 'edit'
      ? api.clusters.updateRateSchedule(drawer.record.id, values)
      : api.clusters.createRateSchedule(id, values),
    onSuccess: () => {
      message.success('Jadwal tarif berhasil disimpan');
      setDrawer({ mode: null, record: null });
      form.resetFields();
      invalidate();
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const remove = useMutation({
    mutationFn: (scheduleId) => api.clusters.removeRateSchedule(scheduleId),
    onSuccess: () => {
      message.success('Jadwal tarif berhasil dihapus');
      invalidate();
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  function openCreate() {
    form.resetFields();
    setDrawer({ mode: 'create', record: null });
  }

  function openEdit(record) {
    form.setFieldsValue({ ...record, effective_date: record.effective_date ? dayjs(record.effective_date) : null });
    setDrawer({ mode: 'edit', record });
  }

  function confirmDelete(record) {
    Modal.confirm({
      title: 'Hapus jadwal tarif?',
      content: `Rp${Number(record.rate).toLocaleString('id-ID')} mulai ${formatDate(record.effective_date)}`,
      okText: 'Hapus',
      okButtonProps: { danger: true },
      onOk: () => remove.mutate(record.id),
    });
  }

  if (detail.isLoading) return <LoadingState rows={8} />;
  if (detail.isError) return <ErrorState error={detail.error} onRetry={detail.refetch} />;

  const cluster = detail.data?.data || {};
  const current = cluster.current_rate_schedule;
  const next = cluster.next_rate_schedule;
  const rows = schedules.data?.data || [];
  const monthlyRateRows = buildMonthlyRateRows(rows, cluster.monthly_rate);
  const allUnits = unitsAll.data?.data || [];
  const occupancyData = unitOccupancyStatusOptions.map((option) => ({
    name: option.label,
    value: allUnits.filter((unit) => unit.occupancy_status === option.value).length,
  }));
  const inactiveUnits = allUnits.filter((unit) => unit.status_id === 'TA');
  const inactiveByTypeData = propertyTypeOptions.map((option) => ({
    name: option.label,
    value: inactiveUnits.filter((unit) => unit.property_type_id === option.value).length,
  }));

  return (
    <section className="cluster-detail-page" data-print-target={printTarget ?? undefined}>
      <PageHeader
        title={cluster.name || 'Detail Cluster'}
        subtitle="Detail cluster dan penjadwalan perubahan tarif."
        breadcrumbs={[{ label: 'Cluster', to: '/clusters' }, { label: cluster.name }]}
        onRefresh={() => { detail.refetch(); incomeStatistics.refetch(); schedules.refetch(); unitsAll.refetch(); }}
        loading={detail.isFetching || schedules.isFetching}
        extra={(
          <Space>
            <Can permission="cluster-maps.view">
              <Button icon={<EnvironmentOutlined />} onClick={() => navigate(`/clusters/${id}/map`)}>Peta Cluster</Button>
            </Can>
            <Can permission="clusters.update-rate">
              <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Tambah Jadwal Tarif</Button>
            </Can>
          </Space>
        )}
      />

      <Card>
        <Descriptions bordered column={{ xs: 1, md: 2 }}>
          <Descriptions.Item label="Kode">{cluster.id}</Descriptions.Item>
          <Descriptions.Item label="Nama">{cluster.name}</Descriptions.Item>
          <Descriptions.Item label="Status">{cluster.is_active ? <Tag color="green">Aktif</Tag> : <Tag>Nonaktif</Tag>}</Descriptions.Item>
          <Descriptions.Item label="Jumlah Unit">{cluster.units_count ?? 0}</Descriptions.Item>
          <Descriptions.Item label="Deskripsi" span={2}>{cluster.description || '-'}</Descriptions.Item>
        </Descriptions>
      </Card>

      <Row gutter={[16, 16]} className="section-row">
        <Col xs={24} md={8}>
          <Card>
            <Statistic title="Tarif Aktif Saat Ini" value={formatCurrency(cluster.monthly_rate)} />
            {current ? <div className="muted">Berlaku sejak {formatDate(current.effective_date)}</div> : <div className="muted">Belum ada jadwal tarif.</div>}
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card>
            <Statistic title="Tarif Berikutnya" value={next ? formatCurrency(next.rate) : '-'} />
            <div className="muted">{next ? `Mulai berlaku ${formatDate(next.effective_date)}` : 'Belum ada jadwal berikutnya.'}</div>
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card>
            <Statistic title="Tanggal Perubahan Berikutnya" value={next ? formatDate(next.effective_date) : '-'} />
            <div className="muted">{next?.notes || ' '}</div>
          </Card>
        </Col>
      </Row>

      <ClusterIncomeChart
        query={incomeStatistics}
        range={incomeRange}
        onRangeChange={changeIncomeRange}
        clusterName={cluster.name}
        printing={printTarget === 'income'}
        onPrint={() => printSection('income')}
      />

      <Card className="section-row" title="Riwayat Biaya IPL Bulanan">
        <ResponsiveTable
          data={monthlyRateRows}
          rowKey="key"
          pagination={false}
          scrollX={520}
          rowClassName={(row) => (row.status === 'current' ? 'ipl-current-month-row' : '')}
          columns={[
            { title: 'Bulan', render: (_, row) => formatPeriod(row.year, row.month), width: 200 },
            { title: 'Biaya IPL', dataIndex: 'rate', render: formatCurrency, width: 180 },
            {
              title: 'Status',
              width: 140,
              render: (_, row) => {
                if (row.status === 'current') return <Tag color="green">Bulan Ini</Tag>;
                if (row.status === 'future') return <Tag color="blue">Akan Datang</Tag>;
                return <Tag color="default">Riwayat</Tag>;
              },
            },
          ]}
        />
      </Card>

      <Card
        className="section-row print-area"
        title={`Unit & Penghuni (${units.data?.meta?.total ?? cluster.units_count ?? 0})`}
        extra={<Button icon={<PrinterOutlined />} onClick={() => printSection('units')}>Cetak</Button>}
      >
        <Row gutter={[16, 16]} className="section-row">
          <Col xs={24} md={12}>
            <DonutChart title="Status Unit" data={occupancyData} loading={unitsAll.isLoading} />
          </Col>
          <Col xs={24} md={12}>
            <DonutChart title="Unit Tidak Aktif Berdasarkan Tipe" data={inactiveByTypeData} loading={unitsAll.isLoading} />
          </Col>
        </Row>
        <FilterBar>
          <Input allowClear placeholder="Cari ID unit, blok, nama pemilik" value={unitsTable.search} onChange={(event) => unitsTable.setSearch(event.target.value)} className="filter-input" />
          <Select allowClear placeholder="Status Unit" options={unitOccupancyStatusOptions} value={unitsTable.filters.occupancy_status} onChange={(value) => unitsTable.setFilters({ ...unitsTable.filters, occupancy_status: value })} className="filter-input" />
          <Select allowClear placeholder="Status Penghuni" options={residentStatusOptions} value={unitsTable.filters.status_id} onChange={(value) => unitsTable.setFilters({ ...unitsTable.filters, status_id: value })} className="filter-input" />
        </FilterBar>
        <ResponsiveTable
          query={units}
          onChange={unitsTable.handleTableChange}
          scrollX={1100}
          columns={[
            { title: 'Unit', dataIndex: 'id', fixed: 'left', width: 90 },
            { title: 'Blok', dataIndex: 'block', width: 80 },
            { title: 'No Unit', dataIndex: 'lot_number', width: 90 },
            {
              title: 'Penghuni',
              width: 220,
              render: (_, row) => (row.resident ? <Link to={`/residents/${row.resident.id}`}>{row.resident.name}</Link> : '-'),
            },
            { title: 'Telepon', render: (_, row) => row.resident?.phone || '-', width: 140 },
            { title: 'Tipe', render: (_, row) => row.property_type?.name || row.propertyType?.name || row.property_type_id, width: 150 },
            { title: 'Status Unit', render: (_, row) => <StatusBadge type="unitOccupancy" value={row.occupancy_status} />, width: 130 },
            { title: 'Status Penghuni', render: (_, row) => <Tag color={row.status_id === 'AK' ? 'green' : 'default'}>{row.status?.name || row.status_id}</Tag>, width: 130 },
          ]}
        />
      </Card>

         <Card className="section-row" title="Riwayat & Jadwal Tarif">
        <ResponsiveTable
          data={rows}
          pagination={false}
          scrollX={960}
          columns={[
            { title: 'Nominal', dataIndex: 'rate', render: formatCurrency, width: 160 },
            { title: 'Mulai Berlaku', dataIndex: 'effective_date', render: formatDate, width: 140 },
            { title: 'Keterangan', dataIndex: 'notes', render: (value) => value || '-' },
            {
              title: 'Status',
              width: 140,
              render: (_, row) => {
                if (!row.is_active) return <Tag>Nonaktif</Tag>;
                if (dayjs(row.effective_date).isAfter(dayjs(), 'day')) return <Tag color="blue">Terjadwal</Tag>;
                if (current && row.id === current.id) return <Tag color="green">Sedang Berlaku</Tag>;
                return <Tag color="default">Riwayat</Tag>;
              },
            },
            { title: 'Dibuat Oleh', render: (_, row) => row.creator?.name || '-', width: 150 },
            { title: 'Diaktifkan', dataIndex: 'activated_at', render: (value) => (value ? formatDateTime(value) : '-'), width: 170 },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 90,
              render: (_, row) => {
                const mutable = row.is_active && dayjs(row.effective_date).isAfter(dayjs(), 'day');
                const items = [
                  { key: 'edit', label: 'Edit', icon: <EditOutlined />, disabled: !mutable, permission: 'clusters.update-rate' },
                  { key: 'delete', label: 'Hapus', icon: <DeleteOutlined />, danger: true, disabled: !mutable, permission: 'clusters.update-rate' },
                ];
                return (
                  <Dropdown menu={{ items, onClick: ({ key }) => {
                    if (key === 'edit') openEdit(row);
                    if (key === 'delete') confirmDelete(row);
                  } }}>
                    <Button icon={<MoreOutlined />} />
                  </Dropdown>
                );
              },
            },
          ]}
        />
      </Card>

      <Drawer
        title={drawer.mode === 'edit' ? 'Edit Jadwal Tarif' : 'Tambah Jadwal Tarif'}
        open={drawer.mode === 'create' || drawer.mode === 'edit'}
        onClose={() => setDrawer({ mode: null, record: null })}
        width={480}
        extra={<Space><Button onClick={() => setDrawer({ mode: null, record: null })}>Batal</Button><Button type="primary" loading={save.isPending} onClick={() => form.submit()}>Simpan</Button></Space>}
        destroyOnHidden
      >
        <Form
          form={form}
          layout="vertical"
          initialValues={{ is_active: true }}
          onFinish={(values) => save.mutate({
            ...values,
            effective_date: values.effective_date?.format?.('YYYY-MM-DD') || values.effective_date,
          })}
        >
          <Form.Item label="Nominal Tarif" name="rate" rules={[{ required: true, message: 'Nominal wajib diisi' }]}>
            <MoneyInput />
          </Form.Item>
          <Form.Item label="Tanggal Mulai Berlaku" name="effective_date" rules={[{ required: true, message: 'Tanggal wajib diisi' }]}>
            <DatePicker style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Keterangan" name="notes">
            <Input.TextArea rows={3} placeholder="Opsional" />
          </Form.Item>
          <Form.Item label="Aktif" name="is_active" valuePropName="checked">
            <Switch />
          </Form.Item>
        </Form>
      </Drawer>
    </section>
  );
}
