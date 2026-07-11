import { Card, Col, Input, Row, Select, Statistic, Tag } from 'antd';
import { useQuery } from '@tanstack/react-query';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { api } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { formatCurrency, formatDate, formatPeriod } from '../utils/format.js';

export default function ReceivablesPage() {
  const table = useTableState();
  const receivables = useQuery({ queryKey: ['receivables', table.params], queryFn: () => api.receivables.list(table.params) });
  const aging = useQuery({ queryKey: ['receivables-aging'], queryFn: api.receivables.aging });
  const bucket = aging.data?.data || {};

  return (
    <section>
      <PageHeader title="Piutang" subtitle="Monitoring outstanding dan aging tagihan." breadcrumbs={[{ label: 'Piutang' }]} onRefresh={() => { receivables.refetch(); aging.refetch(); }} />
      <Row gutter={[16, 16]}>
        <Col xs={24} md={6}><Card><Statistic title="< 30 hari" value={formatCurrency(bucket.lt_30)} /></Card></Col>
        <Col xs={24} md={6}><Card><Statistic title="30-60 hari" value={formatCurrency(bucket.d30_60)} /></Card></Col>
        <Col xs={24} md={6}><Card><Statistic title="60-90 hari" value={formatCurrency(bucket.d60_90)} /></Card></Col>
        <Col xs={24} md={6}><Card><Statistic title="> 90 hari" value={formatCurrency(bucket.gt_90)} /></Card></Col>
      </Row>
      <FilterBar>
        <Input allowClear placeholder="ID unit" value={table.filters.unit_id} onChange={(event) => table.setFilters({ ...table.filters, unit_id: event.target.value || undefined })} className="filter-input" />
        <Select allowClear placeholder="Status" value={table.filters.is_settled} onChange={(value) => table.setFilters({ ...table.filters, is_settled: value })} className="filter-input" options={[{ value: false, label: 'Outstanding' }, { value: true, label: 'Settled' }]} />
      </FilterBar>
      <Card>
        <ResponsiveTable
          query={receivables}
          onChange={table.handleTableChange}
          columns={[
            { title: 'Penghuni', dataIndex: ['unit', 'resident', 'name'] },
            { title: 'Cluster', dataIndex: ['unit', 'cluster', 'name'] },
            { title: 'Periode', render: (_, row) => formatPeriod(row.year, row.month) },
            { title: 'Nominal', dataIndex: 'amount', render: formatCurrency },
            { title: 'Snapshot', dataIndex: 'snapshot_date', render: formatDate },
            { title: 'Status', dataIndex: 'is_settled', render: (value) => <Tag color={value ? 'green' : 'gold'}>{value ? 'Settled' : 'Outstanding'}</Tag> },
          ]}
        />
      </Card>
    </section>
  );
}
