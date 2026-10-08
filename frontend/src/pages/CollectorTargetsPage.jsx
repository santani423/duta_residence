import {
  Button,
  Card,
  Col,
  DatePicker,
  Form,
  Input,
  InputNumber,
  Modal,
  Progress,
  Row,
  Segmented,
  Select,
  Space,
  Switch,
  Tabs,
  Tag,
  Tooltip,
  Typography,
  message,
  theme,
} from 'antd';
import { AimOutlined, DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import { useIsFetching, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import dayjs from 'dayjs';
import PageHeader from '../components/common/PageHeader.jsx';
import Can from '../components/common/Can.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import MoneyInput from '../components/common/MoneyInput.jsx';
import { UnitFilterFields } from '../components/common/UnitFilters.jsx';
import { EmptyData } from '../components/common/ApiState.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import CollectorSelect from '../components/collection/CollectorSelect.jsx';
import StatCard from '../components/collection/StatCard.jsx';
import { useTableState } from '../hooks/useTableState.js';
import { api } from '../services/estateApi.js';
import { formatCurrency, formatDate } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';

const PERIOD_LABELS = { daily: 'Harian', weekly: 'Mingguan', monthly: 'Bulanan' };
const PERIOD_OPTIONS = Object.entries(PERIOD_LABELS).map(([value, label]) => ({ value, label }));
const PICKER_BY_PERIOD = { daily: 'date', weekly: 'week', monthly: 'month' };
const CLUSTER_ONLY = ['block', 'unit_id', 'customer', 'address'];

const PERM_CREATE = ['collector-targets.create', 'collector.target_manage'];
const PERM_UPDATE = ['collector-targets.update', 'collector.target_manage'];
const PERM_DELETE = ['collector-targets.delete', 'collector.target_manage'];

const numberFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const percentFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 });

function formatNumber(value) {
  return value === null || value === undefined || value === '' ? '-' : numberFormatter.format(Number(value));
}

function formatPercent(value) {
  return value === null || value === undefined || value === '' ? '-' : `${percentFormatter.format(Number(value))}%`;
}

// Selaras dengan CollectorPerformanceService::resolvePeriod di backend: harian = hari itu,
// mingguan = Senin, bulanan = tanggal 1. Tidak bergantung pada locale dayjs (awal minggu).
function alignPeriodStart(type, value) {
  const date = dayjs(value || undefined).startOf('day');
  if (type === 'monthly') return date.startOf('month');
  if (type === 'weekly') return date.subtract((date.day() + 6) % 7, 'day');
  return date;
}

function periodEnd(type, start) {
  if (type === 'monthly') return start.endOf('month');
  if (type === 'weekly') return start.add(6, 'day');
  return start;
}

function describePeriod(type, start) {
  if (!start) return '-';
  const aligned = alignPeriodStart(type, start);
  if (type === 'monthly') return aligned.format('MMMM YYYY');
  if (type === 'weekly') return `${aligned.format('DD MMM')} – ${periodEnd(type, aligned).format('DD MMM YYYY')}`;
  return aligned.format('DD MMM YYYY');
}

function PeriodPicker({ periodType, value, onChange, allowClear = false, ...rest }) {
  const type = periodType || 'daily';
  return (
    <DatePicker
      picker={PICKER_BY_PERIOD[type]}
      value={value || null}
      allowClear={allowClear}
      inputReadOnly
      format={(current) => describePeriod(type, current)}
      onChange={(next) => onChange?.(next ? alignPeriodStart(type, next) : null)}
      {...rest}
    />
  );
}

function percentOf(actual, target) {
  const goal = Number(target);
  if (!goal || goal <= 0 || actual === null || actual === undefined) return null;
  return (Number(actual) / goal) * 100;
}

function ProgressCell({ actual, target, percent, format }) {
  const { token } = theme.useToken();
  const hasTarget = target !== null && target !== undefined && Number(target) > 0;
  const pct = percent ?? percentOf(actual, target);

  if (!hasTarget) {
    return (
      <div>
        <Typography.Text>{format(actual)}</Typography.Text>
        <div><Typography.Text type="secondary" style={{ fontSize: token.fontSizeSM }}>Target belum ditetapkan</Typography.Text></div>
      </div>
    );
  }

  const value = Number(pct || 0);
  const color = value >= 100 ? token.colorSuccess : (value >= 70 ? token.colorPrimary : token.colorWarning);

  return (
    <div style={{ minWidth: 150 }}>
      <Progress
        percent={Math.min(100, Math.max(0, value))}
        format={() => formatPercent(value)}
        strokeColor={color}
        size="small"
        style={{ marginBottom: 0 }}
      />
      <Typography.Text type="secondary" style={{ fontSize: token.fontSizeSM }}>
        {format(actual)} / {format(target)}
      </Typography.Text>
    </div>
  );
}

function targetValue(row, key) {
  return row.target?.[key] ?? row.metrics?.[key] ?? null;
}

function hasTarget(row) {
  return Boolean(row.target);
}

function ProgressTab({ onSetTarget, onEditTarget }) {
  const [periodType, setPeriodType] = useState('monthly');
  const [periodStart, setPeriodStart] = useState(() => alignPeriodStart('monthly'));
  const [filters, setFilters] = useState({});
  const [search, setSearch] = useState('');
  const [onlyWithoutTarget, setOnlyWithoutTarget] = useState(false);

  const params = {
    period_type: periodType,
    period_start: periodStart.format('YYYY-MM-DD'),
    cluster_id: filters.cluster_id || undefined,
  };

  const progress = useQuery({
    queryKey: ['collector-targets', 'progress', params],
    queryFn: () => api.collectorTargets.progress(params),
  });

  const rows = useMemo(() => {
    const payload = progress.data?.data;
    return Array.isArray(payload) ? payload : (Array.isArray(payload?.data) ? payload.data : []);
  }, [progress.data]);

  const period = progress.data?.meta?.period;
  const periodLabel = period?.start
    ? `${formatDate(period.start)}${period.end && period.end !== period.start ? ` – ${formatDate(period.end)}` : ''}`
    : describePeriod(periodType, periodStart);

  const summary = useMemo(() => {
    const withTarget = rows.filter(hasTarget);
    const collected = rows.reduce((sum, row) => sum + Number(row.metrics?.collected_amount || 0), 0);
    const targetTotal = withTarget.reduce((sum, row) => sum + Number(targetValue(row, 'target_amount') || 0), 0);
    const achieved = withTarget.filter((row) => {
      const pct = row.metrics?.achievement_percent_raw ?? percentOf(row.metrics?.collected_amount, targetValue(row, 'target_amount'));
      return pct !== null && Number(pct) >= 100;
    }).length;
    const rates = rows.map((row) => row.metrics?.collection_rate).filter((value) => value !== null && value !== undefined);
    return {
      total: rows.length,
      withoutTarget: rows.length - withTarget.length,
      collected,
      targetTotal,
      achieved,
      withTargetCount: withTarget.length,
      averageRate: rates.length ? rates.reduce((sum, value) => sum + Number(value), 0) / rates.length : null,
    };
  }, [rows]);

  const visibleRows = useMemo(() => {
    const keyword = search.trim().toLowerCase();
    return rows.filter((row) => {
      if (onlyWithoutTarget && hasTarget(row)) return false;
      if (!keyword) return true;
      const haystack = `${row.collector?.name || ''} ${row.collector?.collector_code || ''}`.toLowerCase();
      return haystack.includes(keyword);
    });
  }, [rows, search, onlyWithoutTarget]);

  function changePeriodType(type) {
    setPeriodType(type);
    setPeriodStart(alignPeriodStart(type));
  }

  const columns = [
    {
      title: 'Kolektor',
      key: 'collector',
      fixed: 'left',
      width: 220,
      render: (_, row) => (
        <Space orientation="vertical" size={2}>
          <Typography.Text strong>{row.collector?.name || '-'}</Typography.Text>
          <Space size={4} wrap>
            {row.collector?.collector_code ? <Typography.Text type="secondary">{row.collector.collector_code}</Typography.Text> : null}
            {!hasTarget(row) ? <Tag color="gold">Tanpa target</Tag> : null}
          </Space>
        </Space>
      ),
    },
    {
      title: 'Nominal Tertagih',
      key: 'amount',
      width: 220,
      render: (_, row) => (
        <ProgressCell
          actual={row.metrics?.collected_amount ?? 0}
          target={targetValue(row, 'target_amount')}
          percent={hasTarget(row) ? row.metrics?.achievement_percent_raw : null}
          format={formatCurrency}
        />
      ),
    },
    {
      title: 'Kunjungan',
      key: 'visits',
      width: 180,
      render: (_, row) => (
        <ProgressCell
          actual={row.metrics?.visit_count ?? 0}
          target={targetValue(row, 'target_visit_count')}
          percent={row.metrics?.visit_achievement_percent}
          format={formatNumber}
        />
      ),
    },
    {
      title: 'Akun Ditangani',
      key: 'accounts',
      width: 180,
      render: (_, row) => (
        <ProgressCell
          actual={row.metrics?.assigned_accounts ?? 0}
          target={targetValue(row, 'target_account_count')}
          format={formatNumber}
        />
      ),
    },
    {
      title: (
        <Tooltip title="Perkiraan: tertagih ÷ (tertagih + tunggakan saat ini).">
          <span>Collection Rate</span>
        </Tooltip>
      ),
      key: 'rate',
      width: 180,
      render: (_, row) => (
        <ProgressCell
          actual={row.metrics?.collection_rate}
          target={targetValue(row, 'target_collection_rate')}
          format={formatPercent}
        />
      ),
    },
    {
      title: 'Aksi',
      key: 'action',
      fixed: 'right',
      width: 140,
      render: (_, row) => (hasTarget(row) ? (
        row.target?.id ? (
          <Can any={PERM_UPDATE}>
            <Button size="small" icon={<EditOutlined />} onClick={() => onEditTarget(row)}>Ubah</Button>
          </Can>
        ) : null
      ) : (
        <Can any={PERM_CREATE}>
          <Button size="small" type="primary" ghost icon={<AimOutlined />} onClick={() => onSetTarget(row, periodType, periodStart)}>
            Set target
          </Button>
        </Can>
      )),
    },
  ];

  return (
    <div className="stack">
      <FilterBar>
        <Segmented options={PERIOD_OPTIONS} value={periodType} onChange={changePeriodType} />
        <PeriodPicker periodType={periodType} value={periodStart} onChange={(next) => next && setPeriodStart(next)} className="filter-input" />
        <UnitFilterFields value={filters} onChange={setFilters} hide={CLUSTER_ONLY} />
        <Input allowClear placeholder="Cari nama / kode kolektor" value={search} onChange={(event) => setSearch(event.target.value)} className="filter-input" />
        <Space>
          <Switch checked={onlyWithoutTarget} onChange={setOnlyWithoutTarget} aria-label="Hanya kolektor tanpa target" />
          <Typography.Text>Hanya tanpa target</Typography.Text>
        </Space>
      </FilterBar>

      <Row gutter={[16, 16]}>
        <Col xs={24} sm={12} lg={6}>
          <StatCard
            title="Kolektor"
            value={summary.total}
            format="number"
            loading={progress.isLoading}
            footer={<Typography.Text type={summary.withoutTarget ? 'warning' : 'secondary'}>{formatNumber(summary.withoutTarget)} belum punya target</Typography.Text>}
          />
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <StatCard
            title="Total Tertagih"
            value={summary.collected}
            format="currency"
            loading={progress.isLoading}
            progress={summary.targetTotal > 0 ? (summary.collected / summary.targetTotal) * 100 : null}
            status={summary.targetTotal > 0 && summary.collected >= summary.targetTotal ? 'good' : undefined}
            footer={<Typography.Text type="secondary">Target total {formatCurrency(summary.targetTotal)}</Typography.Text>}
          />
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <StatCard
            title="Mencapai Target Nominal"
            value={summary.achieved}
            format="number"
            loading={progress.isLoading}
            footer={<Typography.Text type="secondary">dari {formatNumber(summary.withTargetCount)} kolektor bertarget</Typography.Text>}
          />
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <StatCard
            title="Rata-rata Collection Rate"
            value={summary.averageRate}
            format="percent"
            loading={progress.isLoading}
            hint="Perkiraan: tertagih ÷ (tertagih + tunggakan saat ini), dirata-rata per kolektor."
          />
        </Col>
      </Row>

      <Card
        title={`Progres periode ${periodLabel}`}
        extra={<Typography.Text type="secondary">{PERIOD_LABELS[periodType]}</Typography.Text>}
      >
        <ResponsiveTable
          query={progress}
          data={visibleRows}
          rowKey={(row) => row.collector?.id}
          scrollX={1150}
          pagination={{ pageSize: 20, showSizeChanger: true, showTotal: (total) => `${total} kolektor` }}
          {...(progress.isError ? {} : {
            locale: {
              emptyText: (
                <EmptyData
                  description={rows.length
                    ? 'Tidak ada kolektor yang cocok dengan filter. Ubah pencarian atau matikan "Hanya tanpa target".'
                    : 'Belum ada kolektor dalam cakupan Anda untuk periode ini.'}
                />
              ),
            },
          })}
          columns={columns}
        />
      </Card>
    </div>
  );
}

function TargetListTab({ onEdit, onDelete }) {
  const table = useTableState();
  const list = useQuery({
    queryKey: ['collector-targets', 'list', table.params],
    queryFn: () => api.collectorTargets.list(table.params),
  });
  const filterPeriodType = table.filters.period_type;

  const columns = [
    {
      title: 'Kolektor',
      key: 'collector',
      render: (_, record) => (
        <Space orientation="vertical" size={0}>
          <Typography.Text strong>{record.collector?.name || '-'}</Typography.Text>
          {record.collector?.collector_profile?.collector_code || record.collector?.collector_code ? (
            <Typography.Text type="secondary">{record.collector?.collector_profile?.collector_code || record.collector?.collector_code}</Typography.Text>
          ) : null}
        </Space>
      ),
    },
    { title: 'Periode', key: 'period_type', render: (_, record) => <Tag>{PERIOD_LABELS[record.period_type] || record.period_type}</Tag> },
    {
      title: 'Mulai',
      dataIndex: 'period_start',
      render: (value, record) => (record.period_type === 'daily' || !value ? formatDate(value) : describePeriod(record.period_type, value)),
    },
    { title: 'Target Nominal', dataIndex: 'target_amount', render: formatCurrency },
    { title: 'Target Kunjungan', dataIndex: 'target_visit_count', render: formatNumber },
    { title: 'Target Akun', dataIndex: 'target_account_count', render: formatNumber },
    { title: 'Target Collection Rate', dataIndex: 'target_collection_rate', render: formatPercent },
    {
      title: 'Aksi',
      key: 'action',
      fixed: 'right',
      width: 120,
      render: (_, record) => (
        <Space>
          <Can any={PERM_UPDATE}>
            <Tooltip title="Ubah target">
              <Button size="small" icon={<EditOutlined />} aria-label="Ubah target" onClick={() => onEdit(record)} />
            </Tooltip>
          </Can>
          <Can any={PERM_DELETE}>
            <Tooltip title="Hapus target">
              <Button size="small" danger icon={<DeleteOutlined />} aria-label="Hapus target" onClick={() => onDelete(record)} />
            </Tooltip>
          </Can>
        </Space>
      ),
    },
  ];

  return (
    <div className="stack">
      <FilterBar>
        <CollectorSelect
          includeInactive
          placeholder="Filter kolektor"
          value={table.filters.collector_id}
          onChange={(value) => table.setFilters({ ...table.filters, collector_id: value })}
          className="filter-input"
        />
        <Select
          allowClear
          placeholder="Jenis periode"
          options={PERIOD_OPTIONS}
          value={filterPeriodType}
          onChange={(value) => table.setFilters({ ...table.filters, period_type: value, period_start: undefined })}
          className="filter-input"
        />
        <PeriodPicker
          allowClear
          periodType={filterPeriodType}
          placeholder="Awal periode"
          value={table.filters.period_start ? dayjs(table.filters.period_start) : null}
          onChange={(value) => table.setFilters({ ...table.filters, period_start: value ? value.format('YYYY-MM-DD') : undefined })}
          className="filter-input"
        />
      </FilterBar>

      <Card>
        <ResponsiveTable
          query={list}
          onChange={table.handleTableChange}
          columns={columns}
          scrollX={1150}
          {...(list.isError ? {} : {
            locale: { emptyText: <EmptyData description="Belum ada target untuk filter ini. Tambahkan target baru atau pilih periode lain." /> },
          })}
        />
      </Card>
    </div>
  );
}

export default function CollectorTargetsPage() {
  const [tab, setTab] = useState('progress');
  const [modal, setModal] = useState({ mode: null, record: null });
  const [form] = Form.useForm();
  const formPeriodType = Form.useWatch('period_type', form);
  const queryClient = useQueryClient();
  const fetchingCount = useIsFetching({ queryKey: ['collector-targets'] });

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ['collector-targets'] });
    queryClient.invalidateQueries({ queryKey: ['collection', 'performance'] });
    queryClient.invalidateQueries({ queryKey: ['collector-performance'] });
  }

  function closeModal() {
    setModal({ mode: null, record: null });
  }

  const save = useMutation({
    mutationFn: (values) => {
      const payload = {
        collector_id: values.collector_id,
        period_type: values.period_type,
        period_start: alignPeriodStart(values.period_type, values.period_start).format('YYYY-MM-DD'),
        target_amount: values.target_amount,
        target_visit_count: values.target_visit_count ?? null,
        target_account_count: values.target_account_count ?? null,
        target_collection_rate: values.target_collection_rate ?? null,
      };
      return modal.mode === 'edit' ? api.collectorTargets.update(modal.record.id, payload) : api.collectorTargets.create(payload);
    },
    onSuccess: () => {
      message.success(modal.mode === 'edit' ? 'Target kolektor berhasil diperbarui.' : 'Target kolektor berhasil ditambahkan.');
      closeModal();
      invalidate();
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error, 'Target kolektor gagal disimpan.'));
    },
  });

  const remove = useMutation({
    mutationFn: (id) => api.collectorTargets.remove(id),
    onSuccess: () => {
      message.success('Target kolektor berhasil dihapus.');
      invalidate();
    },
    onError: (error) => message.error(getApiErrorMessage(error, 'Target kolektor gagal dihapus.')),
  });

  function openForm(mode, record, values) {
    form.resetFields();
    form.setFieldsValue(values);
    setModal({ mode, record });
  }

  function openCreate() {
    openForm('create', null, { period_type: 'monthly', period_start: alignPeriodStart('monthly') });
  }

  function openSetTarget(row, periodType, periodStart) {
    openForm('create', null, {
      collector_id: row.collector?.id,
      period_type: periodType,
      period_start: alignPeriodStart(periodType, periodStart),
    });
  }

  function toNumber(value) {
    return value === null || value === undefined || value === '' ? undefined : Number(value);
  }

  function openEdit(record) {
    openForm('edit', record, {
      collector_id: record.collector_id ?? record.collector?.id,
      period_type: record.period_type,
      period_start: record.period_start ? dayjs(String(record.period_start).slice(0, 10)) : undefined,
      target_amount: toNumber(record.target_amount),
      target_visit_count: toNumber(record.target_visit_count),
      target_account_count: toNumber(record.target_account_count),
      target_collection_rate: toNumber(record.target_collection_rate),
    });
  }

  function openEditFromProgress(row) {
    openEdit({ ...row.target, collector_id: row.target?.collector_id ?? row.collector?.id, collector: row.collector });
  }

  function confirmDelete(record) {
    Modal.confirm({
      title: 'Hapus target ini?',
      content: `Target ${PERIOD_LABELS[record.period_type]?.toLowerCase() || ''} ${record.collector?.name || 'kolektor'} periode ${describePeriod(record.period_type, record.period_start)} akan dihapus. Progres periode tersebut akan tampil tanpa target.`,
      okText: 'Hapus',
      cancelText: 'Batal',
      okButtonProps: { danger: true },
      onOk: () => remove.mutateAsync(record.id).catch(() => {}),
    });
  }

  function handleValuesChange(changed, all) {
    if ('period_type' in changed && changed.period_type && all.period_start) {
      form.setFieldValue('period_start', alignPeriodStart(changed.period_type, all.period_start));
    }
  }

  return (
    <section>
      <PageHeader
        title="Target Kolektor"
        subtitle="Pantau progres dan tetapkan target penagihan harian, mingguan, atau bulanan per kolektor."
        breadcrumbs={[{ label: 'Manajemen Kolektor' }, { label: 'Target' }]}
        onRefresh={() => queryClient.invalidateQueries({ queryKey: ['collector-targets'] })}
        loading={fetchingCount > 0}
        extra={(
          <Can any={PERM_CREATE}>
            <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Tambah Target</Button>
          </Can>
        )}
      />

      <Tabs
        activeKey={tab}
        onChange={setTab}
        items={[
          { key: 'progress', label: 'Progres', children: <ProgressTab onSetTarget={openSetTarget} onEditTarget={openEditFromProgress} /> },
          { key: 'list', label: 'Daftar Target', children: <TargetListTab onEdit={openEdit} onDelete={confirmDelete} /> },
        ]}
      />

      <Modal
        title={modal.mode === 'edit' ? 'Ubah Target Kolektor' : 'Tambah Target Kolektor'}
        open={modal.mode === 'create' || modal.mode === 'edit'}
        onCancel={closeModal}
        onOk={() => form.submit()}
        okText="Simpan"
        cancelText="Batal"
        confirmLoading={save.isPending}
        forceRender
      >
        <Form form={form} layout="vertical" onFinish={save.mutate} onValuesChange={handleValuesChange}>
          <Form.Item label="Kolektor" name="collector_id" rules={[{ required: true, message: 'Pilih kolektor' }]}>
            <CollectorSelect includeInactive allowClear={false} />
          </Form.Item>
          <Row gutter={16}>
            <Col xs={24} md={12}>
              <Form.Item label="Jenis Periode" name="period_type" rules={[{ required: true, message: 'Pilih jenis periode' }]}>
                <Select options={PERIOD_OPTIONS} />
              </Form.Item>
            </Col>
            <Col xs={24} md={12}>
              <Form.Item
                label="Periode"
                name="period_start"
                rules={[{ required: true, message: 'Pilih periode' }]}
                extra="Tanggal otomatis disesuaikan ke awal periode (Senin untuk mingguan, tanggal 1 untuk bulanan)."
              >
                <PeriodPicker periodType={formPeriodType} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
          </Row>
          <Form.Item label="Target Nominal (Rp)" name="target_amount" rules={[{ required: true, message: 'Isi target nominal' }]}>
            <MoneyInput />
          </Form.Item>
          <Row gutter={16}>
            <Col xs={24} md={12}>
              <Form.Item label="Target Kunjungan (opsional)" name="target_visit_count">
                <InputNumber min={0} precision={0} style={{ width: '100%' }} placeholder="mis. 40" />
              </Form.Item>
            </Col>
            <Col xs={24} md={12}>
              <Form.Item label="Target Akun Ditangani (opsional)" name="target_account_count">
                <InputNumber min={0} precision={0} style={{ width: '100%' }} placeholder="mis. 25" />
              </Form.Item>
            </Col>
          </Row>
          <Form.Item
            label="Target Collection Rate (opsional)"
            name="target_collection_rate"
            rules={[{ type: 'number', min: 0, max: 100, message: 'Collection rate harus 0 – 100%' }]}
          >
            <InputNumber min={0} max={100} step={0.5} precision={2} suffix="%" style={{ width: '100%' }} placeholder="mis. 85" />
          </Form.Item>
        </Form>
      </Modal>
    </section>
  );
}
