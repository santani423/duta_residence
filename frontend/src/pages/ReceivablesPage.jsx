import { Alert, Card, Col, Input, Row, Select, Statistic } from 'antd';
import { useQuery } from '@tanstack/react-query';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import { api } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { formatCurrency, formatPeriod } from '../utils/format.js';

export default function ReceivablesPage() {
  const table = useTableState();
  const receivables = useQuery({ queryKey: ['receivables', table.params], queryFn: () => api.receivables.list(table.params) });
  const aging = useQuery({ queryKey: ['receivables-aging'], queryFn: api.receivables.aging });
  const dayBuckets = aging.data?.data?.day_buckets || {};
  const tierBuckets = aging.data?.data?.penalty_tier_buckets || {};

  return (
    <section>
      <PageHeader
        title="Piutang"
        subtitle="Monitoring tagihan yang belum lunas beserta denda tunggakan berjenjang: tagihan bulan berjalan tidak dikenakan denda, tunggakan 1-2 bulan dikenakan Rp15.000, dan tunggakan 3 bulan atau lebih dikenakan Rp30.000 per tagihan."
        breadcrumbs={[{ label: 'Piutang' }]}
        onRefresh={() => { receivables.refetch(); aging.refetch(); }}
      />

      <Alert
        type="info"
        showIcon
        className="section-row"
        message="Aturan denda tunggakan"
        description="Denda dihitung per tagihan berdasarkan umur tunggakannya, bukan akumulasi tiap bulan: 0 bulan (berjalan) = Rp0, 1-2 bulan = Rp15.000, 3 bulan atau lebih = Rp30.000 (nilainya tetap meski tunggakan lebih dari 3 bulan)."
      />

      <Row gutter={[16, 16]} className="section-row">
        <Col xs={24} md={8}><Card><Statistic title="Bulan Berjalan" value={formatCurrency(tierBuckets.current)} /></Card></Col>
        <Col xs={24} md={8}><Card><Statistic title="Tunggakan 1-2 Bulan" value={formatCurrency(tierBuckets.tier_1_2_months)} valueStyle={{ color: '#d48806' }} /></Card></Col>
        <Col xs={24} md={8}><Card><Statistic title="Tunggakan 3 Bulan+" value={formatCurrency(tierBuckets.tier_3_plus_months)} valueStyle={{ color: '#cf1322' }} /></Card></Col>
      </Row>

      <Row gutter={[16, 16]} className="section-row">
        <Col xs={24} md={6}><Card><Statistic title="< 30 hari" value={formatCurrency(dayBuckets.lt_30)} /></Card></Col>
        <Col xs={24} md={6}><Card><Statistic title="30-60 hari" value={formatCurrency(dayBuckets.d30_60)} /></Card></Col>
        <Col xs={24} md={6}><Card><Statistic title="60-90 hari" value={formatCurrency(dayBuckets.d60_90)} /></Card></Col>
        <Col xs={24} md={6}><Card><Statistic title="> 90 hari" value={formatCurrency(dayBuckets.gt_90)} /></Card></Col>
      </Row>

      <FilterBar>
        <Input allowClear placeholder="ID unit" value={table.filters.unit_id} onChange={(event) => table.setFilters({ ...table.filters, unit_id: event.target.value || undefined })} className="filter-input" />
        <Select
          allowClear
          placeholder="Status (default: belum lunas)"
          value={table.filters.status_id}
          onChange={(value) => table.setFilters({ ...table.filters, status_id: value })}
          className="filter-input"
          options={[
            { value: '01', label: 'Belum Bayar' },
            { value: '03', label: 'Sebagian' },
            { value: '02', label: 'Lunas' },
            { value: '04', label: 'Dibatalkan' },
          ]}
        />
      </FilterBar>
      <Card>
        <ResponsiveTable
          query={receivables}
          onChange={table.handleTableChange}
          scrollX={1400}
          columns={[
            { title: 'Penghuni', dataIndex: ['unit', 'resident', 'name'], width: 200 },
            { title: 'Unit', dataIndex: 'unit_id', width: 90 },
            { title: 'Cluster', dataIndex: ['unit', 'cluster', 'name'], width: 140 },
            { title: 'Periode', render: (_, row) => formatPeriod(row.year, row.month), width: 120 },
            { title: 'Pokok', render: (_, row) => formatCurrency(row.penalty_detail?.principal_amount ?? row.amount), width: 130 },
            { title: 'Umur Tunggakan', render: (_, row) => `${row.penalty_detail?.overdue_months ?? 0} bulan`, width: 130 },
            { title: 'Denda', render: (_, row) => formatCurrency(row.penalty_detail?.penalty_amount ?? 0), width: 120 },
            { title: 'Total', render: (_, row) => formatCurrency(row.penalty_detail?.total_amount ?? row.amount), width: 130 },
            { title: 'Sisa Tagihan', render: (_, row) => formatCurrency(row.penalty_detail?.total_outstanding ?? 0), width: 140 },
            { title: 'Status', dataIndex: 'status_id', render: (value) => <StatusBadge type="billing" value={value} />, width: 120 },
          ]}
        />
      </Card>
    </section>
  );
}
