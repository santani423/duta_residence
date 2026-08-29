import { Button, Card, Col, DatePicker, Descriptions, Drawer, Dropdown, Empty, Form, Input, InputNumber, Modal, Row, Select, Space, Statistic, Switch, Tag, message } from 'antd';
import { DeleteOutlined, EditOutlined, EnvironmentOutlined, MoreOutlined, PlusOutlined, PrinterOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import dayjs from 'dayjs';
import { Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import PageHeader from '../components/common/PageHeader.jsx';
import Can from '../components/common/Can.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { ErrorState, LoadingState } from '../components/common/ApiState.jsx';
import { api } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { formatCurrency, formatDate, formatDateTime } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';
import { residentStatusOptions, occupancyOptions } from '../components/forms/UnitForm.jsx';

const DONUT_COLORS = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100'];

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
  const [isPrinting, setIsPrinting] = useState(false);
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const unitsTable = useTableState();

  useEffect(() => {
    function handleAfterPrint() {
      setIsPrinting(false);
    }
    window.addEventListener('afterprint', handleAfterPrint);
    return () => window.removeEventListener('afterprint', handleAfterPrint);
  }, []);

  function printUnitsSection() {
    setIsPrinting(true);
    setTimeout(() => window.print(), 50);
  }

  const detail = useQuery({ queryKey: ['clusters', id], queryFn: () => api.clusters.detail(id) });
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
  const allUnits = unitsAll.data?.data || [];
  const occupancyData = occupancyOptions.map((option) => ({
    name: option.label,
    value: allUnits.filter((unit) => unit.occupancy_id === option.value).length,
  }));
  const statusData = residentStatusOptions.map((option) => ({
    name: option.label,
    value: allUnits.filter((unit) => unit.status_id === option.value).length,
  }));

  return (
    <section className={isPrinting ? 'cluster-detail-page printing' : 'cluster-detail-page'}>
      <PageHeader
        title={cluster.name || 'Detail Cluster'}
        subtitle="Detail cluster dan penjadwalan perubahan tarif."
        breadcrumbs={[{ label: 'Cluster', to: '/clusters' }, { label: cluster.name }]}
        onRefresh={() => { detail.refetch(); schedules.refetch(); unitsAll.refetch(); }}
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

   

      <Card
        className="section-row print-area"
        title={`Unit & Penghuni (${units.data?.meta?.total ?? cluster.units_count ?? 0})`}
        extra={<Button icon={<PrinterOutlined />} onClick={printUnitsSection}>Cetak</Button>}
      >
        <Row gutter={[16, 16]} className="section-row">
          <Col xs={24} md={12}>
            <DonutChart title="Okupansi Unit" data={occupancyData} loading={unitsAll.isLoading} />
          </Col>
          <Col xs={24} md={12}>
            <DonutChart title="Status Penghuni" data={statusData} loading={unitsAll.isLoading} />
          </Col>
        </Row>
        <FilterBar>
          <Input allowClear placeholder="Cari ID unit, blok, nama pemilik" value={unitsTable.search} onChange={(event) => unitsTable.setSearch(event.target.value)} className="filter-input" />
          <Select allowClear placeholder="Status" options={residentStatusOptions} value={unitsTable.filters.status_id} onChange={(value) => unitsTable.setFilters({ ...unitsTable.filters, status_id: value })} className="filter-input" />
          <Select allowClear placeholder="Okupansi" options={occupancyOptions} value={unitsTable.filters.occupancy_id} onChange={(value) => unitsTable.setFilters({ ...unitsTable.filters, occupancy_id: value })} className="filter-input" />
        </FilterBar>
        <ResponsiveTable
          query={units}
          onChange={unitsTable.handleTableChange}
          scrollX={1100}
          columns={[
            { title: 'Unit', dataIndex: 'id', fixed: 'left', width: 90 },
            { title: 'Blok', dataIndex: 'block', width: 80 },
            { title: 'Kavling', dataIndex: 'lot_number', width: 90 },
            {
              title: 'Penghuni',
              width: 220,
              render: (_, row) => (row.resident ? <Link to={`/residents/${row.resident.id}`}>{row.resident.name}</Link> : '-'),
            },
            { title: 'Telepon', render: (_, row) => row.resident?.phone || '-', width: 140 },
            { title: 'Tipe', render: (_, row) => row.property_type?.name || row.propertyType?.name || row.property_type_id, width: 150 },
            { title: 'Okupansi', render: (_, row) => row.occupancy?.name || '-', width: 110 },
            { title: 'Status', render: (_, row) => <Tag color={row.status_id === 'AK' ? 'green' : 'default'}>{row.status?.name || row.status_id}</Tag>, width: 130 },
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
