import {
  Alert, Avatar, Button, Card, DatePicker, Drawer, Dropdown, Form, Image, Input, Modal, Progress, Radio, Select, Space,
  Tag, TimePicker, Tooltip, Typography, Upload, message, theme,
} from 'antd';
import {
  DeleteOutlined, EditOutlined, EyeOutlined, MoreOutlined, PlusOutlined, StopOutlined, UploadOutlined, UserOutlined,
} from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import dayjs from 'dayjs';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import Can from '../components/common/Can.jsx';
import StatusBadge, { statusOptions } from '../components/common/StatusBadge.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { EmptyData } from '../components/common/ApiState.jsx';
import CollectorSelect from '../components/collection/CollectorSelect.jsx';
import { api, storageUrl } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { useClusterOptions } from '../hooks/useUnitLookups.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';
import { formatCurrency } from '../utils/format.js';
import { useAuth } from '../state/AuthContext.jsx';
import MoneyInput from '../components/common/MoneyInput.jsx';

const ACCOUNT_STATUS_OPTIONS = statusOptions('collectorAccount');

const EMPLOYMENT_STATUS_OPTIONS = [
  { value: 'tetap', label: 'Tetap' },
  { value: 'kontrak', label: 'Kontrak' },
  { value: 'harian', label: 'Harian' },
];

const numberFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const percentFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 });

// Query yang ikut berubah saat status/penugasan kolektor berubah.
const COLLECTOR_AFFECTED_QUERY_KEYS = ['collectors', 'collector-assignments', 'collection', 'collector-performance', 'collector-targets', 'supervisor-collectors'];

// "08:00" / "08:00:00" -> dayjs (tanpa plugin customParseFormat).
function parseTime(value) {
  if (!value || typeof value !== 'string') return undefined;
  const [hour, minute] = value.split(':').map(Number);
  if (Number.isNaN(hour) || Number.isNaN(minute)) return undefined;
  return dayjs().hour(hour).minute(minute).second(0).millisecond(0);
}

function collectorPhotoUrl(record) {
  const profile = record?.collector_profile;
  if (!profile) return null;
  return profile.photo_url || storageUrl(profile.latest_photo?.path || profile.photos?.[0]?.path) || null;
}

function toFormValues(record) {
  const profile = record.collector_profile || {};
  return {
    name: record.name,
    username: record.username,
    email: record.email,
    phone: record.phone,
    collector_code: profile.collector_code,
    whatsapp_number: profile.whatsapp_number,
    address: profile.address,
    employment_status: profile.employment_status,
    account_status: profile.account_status,
    working_area_notes: profile.working_area_notes,
    admin_notes: profile.admin_notes,
    joined_at: profile.joined_at ? dayjs(profile.joined_at) : undefined,
    duty_start_time: parseTime(profile.duty_start_time),
    duty_end_time: parseTime(profile.duty_end_time),
  };
}

function toSubmitValues(values, isEdit) {
  const payload = {
    ...values,
    joined_at: values.joined_at ? values.joined_at.format('YYYY-MM-DD') : null,
    duty_start_time: values.duty_start_time ? values.duty_start_time.format('HH:mm') : null,
    duty_end_time: values.duty_end_time ? values.duty_end_time.format('HH:mm') : null,
  };
  if (!payload.password) delete payload.password;
  // Status akun saat edit diubah lewat menu "Ubah Status" (alur serah terima penugasan).
  if (isEdit) delete payload.account_status;
  return payload;
}

// Backend menolak (422) nonaktif/hapus bila masih ada penugasan aktif: errors.active_assignment_count.
function activeAssignmentCountFromError(error) {
  if (error?.status !== 422) return null;
  const raw = error.errors?.active_assignment_count;
  if (raw === undefined || raw === null) return null;
  const count = Number(Array.isArray(raw) ? raw[0] : raw);
  return Number.isNaN(count) ? null : count;
}

function withoutCountError(error) {
  if (!error?.errors) return error;
  const rest = { ...error.errors };
  delete rest.active_assignment_count;
  return { ...error, errors: Object.keys(rest).length ? rest : undefined };
}

function AchievementCell({ stats }) {
  const { token } = theme.useToken();
  const collected = Number(stats?.collected_this_month || 0);
  const target = Number(stats?.target_this_month || 0);
  const raw = stats?.achievement_percent_raw;
  const percent = raw === null || raw === undefined ? (target > 0 ? (collected / target) * 100 : null) : Number(raw);

  return (
    <Space direction="vertical" size={0} style={{ width: '100%' }}>
      <Typography.Text strong>{formatCurrency(collected)}</Typography.Text>
      {target > 0 ? (
        <>
          <Typography.Text type="secondary" style={{ fontSize: token.fontSizeSM }}>dari target {formatCurrency(target)}</Typography.Text>
          <Progress
            size="small"
            percent={Math.min(100, Math.max(0, percent || 0))}
            format={() => `${percentFormatter.format(percent || 0)}%`}
            strokeColor={percent >= 100 ? token.colorSuccess : (percent >= 50 ? token.colorWarning : token.colorError)}
          />
        </>
      ) : (
        <Typography.Text type="secondary" style={{ fontSize: token.fontSizeSM }}>Target bulan ini belum diatur</Typography.Text>
      )}
    </Space>
  );
}

// Modal serah terima penugasan (dipasang ulang tiap dibuka lewat `key`, sehingga form selalu bersih).
function HandoverModal({ state, fieldErrors, loading, onCancel, onSubmit }) {
  const [form] = Form.useForm();
  const action = Form.useWatch('assignment_action', form);
  const isDelete = state.kind === 'delete';
  const verb = isDelete ? 'menghapus' : 'menonaktifkan';

  useEffect(() => {
    if (fieldErrors?.length) form.setFields(fieldErrors);
  }, [fieldErrors, form]);

  return (
    <Modal
      title={`Serah Terima Penugasan — ${state.record?.name || ''}`}
      open
      onCancel={onCancel}
      onOk={() => form.submit()}
      okText={isDelete ? 'Proses & Hapus Kolektor' : 'Proses & Simpan Status'}
      okButtonProps={{ danger: true }}
      cancelText="Batal"
      confirmLoading={loading}
    >
      <Alert
        type="warning"
        showIcon
        style={{ marginBottom: 16 }}
        message={`Kolektor ini masih memiliki ${numberFormatter.format(state.count || 0)} penugasan aktif.`}
        description={`Tentukan nasib penugasan tersebut sebelum ${verb} kolektor. Tindakan ini dicatat di log audit.`}
      />
      <Form
        form={form}
        layout="vertical"
        initialValues={{ assignment_action: 'reassign', reason: state.initialReason }}
        onFinish={onSubmit}
      >
        <Form.Item label="Tindakan untuk penugasan aktif" name="assignment_action" rules={[{ required: true, message: 'Pilih tindakan' }]}>
          <Radio.Group>
            <Space direction="vertical">
              <Radio value="reassign">Pindahkan semua penugasan ke kolektor lain</Radio>
              <Radio value="end">Akhiri semua penugasan (unit menjadi belum ditugaskan)</Radio>
            </Space>
          </Radio.Group>
        </Form.Item>
        {action === 'reassign' ? (
          <Form.Item
            label="Kolektor Tujuan"
            name="reassign_to_collector_id"
            rules={[{ required: true, message: 'Pilih kolektor tujuan' }]}
            extra="Hanya kolektor aktif yang dapat dipilih."
          >
            <CollectorSelect exclude={state.record ? [state.record.id] : []} placeholder="Pilih kolektor aktif" />
          </Form.Item>
        ) : null}
        <Form.Item
          label="Alasan"
          name="reason"
          rules={[
            { required: true, message: 'Alasan wajib diisi' },
            { min: 5, message: 'Alasan minimal 5 karakter' },
            { max: 500, message: 'Alasan maksimal 500 karakter' },
          ]}
        >
          <Input.TextArea rows={3} showCount maxLength={500} placeholder="Contoh: Kolektor resign per akhir bulan" />
        </Form.Item>
      </Form>
    </Modal>
  );
}

export default function CollectorsPage() {
  const table = useTableState();
  const clusters = useClusterOptions();
  const [drawer, setDrawer] = useState({ type: null, record: null });
  const [photoPreview, setPhotoPreview] = useState(null);
  const [statusModal, setStatusModal] = useState(null);
  const [handover, setHandover] = useState(null);
  const [handoverErrors, setHandoverErrors] = useState(null);
  const [statusForm] = Form.useForm();
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const { can } = useAuth();
  const isEdit = drawer.type === 'edit';

  const collectors = useQuery({ queryKey: ['collectors', table.params], queryFn: () => api.collectors.list(table.params) });

  function invalidate(keys = ['collectors']) {
    keys.forEach((key) => queryClient.invalidateQueries({ queryKey: [key] }));
  }

  function closeDrawer() {
    setDrawer({ type: null, record: null });
    setPhotoPreview(null);
  }

  const save = useMutation({
    mutationFn: (values) => {
      const payload = toSubmitValues(values, isEdit);
      return isEdit ? api.collectors.update(drawer.record.id, payload) : api.collectors.create(payload);
    },
    onSuccess: () => {
      message.success(isEdit ? 'Data kolektor berhasil diperbarui.' : 'Kolektor berhasil ditambahkan.');
      closeDrawer();
      form.resetFields();
      invalidate(['collectors', 'collection']);
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(withoutCountError(error)));
      message.error(getApiErrorMessage(withoutCountError(error)));
    },
  });

  // Satu mutation untuk ubah status & hapus, dengan/ tanpa data serah terima penugasan.
  const lifecycle = useMutation({
    mutationFn: ({ kind, record, payload }) => (kind === 'delete'
      ? api.collectors.remove(record.id, payload)
      : api.collectors.updateStatus(record.id, payload)),
    onSuccess: (_, { kind }) => {
      message.success(kind === 'delete' ? 'Kolektor berhasil dihapus.' : 'Status kolektor berhasil diperbarui.');
      setStatusModal(null);
      setHandover(null);
      invalidate(COLLECTOR_AFFECTED_QUERY_KEYS);
    },
    onError: (error, variables) => {
      const count = activeAssignmentCountFromError(error);
      if (count !== null && !variables.payload?.assignment_action) {
        // Data stats di tabel sudah usang: minta serah terima lalu kirim ulang.
        setStatusModal(null);
        openHandover({ ...variables, count: count || variables.record.stats?.active_assignment_count || 0 });
        message.warning('Kolektor masih memiliki penugasan aktif. Tentukan serah terima penugasan terlebih dahulu.');
        return;
      }
      const cleaned = withoutCountError(error);
      if (variables.payload?.assignment_action) {
        setHandoverErrors(mapValidationErrors(cleaned).filter((field) => ['assignment_action', 'reassign_to_collector_id', 'reason'].includes(field.name)));
      } else if (variables.kind === 'status') {
        statusForm.setFields(mapValidationErrors(cleaned).filter((field) => ['account_status', 'reason'].includes(field.name)));
      }
      message.error(getApiErrorMessage(cleaned));
    },
  });

  const uploadPhoto = useMutation({
    mutationFn: ({ id, formData }) => api.collectors.uploadPhoto(id, formData),
    onSuccess: (response) => {
      message.success('Foto profil berhasil diunggah.');
      const path = response?.data?.path;
      if (path) setPhotoPreview(storageUrl(path));
      invalidate(['collectors']);
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  function openHandover({ kind, record, payload, count }) {
    setHandoverErrors(null);
    setHandover({ key: Date.now(), kind, record, basePayload: payload || {}, count, initialReason: payload?.reason });
  }

  function submitHandover(values) {
    const payload = {
      ...handover.basePayload,
      assignment_action: values.assignment_action,
      reassign_to_collector_id: values.assignment_action === 'reassign' ? values.reassign_to_collector_id : undefined,
      reason: values.reason,
    };
    lifecycle.mutate({ kind: handover.kind, record: handover.record, payload });
  }

  function submitStatus(values) {
    const record = statusModal;
    const payload = { account_status: values.account_status, reason: values.reason || undefined };
    const count = Number(record.stats?.active_assignment_count || 0);
    if (values.account_status !== 'active' && count > 0) {
      setStatusModal(null);
      openHandover({ kind: 'status', record, payload, count });
      return;
    }
    lifecycle.mutate({ kind: 'status', record, payload });
  }

  function requestDelete(record) {
    const count = Number(record.stats?.active_assignment_count || 0);
    if (count > 0) {
      openHandover({ kind: 'delete', record, payload: {}, count });
      return;
    }
    Modal.confirm({
      title: 'Hapus kolektor ini?',
      content: `${record.name} tidak akan bisa login lagi. Riwayat penagihan tetap tersimpan.`,
      okText: 'Hapus',
      cancelText: 'Batal',
      okButtonProps: { danger: true },
      onOk: () => lifecycle.mutateAsync({ kind: 'delete', record, payload: undefined }).catch(() => {}),
    });
  }

  function openCreate() {
    form.resetFields();
    form.setFieldsValue({ account_status: 'active', employment_status: 'tetap' });
    setPhotoPreview(null);
    setDrawer({ type: 'create', record: null });
  }

  function openEdit(record) {
    form.resetFields();
    form.setFieldsValue(toFormValues(record));
    setPhotoPreview(collectorPhotoUrl(record));
    setDrawer({ type: 'edit', record });
  }

  const hasFilters = Boolean(table.search || table.filters.account_status || table.filters.employment_status || table.filters.cluster_id);

  return (
    <section>
      <PageHeader
        title="Data Kolektor"
        subtitle="Kelola akun, profil, status kepegawaian, dan portofolio penagihan kolektor."
        breadcrumbs={[{ label: 'Manajemen Kolektor' }, { label: 'Data Kolektor' }]}
        onRefresh={collectors.refetch}
        loading={collectors.isFetching}
        extra={
          <Can permission="collector.create">
            <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Tambah Kolektor</Button>
          </Can>
        }
      />

      <FilterBar>
        <Input
          allowClear
          placeholder="Cari nama, username, kode kolektor"
          value={table.search}
          onChange={(event) => table.setSearch(event.target.value)}
          className="filter-input"
        />
        <Select
          allowClear
          showSearch
          optionFilterProp="label"
          placeholder="Cluster wilayah tugas"
          options={clusters.options}
          loading={clusters.loading}
          value={table.filters.cluster_id}
          onChange={(value) => table.setFilters({ ...table.filters, cluster_id: value })}
          className="filter-input"
        />
        <Select
          allowClear
          placeholder="Status Akun"
          options={ACCOUNT_STATUS_OPTIONS}
          value={table.filters.account_status}
          onChange={(value) => table.setFilters({ ...table.filters, account_status: value })}
          className="filter-input"
        />
        <Select
          allowClear
          placeholder="Status Kepegawaian"
          options={EMPLOYMENT_STATUS_OPTIONS}
          value={table.filters.employment_status}
          onChange={(value) => table.setFilters({ ...table.filters, employment_status: value })}
          className="filter-input"
        />
      </FilterBar>

      <Card>
        <ResponsiveTable
          query={collectors}
          onChange={table.handleTableChange}
          scrollX={1500}
          {...(collectors.isError ? {} : {
            locale: {
              emptyText: (
                <EmptyData
                  description={hasFilters
                    ? 'Tidak ada kolektor yang cocok dengan filter. Coba ubah atau kosongkan filter.'
                    : 'Belum ada kolektor. Klik "Tambah Kolektor" untuk mendaftarkan kolektor pertama.'}
                />
              ),
            },
          })}
          columns={[
            { title: 'Kode', dataIndex: ['collector_profile', 'collector_code'], width: 110, render: (value) => value || '-' },
            {
              title: 'Kolektor',
              width: 230,
              render: (_, record) => (
                <Space>
                  <Avatar src={collectorPhotoUrl(record) || undefined} icon={<UserOutlined />} alt="" />
                  <Space direction="vertical" size={0}>
                    <Typography.Link onClick={() => navigate(`/admin/collectors/list/${record.id}`)}>{record.name}</Typography.Link>
                    <Typography.Text type="secondary">@{record.username}</Typography.Text>
                  </Space>
                </Space>
              ),
            },
            { title: 'Kontak', width: 140, render: (_, record) => record.phone || record.collector_profile?.whatsapp_number || '-' },
            {
              title: 'Status',
              width: 150,
              render: (_, record) => (
                <Space direction="vertical" size={2}>
                  <StatusBadge type="collectorAccount" value={record.collector_profile?.account_status} />
                  <Typography.Text type="secondary">
                    {EMPLOYMENT_STATUS_OPTIONS.find((o) => o.value === record.collector_profile?.employment_status)?.label || '-'}
                  </Typography.Text>
                </Space>
              ),
            },
            {
              title: 'Akun Kelolaan',
              width: 140,
              render: (_, record) => (
                <Space direction="vertical" size={0}>
                  <Typography.Text strong>{numberFormatter.format(Number(record.stats?.assigned_accounts || 0))} akun</Typography.Text>
                  <Typography.Text type="secondary">{numberFormatter.format(Number(record.stats?.active_assignment_count || 0))} penugasan aktif</Typography.Text>
                </Space>
              ),
            },
            {
              title: 'Total Tunggakan',
              width: 150,
              render: (_, record) => formatCurrency(record.stats?.outstanding_total || 0),
            },
            {
              title: 'Menunggak / Kritis',
              width: 150,
              render: (_, record) => {
                const overdue = Number(record.stats?.overdue_accounts || 0);
                const critical = Number(record.stats?.critical_accounts || 0);
                return (
                  <Space size={4} wrap>
                    <Tooltip title="Akun menunggak"><Tag color={overdue ? 'volcano' : 'default'}>{numberFormatter.format(overdue)} menunggak</Tag></Tooltip>
                    <Tooltip title="Akun prioritas kritis"><Tag color={critical ? 'red' : 'default'}>{numberFormatter.format(critical)} kritis</Tag></Tooltip>
                  </Space>
                );
              },
            },
            {
              title: 'Tertagih Bulan Ini',
              width: 220,
              render: (_, record) => <AchievementCell stats={record.stats} />,
            },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 80,
              render: (_, record) => {
                const isActive = record.collector_profile?.account_status === 'active';
                return (
                  <Dropdown
                    trigger={['click']}
                    menu={{
                      items: [
                        { key: 'detail', label: 'Detail', icon: <EyeOutlined /> },
                        can('collector.update') ? { key: 'edit', label: 'Edit', icon: <EditOutlined /> } : null,
                        can('collector.activate') ? { key: 'status', label: isActive ? 'Ubah Status / Nonaktifkan' : 'Ubah Status', icon: <StopOutlined /> } : null,
                        can('collector.delete') ? { type: 'divider' } : null,
                        can('collector.delete') ? { key: 'delete', label: 'Hapus', icon: <DeleteOutlined />, danger: true } : null,
                      ].filter(Boolean),
                      onClick: ({ key }) => {
                        if (key === 'detail') navigate(`/admin/collectors/list/${record.id}`);
                        if (key === 'edit') openEdit(record);
                        if (key === 'status') {
                          statusForm.resetFields();
                          statusForm.setFieldsValue({ account_status: record.collector_profile?.account_status });
                          setStatusModal(record);
                        }
                        if (key === 'delete') requestDelete(record);
                      },
                    }}
                  >
                    <Button icon={<MoreOutlined />} aria-label={`Aksi untuk ${record.name}`} />
                  </Dropdown>
                );
              },
            },
          ]}
        />
      </Card>

      <Drawer
        title={isEdit ? `Edit Kolektor — ${drawer.record?.name || ''}` : 'Tambah Kolektor'}
        open={drawer.type === 'create' || isEdit}
        onClose={closeDrawer}
        width={680}
        extra={
          <Space>
            <Button onClick={closeDrawer}>Batal</Button>
            <Button type="primary" loading={save.isPending} onClick={() => form.submit()}>Simpan</Button>
          </Space>
        }
        destroyOnHidden
      >
        <Form form={form} layout="vertical" className="responsive-form" onFinish={save.mutate}>
          {isEdit && (
            <Form.Item label="Foto Profil" className="full-span">
              <Space align="center" size="middle" wrap>
                {photoPreview ? (
                  <Image src={photoPreview} width={96} height={96} style={{ objectFit: 'cover', borderRadius: 8 }} alt={`Foto ${drawer.record?.name || ''}`} />
                ) : (
                  <Avatar size={96} shape="square" icon={<UserOutlined />} />
                )}
                <Can permission="collector.update">
                  <Upload
                    accept="image/*"
                    showUploadList={false}
                    beforeUpload={(file) => {
                      if (file.size > 5 * 1024 * 1024) {
                        message.error('Ukuran foto maksimal 5 MB.');
                        return Upload.LIST_IGNORE;
                      }
                      const formData = new FormData();
                      formData.append('photo', file);
                      uploadPhoto.mutate({ id: drawer.record.id, formData });
                      return false;
                    }}
                  >
                    <Button icon={<UploadOutlined />} loading={uploadPhoto.isPending}>
                      {photoPreview ? 'Ganti Foto' : 'Unggah Foto'}
                    </Button>
                  </Upload>
                </Can>
              </Space>
            </Form.Item>
          )}
          <Form.Item label="Nama Lengkap" name="name" rules={[{ required: true, message: 'Nama wajib diisi' }, { max: 100, message: 'Maksimal 100 karakter' }]}>
            <Input />
          </Form.Item>
          <Form.Item
            label="Username"
            name="username"
            rules={[{ required: true, message: 'Username wajib diisi' }, { max: 50, message: 'Maksimal 50 karakter' }]}
            extra={isEdit ? 'Mengubah username akan mengeluarkan kolektor dari semua perangkat.' : undefined}
          >
            <Input autoComplete="off" />
          </Form.Item>
          <Form.Item
            label="Password"
            name="password"
            tooltip={isEdit ? 'Kosongkan jika tidak ingin mengubah password' : undefined}
            rules={[{ required: !isEdit, message: 'Password wajib diisi' }, { min: 8, message: 'Minimal 8 karakter' }]}
          >
            <Input.Password autoComplete="new-password" />
          </Form.Item>
          <Form.Item label="Kode Kolektor" name="collector_code" tooltip="Kosongkan untuk membuat kode otomatis (COL-0001, dst.)">
            <Input placeholder="COL-0001" />
          </Form.Item>
          <Form.Item label="Email" name="email" rules={[{ type: 'email', message: 'Format email tidak valid' }]}>
            <Input />
          </Form.Item>
          <Form.Item label="Nomor Telepon" name="phone">
            <Input />
          </Form.Item>
          <Form.Item label="Nomor WhatsApp" name="whatsapp_number">
            <Input />
          </Form.Item>
          <Form.Item label="Tanggal Bergabung" name="joined_at">
            <DatePicker style={{ width: '100%' }} format="DD MMM YYYY" />
          </Form.Item>
          <Form.Item label="Jam Tugas Mulai" name="duty_start_time" tooltip="Dipakai untuk membatasi pelacakan lokasi di luar jam kerja">
            <TimePicker format="HH:mm" minuteStep={5} style={{ width: '100%' }} placeholder="08:00" />
          </Form.Item>
          <Form.Item
            label="Jam Tugas Selesai"
            name="duty_end_time"
            dependencies={['duty_start_time']}
            rules={[
              ({ getFieldValue }) => ({
                validator(_, value) {
                  const start = getFieldValue('duty_start_time');
                  if (!start) return Promise.resolve();
                  if (!value) return Promise.reject(new Error('Jam selesai wajib diisi bila jam mulai diisi'));
                  if (value.format('HH:mm') <= start.format('HH:mm')) return Promise.reject(new Error('Jam selesai harus setelah jam mulai'));
                  return Promise.resolve();
                },
              }),
            ]}
          >
            <TimePicker format="HH:mm" minuteStep={5} style={{ width: '100%' }} placeholder="17:00" />
          </Form.Item>
          <Form.Item label="Status Kepegawaian" name="employment_status">
            <Select options={EMPLOYMENT_STATUS_OPTIONS} />
          </Form.Item>
          <Form.Item
            label="Status Akun"
            name="account_status"
            rules={[{ required: !isEdit, message: 'Status akun wajib dipilih' }]}
            extra={isEdit ? 'Ubah status lewat menu "Ubah Status" agar penugasan aktif ikut diserahterimakan.' : undefined}
          >
            <Select options={ACCOUNT_STATUS_OPTIONS} disabled={isEdit} />
          </Form.Item>
          <Form.Item label="Alamat" name="address" className="full-span">
            <Input.TextArea rows={2} />
          </Form.Item>
          <Form.Item
            label="Wilayah Kerja (catatan singkat)"
            name="working_area_notes"
            className="full-span"
            tooltip="Penugasan cluster/blok/unit/penghuni terstruktur diatur di menu Penugasan Kolektor"
          >
            <Input.TextArea rows={2} placeholder="Contoh: Cluster Alamanda & sekitarnya" />
          </Form.Item>
          {!isEdit && (
            <Form.Item label="Target Penagihan Bulanan Awal (Rp, opsional)" name="initial_monthly_target" className="full-span">
              <MoneyInput />
            </Form.Item>
          )}
          <Form.Item label="Catatan Admin" name="admin_notes" className="full-span">
            <Input.TextArea rows={2} />
          </Form.Item>
        </Form>
      </Drawer>

      <Modal
        title={`Ubah Status — ${statusModal?.name || ''}`}
        open={Boolean(statusModal)}
        onCancel={() => setStatusModal(null)}
        onOk={() => statusForm.submit()}
        okText="Simpan"
        cancelText="Batal"
        confirmLoading={lifecycle.isPending}
        destroyOnHidden
      >
        {Number(statusModal?.stats?.active_assignment_count || 0) > 0 ? (
          <Alert
            type="info"
            showIcon
            style={{ marginBottom: 16 }}
            message={`Kolektor ini memegang ${numberFormatter.format(Number(statusModal.stats.active_assignment_count))} penugasan aktif.`}
            description="Bila status diubah menjadi selain Aktif, Anda akan diminta memindahkan atau mengakhiri penugasan tersebut."
          />
        ) : null}
        <Form form={statusForm} layout="vertical" onFinish={submitStatus}>
          <Form.Item label="Status Akun" name="account_status" rules={[{ required: true, message: 'Pilih status akun' }]}>
            <Select options={ACCOUNT_STATUS_OPTIONS} />
          </Form.Item>
          <Form.Item
            label="Alasan / Catatan"
            name="reason"
            rules={[{ max: 500, message: 'Maksimal 500 karakter' }]}
            extra="Kolektor yang dinonaktifkan langsung dikeluarkan dari aplikasi."
          >
            <Input.TextArea rows={3} />
          </Form.Item>
        </Form>
      </Modal>

      {handover ? (
        <HandoverModal
          key={handover.key}
          state={handover}
          fieldErrors={handoverErrors}
          loading={lifecycle.isPending}
          onCancel={() => setHandover(null)}
          onSubmit={submitHandover}
        />
      ) : null}
    </section>
  );
}
