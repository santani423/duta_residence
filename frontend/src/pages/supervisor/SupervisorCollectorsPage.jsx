import { Button, Card, Input, Select, Tag } from 'antd';
import { EyeOutlined } from '@ant-design/icons';
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import PageHeader from '../../components/common/PageHeader.jsx';
import FilterBar from '../../components/common/FilterBar.jsx';
import StatusBadge from '../../components/common/StatusBadge.jsx';
import ResponsiveTable from '../../components/tables/ResponsiveTable.jsx';
import { useTableState } from '../../hooks/useTableState.js';
import { api } from '../../services/estateApi.js';
import { formatDateTime } from '../../utils/format.js';

const ACCOUNT_STATUS_OPTIONS = [
  { value: 'active', label: 'Aktif' },
  { value: 'inactive', label: 'Nonaktif' },
  { value: 'leave', label: 'Cuti' },
  { value: 'suspended', label: 'Suspend' },
];

export default function SupervisorCollectorsPage() {
  const table = useTableState();
  const navigate = useNavigate();

  const collectors = useQuery({ queryKey: ['supervisor-collectors', table.params], queryFn: () => api.supervisorMonitoring.collectors(table.params) });

  return (
    <section>
      <PageHeader
        title="Monitoring Kolektor"
        subtitle="Pantau status, lokasi terakhir, dan aktivitas harian kolektor di wilayah Anda."
        breadcrumbs={[{ label: 'Supervisor' }, { label: 'Monitoring Kolektor' }]}
        onRefresh={collectors.refetch}
      />

      <FilterBar>
        <Input
          allowClear
          placeholder="Cari nama kolektor"
          value={table.search}
          onChange={(event) => table.setSearch(event.target.value)}
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
      </FilterBar>

      <Card>
        <ResponsiveTable
          query={collectors}
          onChange={table.handleTableChange}
          scrollX={1000}
          columns={[
            { title: 'Kode', dataIndex: ['collector_profile', 'collector_code'], width: 110 },
            { title: 'Nama', dataIndex: 'name' },
            { title: 'Status Akun', width: 120, render: (_, r) => <StatusBadge type="collectorAccount" value={r.collector_profile?.account_status} /> },
            { title: 'Lokasi Terakhir', render: (_, r) => (r.latest_location ? formatDateTime(r.latest_location.recorded_at) : <Tag>Belum ada</Tag>) },
            { title: 'Kunjungan Hari Ini', dataIndex: 'today_visit_count', width: 130 },
            { title: 'PTP Aktif', dataIndex: 'active_ptp_count', width: 100 },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 90,
              render: (_, record) => (
                <Button size="small" icon={<EyeOutlined />} onClick={() => navigate(`/supervisor/collectors/${record.id}`)}>Detail</Button>
              ),
            },
          ]}
        />
      </Card>
    </section>
  );
}
