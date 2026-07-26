import { Button, Card, Col, Descriptions, Empty, Form, Input, Modal, Row, Select, Statistic, Table, Tabs, Tag, Typography } from 'antd';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';
import { useParams } from 'react-router-dom';
import L from 'leaflet';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';
import PageHeader from '../components/common/PageHeader.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import Can from '../components/common/Can.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { ErrorState, LoadingState } from '../components/common/ApiState.jsx';
import { api, storageUrl } from '../services/estateApi.js';
import { formatCurrency, formatDate, formatDateTime } from '../utils/format.js';
import { mapValidationErrors } from '../utils/apiError.js';

const markerIcon = L.icon({
  iconRetinaUrl, iconUrl, shadowUrl,
  iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41],
});

const SCOPE_LABELS = { cluster: 'Cluster', block: 'Blok', unit: 'Unit', resident: 'Penghuni' };

function scopeSummary(record) {
  if (record.scope_type === 'cluster') return `Cluster ${record.cluster?.name || record.cluster_id}`;
  if (record.scope_type === 'block') return `${record.cluster?.name || record.cluster_id} — Blok ${record.block}`;
  if (record.scope_type === 'unit') return `Unit ${record.unit_id}`;
  if (record.scope_type === 'resident') return `Penghuni ${record.resident?.name || record.resident_id}`;
  return '-';
}

export default function CollectorDetailPage() {
  const { id } = useParams();
  const queryClient = useQueryClient();
  const [reassignModal, setReassignModal] = useState(null);
  const [reassignForm] = Form.useForm();

  const detail = useQuery({ queryKey: ['collectors', id], queryFn: () => api.collectors.detail(id) });
  const history = useQuery({ queryKey: ['collectors', id, 'assignment-history'], queryFn: () => api.collectors.assignmentHistory(id) });
  const collectors = useQuery({ queryKey: ['collectors', 'lookup'], queryFn: () => api.collectors.list({ per_page: 100 }) });

  const reassign = useMutation({
    mutationFn: ({ assignmentId, values }) => api.collectorAssignments.reassign(assignmentId, values),
    onSuccess: () => {
      setReassignModal(null);
      queryClient.invalidateQueries({ queryKey: ['collectors', id] });
      queryClient.invalidateQueries({ queryKey: ['collectors', id, 'assignment-history'] });
    },
    onError: (error) => {
      reassignForm.setFields(mapValidationErrors(error));
    },
  });

  if (detail.isLoading) return <LoadingState rows={8} />;
  if (detail.isError) return <ErrorState error={detail.error} onRetry={detail.refetch} />;

  const payload = detail.data?.data || {};
  const collector = payload.collector || {};
  const profile = collector.collector_profile || {};
  const summary = payload.summary || {};
  const photo = profile.photos?.[0];
  const collectorOptions = (collectors.data?.data?.data || collectors.data?.data || [])
    .filter((c) => c.id !== collector.id)
    .map((c) => ({ value: c.id, label: c.name }));

  return (
    <section>
      <PageHeader
        title={collector.name}
        subtitle={`Kode Kolektor: ${profile.collector_code || '-'}`}
        breadcrumbs={[{ label: 'Manajemen Kolektor' }, { label: 'Data Kolektor', to: '/admin/collectors/list' }, { label: collector.name }]}
        onRefresh={() => { detail.refetch(); history.refetch(); }}
      />

      <Row gutter={16} style={{ marginBottom: 16 }}>
        <Col xs={24} md={6}><Card><Statistic title="Total Penghuni" value={summary.total_residents ?? 0} /></Card></Col>
        <Col xs={24} md={6}><Card><Statistic title="Total Unit" value={summary.total_units ?? 0} /></Card></Col>
        <Col xs={24} md={6}><Card><Statistic title="Tunggakan Belum Ditagih" value={formatCurrency(summary.total_outstanding ?? 0)} /></Card></Col>
        <Col xs={24} md={6}><Card><Statistic title="Pembayaran Berhasil" value={formatCurrency(summary.total_payments_collected ?? 0)} /></Card></Col>
      </Row>

      <Tabs
        items={[
          {
            key: 'profile',
            label: 'Profil',
            children: (
              <Card>
                <Row gutter={16}>
                  {photo ? (
                    <Col xs={24} md={4}>
                      <img src={storageUrl(photo.path)} alt="" style={{ width: '100%', borderRadius: 8, objectFit: 'cover' }} />
                    </Col>
                  ) : null}
                  <Col xs={24} md={photo ? 20 : 24}>
                    <Descriptions bordered column={{ xs: 1, md: 2 }}>
                      <Descriptions.Item label="Nama">{collector.name}</Descriptions.Item>
                      <Descriptions.Item label="Username">{collector.username}</Descriptions.Item>
                      <Descriptions.Item label="Email">{collector.email || '-'}</Descriptions.Item>
                      <Descriptions.Item label="Telepon">{collector.phone || '-'}</Descriptions.Item>
                      <Descriptions.Item label="WhatsApp">{profile.whatsapp_number || '-'}</Descriptions.Item>
                      <Descriptions.Item label="Alamat">{profile.address || '-'}</Descriptions.Item>
                      <Descriptions.Item label="Tanggal Bergabung">{formatDate(profile.joined_at)}</Descriptions.Item>
                      <Descriptions.Item label="Status Kepegawaian">{profile.employment_status || '-'}</Descriptions.Item>
                      <Descriptions.Item label="Status Akun"><StatusBadge type="collectorAccount" value={profile.account_status} /></Descriptions.Item>
                      <Descriptions.Item label="Wilayah Kerja">{profile.working_area_notes || '-'}</Descriptions.Item>
                      <Descriptions.Item label="Catatan Admin" span={2}>{profile.admin_notes || '-'}</Descriptions.Item>
                    </Descriptions>
                  </Col>
                </Row>
              </Card>
            ),
          },
          {
            key: 'assignments',
            label: 'Wilayah & Penugasan',
            children: (
              <Card>
                {(payload.assignments || []).length ? (
                  <Table
                    rowKey="id"
                    dataSource={payload.assignments}
                    pagination={false}
                    columns={[
                      { title: 'Cakupan', render: (_, record) => <Tag>{SCOPE_LABELS[record.scope_type]}</Tag> },
                      { title: 'Detail', render: (_, record) => scopeSummary(record) },
                      { title: 'Prioritas', dataIndex: 'priority' },
                      { title: 'Mulai', dataIndex: 'start_date', render: (value) => formatDate(value) },
                      { title: 'Selesai', dataIndex: 'end_date', render: (value) => formatDate(value) },
                      {
                        title: 'Aksi',
                        render: (_, record) => (
                          <Can permission="collector.reassign">
                            <Button
                              size="small"
                              onClick={() => {
                                reassignForm.resetFields();
                                setReassignModal(record);
                              }}
                            >
                              Pindahkan
                            </Button>
                          </Can>
                        ),
                      },
                    ]}
                  />
                ) : (
                  <Empty description="Belum ada penugasan aktif." />
                )}
              </Card>
            ),
          },
          {
            key: 'targets',
            label: 'Target & Performa',
            children: (
              <Card>
                <Table
                  rowKey="id"
                  dataSource={payload.targets || []}
                  pagination={false}
                  columns={[
                    { title: 'Periode', dataIndex: 'period_type' },
                    { title: 'Mulai', dataIndex: 'period_start', render: (value) => formatDate(value) },
                    { title: 'Target Nominal', dataIndex: 'target_amount', render: formatCurrency },
                    { title: 'Target Kunjungan', dataIndex: 'target_visit_count', render: (value) => value ?? '-' },
                  ]}
                />
              </Card>
            ),
          },
          {
            key: 'history',
            label: 'Ringkasan & Riwayat',
            children: (
              <Row gutter={16}>
                <Col xs={24} md={12}>
                  <Card title={`Kunjungan Terakhir (${summary.total_visits ?? 0} total)`} style={{ marginBottom: 16 }}>
                    {(payload.recent_visits || []).length ? (
                      <Table
                        size="small"
                        rowKey="id"
                        dataSource={payload.recent_visits}
                        pagination={false}
                        columns={[
                          { title: 'Unit', dataIndex: 'unit_id' },
                          { title: 'Tujuan', dataIndex: 'purpose' },
                          { title: 'Status', dataIndex: 'status' },
                          { title: 'Tanggal', dataIndex: 'visit_date', render: (value) => formatDateTime(value) },
                        ]}
                      />
                    ) : <Empty description="Belum ada kunjungan." />}
                  </Card>
                  <Card title={`Promise to Pay (${summary.total_payment_promises ?? 0} total)`}>
                    {(payload.recent_payment_promises || []).length ? (
                      <Table
                        size="small"
                        rowKey="id"
                        dataSource={payload.recent_payment_promises}
                        pagination={false}
                        columns={[
                          { title: 'Unit', dataIndex: 'unit_id' },
                          { title: 'Jumlah', dataIndex: 'promised_amount', render: formatCurrency },
                          { title: 'Status', dataIndex: 'status' },
                          { title: 'Tanggal Janji', dataIndex: 'promised_date', render: (value) => formatDate(value) },
                        ]}
                      />
                    ) : <Empty description="Belum ada janji pembayaran." />}
                  </Card>
                </Col>
                <Col xs={24} md={12}>
                  <Card title={`Komplain (${summary.total_complaints ?? 0} total)`}>
                    {(payload.recent_complaints || []).length ? (
                      <Table
                        size="small"
                        rowKey="id"
                        dataSource={payload.recent_complaints}
                        pagination={false}
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
            label: 'Riwayat Penugasan',
            children: (
              <Card>
                <ResponsiveTable
                  query={history}
                  columns={[
                    { title: 'Waktu', dataIndex: 'created_at', render: (value) => formatDateTime(value) },
                    { title: 'Aktivitas', dataIndex: 'activity' },
                    { title: 'Action', dataIndex: 'action' },
                    { title: 'Oleh', dataIndex: 'user_name' },
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
        ]}
      />

      <Modal
        title="Pindahkan Penugasan"
        open={Boolean(reassignModal)}
        onCancel={() => setReassignModal(null)}
        onOk={() => reassignForm.submit()}
        confirmLoading={reassign.isPending}
        destroyOnHidden
      >
        {reassignModal ? (
          <Typography.Paragraph type="secondary">
            Memindahkan penugasan <strong>{scopeSummary(reassignModal)}</strong> dari {collector.name} ke kolektor lain.
          </Typography.Paragraph>
        ) : null}
        <Form
          form={reassignForm}
          layout="vertical"
          onFinish={(values) => reassign.mutate({ assignmentId: reassignModal.id, values })}
        >
          <Form.Item label="Kolektor Tujuan" name="new_collector_id" rules={[{ required: true, message: 'Pilih kolektor tujuan' }]}>
            <Select showSearch optionFilterProp="label" options={collectorOptions} />
          </Form.Item>
          <Form.Item label="Catatan" name="notes">
            <Input.TextArea rows={2} />
          </Form.Item>
        </Form>
      </Modal>
    </section>
  );
}

function LocationTab({ location }) {
  const containerRef = useRef(null);
  const mapRef = useRef(null);

  useEffect(() => {
    if (!location || !containerRef.current || mapRef.current) return undefined;
    const point = [location.latitude, location.longitude];
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
    return <Card><Empty description="Belum ada laporan lokasi." /></Card>;
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
