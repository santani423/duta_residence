import { Card, DatePicker, Descriptions, Drawer, Input, Select, Space, Tag, Typography } from 'antd';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { api } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { formatDateTime } from '../utils/format.js';

function DiffView({ oldData = {}, newData = {} }) {
  const keys = Array.from(new Set([...Object.keys(oldData || {}), ...Object.keys(newData || {})])).filter((key) => JSON.stringify(oldData?.[key]) !== JSON.stringify(newData?.[key]));
  if (!keys.length) return <Typography.Text type="secondary">Tidak ada perubahan field tersimpan.</Typography.Text>;
  return (
    <div className="diff-list">
      {keys.map((key) => (
        <div key={key} className="diff-item">
          <Typography.Text strong>{key}</Typography.Text>
          <div><Tag>Before</Tag><Typography.Text code>{JSON.stringify(oldData?.[key] ?? null)}</Typography.Text></div>
          <div><Tag color="green">After</Tag><Typography.Text code>{JSON.stringify(newData?.[key] ?? null)}</Typography.Text></div>
        </div>
      ))}
    </div>
  );
}

export default function AuditLogsPage() {
  const table = useTableState();
  const [selected, setSelected] = useState(null);
  const logs = useQuery({ queryKey: ['audit-logs', table.params], queryFn: () => api.auditLogs.list(table.params) });

  return (
    <section>
      <PageHeader title="Audit Log" subtitle="Jejak aktivitas sistem, user, endpoint, dan perubahan data." breadcrumbs={[{ label: 'Audit Log' }]} onRefresh={logs.refetch} />
      <FilterBar>
        <Input allowClear placeholder="Cari aktivitas, endpoint" value={table.search} onChange={(event) => table.setSearch(event.target.value)} className="filter-input" />
        <Input allowClear placeholder="Role" value={table.filters.role} onChange={(event) => table.setFilters({ ...table.filters, role: event.target.value || undefined })} className="filter-input" />
        <Select allowClear placeholder="Modul" value={table.filters.module} onChange={(value) => table.setFilters({ ...table.filters, module: value })} className="filter-input" options={['auth', 'customers', 'billings', 'payments', 'users', 'installments', 'clusters'].map((value) => ({ value, label: value }))} />
        <Input allowClear placeholder="Action" value={table.filters.action} onChange={(event) => table.setFilters({ ...table.filters, action: event.target.value || undefined })} className="filter-input" />
        <Select allowClear placeholder="Status" value={table.filters.status} onChange={(value) => table.setFilters({ ...table.filters, status: value })} className="filter-input" options={[{ value: 'success', label: 'Success' }, { value: 'failed', label: 'Failed' }]} />
        <DatePicker.RangePicker onChange={(value) => table.setFilters({ ...table.filters, date_from: value?.[0]?.format('YYYY-MM-DD'), date_to: value?.[1]?.format('YYYY-MM-DD') })} />
      </FilterBar>
      <Card>
        <ResponsiveTable
          query={logs}
          onChange={table.handleTableChange}
          scrollX={1320}
          onRow={(record) => ({ onClick: () => setSelected(record) })}
          columns={[
            { title: 'Waktu', dataIndex: 'created_at', render: formatDateTime, width: 170, fixed: 'left' },
            { title: 'User', dataIndex: 'user_name', width: 160 },
            { title: 'Role', dataIndex: 'user_role', width: 140 },
            { title: 'Modul', dataIndex: 'module', width: 120 },
            { title: 'Action', dataIndex: 'action', width: 140 },
            { title: 'Aktivitas', dataIndex: 'activity', width: 220 },
            { title: 'Endpoint', dataIndex: 'endpoint', width: 260 },
            { title: 'Status', dataIndex: 'status', render: (value) => <Tag color={value === 'success' ? 'green' : 'red'}>{value}</Tag>, width: 100 },
            { title: 'IP', dataIndex: 'ip_address', width: 140 },
          ]}
        />
      </Card>
      <Drawer title="Detail Aktivitas" open={Boolean(selected)} onClose={() => setSelected(null)} width={760}>
        <Descriptions bordered column={1}>
          <Descriptions.Item label="Waktu">{formatDateTime(selected?.created_at)}</Descriptions.Item>
          <Descriptions.Item label="User">{selected?.user_name}</Descriptions.Item>
          <Descriptions.Item label="Role">{selected?.user_role}</Descriptions.Item>
          <Descriptions.Item label="Modul">{selected?.module}</Descriptions.Item>
          <Descriptions.Item label="Action">{selected?.action}</Descriptions.Item>
          <Descriptions.Item label="Endpoint">{selected?.http_method} {selected?.endpoint}</Descriptions.Item>
          <Descriptions.Item label="IP">{selected?.ip_address}</Descriptions.Item>
          <Descriptions.Item label="User Agent">{selected?.user_agent}</Descriptions.Item>
        </Descriptions>
        <Card title="Perubahan Data" className="section-row">
          <DiffView oldData={selected?.old_data} newData={selected?.new_data} />
        </Card>
      </Drawer>
    </section>
  );
}
