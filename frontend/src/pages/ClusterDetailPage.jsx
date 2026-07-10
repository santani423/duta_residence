import { Button, Card, Col, DatePicker, Descriptions, Drawer, Dropdown, Form, Input, InputNumber, Modal, Row, Space, Statistic, Switch, Tag, message } from 'antd';
import { DeleteOutlined, EditOutlined, MoreOutlined, PlusOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useParams } from 'react-router-dom';
import dayjs from 'dayjs';
import PageHeader from '../components/common/PageHeader.jsx';
import Can from '../components/common/Can.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { ErrorState, LoadingState } from '../components/common/ApiState.jsx';
import { api } from '../services/estateApi.js';
import { formatCurrency, formatDate, formatDateTime } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';

export default function ClusterDetailPage() {
  const { id } = useParams();
  const [drawer, setDrawer] = useState({ mode: null, record: null });
  const [form] = Form.useForm();
  const queryClient = useQueryClient();

  const detail = useQuery({ queryKey: ['clusters', id], queryFn: () => api.clusters.detail(id) });
  const schedules = useQuery({ queryKey: ['clusters', id, 'rate-schedules'], queryFn: () => api.clusters.rateSchedules(id) });

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

  return (
    <section>
      <PageHeader
        title={cluster.name || 'Detail Cluster'}
        subtitle="Detail cluster dan penjadwalan perubahan tarif."
        breadcrumbs={[{ label: 'Cluster', to: '/clusters' }, { label: cluster.name }]}
        onRefresh={() => { detail.refetch(); schedules.refetch(); }}
        loading={detail.isFetching || schedules.isFetching}
        extra={<Can permission="clusters.update-rate"><Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Tambah Jadwal Tarif</Button></Can>}
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
            <InputNumber min={0} style={{ width: '100%' }} addonBefore="Rp" />
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
