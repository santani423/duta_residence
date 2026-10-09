import {
  Alert,
  Button,
  Card,
  Checkbox,
  Col,
  DatePicker,
  Descriptions,
  Empty,
  Form,
  Input,
  Modal,
  Popconfirm,
  Row,
  Segmented,
  Select,
  Skeleton,
  Space,
  Statistic,
  Switch,
  Table,
  Tabs,
  Tag,
  Tooltip,
  Typography,
  message,
} from 'antd';
import {
  ClearOutlined,
  DeleteOutlined,
  EditOutlined,
  PlusOutlined,
  SwapOutlined,
  UserAddOutlined,
} from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import dayjs from 'dayjs';
import PageHeader from '../components/common/PageHeader.jsx';
import Can from '../components/common/Can.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import { EmptyData, ErrorState } from '../components/common/ApiState.jsx';
import { UnitFilterFields, UnitPicker } from '../components/common/UnitFilters.jsx';
import CollectorSelect from '../components/collection/CollectorSelect.jsx';
import { useTableState } from '../hooks/useTableState.js';
import { useDebounce } from '../hooks/useDebounce.js';
import { useBlockOptions, useClusterOptions } from '../hooks/useUnitLookups.js';
import { useAuth } from '../state/AuthContext.jsx';
import { api } from '../services/estateApi.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';
import { formatCurrency, formatDate } from '../utils/format.js';

const MAX_BULK_UNITS = 500;

const PRIORITY_OPTIONS = [
  { value: 'low', label: 'Rendah' },
  { value: 'normal', label: 'Normal' },
  { value: 'high', label: 'Tinggi' },
  { value: 'urgent', label: 'Mendesak' },
];

// Warna preset antd (bukan hex) supaya ikut tema terang/gelap.
const PRIORITY_COLORS = { low: 'default', normal: 'blue', high: 'orange', urgent: 'red' };

const SCOPE_LABELS = {
  cluster: 'Cluster',
  block: 'Blok',
  unit: 'Unit',
  resident: 'Penghuni',
};

const SCOPE_OPTIONS = [
  { value: 'cluster', label: 'Seluruh Cluster' },
  { value: 'block', label: 'Blok Tertentu' },
  { value: 'unit', label: 'Unit Tertentu' },
  { value: 'resident', label: 'Penghuni Tertentu (mengikuti orangnya, bukan alamat)' },
];

const STATUS_FILTER_OPTIONS = [
  { value: 'active', label: 'Aktif' },
  { value: 'completed', label: 'Selesai' },
  { value: 'cancelled', label: 'Dibatalkan' },
  { value: 'transferred', label: 'Dipindahkan' },
];

const EDITABLE_STATUS_OPTIONS = [
  { value: 'active', label: 'Aktif' },
  { value: 'completed', label: 'Selesai' },
];

const ASSIGN_MODES = [
  { value: 'manual', label: 'Manual' },
  { value: 'bulk', label: 'Massal' },
  { value: 'area', label: 'Area' },
];

const TAB_KEYS = ['list', 'assign', 'unassigned'];

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

// Tanggal dari backend bisa "2026-10-08" atau ISO penuh; ambil bagian tanggal saja agar tidak bergeser zona waktu.
function toDay(value) {
  if (!value) return undefined;
  const parsed = dayjs(String(value).slice(0, 10));
  return parsed.isValid() ? parsed : undefined;
}

function formatDay(value, fallback = '-') {
  return value ? formatDate(String(value).slice(0, 10), fallback) : fallback;
}

function toApiDate(value) {
  return value ? value.format('YYYY-MM-DD') : undefined;
}

function priorityLabel(value) {
  return PRIORITY_OPTIONS.find((option) => option.value === value)?.label || value || '-';
}

function scopeSummary(record) {
  if (!record) return '-';
  const clusterName = record.cluster?.name || record.cluster_id;
  // Nama cluster umumnya sudah diawali "Cluster" (mis. "Cluster Flamboyan").
  if (record.scope_type === 'cluster') return /^cluster\b/i.test(String(clusterName)) ? `Seluruh ${clusterName}` : `Seluruh cluster ${clusterName}`;
  if (record.scope_type === 'block') return `${clusterName} · Blok ${record.block}`;
  if (record.scope_type === 'unit') return `Unit ${record.unit_id}`;
  if (record.scope_type === 'resident') return `Penghuni ${record.resident?.name || record.resident_id}`;
  return '-';
}

function unitAddress(unit) {
  if (!unit) return null;
  return [unit.cluster?.name || unit.cluster_name || unit.cluster_id, unit.block && `Blok ${unit.block}`, unit.lot_number && `No ${unit.lot_number}`]
    .filter(Boolean)
    .join(' · ');
}

// Baris unit bisa berasal dari /collection/assignments/unassigned-units (unit_id) atau /units (id).
function unitRowKey(row) {
  return row?.unit_id ?? row?.id;
}

function listItems(response) {
  const payload = response?.data;
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload?.data)) return payload.data;
  return [];
}

function endAfterStartRule({ getFieldValue }) {
  return {
    validator(_, value) {
      const start = getFieldValue('start_date');
      if (!value || !start || !value.isBefore(start, 'day')) return Promise.resolve();
      return Promise.reject(new Error('Tanggal selesai tidak boleh sebelum tanggal mulai.'));
    },
  };
}

function schedulePayload(values) {
  return {
    priority: values.priority || 'normal',
    start_date: toApiDate(values.start_date),
    end_date: toApiDate(values.end_date),
    notes: values.notes?.trim() ? values.notes.trim() : undefined,
  };
}

// Semua data yang terpengaruh perubahan penugasan: daftar penugasan, statistik kolektor,
// unit belum ditugaskan, preview, dan monitoring penagihan (kecuali opsi dropdown kolektor).
function invalidateAssignmentData(queryClient) {
  queryClient.invalidateQueries({ queryKey: ['collector-assignments'] });
  queryClient.invalidateQueries({ queryKey: ['collectors'] });
  queryClient.invalidateQueries({ queryKey: ['collector-performance'] });
  queryClient.invalidateQueries({
    predicate: (query) => query.queryKey[0] === 'collection' && query.queryKey[1] !== 'collector-options',
  });
}

const INITIAL_SCHEDULE = { priority: 'normal' };

function initialScheduleValues() {
  return { ...INITIAL_SCHEDULE, start_date: dayjs() };
}

// ---------------------------------------------------------------------------
// Halaman
// ---------------------------------------------------------------------------

export default function CollectorAssignmentsPage() {
  const { can } = useAuth();
  const canAssign = can('collector-assignments.assign') || can('collector.assign');
  const [searchParams, setSearchParams] = useSearchParams();
  const requestedTab = searchParams.get('tab');
  const activeTab = TAB_KEYS.includes(requestedTab) && (requestedTab !== 'assign' || canAssign) ? requestedTab : 'list';
  const [assignMode, setAssignMode] = useState('manual');
  const [preselect, setPreselect] = useState({ nonce: 0, rows: [] });

  function changeTab(key) {
    const next = new URLSearchParams(searchParams);
    if (key === 'list') next.delete('tab');
    else next.set('tab', key);
    setSearchParams(next, { replace: true });
  }

  function startBulkAssign(rows) {
    const limited = rows.slice(0, MAX_BULK_UNITS);
    if (rows.length > MAX_BULK_UNITS) message.warning(`Hanya ${MAX_BULK_UNITS} unit pertama yang dipilih.`);
    setPreselect((current) => ({ nonce: current.nonce + 1, rows: limited }));
    setAssignMode('bulk');
    changeTab('assign');
  }

  const items = [
    {
      key: 'list',
      label: 'Daftar Penugasan',
      children: <AssignmentListTab />,
    },
    canAssign ? {
      key: 'assign',
      label: 'Tugaskan',
      children: (
        <AssignTab
          mode={assignMode}
          onModeChange={setAssignMode}
          preselect={preselect}
        />
      ),
    } : null,
    {
      key: 'unassigned',
      label: 'Unit Belum Ditugaskan',
      children: <UnassignedUnitsTab canAssign={canAssign} onAssign={startBulkAssign} />,
    },
  ].filter(Boolean);

  return (
    <section>
      <PageHeader
        title="Penugasan Kolektor"
        subtitle="Atur wilayah kerja setiap kolektor: cluster, blok, unit, atau penghuni tertentu."
        breadcrumbs={[{ label: 'Manajemen Kolektor' }, { label: 'Penugasan' }]}
        extra={canAssign && activeTab !== 'assign' ? (
          <Button type="primary" icon={<PlusOutlined />} onClick={() => changeTab('assign')}>Tugaskan Kolektor</Button>
        ) : null}
      />
      <Tabs activeKey={activeTab} onChange={changeTab} items={items} />
    </section>
  );
}

// ---------------------------------------------------------------------------
// Tab 1 — Daftar Penugasan
// ---------------------------------------------------------------------------

function AssignmentListTab() {
  const { can } = useAuth();
  const queryClient = useQueryClient();
  const table = useTableState();
  const [editRecord, setEditRecord] = useState(null);
  const [reassignRecord, setReassignRecord] = useState(null);

  const list = useQuery({
    queryKey: ['collector-assignments', table.params],
    queryFn: () => api.collectorAssignments.list(table.params),
  });

  const remove = useMutation({
    mutationFn: (id) => api.collectorAssignments.remove(id),
    onSuccess: () => {
      message.success('Penugasan kolektor berhasil dicabut.');
      invalidateAssignmentData(queryClient);
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const filters = table.filters;
  const setFilter = (patch) => table.setFilters({ ...filters, ...patch });
  const hasFilters = Boolean(table.search) || Object.values(filters).some((value) => value !== undefined && value !== null && value !== '');

  const columns = [
    {
      title: 'Kolektor',
      key: 'collector',
      width: 180,
      render: (_, record) => {
        const name = record.collector?.name || `#${record.collector_id}`;
        return can('collector.detail') && record.collector_id
          ? <Link to={`/admin/collectors/list/${record.collector_id}`}>{name}</Link>
          : name;
      },
    },
    {
      title: 'Cakupan',
      key: 'scope',
      width: 260,
      render: (_, record) => (
        <Space direction="vertical" size={0}>
          <Space size={4} wrap>
            <Tag>{SCOPE_LABELS[record.scope_type] || record.scope_type}</Tag>
            <span>{scopeSummary(record)}</span>
          </Space>
          {record.scope_type === 'unit' && record.unit ? (
            <Typography.Text type="secondary">{unitAddress(record.unit)}</Typography.Text>
          ) : null}
        </Space>
      ),
    },
    {
      title: 'Periode',
      key: 'period',
      width: 200,
      render: (_, record) => `${formatDay(record.start_date)} s/d ${record.end_date ? formatDay(record.end_date) : 'sekarang'}`,
    },
    {
      title: 'Prioritas',
      dataIndex: 'priority',
      width: 110,
      render: (value) => <Tag color={PRIORITY_COLORS[value] || 'default'}>{priorityLabel(value)}</Tag>,
    },
    {
      title: 'Status',
      dataIndex: 'status',
      width: 120,
      render: (value) => <StatusBadge type="assignmentStatus" value={value} />,
    },
    {
      title: 'Dipindahkan Dari',
      key: 'reassigned_from',
      width: 220,
      render: (_, record) => {
        const from = record.reassigned_from;
        if (!from && !record.reassign_reason) return <Typography.Text type="secondary">-</Typography.Text>;
        return (
          <Space direction="vertical" size={0}>
            <span>{from?.collector?.name || (from ? `#${from.collector_id}` : '-')}</span>
            {record.reassign_reason ? (
              <Tooltip title={record.reassign_reason}>
                <Typography.Text type="secondary" ellipsis style={{ maxWidth: 200 }}>
                  Alasan: {record.reassign_reason}
                </Typography.Text>
              </Tooltip>
            ) : null}
          </Space>
        );
      },
    },
    {
      title: 'Ditugaskan Oleh',
      key: 'assigned_by',
      width: 150,
      render: (_, record) => record.assigned_by?.name || record.assignedBy?.name || '-',
    },
    {
      title: 'Aksi',
      key: 'actions',
      fixed: 'right',
      width: 140,
      render: (_, record) => {
        const isActive = Boolean(record.is_active) && record.status === 'active';
        const editable = ['active', 'completed'].includes(record.status);
        return (
          <Space size={4}>
            <Can permission="collector-assignments.update">
              <Tooltip title={editable ? 'Edit' : 'Penugasan yang dibatalkan/dipindahkan tidak dapat diedit'}>
                <Button size="small" icon={<EditOutlined />} aria-label="Edit penugasan" disabled={!editable} onClick={() => setEditRecord(record)} />
              </Tooltip>
            </Can>
            <Can permission="collector.reassign">
              <Tooltip title={isActive ? 'Pindahkan ke kolektor lain' : 'Hanya penugasan aktif yang dapat dipindahkan'}>
                <Button size="small" icon={<SwapOutlined />} aria-label="Pindahkan penugasan" disabled={!isActive} onClick={() => setReassignRecord(record)} />
              </Tooltip>
            </Can>
            <Can permission="collector-assignments.delete">
              <Popconfirm
                title="Cabut penugasan ini?"
                description={`${scopeSummary(record)} — ${record.collector?.name || 'kolektor'}`}
                okText="Cabut"
                cancelText="Batal"
                okButtonProps={{ danger: true, loading: remove.isPending }}
                disabled={!isActive}
                onConfirm={() => remove.mutateAsync(record.id).catch(() => {})}
              >
                <Tooltip title={isActive ? 'Cabut penugasan' : 'Penugasan sudah tidak aktif'}>
                  <Button size="small" danger icon={<DeleteOutlined />} aria-label="Cabut penugasan" disabled={!isActive} />
                </Tooltip>
              </Popconfirm>
            </Can>
          </Space>
        );
      },
    },
  ];

  return (
    <>
      <FilterBar
        extra={hasFilters ? (
          <Button
            icon={<ClearOutlined />}
            onClick={() => {
              table.setSearch('');
              table.setFilters({});
            }}
          >
            Reset
          </Button>
        ) : null}
      >
        <Input.Search
          allowClear
          placeholder="Cari unit / nama penghuni"
          value={table.search}
          onChange={(event) => table.setSearch(event.target.value)}
          className="filter-input"
        />
        <UnitFilterFields value={filters} onChange={(next) => table.setFilters(next)} hide={['customer', 'address']} />
        <CollectorSelect
          includeInactive
          placeholder="Semua kolektor"
          value={filters.collector_id}
          onChange={(value) => setFilter({ collector_id: value })}
          className="filter-input"
        />
        <Select
          allowClear
          placeholder="Jenis cakupan"
          options={Object.entries(SCOPE_LABELS).map(([value, label]) => ({ value, label }))}
          value={filters.scope_type}
          onChange={(value) => setFilter({ scope_type: value })}
          className="filter-input"
        />
        <Select
          allowClear
          placeholder="Status penugasan"
          options={STATUS_FILTER_OPTIONS}
          value={filters.status}
          onChange={(value) => setFilter({ status: value })}
          className="filter-input"
        />
        <Checkbox
          checked={filters.currently_effective === 1}
          onChange={(event) => setFilter({ currently_effective: event.target.checked ? 1 : undefined })}
          style={{ alignSelf: 'center' }}
        >
          Hanya yang berlaku hari ini
        </Checkbox>
      </FilterBar>

      <Card>
        <ResponsiveTable
          query={list}
          onChange={table.handleTableChange}
          columns={columns}
          scrollX={1400}
          {...(list.isError ? {} : { locale: {
            emptyText: (
              <EmptyData
                description={hasFilters
                  ? 'Tidak ada penugasan yang cocok dengan filter. Coba ubah atau reset filter.'
                  : 'Belum ada penugasan. Buka tab "Tugaskan" untuk memberi wilayah kerja ke kolektor.'}
              />
            ),
          }})}
        />
      </Card>

      <EditAssignmentModal record={editRecord} onClose={() => setEditRecord(null)} />
      <ReassignModal record={reassignRecord} onClose={() => setReassignRecord(null)} />
    </>
  );
}

function EditAssignmentModal({ record, onClose }) {
  const queryClient = useQueryClient();
  const [form] = Form.useForm();

  const save = useMutation({
    mutationFn: (values) => api.collectorAssignments.update(record.id, {
      status: values.status,
      priority: values.priority,
      start_date: toApiDate(values.start_date) ?? null,
      end_date: toApiDate(values.end_date) ?? null,
      notes: values.notes?.trim() ? values.notes.trim() : null,
    }),
    onSuccess: () => {
      message.success('Penugasan kolektor berhasil diperbarui.');
      invalidateAssignmentData(queryClient);
      onClose();
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  return (
    <Modal
      title="Edit Penugasan Kolektor"
      open={Boolean(record)}
      onCancel={onClose}
      onOk={() => form.submit()}
      okText="Simpan"
      cancelText="Batal"
      confirmLoading={save.isPending}
      destroyOnHidden
    >
      {record ? (
        <>
          <Descriptions size="small" column={1} bordered style={{ marginBottom: 16 }}>
            <Descriptions.Item label="Kolektor">{record.collector?.name || `#${record.collector_id}`}</Descriptions.Item>
            <Descriptions.Item label="Cakupan">
              <Tag>{SCOPE_LABELS[record.scope_type] || record.scope_type}</Tag> {scopeSummary(record)}
            </Descriptions.Item>
          </Descriptions>
          <Alert
            type="info"
            showIcon
            style={{ marginBottom: 16 }}
            message="Kolektor dan cakupan tidak dapat diubah di sini."
            description="Gunakan aksi Pindahkan untuk mengganti kolektor, atau buat penugasan baru untuk cakupan lain."
          />
          <Form
            form={form}
            layout="vertical"
            onFinish={save.mutate}
            initialValues={{
              status: record.status === 'completed' ? 'completed' : 'active',
              priority: record.priority || 'normal',
              start_date: toDay(record.start_date),
              end_date: toDay(record.end_date),
              notes: record.notes || '',
            }}
          >
            <Row gutter={12}>
              <Col xs={24} md={12}>
                <Form.Item label="Status" name="status" rules={[{ required: true, message: 'Pilih status' }]}>
                  <Select options={EDITABLE_STATUS_OPTIONS} />
                </Form.Item>
              </Col>
              <Col xs={24} md={12}>
                <Form.Item label="Prioritas" name="priority" rules={[{ required: true, message: 'Pilih prioritas' }]}>
                  <Select options={PRIORITY_OPTIONS} />
                </Form.Item>
              </Col>
              <Col xs={12}>
                <Form.Item label="Tanggal Mulai" name="start_date" rules={[{ required: true, message: 'Isi tanggal mulai' }]}>
                  <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" />
                </Form.Item>
              </Col>
              <Col xs={12}>
                <Form.Item label="Tanggal Selesai" name="end_date" dependencies={['start_date']} rules={[endAfterStartRule]}>
                  <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" placeholder="Opsional" />
                </Form.Item>
              </Col>
            </Row>
            <Form.Item label="Catatan" name="notes">
              <Input.TextArea rows={3} maxLength={1000} showCount />
            </Form.Item>
          </Form>
        </>
      ) : null}
    </Modal>
  );
}

function ReassignModal({ record, onClose }) {
  const queryClient = useQueryClient();
  const [form] = Form.useForm();

  const reassign = useMutation({
    mutationFn: (values) => api.collectorAssignments.reassign(record.id, {
      new_collector_id: values.new_collector_id,
      reason: values.reason.trim(),
      start_date: toApiDate(values.start_date),
      notes: values.notes?.trim() || undefined,
    }),
    onSuccess: () => {
      message.success('Penugasan berhasil dipindahkan.');
      invalidateAssignmentData(queryClient);
      onClose();
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  return (
    <Modal
      title="Pindahkan Penugasan"
      open={Boolean(record)}
      onCancel={onClose}
      onOk={() => form.submit()}
      okText="Pindahkan"
      cancelText="Batal"
      confirmLoading={reassign.isPending}
      destroyOnHidden
    >
      {record ? (
        <Alert
          type="warning"
          showIcon
          style={{ marginBottom: 16 }}
          message={(
            <span>
              <strong>{scopeSummary(record)}</strong> akan dipindahkan dari <strong>{record.collector?.name || `#${record.collector_id}`}</strong>.
            </span>
          )}
          description="Penugasan lama ditandai “Dipindahkan” dan kolektor tujuan mendapat penugasan baru dengan cakupan yang sama."
        />
      ) : null}
      <Form form={form} layout="vertical" onFinish={reassign.mutate}>
        <Form.Item label="Kolektor Tujuan" name="new_collector_id" rules={[{ required: true, message: 'Pilih kolektor tujuan' }]}>
          <CollectorSelect exclude={record ? [record.collector_id] : []} placeholder="Pilih kolektor aktif" />
        </Form.Item>
        <Form.Item
          label="Alasan Pemindahan"
          name="reason"
          rules={[
            { required: true, whitespace: true, message: 'Alasan wajib diisi' },
            { min: 5, message: 'Alasan minimal 5 karakter' },
            { max: 500, message: 'Alasan maksimal 500 karakter' },
          ]}
        >
          <Input.TextArea rows={3} maxLength={500} showCount placeholder="Contoh: Kolektor lama cuti panjang" />
        </Form.Item>
        <Row gutter={12}>
          <Col xs={24} md={12}>
            <Form.Item label="Tanggal Mulai (opsional)" name="start_date" tooltip="Kosongkan untuk mulai hari ini.">
              <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" />
            </Form.Item>
          </Col>
        </Row>
        <Form.Item label="Catatan (opsional)" name="notes">
          <Input.TextArea rows={2} maxLength={1000} />
        </Form.Item>
      </Form>
    </Modal>
  );
}

// ---------------------------------------------------------------------------
// Tab 2 — Tugaskan (Manual / Massal / Area)
// ---------------------------------------------------------------------------

function AssignTab({ mode, onModeChange, preselect }) {
  return (
    <div className="stack">
      <Card size="small">
        <Space direction="vertical" size={8} style={{ width: '100%' }}>
          <Segmented block options={ASSIGN_MODES} value={mode} onChange={onModeChange} />
          <Typography.Text type="secondary">
            {mode === 'manual' && 'Satu penugasan untuk satu cakupan: cluster, blok, unit, atau penghuni tertentu.'}
            {mode === 'bulk' && `Pilih banyak unit sekaligus (maksimal ${MAX_BULK_UNITS}); setiap unit menjadi penugasan tersendiri.`}
            {mode === 'area' && 'Tugaskan seluruh cluster atau satu blok di dalam cluster ke satu kolektor.'}
          </Typography.Text>
        </Space>
      </Card>
      {mode === 'manual' ? <ManualAssignForm /> : null}
      {mode === 'bulk' ? <BulkAssignForm key={preselect.nonce} initialRows={preselect.rows} /> : null}
      {mode === 'area' ? <AreaAssignForm /> : null}
    </div>
  );
}

function useAssignmentPreview(payload) {
  const serialized = payload ? JSON.stringify(payload) : null;
  const debounced = useDebounce(serialized, 400);
  const settled = debounced === serialized;
  const query = useQuery({
    queryKey: ['collection', 'assignment-preview', debounced],
    queryFn: () => api.collection.assignmentPreview(JSON.parse(debounced)),
    enabled: Boolean(debounced) && settled,
    staleTime: 30 * 1000,
    retry: false,
  });
  return { query, enabled: Boolean(serialized), pending: Boolean(serialized) && !settled };
}

function PreviewCard({ preview, collectorId, emptyHint }) {
  const { query, enabled, pending } = preview;
  const data = query.data?.data;
  const others = (data?.current_collectors || []).filter((item) => Number(item.collector_id) !== Number(collectorId));

  let body;
  if (!enabled) {
    body = <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description={emptyHint} />;
  } else if (pending || query.isLoading) {
    body = <Skeleton active paragraph={{ rows: 4 }} />;
  } else if (query.isError) {
    body = <ErrorState error={{ message: getApiErrorMessage(query.error, 'Ringkasan cakupan gagal dimuat.') }} onRetry={() => query.refetch()} />;
  } else if (data) {
    body = (
      <Space direction="vertical" size={12} style={{ width: '100%' }}>
        <Row gutter={[12, 12]}>
          <Col xs={12}><Statistic title="Unit tercakup" value={data.unit_count ?? 0} /></Col>
          <Col xs={12}><Statistic title="Belum ditugaskan" value={data.unassigned_count ?? 0} /></Col>
          <Col xs={24}><Statistic title="Total tunggakan" value={data.outstanding_total ?? 0} formatter={(value) => formatCurrency(value)} /></Col>
          <Col xs={12}><Statistic title="Akun menunggak" value={data.overdue_accounts ?? 0} /></Col>
          <Col xs={12}><Statistic title="Akun kritis" value={data.critical_accounts ?? 0} /></Col>
        </Row>
        {Number(data.unit_count) === 0 ? (
          <Alert type="warning" showIcon message="Cakupan ini tidak mencakup unit apa pun." />
        ) : null}
        <div>
          <Typography.Text strong>Kolektor saat ini</Typography.Text>
          <div style={{ marginTop: 6 }}>
            {(data.current_collectors || []).length ? (
              <Space size={[4, 4]} wrap>
                {data.current_collectors.map((item) => (
                  <Tag key={item.collector_id} color={Number(item.collector_id) === Number(collectorId) ? 'green' : undefined}>
                    {item.name} · {item.unit_count} unit
                  </Tag>
                ))}
              </Space>
            ) : (
              <Typography.Text type="secondary">Belum ada kolektor yang memegang unit di cakupan ini.</Typography.Text>
            )}
          </div>
        </div>
        {others.length ? (
          <Alert
            type="info"
            showIcon
            message="Sebagian unit sudah dipegang kolektor lain."
            description="Penugasan yang lebih spesifik (unit > blok > cluster) akan diprioritaskan. Gunakan Pindahkan bila ingin mengganti kolektornya."
          />
        ) : null}
      </Space>
    );
  } else {
    body = <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Belum ada ringkasan." />;
  }

  return (
    <Card title="Ringkasan Cakupan" size="small">
      {body}
    </Card>
  );
}

function ScheduleFields() {
  return (
    <Row gutter={12}>
      <Col xs={24} md={8}>
        <Form.Item label="Prioritas" name="priority">
          <Select options={PRIORITY_OPTIONS} />
        </Form.Item>
      </Col>
      <Col xs={12} md={8}>
        <Form.Item label="Tanggal Mulai" name="start_date">
          <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" placeholder="Hari ini" />
        </Form.Item>
      </Col>
      <Col xs={12} md={8}>
        <Form.Item label="Tanggal Selesai" name="end_date" dependencies={['start_date']} rules={[endAfterStartRule]}>
          <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" placeholder="Opsional" />
        </Form.Item>
      </Col>
      <Col xs={24}>
        <Form.Item label="Catatan Penugasan" name="notes">
          <Input.TextArea rows={2} maxLength={1000} showCount />
        </Form.Item>
      </Col>
    </Row>
  );
}

function ClusterSelect(props) {
  const clusters = useClusterOptions();
  return <Select showSearch optionFilterProp="label" placeholder="Pilih cluster" options={clusters.options} loading={clusters.loading} {...props} />;
}

// Blok selalu satu string, diambil dari daftar blok unit di cluster terpilih.
function BlockSelect({ clusterId, ...props }) {
  const blocks = useBlockOptions(clusterId);
  return (
    <Select
      showSearch
      optionFilterProp="label"
      placeholder={clusterId ? 'Pilih blok' : 'Pilih cluster dulu'}
      disabled={!clusterId}
      options={clusterId ? blocks.options : []}
      loading={blocks.loading}
      notFoundContent={blocks.notFoundContent}
      {...props}
    />
  );
}

function ResidentSelect({ value, onChange }) {
  const [search, setSearch] = useState('');
  const debounced = useDebounce(search);
  const residents = useQuery({
    queryKey: ['residents', 'search', debounced],
    queryFn: () => api.residents.list({ search: debounced || undefined, per_page: 20 }),
  });
  const options = listItems(residents.data).map((resident) => ({ value: resident.id, label: `${resident.id} — ${resident.name}` }));

  return (
    <Select
      showSearch
      allowClear
      filterOption={false}
      placeholder="Cari ID atau nama penghuni"
      value={value}
      onChange={onChange}
      onSearch={setSearch}
      options={options}
      loading={residents.isFetching}
      notFoundContent={residents.isFetching ? 'Mencari...' : (residents.isError ? getApiErrorMessage(residents.error) : 'Tidak ditemukan')}
    />
  );
}

function AssignLayout({ form, preview, children }) {
  return (
    <Row gutter={[16, 16]}>
      <Col xs={24} lg={15}>
        <Card>{form}</Card>
        {children}
      </Col>
      <Col xs={24} lg={9}>
        {preview}
      </Col>
    </Row>
  );
}

function ManualAssignForm() {
  const queryClient = useQueryClient();
  const [form] = Form.useForm();
  const collectorId = Form.useWatch('collector_id', form);
  const scopeType = Form.useWatch('scope_type', form);
  const clusterId = Form.useWatch('cluster_id', form);
  const block = Form.useWatch('block', form);
  const unitId = Form.useWatch('unit_id', form);
  const residentId = Form.useWatch('resident_id', form);

  const previewPayload = useMemo(() => {
    if (scopeType === 'cluster' && clusterId) return { scope_type: 'cluster', cluster_id: clusterId };
    if (scopeType === 'block' && clusterId && block) return { scope_type: 'block', cluster_id: clusterId, block };
    if (scopeType === 'unit' && unitId) return { scope_type: 'unit', unit_id: unitId };
    if (scopeType === 'resident' && residentId) return { scope_type: 'resident', resident_id: residentId };
    return null;
  }, [scopeType, clusterId, block, unitId, residentId]);
  const preview = useAssignmentPreview(previewPayload);

  const create = useMutation({
    mutationFn: (values) => api.collectorAssignments.create({
      collector_id: values.collector_id,
      scope_type: values.scope_type,
      cluster_id: ['cluster', 'block'].includes(values.scope_type) ? values.cluster_id : undefined,
      block: values.scope_type === 'block' ? values.block : undefined,
      unit_id: values.scope_type === 'unit' ? values.unit_id : undefined,
      resident_id: values.scope_type === 'resident' ? values.resident_id : undefined,
      ...schedulePayload(values),
    }),
    onSuccess: () => {
      message.success('Penugasan kolektor berhasil dibuat.');
      invalidateAssignmentData(queryClient);
      form.setFieldsValue({ cluster_id: undefined, block: undefined, unit_id: undefined, resident_id: undefined, notes: undefined });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  function handleValuesChange(changed) {
    if ('scope_type' in changed) {
      form.setFieldsValue({ cluster_id: undefined, block: undefined, unit_id: undefined, resident_id: undefined });
    }
    if ('cluster_id' in changed) {
      form.setFieldsValue({ block: undefined });
    }
  }

  return (
    <AssignLayout
      preview={<PreviewCard preview={preview} collectorId={collectorId} emptyHint="Pilih jenis dan detail cakupan untuk melihat jumlah unit dan tunggakannya." />}
      form={(
        <Form
          form={form}
          layout="vertical"
          initialValues={{ ...initialScheduleValues(), scope_type: 'cluster' }}
          onValuesChange={handleValuesChange}
          onFinish={create.mutate}
        >
          <Row gutter={12}>
            <Col xs={24} md={12}>
              <Form.Item label="Kolektor" name="collector_id" rules={[{ required: true, message: 'Pilih kolektor' }]}>
                <CollectorSelect placeholder="Pilih kolektor aktif" />
              </Form.Item>
            </Col>
            <Col xs={24} md={12}>
              <Form.Item label="Jenis Cakupan" name="scope_type" rules={[{ required: true, message: 'Pilih jenis cakupan' }]}>
                <Select options={SCOPE_OPTIONS} />
              </Form.Item>
            </Col>
            {(scopeType === 'cluster' || scopeType === 'block') ? (
              <Col xs={24} md={12}>
                <Form.Item label="Cluster" name="cluster_id" rules={[{ required: true, message: 'Pilih cluster' }]}>
                  <ClusterSelect />
                </Form.Item>
              </Col>
            ) : null}
            {scopeType === 'block' ? (
              <Col xs={24} md={12}>
                <Form.Item label="Blok" name="block" rules={[{ required: true, message: 'Pilih blok' }]}>
                  <BlockSelect clusterId={clusterId} />
                </Form.Item>
              </Col>
            ) : null}
            {scopeType === 'unit' ? (
              <Col xs={24}>
                <Form.Item label="Unit" name="unit_id" rules={[{ required: true, message: 'Pilih unit' }]}>
                  <UnitPicker />
                </Form.Item>
              </Col>
            ) : null}
            {scopeType === 'resident' ? (
              <Col xs={24}>
                <Form.Item
                  label="Penghuni"
                  name="resident_id"
                  rules={[{ required: true, message: 'Pilih penghuni' }]}
                  extra="Penugasan mengikuti penghuni ke unit mana pun yang ia tempati."
                >
                  <ResidentSelect />
                </Form.Item>
              </Col>
            ) : null}
          </Row>
          <ScheduleFields />
          <Space wrap>
            <Button type="primary" htmlType="submit" icon={<UserAddOutlined />} loading={create.isPending}>Simpan Penugasan</Button>
            <Button onClick={() => form.resetFields()} disabled={create.isPending}>Reset</Button>
          </Space>
        </Form>
      )}
    />
  );
}

function BulkAssignForm({ initialRows = [] }) {
  const queryClient = useQueryClient();
  const [form] = Form.useForm();
  const collectorId = Form.useWatch('collector_id', form);
  const [selected, setSelected] = useState(() => new Map(initialRows.map((row) => [unitRowKey(row), row])));
  const [source, setSource] = useState('unassigned');
  const [result, setResult] = useState(null);
  const table = useTableState();

  const sourceParams = source === 'unassigned' ? table.params : { ...table.params, has_outstanding: undefined };
  const units = useQuery({
    queryKey: source === 'unassigned'
      ? ['collection', 'unassigned-units', sourceParams]
      : ['units', 'assign-picker', sourceParams],
    queryFn: () => (source === 'unassigned' ? api.collection.unassignedUnits(sourceParams) : api.units.list(sourceParams)),
  });

  const selectedIds = useMemo(() => Array.from(selected.keys()), [selected]);
  const preview = useAssignmentPreview(selectedIds.length ? { scope_type: 'units', unit_ids: selectedIds } : null);

  const bulk = useMutation({
    mutationFn: (payload) => api.collection.bulkAssign(payload),
    onSuccess: (response, payload) => {
      const data = response?.data || {};
      setResult({ data, payload });
      invalidateAssignmentData(queryClient);
      // Unit yang sudah beres (dibuat/dilewati/dipindahkan) dikeluarkan dari pilihan; konflik tetap dipilih.
      const conflictIds = new Set((data.conflicts || []).map((item) => item.unit_id));
      setSelected((current) => new Map(Array.from(current.entries()).filter(([key]) => conflictIds.has(key))));
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  function handleSelectionChange(keys, rows) {
    let nextKeys = keys;
    if (keys.length > MAX_BULK_UNITS) {
      message.warning(`Maksimal ${MAX_BULK_UNITS} unit per penugasan massal.`);
      nextKeys = keys.slice(0, MAX_BULK_UNITS);
    }
    setSelected((current) => {
      const next = new Map();
      nextKeys.forEach((key) => {
        const row = rows.find((item) => item && unitRowKey(item) === key);
        next.set(key, row || current.get(key) || { unit_id: key });
      });
      return next;
    });
  }

  function removeUnit(key) {
    setSelected((current) => {
      const next = new Map(current);
      next.delete(key);
      return next;
    });
  }

  function submit(values) {
    if (!selectedIds.length) {
      message.warning('Pilih minimal satu unit dari tabel.');
      return;
    }
    bulk.mutate({
      mode: 'units',
      collector_id: values.collector_id,
      unit_ids: selectedIds,
      ...schedulePayload(values),
    });
  }

  const selectedList = Array.from(selected.entries());
  const columns = [
    { title: 'Unit', key: 'unit', width: 120, render: (_, row) => unitRowKey(row) },
    { title: 'Alamat', key: 'address', render: (_, row) => unitAddress(row) || '-' },
    {
      title: 'Customer',
      key: 'customer',
      render: (_, row) => row.customer?.name || row.resident?.name || <Typography.Text type="secondary">Belum ada penghuni</Typography.Text>,
    },
    ...(source === 'unassigned' ? [
      { title: 'Tunggakan', dataIndex: 'outstanding_total', align: 'right', render: (value) => formatCurrency(value) },
      { title: 'Status', dataIndex: 'status', render: (value) => (value ? <StatusBadge type="collectionAccount" value={value} /> : '-') },
    ] : []),
  ];

  return (
    <>
      <AssignLayout
        preview={<PreviewCard preview={preview} collectorId={collectorId} emptyHint="Pilih unit dari tabel di bawah untuk melihat ringkasan cakupannya." />}
        form={(
          <Form form={form} layout="vertical" initialValues={initialScheduleValues()} onFinish={submit}>
            <Form.Item label="Kolektor" name="collector_id" rules={[{ required: true, message: 'Pilih kolektor' }]}>
              <CollectorSelect placeholder="Pilih kolektor aktif" />
            </Form.Item>
            <Form.Item label={`Unit terpilih (${selectedIds.length}/${MAX_BULK_UNITS})`} name="unit_ids" required>
              <div>
                {selectedList.length ? (
                  <Space size={[4, 4]} wrap>
                    {selectedList.slice(0, 30).map(([key]) => (
                      <Tag key={key} closable onClose={(event) => { event.preventDefault(); removeUnit(key); }}>{key}</Tag>
                    ))}
                    {selectedList.length > 30 ? <Tag>+{selectedList.length - 30} lainnya</Tag> : null}
                    <Button size="small" type="link" icon={<ClearOutlined />} onClick={() => setSelected(new Map())}>Kosongkan</Button>
                  </Space>
                ) : (
                  <Typography.Text type="secondary">Belum ada unit dipilih. Centang unit pada tabel di bawah.</Typography.Text>
                )}
              </div>
            </Form.Item>
            <ScheduleFields />
            <Button type="primary" htmlType="submit" icon={<UserAddOutlined />} loading={bulk.isPending} disabled={!selectedIds.length}>
              Tugaskan {selectedIds.length || ''} Unit
            </Button>
          </Form>
        )}
      >
        <Card
          title="Pilih Unit"
          style={{ marginTop: 16 }}
          extra={(
            <Segmented
              size="small"
              value={source}
              onChange={(value) => {
                setSource(value);
                table.setPagination((current) => ({ ...current, current: 1 }));
              }}
              options={[{ value: 'unassigned', label: 'Belum ditugaskan' }, { value: 'all', label: 'Semua unit' }]}
            />
          )}
        >
          <div className="filter-grid" style={{ marginBottom: 12 }}>
            <UnitFilterFields value={table.filters} onChange={(next) => table.setFilters(next)} hide={['unit_id']} />
            {source === 'unassigned' ? (
              <Space style={{ alignSelf: 'center' }}>
                <Switch
                  size="small"
                  checked={table.filters.has_outstanding === 1}
                  onChange={(checked) => table.setFilters({ ...table.filters, has_outstanding: checked ? 1 : undefined })}
                />
                <span>Hanya yang menunggak</span>
              </Space>
            ) : null}
          </div>
          <ResponsiveTable
            query={units}
            rowKey={unitRowKey}
            onChange={table.handleTableChange}
            columns={columns}
            scrollX={760}
            size="small"
            rowSelection={{
              selectedRowKeys: selectedIds,
              preserveSelectedRowKeys: true,
              onChange: handleSelectionChange,
              getCheckboxProps: (row) => ({
                disabled: selected.size >= MAX_BULK_UNITS && !selected.has(unitRowKey(row)),
              }),
            }}
            {...(units.isError ? {} : { locale: {
              emptyText: (
                <EmptyData
                  description={source === 'unassigned'
                    ? 'Tidak ada unit tanpa kolektor untuk filter ini. Pilih "Semua unit" untuk menugaskan ulang unit yang sudah dipegang.'
                    : 'Tidak ada unit yang cocok dengan filter.'}
                />
              ),
            }})}
          />
        </Card>
      </AssignLayout>

      <BulkResultModal
        result={result}
        retrying={bulk.isPending}
        onClose={() => setResult(null)}
        onRetry={(reason) => {
          const conflicts = result?.data?.conflicts || [];
          bulk.mutate({
            ...result.payload,
            unit_ids: conflicts.map((item) => item.unit_id),
            transfer_conflicts: true,
            reason,
          });
        }}
      />
    </>
  );
}

function AreaAssignForm() {
  const queryClient = useQueryClient();
  const [form] = Form.useForm();
  const collectorId = Form.useWatch('collector_id', form);
  const clusterId = Form.useWatch('cluster_id', form);
  const block = Form.useWatch('block', form);
  const [result, setResult] = useState(null);

  const previewPayload = useMemo(() => {
    if (!clusterId) return null;
    return block ? { scope_type: 'block', cluster_id: clusterId, block } : { scope_type: 'cluster', cluster_id: clusterId };
  }, [clusterId, block]);
  const preview = useAssignmentPreview(previewPayload);

  const bulk = useMutation({
    mutationFn: (payload) => api.collection.bulkAssign(payload),
    onSuccess: (response, payload) => {
      setResult({ data: response?.data || {}, payload });
      invalidateAssignmentData(queryClient);
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  function submit(values) {
    bulk.mutate({
      mode: 'area',
      collector_id: values.collector_id,
      cluster_id: values.cluster_id,
      block: values.block || undefined,
      ...schedulePayload(values),
    });
  }

  return (
    <>
      <AssignLayout
        preview={<PreviewCard preview={preview} collectorId={collectorId} emptyHint="Pilih cluster (dan blok bila perlu) untuk melihat ringkasan area." />}
        form={(
          <Form
            form={form}
            layout="vertical"
            initialValues={initialScheduleValues()}
            onValuesChange={(changed) => {
              if ('cluster_id' in changed) form.setFieldsValue({ block: undefined });
            }}
            onFinish={submit}
          >
            <Row gutter={12}>
              <Col xs={24}>
                <Form.Item label="Kolektor" name="collector_id" rules={[{ required: true, message: 'Pilih kolektor' }]}>
                  <CollectorSelect placeholder="Pilih kolektor aktif" />
                </Form.Item>
              </Col>
              <Col xs={24} md={12}>
                <Form.Item label="Cluster" name="cluster_id" rules={[{ required: true, message: 'Pilih cluster' }]}>
                  <ClusterSelect />
                </Form.Item>
              </Col>
              <Col xs={24} md={12}>
                <Form.Item label="Blok (opsional)" name="block" extra="Kosongkan untuk menugaskan seluruh cluster.">
                  <BlockSelect clusterId={clusterId} allowClear />
                </Form.Item>
              </Col>
            </Row>
            <ScheduleFields />
            <Button type="primary" htmlType="submit" icon={<UserAddOutlined />} loading={bulk.isPending}>
              {block ? `Tugaskan Blok ${block}` : 'Tugaskan Seluruh Cluster'}
            </Button>
          </Form>
        )}
      />
      <BulkResultModal result={result} onClose={() => setResult(null)} />
    </>
  );
}

function BulkResultModal({ result, onClose, onRetry, retrying = false }) {
  const [form] = Form.useForm();
  const data = result?.data || {};
  const created = data.created || [];
  const skipped = data.skipped || [];
  const conflicts = data.conflicts || [];
  const transferred = data.transferred || [];
  const nothingDone = !created.length && !transferred.length;

  function confirmRetry(values) {
    Modal.confirm({
      title: `Pindahkan ${conflicts.length} unit dari kolektor lain?`,
      content: 'Penugasan kolektor lama untuk unit-unit ini akan ditandai "Dipindahkan" dan diganti ke kolektor yang Anda pilih.',
      okText: 'Pindahkan',
      cancelText: 'Batal',
      okButtonProps: { danger: true },
      onOk: () => onRetry(values.reason.trim()),
    });
  }

  return (
    <Modal
      title="Hasil Penugasan"
      open={Boolean(result)}
      onCancel={onClose}
      footer={<Button onClick={onClose}>Tutup</Button>}
      width={640}
      destroyOnHidden
    >
      <Space direction="vertical" size={16} style={{ width: '100%' }}>
        <Alert
          type={nothingDone ? 'warning' : (conflicts.length ? 'info' : 'success')}
          showIcon
          message={nothingDone ? 'Tidak ada penugasan baru yang dibuat.' : 'Penugasan berhasil diproses.'}
        />
        <Row gutter={[12, 12]}>
          <Col xs={12} md={6}><Statistic title="Dibuat" value={created.length} /></Col>
          <Col xs={12} md={6}><Statistic title="Dipindahkan" value={transferred.length} /></Col>
          <Col xs={12} md={6}><Statistic title="Dilewati" value={skipped.length} /></Col>
          <Col xs={12} md={6}><Statistic title="Konflik" value={conflicts.length} /></Col>
        </Row>
        {skipped.length ? (
          <div>
            <Typography.Text strong>Dilewati (sudah dipegang kolektor ini)</Typography.Text>
            <div style={{ marginTop: 6 }}>
              <Space size={[4, 4]} wrap>
                {skipped.slice(0, 50).map((unitId) => <Tag key={unitId}>{unitId}</Tag>)}
                {skipped.length > 50 ? <Tag>+{skipped.length - 50} lainnya</Tag> : null}
              </Space>
            </div>
          </div>
        ) : null}
        {conflicts.length ? (
          <div>
            <Typography.Text strong>Konflik (dipegang kolektor lain)</Typography.Text>
            <Table
              size="small"
              rowKey={(row) => `${row.unit_id}-${row.assignment_id}`}
              dataSource={conflicts}
              pagination={conflicts.length > 10 ? { pageSize: 10, size: 'small' } : false}
              style={{ marginTop: 6 }}
              columns={[
                { title: 'Unit', dataIndex: 'unit_id' },
                { title: 'Dipegang oleh', dataIndex: 'collector_name', render: (value, row) => value || `#${row.collector_id}` },
              ]}
            />
          </div>
        ) : null}
        {conflicts.length && onRetry ? (
          <Can permission="collector.reassign" fallback={<Typography.Text type="secondary">Hubungi pengguna dengan izin pemindahan penugasan untuk memindahkan unit konflik.</Typography.Text>}>
            <Card size="small" title="Pindahkan unit konflik ke kolektor ini">
              <Form form={form} layout="vertical" onFinish={confirmRetry}>
                <Form.Item
                  label="Alasan Pemindahan"
                  name="reason"
                  rules={[
                    { required: true, whitespace: true, message: 'Alasan wajib diisi' },
                    { min: 5, message: 'Alasan minimal 5 karakter' },
                    { max: 500, message: 'Alasan maksimal 500 karakter' },
                  ]}
                >
                  <Input.TextArea rows={2} maxLength={500} showCount placeholder="Contoh: Penataan ulang wilayah kerja" />
                </Form.Item>
                <Button type="primary" danger htmlType="submit" icon={<SwapOutlined />} loading={retrying}>
                  Pindahkan {conflicts.length} Unit
                </Button>
              </Form>
            </Card>
          </Can>
        ) : null}
      </Space>
    </Modal>
  );
}

// ---------------------------------------------------------------------------
// Tab 3 — Unit Belum Ditugaskan
// ---------------------------------------------------------------------------

function UnassignedUnitsTab({ canAssign, onAssign }) {
  const table = useTableState();
  const [selected, setSelected] = useState(() => new Map());
  const units = useQuery({
    queryKey: ['collection', 'unassigned-units', table.params],
    queryFn: () => api.collection.unassignedUnits(table.params),
  });

  const filters = table.filters;
  const hasFilters = Object.values(filters).some((value) => value !== undefined && value !== null && value !== '');

  function handleSelectionChange(keys, rows) {
    setSelected((current) => {
      const next = new Map();
      keys.slice(0, MAX_BULK_UNITS).forEach((key) => {
        next.set(key, rows.find((item) => item && unitRowKey(item) === key) || current.get(key) || { unit_id: key });
      });
      return next;
    });
    if (keys.length > MAX_BULK_UNITS) message.warning(`Maksimal ${MAX_BULK_UNITS} unit per penugasan massal.`);
  }

  const columns = [
    { title: 'Unit', dataIndex: 'unit_id', width: 120 },
    { title: 'Cluster', key: 'cluster', width: 160, render: (_, row) => row.cluster_name || row.cluster_id || '-' },
    { title: 'Blok / No', key: 'lot', width: 110, render: (_, row) => [row.block, row.lot_number].filter(Boolean).join(' / ') || '-' },
    {
      title: 'Customer',
      key: 'customer',
      width: 200,
      render: (_, row) => (row.customer ? (
        <Space direction="vertical" size={0}>
          <span>{row.customer.name}</span>
          {row.customer.phone ? <Typography.Text type="secondary">{row.customer.phone}</Typography.Text> : null}
        </Space>
      ) : <Typography.Text type="secondary">Belum ada penghuni</Typography.Text>),
    },
    { title: 'Tunggakan', dataIndex: 'outstanding_total', width: 150, align: 'right', render: (value) => formatCurrency(value) },
    { title: 'Status', dataIndex: 'status', width: 130, render: (value) => (value ? <StatusBadge type="collectionAccount" value={value} /> : '-') },
    { title: 'Prioritas', dataIndex: 'priority_level', width: 110, render: (value) => (value ? <StatusBadge type="priorityLevel" value={value} /> : '-') },
    ...(canAssign ? [{
      title: 'Aksi',
      key: 'actions',
      fixed: 'right',
      width: 120,
      render: (_, row) => (
        <Button size="small" icon={<UserAddOutlined />} onClick={() => onAssign([row])}>Tugaskan</Button>
      ),
    }] : []),
  ];

  return (
    <>
      <FilterBar
        extra={(
          <Space wrap>
            {hasFilters ? <Button icon={<ClearOutlined />} onClick={() => table.setFilters({})}>Reset</Button> : null}
            {canAssign ? (
              <Button
                type="primary"
                icon={<UserAddOutlined />}
                disabled={!selected.size}
                onClick={() => onAssign(Array.from(selected.values()))}
              >
                Tugaskan {selected.size ? `${selected.size} Unit` : 'Terpilih'}
              </Button>
            ) : null}
          </Space>
        )}
      >
        <UnitFilterFields value={filters} onChange={(next) => table.setFilters(next)} />
        <Space style={{ alignSelf: 'center' }}>
          <Switch
            size="small"
            checked={filters.has_outstanding === 1}
            onChange={(checked) => table.setFilters({ ...filters, has_outstanding: checked ? 1 : undefined })}
          />
          <span>Hanya yang menunggak</span>
        </Space>
      </FilterBar>

      <Card>
        <ResponsiveTable
          query={units}
          rowKey={unitRowKey}
          onChange={table.handleTableChange}
          columns={columns}
          scrollX={1100}
          rowSelection={canAssign ? {
            selectedRowKeys: Array.from(selected.keys()),
            preserveSelectedRowKeys: true,
            onChange: handleSelectionChange,
          } : undefined}
          {...(units.isError ? {} : { locale: {
            emptyText: (
              <EmptyData
                description={hasFilters
                  ? 'Tidak ada unit tanpa kolektor untuk filter ini.'
                  : 'Semua unit dalam wilayah Anda sudah memiliki kolektor.'}
              />
            ),
          }})}
        />
      </Card>
    </>
  );
}
