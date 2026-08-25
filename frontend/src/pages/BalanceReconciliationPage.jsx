import { Alert, Button, Card, Drawer, Input, Select, Space, Statistic, Tag, Typography } from 'antd';
import { EyeOutlined } from '@ant-design/icons';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { api } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { formatCurrency, formatDateTime } from '../utils/format.js';

const STATUS_OPTIONS = [
  { value: 'all', label: 'Semua' },
  { value: 'balanced', label: 'Balanced' },
  { value: 'mismatch', label: 'Mismatch' },
  { value: 'negative', label: 'Saldo Negatif' },
];

function ReconciliationStatusTag({ row }) {
  if (row.is_negative) return <Tag color="volcano">Saldo Negatif</Tag>;
  if (row.status === 'balanced') return <Tag color="green">Balanced</Tag>;
  return <Tag color="red">Mismatch</Tag>;
}

function LedgerDrawer({ unitId, onClose }) {
  const table = useTableState();
  const ledger = useQuery({
    queryKey: ['units', unitId, 'balance-ledger', table.params],
    queryFn: () => api.balances.ledger(unitId, table.params),
    enabled: Boolean(unitId),
  });

  return (
    <Drawer title={`Ledger Saldo - Unit ${unitId || ''}`} open={Boolean(unitId)} onClose={onClose} width={820} destroyOnHidden>
      <ResponsiveTable
        query={ledger}
        onChange={table.handleTableChange}
        scrollX={900}
        columns={[
          { title: 'Tanggal', dataIndex: 'created_at', render: formatDateTime, width: 160 },
          { title: 'Tipe', dataIndex: 'type', width: 150 },
          { title: 'Kredit', render: (_, row) => (row.direction === 'credit' ? formatCurrency(row.amount) : '-'), width: 120 },
          { title: 'Debit', render: (_, row) => (row.direction === 'debit' ? formatCurrency(row.amount) : '-'), width: 120 },
          { title: 'Saldo Sebelum', dataIndex: 'balance_before', render: formatCurrency, width: 130 },
          { title: 'Saldo Sesudah', dataIndex: 'balance_after', render: formatCurrency, width: 130 },
          { title: 'Referensi', render: (_, row) => row.receipt_number || row.reference_id || '-', width: 150 },
          { title: 'Keterangan', dataIndex: 'notes', width: 200 },
        ]}
      />
    </Drawer>
  );
}

export default function BalanceReconciliationPage() {
  const table = useTableState();
  const [detailUnitId, setDetailUnitId] = useState(null);
  const status = table.filters.status || 'all';

  const reconciliation = useQuery({
    queryKey: ['balances', 'reconciliation', table.params],
    queryFn: () => api.balances.reconciliation(table.params),
  });

  const rows = reconciliation.data?.data || [];
  const mismatchCount = rows.filter((row) => row.status === 'mismatch').length;
  const negativeCount = rows.filter((row) => row.is_negative).length;

  return (
    <section>
      <PageHeader
        title="Rekonsiliasi Saldo"
        subtitle="Bandingkan saldo tersimpan setiap unit dengan hasil perhitungan ulang dari ledger transaksi."
        breadcrumbs={[{ label: 'Rekonsiliasi Saldo' }]}
        onRefresh={() => reconciliation.refetch()}
        loading={reconciliation.isFetching}
      />

      <Space size="large" wrap className="section-row">
        <Statistic title="Total Unit Ditampilkan" value={reconciliation.data?.meta?.total ?? rows.length} />
        <Statistic title="Mismatch (halaman ini)" value={mismatchCount} valueStyle={mismatchCount ? { color: '#cf1322' } : undefined} />
        <Statistic title="Saldo Negatif (halaman ini)" value={negativeCount} valueStyle={negativeCount ? { color: '#d46b08' } : undefined} />
      </Space>

      <Alert
        className="section-row"
        type="info"
        showIcon
        message="Stored Balance diambil dari kolom units.balance (cache). Calculated Balance dihitung ulang dari SUM(kredit) - SUM(debit) pada ledger unit_deposits. Mismatch tidak pernah dikoreksi otomatis - gunakan Penyesuaian Saldo pada tab Saldo & Ledger unit terkait bila diperlukan."
      />

      <FilterBar>
        <Input allowClear placeholder="Cari ID unit atau nama penghuni" value={table.search} onChange={(event) => table.setSearch(event.target.value)} className="filter-input" />
        <Select
          value={status}
          onChange={(value) => table.setFilters({ ...table.filters, status: value === 'all' ? undefined : value })}
          options={STATUS_OPTIONS}
          className="filter-input"
        />
      </FilterBar>

      <Card>
        <ResponsiveTable
          query={reconciliation}
          onChange={table.handleTableChange}
          rowKey="unit_id"
          scrollX={1100}
          columns={[
            { title: 'Unit', dataIndex: 'unit_id', width: 100, fixed: 'left' },
            { title: 'Penghuni', dataIndex: 'resident_name', width: 200 },
            { title: 'Alamat', render: (_, row) => `${row.block || ''}/${row.lot_number || ''}`, width: 120 },
            { title: 'Stored Balance', dataIndex: 'stored_balance', render: formatCurrency, width: 150 },
            { title: 'Calculated Balance', dataIndex: 'calculated_balance', render: formatCurrency, width: 160 },
            { title: 'Selisih', render: (_, row) => <Typography.Text type={row.difference !== 0 ? 'danger' : undefined}>{formatCurrency(row.difference)}</Typography.Text>, width: 140 },
            { title: 'Status', render: (_, row) => <ReconciliationStatusTag row={row} />, width: 140 },
            { title: 'Transaksi Terakhir', dataIndex: 'last_transaction_at', render: (value) => (value ? formatDateTime(value) : '-'), width: 170 },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 140,
              render: (_, row) => <Button size="small" icon={<EyeOutlined />} onClick={() => setDetailUnitId(row.unit_id)}>Lihat Ledger</Button>,
            },
          ]}
        />
      </Card>

      <LedgerDrawer unitId={detailUnitId} onClose={() => setDetailUnitId(null)} />
    </section>
  );
}
