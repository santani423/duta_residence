import {
  Alert, Avatar, Button, Card, Col, DatePicker, Descriptions, Empty, Form, Image, Input, Modal, Row, Select, Space, Table, Tabs,
  Tag, Typography, message,
} from 'antd';
import { SwapOutlined, UserOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';
import { useParams } from 'react-router-dom';
import dayjs from 'dayjs';
import L from 'leaflet';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import StatusBadge, { statusOptions } from '../components/common/StatusBadge.jsx';
import { UnitFilterFields } from '../components/common/UnitFilters.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { EmptyData, ErrorState } from '../components/common/ApiState.jsx';
import StatCard from '../components/collection/StatCard.jsx';
import CollectorSelect from '../components/collection/CollectorSelect.jsx';
import { api, storageUrl } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { useAuth } from '../state/AuthContext.jsx';
import { formatCurrency, formatDate, formatDateTime } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';

const markerIcon = L.icon({
  iconRetinaUrl, iconUrl, shadowUrl,
  iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41],
});

const SCOPE_LABELS = { cluster: 'Cluster', block: 'Blok', unit: 'Unit', resident: 'Penghuni' };
const PRIORITY_LABELS = { low: 'Rendah', normal: 'Normal', high: 'Tinggi', urgent: 'Mendesak', critical: 'Kritis' };
const PERIOD_LABELS = { daily: 'Harian', weekly: 'Mingguan', monthly: 'Bulanan' };
const EMPLOYMENT_LABELS = { tetap: 'Tetap', kontrak: 'Kontrak', harian: 'Harian' };
const PROMISE_STATUS_LABELS = {
  pending: 'Menunggu', fulfilled: 'Ditepati', broken: 'Diingkari', rescheduled: 'Dijadwalkan Ulang', cancelled: 'Dibatalkan',
};
const SORTABLE_ACCOUNT_FIELDS = { outstanding_total: 'outstanding_total', aging_days: 'aging_days', last_contact_at: 'last_contact_at', priority_score: 'priority_score' };

// Query yang terpengaruh saat penugasan dipindahkan.
const ASSIGNMENT_AFFECTED_QUERY_KEYS = ['collectors', 'collector-assignments', 'collection', 'collector-performance', 'supervisor-collectors'];

function scopeSummary(record) {
  if (record.scope_type === 'cluster') {
    const name = record.cluster?.name || record.cluster_id;
    // Nama cluster umumnya sudah diawali "Cluster" (mis. "Cluster Alamanda").
    return /^cluster\b/i.test(String(name)) ? name : `Cluster ${name}`;
  }
  if (record.scope_type === 'block') return `${record.cluster?.name || record.cluster_id} — Blok ${record.block}`;
  if (record.scope_type === 'unit') return `Unit ${record.unit_id}`;
  if (record.scope_type === 'resident') return `Penghuni ${record.resident?.name || record.resident_id}`;
  return '-';
}

function formatTime(value) {
  if (!value || typeof value !== 'string') return null;
  return value.slice(0, 5);
}

function photoUrlOf(profile) {
  return profile?.photo_url || storageUrl(profile?.latest_photo?.path || profile?.photos?.[0]?.path) || null;
}

function reassignedFromName(record) {
  return record.reassigned_from?.collector?.name || record.reassigned_from_collector?.name || null;
}

function ReassignOrigin({ record }) {
  const from = reassignedFromName(record);
  if (!from && !record.reassign_reason) return <Typography.Text type="secondary">Penugasan langsung</Typography.Text>;
  return (
    <Space direction="vertical" size={0}>
      {from ? <Typography.Text>Dipindahkan dari <strong>{from}</strong></Typography.Text> : null}
      {record.reassign_reason ? <Typography.Text type="secondary">Alasan: {record.reassign_reason}</Typography.Text> : null}
    </Space>
  );
}

function unitLabel(unit) {
  if (!unit) return '-';
  const address = [unit.cluster_name || unit.cluster_id, unit.block && `Blok ${unit.block}`, unit.lot_number && `No ${unit.lot_number}`].filter(Boolean).join(' · ');
  return (
    <Space direction="vertical" size={0}>
      <Typography.Text strong>{unit.id}</Typography.Text>
      {address ? <Typography.Text type="secondary">{address}</Typography.Text> : null}
    </Space>
  );
}

export default function CollectorDetailPage() {
  const { id } = useParams();
  const queryClient = useQueryClient();
  const { can } = useAuth();
  const [reassignModal, setReassignModal] = useState(null);
  const [reassignForm] = Form.useForm();
  const [historyPage, setHistoryPage] = useState({ page: 1, per_page: 20 });

  const detail = useQuery({ queryKey: ['collectors', id], queryFn: () => api.collectors.detail(id) });
  // Log audit penugasan, dipaginasi backend (default 20, maks 100).
  const history = useQuery({
    queryKey: ['collectors', id, 'assignment-history', historyPage],
    queryFn: () => api.collectors.assignmentHistory(id, historyPage),
  });

  const reassign = useMutation({
    mutationFn: ({ assignmentId, values }) => api.collectorAssignments.reassign(assignmentId, {
      new_collector_id: values.new_collector_id,
      reason: values.reason,
      start_date: values.start_date ? values.start_date.format('YYYY-MM-DD') : undefined,
      notes: values.notes || undefined,
    }),
    onSuccess: () => {
      message.success('Penugasan berhasil dipindahkan.');
      setReassignModal(null);
      ASSIGNMENT_AFFECTED_QUERY_KEYS.forEach((key) => queryClient.invalidateQueries({ queryKey: [key] }));
    },
    onError: (error) => {
      reassignForm.setFields(mapValidationErrors(error).filter((field) => ['new_collector_id', 'reason', 'start_date', 'notes'].includes(field.name)));
      message.error(getApiErrorMessage(error));
    },
  });

  const breadcrumbsBase = [{ label: 'Manajemen Kolektor' }, { label: 'Data Kolektor', to: '/admin/collectors/list' }];

  if (detail.isLoading) {
    return (
      <section>
        <PageHeader title="Detail Kolektor" breadcrumbs={[...breadcrumbsBase, { label: 'Memuat…' }]} />
        <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
          {[0, 1, 2, 3].map((key) => (
            <Col key={key} xs={24} sm={12} lg={6}><StatCard loading title="" /></Col>
          ))}
        </Row>
        <Card loading />
      </section>
    );
  }

  if (detail.isError) {
    return (
      <section>
        <PageHeader title="Detail Kolektor" breadcrumbs={[...breadcrumbsBase, { label: 'Detail' }]} />
        <ErrorState error={detail.error} onRetry={detail.refetch} />
      </section>
    );
  }

  const payload = detail.data?.data || {};
  const collector = payload.collector || {};
  const profile = collector.collector_profile || {};
  // GET /collectors/{id} → summary datar: beban akun (collection_account_states, diiris ke cakupan
  // supervisor) + metrik bulan berjalan (collected_this_month, visit_count, ptp_*) + period {type,start,end}.
  const summary = payload.summary || {};
  const pick = (...keys) => {
    for (const key of keys) {
      if (summary[key] !== undefined && summary[key] !== null) return summary[key];
    }
    return null;
  };
  const photoUrl = photoUrlOf(profile);
  const assignments = payload.assignments || [];
  const collected = pick('collected_this_month');
  const target = pick('target_this_month');
  const achievement = pick('achievement_percent_raw');
  const achievementValue = achievement !== null ? Number(achievement) : (Number(target) > 0 ? (Number(collected || 0) / Number(target)) * 100 : null);
  const visitCount = pick('visit_count');
  const successRate = pick('successful_visit_rate');
  const ptpCreated = pick('ptp_created');
  const ptpFulfilled = pick('ptp_fulfilled');
  const ptpBroken = pick('ptp_broken');
  const totalUnits = pick('total_units');
  // Termasuk penugasan aktif yang dijadwalkan mulai nanti (definisi sama dengan guard nonaktif/hapus).
  const activeAssignmentCount = pick('active_assignment_count') ?? assignments.length;
  const scheduledCount = Math.max(0, Number(activeAssignmentCount) - assignments.length);
  const periodLabel = summary.period?.start
    ? `${formatDate(summary.period.start)} – ${formatDate(summary.period.end)}`
    : null;
  const dutyStart = formatTime(profile.duty_start_time);
  const dutyEnd = formatTime(profile.duty_end_time);

  function openReassign(record) {
    reassignForm.resetFields();
    reassignForm.setFieldsValue({ start_date: dayjs() });
    setReassignModal(record);
  }

  const tabs = [
    {
      key: 'profile',
      label: 'Profil',
      children: (
        <Card>
          <Row gutter={[16, 16]}>
            <Col xs={24} md={5} style={{ textAlign: 'center' }}>
              {photoUrl ? (
                <Image src={photoUrl} alt={`Foto ${collector.name || ''}`} style={{ width: '100%', maxWidth: 200, borderRadius: 8, objectFit: 'cover' }} />
              ) : (
                <Avatar size={120} shape="square" icon={<UserOutlined />} />
              )}
            </Col>
            <Col xs={24} md={19}>
              <Descriptions bordered column={{ xs: 1, md: 2 }}>
                <Descriptions.Item label="Nama">{collector.name}</Descriptions.Item>
                <Descriptions.Item label="Username">{collector.username || '-'}</Descriptions.Item>
                <Descriptions.Item label="Email">{collector.email || '-'}</Descriptions.Item>
                <Descriptions.Item label="Telepon">{collector.phone || '-'}</Descriptions.Item>
                <Descriptions.Item label="WhatsApp">{profile.whatsapp_number || '-'}</Descriptions.Item>
                <Descriptions.Item label="Alamat">{profile.address || '-'}</Descriptions.Item>
                <Descriptions.Item label="Tanggal Bergabung">{formatDate(profile.joined_at)}</Descriptions.Item>
                <Descriptions.Item label="Jam Tugas">{dutyStart ? `${dutyStart} – ${dutyEnd || '?'}` : 'Belum diatur'}</Descriptions.Item>
                <Descriptions.Item label="Status Kepegawaian">{EMPLOYMENT_LABELS[profile.employment_status] || profile.employment_status || '-'}</Descriptions.Item>
                <Descriptions.Item label="Status Akun"><StatusBadge type="collectorAccount" value={profile.account_status} /></Descriptions.Item>
                <Descriptions.Item label="Wilayah Kerja" span={2}>{profile.working_area_notes || '-'}</Descriptions.Item>
                {profile.admin_notes !== undefined ? (
                  <Descriptions.Item label="Catatan Admin" span={2}>
                    <Typography.Paragraph style={{ whiteSpace: 'pre-line', marginBottom: 0 }}>{profile.admin_notes || '-'}</Typography.Paragraph>
                  </Descriptions.Item>
                ) : null}
              </Descriptions>
            </Col>
          </Row>
        </Card>
      ),
    },
    can('collector-monitoring.view') ? {
      key: 'portfolio',
      label: 'Portofolio Akun',
      children: <PortfolioTab collectorId={id} />,
    } : null,
    {
      key: 'assignments',
      label: `Wilayah & Penugasan (${assignments.length})`,
      children: (
        <div className="stack">
          <Card title="Penugasan Aktif">
            {assignments.length ? (
              <Table
                rowKey="id"
                dataSource={assignments}
                pagination={false}
                scroll={{ x: 900 }}
                columns={[
                  { title: 'Cakupan', width: 100, render: (_, record) => <Tag>{SCOPE_LABELS[record.scope_type] || record.scope_type}</Tag> },
                  { title: 'Detail', render: (_, record) => scopeSummary(record) },
                  { title: 'Prioritas', dataIndex: 'priority', width: 100, render: (value) => PRIORITY_LABELS[value] || value || '-' },
                  { title: 'Mulai', dataIndex: 'start_date', width: 120, render: (value) => formatDate(value) },
                  { title: 'Selesai', dataIndex: 'end_date', width: 120, render: (value) => formatDate(value, 'Tanpa batas') },
                  { title: 'Asal Penugasan', width: 240, render: (_, record) => <ReassignOrigin record={record} /> },
                  can('collector.reassign') ? {
                    title: 'Aksi',
                    width: 120,
                    fixed: 'right',
                    render: (_, record) => (
                      <Button size="small" icon={<SwapOutlined />} onClick={() => openReassign(record)}>Pindahkan</Button>
                    ),
                  } : null,
                ].filter(Boolean)}
              />
            ) : (
              <Empty description="Kolektor ini belum memegang penugasan aktif. Tambahkan dari menu Penugasan Kolektor." />
            )}
          </Card>
          {can('collector-assignments.view') ? <AssignmentListCard collectorId={id} /> : null}
        </div>
      ),
    },
    {
      key: 'targets',
      label: 'Target',
      children: (
        <Card>
          <Table
            rowKey="id"
            dataSource={payload.targets || []}
            pagination={false}
            scroll={{ x: 760 }}
            locale={{ emptyText: <EmptyData description="Belum ada target untuk kolektor ini. Atur di menu Target Kolektor." /> }}
            columns={[
              { title: 'Periode', dataIndex: 'period_type', render: (value) => PERIOD_LABELS[value] || value || '-' },
              { title: 'Mulai', dataIndex: 'period_start', render: (value) => formatDate(value) },
              { title: 'Target Nominal', dataIndex: 'target_amount', render: formatCurrency },
              { title: 'Target Kunjungan', dataIndex: 'target_visit_count', render: (value) => value ?? '-' },
              { title: 'Target Akun', dataIndex: 'target_account_count', render: (value) => value ?? '-' },
              { title: 'Target Collection Rate', dataIndex: 'target_collection_rate', render: (value) => (value === null || value === undefined ? '-' : `${value}%`) },
            ]}
          />
        </Card>
      ),
    },
    {
      key: 'history',
      label: 'Aktivitas Terakhir',
      children: (
        <Row gutter={[16, 16]}>
          <Col xs={24} lg={12}>
            <div className="stack">
              <Card title={`Kunjungan Terakhir (${summary.total_visits ?? 0} total)`}>
                {(payload.recent_visits || []).length ? (
                  <Table
                    size="small"
                    rowKey="id"
                    dataSource={payload.recent_visits}
                    pagination={false}
                    scroll={{ x: 480 }}
                    columns={[
                      { title: 'Unit', dataIndex: 'unit_id' },
                      { title: 'Tujuan', dataIndex: 'purpose' },
                      { title: 'Status', dataIndex: 'status' },
                      { title: 'Tanggal', dataIndex: 'visit_date', render: (value) => formatDateTime(value) },
                    ]}
                  />
                ) : <Empty description="Belum ada kunjungan tercatat." />}
              </Card>
              <Card title={`Janji Bayar (${summary.total_payment_promises ?? 0} total)`}>
                {(payload.recent_payment_promises || []).length ? (
                  <Table
                    size="small"
                    rowKey="id"
                    dataSource={payload.recent_payment_promises}
                    pagination={false}
                    scroll={{ x: 480 }}
                    columns={[
                      { title: 'Unit', dataIndex: 'unit_id' },
                      { title: 'Jumlah', dataIndex: 'promised_amount', render: formatCurrency },
                      { title: 'Status', dataIndex: 'status', render: (value) => PROMISE_STATUS_LABELS[value] || value || '-' },
                      { title: 'Tanggal Janji', dataIndex: 'promised_date', render: (value) => formatDate(value) },
                    ]}
                  />
                ) : <Empty description="Belum ada janji pembayaran." />}
              </Card>
            </div>
          </Col>
          <Col xs={24} lg={12}>
            <Card title={`Komplain (${summary.total_complaints ?? 0} total)`}>
              {(payload.recent_complaints || []).length ? (
                <Table
                  size="small"
                  rowKey="id"
                  dataSource={payload.recent_complaints}
                  pagination={false}
                  scroll={{ x: 420 }}
                  columns={[
                    { title: 'Unit', dataIndex: 'unit_id' },
                    { title: 'Deskripsi', dataIndex: 'description', ellipsis: true },
                    { title: 'Status', dataIndex: 'status' },
                  ]}
                />
              ) : <Empty description="Belum ada komplain." />}
            </Card>
          </Col>
        </Row>
      ),
    },
    {
      key: 'assignment-history',
      label: 'Log Penugasan',
      children: (
        <Card>
          <ResponsiveTable
            query={history}
            scrollX={720}
            onChange={(pagination) => setHistoryPage({ page: pagination.current || 1, per_page: pagination.pageSize || 20 })}
            {...(history.isError ? {} : { locale: { emptyText: <EmptyData description="Belum ada log perubahan penugasan." /> } })}
            columns={[
              { title: 'Waktu', dataIndex: 'created_at', width: 170, render: (value) => formatDateTime(value) },
              { title: 'Aktivitas', dataIndex: 'activity' },
              { title: 'Aksi', dataIndex: 'action', width: 220 },
              { title: 'Oleh', dataIndex: 'user_name', width: 160, render: (value) => value || '-' },
            ]}
          />
        </Card>
      ),
    },
    {
      key: 'location',
      label: 'Lokasi Terakhir',
      children: <LocationTab location={payload.latest_location} />,
    },
  ].filter(Boolean);

  return (
    <section>
      <PageHeader
        title={collector.name || 'Detail Kolektor'}
        subtitle={(
          <Space wrap size={8}>
            <span>Kode Kolektor: {profile.collector_code || '-'}</span>
            <StatusBadge type="collectorAccount" value={profile.account_status} />
          </Space>
        )}
        breadcrumbs={[...breadcrumbsBase, { label: collector.name || 'Detail' }]}
        onRefresh={() => { detail.refetch(); history.refetch(); }}
        loading={detail.isFetching}
      />

      <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
        <Col xs={24} sm={12} lg={6}>
          <StatCard
            title="Akun Kelolaan"
            value={pick('assigned_accounts')}
            format="number"
            hint="Unit yang saat ini menjadikan kolektor ini sebagai penagih utama."
            footer={totalUnits !== null ? <Typography.Text type="secondary">{totalUnits} unit dalam cakupan penugasan</Typography.Text> : null}
          />
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <StatCard title="Total Tunggakan" value={pick('outstanding_total', 'total_outstanding')} format="currency" hint="Pokok + denda dari akun kelolaan." />
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <StatCard title="Akun Menunggak" value={pick('overdue_accounts')} format="number" />
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <StatCard title="Akun Prioritas Kritis" value={pick('critical_accounts')} format="number" />
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <StatCard
            title="Tertagih Bulan Ini"
            value={collected}
            format="currency"
            hint={periodLabel ? `Periode ${periodLabel}. Pembayaran lunas yang ditagih kolektor ini.` : undefined}
            progress={Number(target) > 0 ? achievementValue : null}
            status={achievementValue >= 100 ? 'good' : (achievementValue >= 50 ? 'warning' : 'critical')}
            footer={<Typography.Text type="secondary">{Number(target) > 0 ? `Target ${formatCurrency(target)}` : 'Target bulan ini belum diatur'}</Typography.Text>}
          />
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <StatCard
            title="Kunjungan Bulan Ini"
            value={visitCount}
            format="number"
            footer={successRate !== null ? <Typography.Text type="secondary">Berhasil {Number(successRate).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%</Typography.Text> : null}
          />
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <StatCard
            title="Janji Bayar Bulan Ini"
            value={ptpCreated}
            format="number"
            footer={ptpFulfilled !== null || ptpBroken !== null
              ? <Typography.Text type="secondary">Ditepati {ptpFulfilled ?? 0} · Ingkar {ptpBroken ?? 0}</Typography.Text>
              : null}
          />
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <StatCard
            title="Penugasan Aktif"
            value={activeAssignmentCount}
            format="number"
            footer={scheduledCount > 0
              ? <Typography.Text type="secondary">{scheduledCount} dijadwalkan mulai nanti</Typography.Text>
              : null}
          />
        </Col>
      </Row>

      <Tabs items={tabs} destroyOnHidden={false} />

      <Modal
        title="Pindahkan Penugasan"
        open={Boolean(reassignModal)}
        onCancel={() => setReassignModal(null)}
        onOk={() => reassignForm.submit()}
        okText="Pindahkan"
        cancelText="Batal"
        confirmLoading={reassign.isPending}
        destroyOnHidden
      >
        {reassignModal ? (
          <Alert
            type="info"
            showIcon
            style={{ marginBottom: 16 }}
            message={<>Memindahkan <strong>{scopeSummary(reassignModal)}</strong> dari {collector.name} ke kolektor lain.</>}
            description="Penugasan lama ditandai Dipindahkan dan berakhir hari ini. Akun penagihan pada cakupan ini berpindah ke kolektor tujuan."
          />
        ) : null}
        <Form
          form={reassignForm}
          layout="vertical"
          onFinish={(values) => reassign.mutate({ assignmentId: reassignModal.id, values })}
        >
          <Form.Item
            label="Kolektor Tujuan"
            name="new_collector_id"
            rules={[{ required: true, message: 'Pilih kolektor tujuan' }]}
            extra="Hanya kolektor aktif yang ditampilkan."
          >
            <CollectorSelect exclude={[collector.id]} placeholder="Pilih kolektor aktif" />
          </Form.Item>
          <Form.Item
            label="Alasan Pemindahan"
            name="reason"
            rules={[
              { required: true, message: 'Alasan wajib diisi' },
              { min: 5, message: 'Alasan minimal 5 karakter' },
              { max: 500, message: 'Alasan maksimal 500 karakter' },
            ]}
          >
            <Input.TextArea rows={3} showCount maxLength={500} placeholder="Contoh: Rotasi wilayah / kolektor cuti panjang" />
          </Form.Item>
          <Form.Item label="Mulai Berlaku" name="start_date">
            <DatePicker style={{ width: '100%' }} format="DD MMM YYYY" />
          </Form.Item>
          <Form.Item label="Catatan (opsional)" name="notes">
            <Input.TextArea rows={2} />
          </Form.Item>
        </Form>
      </Modal>
    </section>
  );
}

// Daftar akun penagihan yang saat ini dipegang kolektor (GET /collection/accounts?collector_id=).
function PortfolioTab({ collectorId }) {
  const table = useTableState({ has_outstanding: 1 });
  const [sort, setSort] = useState({ sort: 'priority_score', direction: 'desc' });
  const params = { ...table.params, ...sort, collector_id: collectorId };
  const accounts = useQuery({
    queryKey: ['collection', 'accounts', params],
    queryFn: () => api.collection.accounts(params),
  });
  const hasFilters = Boolean(table.search || table.filters.status || table.filters.priority || table.filters.cluster_id || table.filters.block || table.filters.unit_id);
  const summary = accounts.data?.summary;

  function handleChange(pagination, _filters, sorter) {
    table.handleTableChange(pagination);
    const field = SORTABLE_ACCOUNT_FIELDS[sorter?.columnKey];
    setSort(field && sorter.order
      ? { sort: field, direction: sorter.order === 'ascend' ? 'asc' : 'desc' }
      : { sort: 'priority_score', direction: 'desc' });
  }

  const sortOrder = (key) => (sort.sort === key ? (sort.direction === 'asc' ? 'ascend' : 'descend') : null);

  return (
    <div className="stack">
      <FilterBar>
        <Input
          allowClear
          placeholder="Cari unit, nama, atau telepon"
          value={table.search}
          onChange={(event) => table.setSearch(event.target.value)}
          className="filter-input"
        />
        <UnitFilterFields value={table.filters} onChange={table.setFilters} hide={['customer', 'address']} />
        <Select
          allowClear
          mode="multiple"
          maxTagCount="responsive"
          placeholder="Status akun"
          options={statusOptions('collectionAccount')}
          value={table.filters.status}
          onChange={(value) => table.setFilters({ ...table.filters, status: value?.length ? value : undefined })}
          className="filter-input"
        />
        <Select
          allowClear
          mode="multiple"
          maxTagCount="responsive"
          placeholder="Prioritas"
          options={statusOptions('priorityLevel')}
          value={table.filters.priority}
          onChange={(value) => table.setFilters({ ...table.filters, priority: value?.length ? value : undefined })}
          className="filter-input"
        />
        <Select
          options={[{ value: 1, label: 'Hanya yang ada tunggakan' }, { value: 0, label: 'Semua akun' }]}
          value={table.filters.has_outstanding}
          onChange={(value) => table.setFilters({ ...table.filters, has_outstanding: value })}
          className="filter-input"
        />
      </FilterBar>

      {summary ? (
        <Typography.Text type="secondary">
          {Number(summary.account_count || 0).toLocaleString('id-ID')} akun · total tunggakan {formatCurrency(summary.outstanding_total)}
        </Typography.Text>
      ) : null}

      <Card>
        <ResponsiveTable
          query={accounts}
          rowKey={(record) => record.unit?.id}
          onChange={handleChange}
          scrollX={1100}
          {...(accounts.isError ? {} : {
            locale: {
              emptyText: (
                <EmptyData
                  description={hasFilters
                    ? 'Tidak ada akun yang cocok dengan filter. Coba ubah atau kosongkan filter.'
                    : 'Kolektor ini belum memegang akun penagihan. Akun muncul setelah kolektor ditugaskan ke cluster/blok/unit.'}
                />
              ),
            },
          })}
          columns={[
            { title: 'Unit', key: 'unit', width: 200, render: (_, record) => unitLabel(record.unit) },
            {
              title: 'Customer',
              key: 'customer',
              width: 190,
              render: (_, record) => (record.customer ? (
                <Space direction="vertical" size={0}>
                  <Typography.Text>{record.customer.name}</Typography.Text>
                  {record.customer.phone ? <Typography.Text type="secondary">{record.customer.phone}</Typography.Text> : null}
                </Space>
              ) : <Typography.Text type="secondary">Belum ada penghuni</Typography.Text>),
            },
            {
              title: 'Tunggakan',
              key: 'outstanding_total',
              width: 160,
              sorter: true,
              sortOrder: sortOrder('outstanding_total'),
              render: (_, record) => (
                <Space direction="vertical" size={0}>
                  <Typography.Text strong>{formatCurrency(record.outstanding_total)}</Typography.Text>
                  {record.open_invoice_count ? <Typography.Text type="secondary">{record.open_invoice_count} tagihan terbuka</Typography.Text> : null}
                </Space>
              ),
            },
            {
              title: 'Umur',
              key: 'aging_days',
              width: 140,
              sorter: true,
              sortOrder: sortOrder('aging_days'),
              render: (_, record) => (
                <Space direction="vertical" size={2}>
                  <StatusBadge type="agingBucket" value={record.aging_bucket} />
                  <Typography.Text type="secondary">{Number(record.aging_days || 0)} hari</Typography.Text>
                </Space>
              ),
            },
            { title: 'Status', key: 'status', width: 150, render: (_, record) => <StatusBadge type="collectionAccount" value={record.status} /> },
            {
              title: 'Prioritas',
              key: 'priority_score',
              width: 120,
              sorter: true,
              sortOrder: sortOrder('priority_score'),
              render: (_, record) => <StatusBadge type="priorityLevel" value={record.priority_level} />,
            },
            {
              title: 'Kontak Terakhir',
              key: 'last_contact_at',
              width: 170,
              sorter: true,
              sortOrder: sortOrder('last_contact_at'),
              render: (_, record) => (record.last_contact_at ? (
                <Space direction="vertical" size={0}>
                  <Typography.Text>{formatDateTime(record.last_contact_at)}</Typography.Text>
                  {record.last_contact_result ? <Typography.Text type="secondary">{record.last_contact_result}</Typography.Text> : null}
                </Space>
              ) : <Typography.Text type="secondary">Belum pernah</Typography.Text>),
            },
          ]}
        />
      </Card>
    </div>
  );
}

// Semua penugasan kolektor (termasuk yang sudah dipindahkan/selesai) dari GET /collector-assignments.
function AssignmentListCard({ collectorId }) {
  const table = useTableState();
  const params = { ...table.params, collector_id: collectorId };
  const list = useQuery({
    queryKey: ['collector-assignments', params],
    queryFn: () => api.collectorAssignments.list(params),
  });

  return (
    <Card
      title="Riwayat Semua Penugasan"
      extra={(
        <Select
          allowClear
          placeholder="Semua status"
          options={statusOptions('assignmentStatus')}
          value={table.filters.status}
          onChange={(value) => table.setFilters({ ...table.filters, status: value })}
          style={{ width: 180 }}
        />
      )}
    >
      <ResponsiveTable
        query={list}
        onChange={table.handleTableChange}
        scrollX={980}
        {...(list.isError ? {} : { locale: { emptyText: <EmptyData description="Belum ada riwayat penugasan untuk kolektor ini." /> } })}
        columns={[
          { title: 'Cakupan', width: 100, render: (_, record) => <Tag>{SCOPE_LABELS[record.scope_type] || record.scope_type}</Tag> },
          { title: 'Detail', render: (_, record) => scopeSummary(record) },
          { title: 'Status', dataIndex: 'status', width: 130, render: (value) => <StatusBadge type="assignmentStatus" value={value} /> },
          { title: 'Periode', width: 220, render: (_, record) => `${formatDate(record.start_date)} – ${formatDate(record.end_date, 'sekarang')}` },
          { title: 'Asal Penugasan', width: 260, render: (_, record) => <ReassignOrigin record={record} /> },
          { title: 'Ditugaskan oleh', width: 150, render: (_, record) => record.assigned_by?.name || record.assigned_by_user?.name || '-' },
        ]}
      />
    </Card>
  );
}

function LocationTab({ location }) {
  const containerRef = useRef(null);
  const mapRef = useRef(null);

  useEffect(() => {
    if (!location || !containerRef.current || mapRef.current) return undefined;
    // latitude/longitude dikirim sebagai string desimal ("-6.1234567").
    const point = [Number(location.latitude), Number(location.longitude)];
    mapRef.current = L.map(containerRef.current).setView(point, 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19,
    }).addTo(mapRef.current);
    L.marker(point, { icon: markerIcon }).addTo(mapRef.current);

    return () => {
      mapRef.current?.remove();
      mapRef.current = null;
    };
  }, [location]);

  if (!location) {
    return <Card><Empty description="Belum ada laporan lokasi. Lokasi muncul setelah kolektor mengirim lokasi dari aplikasi pada jam tugas." /></Card>;
  }

  return (
    <Card>
      <Typography.Paragraph>
        Terakhir dilaporkan: {formatDateTime(location.recorded_at)} (akurasi ±{Number(location.accuracy_meters || 0).toFixed(0)} m)
      </Typography.Paragraph>
      <div ref={containerRef} style={{ height: 320, borderRadius: 8, overflow: 'hidden' }} />
    </Card>
  );
}
