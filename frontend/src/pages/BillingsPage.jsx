import { Alert, Button, Card, Col, DatePicker, Drawer, Form, Input, InputNumber, Modal, Row, Select, Space, Statistic, Tabs, message } from 'antd';
import { CheckOutlined, DollarOutlined, FileExcelOutlined, FilePdfOutlined, PercentageOutlined, PlusOutlined } from '@ant-design/icons';
import { useMutation, useQueries, useQuery, useQueryClient } from '@tanstack/react-query';
import dayjs from 'dayjs';
import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import Can from '../components/common/Can.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import BillingPaymentModal from '../components/common/BillingPaymentModal.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { api } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { useDebounce } from '../hooks/useDebounce.js';
import { useDiscountLimit } from '../hooks/useDiscountLimit.js';
import { DISCOUNT_TYPE_PERCENTAGE, finalPrice, fromNominalDiscount, maxNominalFromPercent, toNominalDiscount } from '../utils/discount.js';
import { formatCurrency, formatDateTime, formatPeriod } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';
import { downloadBlob } from '../utils/download.js';
import MoneyInput from '../components/common/MoneyInput.jsx';

// mode 'outstanding' = halaman Tagihan (semua tagihan belum lunas, lintas tahun);
// mode 'history' = Riwayat Tagihan (semua status, lintas tahun). Tidak ada filter tahun bawaan.
export default function BillingsPage({ mode = 'outstanding' }) {
  const isHistory = mode === 'history';
  const [searchParams] = useSearchParams();
  const urlUnitId = searchParams.get('unit_id') || undefined;
  const table = useTableState({ unit_id: urlUnitId });
  const [drawer, setDrawer] = useState(null);
  const [selected, setSelected] = useState([]);
  const [discountTarget, setDiscountTarget] = useState(null);
  const [exporting, setExporting] = useState(null);
  const [payTarget, setPayTarget] = useState(null);
  const [form] = Form.useForm();
  const [approveForm] = Form.useForm();
  const [discountForm] = Form.useForm();
  const { maximumPercent: maxDiscountPercent, discountType } = useDiscountLimit();
  const watchedDiscount = Form.useWatch('discount', discountForm);
  const queryClient = useQueryClient();

  // Sinkronkan filter unit bila URL berubah (mis. dari aksi Unit) saat halaman sudah terbuka.
  useEffect(() => {
    if ((table.filters.unit_id || undefined) !== urlUnitId) table.setFilters({ ...table.filters, unit_id: urlUnitId });
  }, [urlUnitId]); // eslint-disable-line react-hooks/exhaustive-deps

  const filterUnitId = table.filters.unit_id?.trim() || undefined;
  const listParams = isHistory ? table.params : { ...table.params, outstanding: 1 };
  const billings = useQuery({ queryKey: ['billings', mode, listParams], queryFn: () => api.billings.list(listParams) });
  const { page: _p, per_page: _pp, ...summaryParams } = listParams;
  const summary = useQuery({ queryKey: ['billings', 'summary', mode, summaryParams], queryFn: () => api.billings.summary(summaryParams), enabled: !isHistory });
  const clusters = useQuery({ queryKey: ['clusters'], queryFn: () => api.clusters.list() });
  // Bila daftar hanya berisi satu unit (mis. difilter per unit), petugas bisa memilih beberapa tagihan lalu langsung membayarnya.
  const rows = billings.data?.data || [];
  const listUnitIds = [...new Set(rows.map((row) => row.unit_id))];
  const singleUnitId = listUnitIds.length === 1 && (billings.data?.meta?.total ?? rows.length) <= rows.length ? listUnitIds[0] : null;
  const selectedRows = rows.filter((row) => selected.includes(row.id));
  const pendingApprovalIds = selectedRows.filter((row) => !row.approved_at).map((row) => row.id);
  const payableIds = selectedRows.filter((row) => row.approved_at).map((row) => row.id);
  const selectedUnitId = Form.useWatch('unit_id', form);
  const backEnd = Form.useWatch('period_end', form);

  // The start of a back-billing range is decided by the unit's last billing, never by the user.
  const backRange = useQuery({
    queryKey: ['billings', 'back-range', selectedUnitId],
    queryFn: () => api.billings.backRange({ unit_id: selectedUnitId }),
    enabled: drawer === 'back' && Boolean(selectedUnitId),
    retry: false,
    staleTime: 0,
    gcTime: 0,
  });
  const backStart = backRange.data?.data?.start_period
    ? dayjs(new Date(backRange.data.data.start_period.year, backRange.data.data.start_period.month - 1, 1))
    : null;
  const backLast = backRange.data?.data?.last_billed_period;

  useEffect(() => {
    // New unit / new start: the end can never precede the start, so reset it to the start.
    if (drawer === 'back') form.setFieldValue('period_end', backStart);
  }, [drawer, form, backStart?.valueOf()]); // eslint-disable-line react-hooks/exhaustive-deps

  const monthly = useMutation({
    mutationFn: ({ period }) => api.billings.prepareMonthly({ year: period.year(), month: period.month() + 1 }),
    onSuccess: (response) => {
      message.success(response.message || 'Tagihan bulanan berhasil disiapkan');
      setDrawer(null);
      queryClient.invalidateQueries({ queryKey: ['billings'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const special = useMutation({
    mutationFn: (values) => api.billings.prepareSpecial({
      unit_id: values.unit_id,
      year: values.period.year(),
      month: values.period.month() + 1,
      amount: values.amount,
    }),
    onSuccess: () => {
      message.success('Tagihan khusus berhasil dibuat');
      setDrawer(null);
      queryClient.invalidateQueries({ queryKey: ['billings'] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const back = useMutation({
    mutationFn: (values) => api.billings.prepareBack({
      unit_id: values.unit_id,
      periods: monthsInRange(backStart, values.period_end).map((period) => ({
        year: period.year(),
        month: period.month() + 1,
      })),
    }),
    onSuccess: () => {
      message.success('Tagihan mundur berhasil dibuat. Nominal IPL diambil otomatis untuk setiap periode.');
      setDrawer(null);
      form.resetFields();
      queryClient.invalidateQueries({ queryKey: ['billings'] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const approve = useMutation({
    mutationFn: ({ ids, notes }) => ids.length === 1
      ? api.billings.approve(ids[0], { approval_notes: notes })
      : api.billings.approveBatch({ billing_ids: ids, approval_notes: notes }),
    onSuccess: () => {
      message.success('Tagihan berhasil disetujui');
      setSelected([]);
      approveForm.resetFields();
      queryClient.invalidateQueries({ queryKey: ['billings'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const setDiscount = useMutation({
    mutationFn: ({ id, discount, discount_type: type, reason }) => api.billings.updateDiscount(id, { discount, discount_type: type, reason }),
    onSuccess: () => {
      message.success('Diskon tagihan berhasil diperbarui');
      setDiscountTarget(null);
      discountForm.resetFields();
      queryClient.invalidateQueries({ queryKey: ['billings'] });
    },
    onError: (error) => {
      discountForm.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  // Sama seperti backend: batas dihitung dari pokok tagihan, dibulatkan ke sen.
  const isPercentInput = discountType === DISCOUNT_TYPE_PERCENTAGE;
  const discountLimitAmount = maxDiscountPercent === null ? null : maxNominalFromPercent(discountTarget?.amount, maxDiscountPercent);
  const remainingPrincipal = Number(discountTarget?.amount || 0) - Number(discountTarget?.principal_paid || 0);
  const nominalDiscount = toNominalDiscount(discountTarget?.amount, watchedDiscount, discountType);

  async function handleExport(format) {
    setExporting(format);
    try {
      const { search: _search, page: _page, per_page: _perPage, ...filters } = listParams;
      const blob = format === 'pdf'
        ? await api.documents.billingRecapPdf(filters)
        : await api.documents.billingRecapExcel(filters);
      downloadBlob(blob, `${isHistory ? 'riwayat-tagihan' : 'tagihan'}.${format === 'pdf' ? 'pdf' : 'csv'}`);
    } catch (error) {
      message.error(getApiErrorMessage(error, 'Gagal mengunduh data tagihan'));
    } finally {
      setExporting(null);
    }
  }

  const columns = [
    { title: 'ID Unit', dataIndex: ['unit', 'id'], width: 100, fixed: 'left' },
    { title: 'Penghuni', dataIndex: ['unit', 'resident', 'name'], width: 220 },
    { title: 'Cluster', dataIndex: ['unit', 'cluster', 'name'], width: 140 },
    { title: 'Periode', render: (_, row) => formatPeriod(row.year, row.month), width: 140 },
    { title: 'Tipe', dataIndex: 'billing_type', width: 100 },
    { title: 'Nominal', dataIndex: 'amount', render: formatCurrency, width: 140 },
    { title: 'Diskon', dataIndex: 'discount', render: formatCurrency, width: 120 },
    { title: 'Umur Tunggakan', render: (_, row) => `${row.penalty_detail?.overdue_months ?? 0} bulan`, width: 130 },
    { title: 'Denda', render: (_, row) => formatCurrency(row.penalty_detail?.penalty_amount ?? 0), width: 130 },
    { title: 'Total', render: (_, row) => formatCurrency(row.penalty_detail?.total_amount ?? row.amount), width: 140 },
    { title: 'Dibayar', render: (_, row) => formatCurrency(row.penalty_detail?.total_paid ?? 0), width: 140 },
    { title: 'Sisa Tagihan', render: (_, row) => formatCurrency(row.penalty_detail?.total_outstanding ?? 0), width: 140 },
    { title: 'Status', dataIndex: 'status_id', render: (value) => <StatusBadge type="billing" value={value} />, width: 120 },
    { title: 'Approval', render: (_, row) => <StatusBadge type="approval" value={row.approved_at ? 'approved' : 'pending'} />, width: 130 },
    { title: 'Approved At', dataIndex: 'approved_at', render: formatDateTime, width: 170 },
    ...(isHistory ? [
      { title: 'Tgl Bayar', dataIndex: 'paid_at', render: (value) => (value ? formatDateTime(value) : '-'), width: 170 },
      { title: 'No. Kuitansi', dataIndex: 'receipt_number', render: (value) => value || '-', width: 170 },
    ] : [{
      title: 'Aksi',
      fixed: 'right',
      width: 320,
      render: (_, row) => (
        <Space>
          <Can any={['payments.process', 'payments.create']}>
            <Button
              size="small"
              type="primary"
              icon={<DollarOutlined />}
              disabled={!row.approved_at}
              title={row.approved_at ? undefined : 'Tagihan harus disetujui terlebih dahulu'}
              onClick={() => setPayTarget({ unitId: row.unit_id, ids: [row.id] })}
            >
              Bayar
            </Button>
          </Can>
          <Can permission="billings.approve">
            <Button
              size="small"
              icon={<CheckOutlined />}
              disabled={Boolean(row.approved_at)}
              onClick={() => approve.mutate({ ids: [row.id], notes: null })}
              loading={approve.isPending}
            >
              Approve
            </Button>
          </Can>
          <Can permission="billings.set-discount">
            <Button
              size="small"
              icon={<PercentageOutlined />}
              disabled={!['01', '03'].includes(row.status_id)}
              onClick={() => {
                setDiscountTarget(row);
                discountForm.setFieldsValue({ discount: fromNominalDiscount(row.amount, row.discount, discountType), reason: '' });
              }}
            >
              Set Diskon
            </Button>
          </Can>
        </Space>
      ),
    }]),
  ];

  return (
    <section>
      <PageHeader
        title={isHistory ? 'Riwayat Tagihan' : 'Tagihan'}
        subtitle={isHistory
          ? `Seluruh riwayat tagihan (belum bayar, sudah bayar, lunas) dari semua tahun${filterUnitId ? ` untuk unit ${filterUnitId}` : ''}.`
          : `Seluruh tagihan yang belum lunas dari semua tahun${filterUnitId ? ` untuk unit ${filterUnitId}` : ''}. Generate, filter, dan approval tagihan estate.`}
        breadcrumbs={[{ label: isHistory ? 'Riwayat Tagihan' : 'Tagihan' }]}
        onRefresh={() => {
          billings.refetch();
          if (!isHistory) summary.refetch();
        }}
        loading={billings.isFetching}
        extra={
          <Space wrap>
            {isHistory ? null : (
              <>
                <Can permission="billings.prepare"><Button icon={<PlusOutlined />} onClick={() => setDrawer('monthly')}>Generate Bulanan</Button></Can>
                <Can permission="billings.prepare-special"><Button onClick={() => setDrawer('special')}>Tagihan Khusus</Button></Can>
                <Can permission="billings.prepare-back"><Button onClick={() => setDrawer('back')}>Tagihan Mundur</Button></Can>
              </>
            )}
            <Can permission="documents.generate">
              <Button icon={<FilePdfOutlined />} loading={exporting === 'pdf'} disabled={Boolean(exporting)} onClick={() => handleExport('pdf')}>Download PDF</Button>
              <Button icon={<FileExcelOutlined />} loading={exporting === 'excel'} disabled={Boolean(exporting)} onClick={() => handleExport('excel')}>Download Excel</Button>
            </Can>
          </Space>
        }
      />

      <FilterBar
        extra={isHistory ? null : (
          <Space wrap>
            <Can any={['payments.process', 'payments.create']}>
              {singleUnitId ? (
                <Button type="primary" icon={<DollarOutlined />} disabled={!payableIds.length} onClick={() => setPayTarget({ unitId: singleUnitId, ids: payableIds })}>
                  Bayar Terpilih{payableIds.length ? ` (${payableIds.length})` : ''}
                </Button>
              ) : null}
            </Can>
            <Can permission="billings.approve">
              <Button icon={<CheckOutlined />} disabled={!pendingApprovalIds.length} onClick={() => approve.mutate({ ids: pendingApprovalIds, notes: approveForm.getFieldValue('approval_notes') })}>
                Approve Terpilih
              </Button>
            </Can>
          </Space>
        )}
      >
        <Input allowClear placeholder="ID unit" value={table.filters.unit_id} onChange={(event) => table.setFilters({ ...table.filters, unit_id: event.target.value || undefined })} className="filter-input" />
        <ResidentFilter value={table.filters.resident_id} onChange={(value) => table.setFilters({ ...table.filters, resident_id: value })} />
        <Select allowClear placeholder="Cluster" options={(clusters.data?.data || []).map((item) => ({ value: item.id, label: item.name }))} value={table.filters.cluster_id} onChange={(value) => table.setFilters({ ...table.filters, cluster_id: value })} className="filter-input" />
        <Input allowClear placeholder="Blok" value={table.filters.block} onChange={(event) => table.setFilters({ ...table.filters, block: event.target.value || undefined })} className="filter-input" />
        <InputNumber placeholder="Tahun" value={table.filters.year} onChange={(value) => table.setFilters({ ...table.filters, year: value })} className="filter-input" />
        <Select allowClear placeholder="Bulan" value={table.filters.month} onChange={(value) => table.setFilters({ ...table.filters, month: value })} className="filter-input" options={Array.from({ length: 12 }, (_, index) => ({ value: index + 1, label: dayjs().month(index).format('MMMM') }))} />
        <Select allowClear placeholder="Status" value={table.filters.status_id} onChange={(value) => table.setFilters({ ...table.filters, status_id: value })} className="filter-input" options={isHistory ? [{ value: '01', label: 'Belum Bayar' }, { value: '03', label: 'Sudah Bayar' }, { value: '02', label: 'Lunas' }, { value: '04', label: 'Dibatalkan' }] : [{ value: '01', label: 'Belum Bayar' }, { value: '03', label: 'Sudah Bayar' }]} />
      </FilterBar>

      {isHistory ? null : (
        <Row gutter={[16, 16]} className="section-row">
          <Col xs={24} md={8}><Card loading={summary.isLoading}><Statistic title={`Total Tagihan Belum Lunas (${summary.data?.data?.invoice_count ?? 0} tagihan)`} value={formatCurrency(summary.data?.data?.total_outstanding ?? 0)} /></Card></Col>
          <Col xs={24} md={8}><Card loading={summary.isLoading}><Statistic title="Pokok Belum Dibayar" value={formatCurrency(summary.data?.data?.total_principal_outstanding ?? 0)} /></Card></Col>
          <Col xs={24} md={8}><Card loading={summary.isLoading}><Statistic title="Denda Belum Dibayar" value={formatCurrency(summary.data?.data?.total_penalty_outstanding ?? 0)} /></Card></Col>
        </Row>
      )}

      <Card>
        <Tabs
          items={[
            {
              key: 'all',
              label: isHistory ? 'Semua Riwayat' : 'Tagihan Belum Lunas',
              children: (
                <ResponsiveTable
                  query={billings}
                  columns={columns}
                  onChange={table.handleTableChange}
                  rowSelection={isHistory ? undefined : { selectedRowKeys: selected, onChange: setSelected }}
                  scrollX={isHistory ? 2000 : 1970}
                />
              ),
            },
          ]}
        />
      </Card>

      <Drawer title="Generate Tagihan Bulanan" open={drawer === 'monthly'} onClose={() => setDrawer(null)} width={460} extra={<Button type="primary" onClick={() => form.submit()} loading={monthly.isPending}>Generate</Button>} destroyOnHidden>
        <Form form={form} layout="vertical" onFinish={monthly.mutate} initialValues={{ period: dayjs() }}>
          <Form.Item label="Periode" name="period" rules={[{ required: true }]}>
            <DatePicker picker="month" style={{ width: '100%' }} />
          </Form.Item>
        </Form>
      </Drawer>

      <Drawer title={drawer === 'special' ? 'Tagihan Khusus' : 'Tagihan Mundur'} open={drawer === 'special' || drawer === 'back'} onClose={() => setDrawer(null)} width={620} extra={<Button type="primary" onClick={() => form.submit()} loading={special.isPending || back.isPending}>Simpan</Button>} destroyOnHidden>
        {drawer === 'special' ? (
          <Form form={form} layout="vertical" onFinish={special.mutate}>
            <Form.Item label="Unit" name="unit_id" rules={[{ required: true, message: 'Pilih unit' }]}>
              <UnitPicker clusters={clusters.data?.data || []} />
            </Form.Item>
            <Form.Item label="Periode" name="period" rules={[{ required: true }]}>
              <DatePicker picker="month" style={{ width: '100%' }} />
            </Form.Item>
            <Form.Item label="Nominal" name="amount" rules={[{ required: true }]}><MoneyInput /></Form.Item>
          </Form>
        ) : (
          <Form form={form} layout="vertical" onFinish={back.mutate}>
            <Form.Item label="Unit" name="unit_id" rules={[{ required: true, message: 'Pilih unit' }]}>
              <UnitPicker clusters={clusters.data?.data || []} statusId="AK" />
            </Form.Item>
            <p className="ant-form-text" style={{ marginBottom: 12 }}>
              Nominal IPL diambil otomatis dari nominal IPL yang berlaku pada tiap periode (atau periode terakhir
              sebelumnya yang sudah memiliki nominal IPL, jika periode tersebut belum diset) dan tidak bisa diubah manual.
            </p>
            {selectedUnitId && backRange.isError ? <Alert type="error" showIcon style={{ marginBottom: 12 }} message={getApiErrorMessage(backRange.error, 'Gagal memuat periode tagihan unit')} /> : null}
            {selectedUnitId && backStart ? (
              <p className="ant-form-text" style={{ marginBottom: 12 }}>
                {backLast ? `Tagihan unit ini sudah sampai ${formatPeriod(backLast.year, backLast.month)}.` : 'Unit ini belum memiliki tagihan.'}
                {' '}Rentang periode dimulai dari bulan berikutnya dan tidak dapat diubah.
              </p>
            ) : null}
            <Space align="start" wrap>
              <Form.Item label="Mulai Periode">
                <Input disabled value={backStart ? formatPeriod(backStart.year(), backStart.month() + 1) : 'Pilih unit terlebih dahulu'} style={{ width: 220 }} />
              </Form.Item>
              <Form.Item label="Sampai Periode" name="period_end" rules={[{ required: true, message: 'Pilih bulan akhir' }]}>
                <DatePicker
                  picker="month"
                  style={{ width: 220 }}
                  disabled={!backStart}
                  allowClear={false}
                  placeholder="Pilih bulan akhir"
                  disabledDate={(current) => Boolean(backStart) && Boolean(current) && current.isBefore(backStart, 'month')}
                />
              </Form.Item>
            </Space>
            <BackPeriodsPreview unitId={selectedUnitId} start={backStart} end={backEnd} />
          </Form>
        )}
      </Drawer>

      <BillingPaymentModal
        open={Boolean(payTarget)}
        unitId={payTarget?.unitId}
        billingIds={payTarget?.ids}
        onClose={() => {
          setPayTarget(null);
          setSelected([]);
        }}
      />

      <Modal
        title={`Set Diskon - BIL-${discountTarget?.id ?? ''}`}
        open={Boolean(discountTarget)}
        onCancel={() => setDiscountTarget(null)}
        onOk={() => discountForm.submit()}
        confirmLoading={setDiscount.isPending}
        destroyOnHidden
      >
        <Form
          form={discountForm}
          layout="vertical"
          onFinish={(values) => setDiscount.mutate({ id: discountTarget.id, discount: values.discount, discount_type: discountType, reason: values.reason })}
        >
          <Form.Item label="Nominal Tagihan">
            <Input value={formatCurrency(discountTarget?.amount ?? 0)} disabled />
          </Form.Item>
          <Form.Item
            label={isPercentInput ? 'Persentase Diskon (%)' : 'Nominal Diskon (Rp)'}
            name="discount"
            extra={maxDiscountPercent === null
              ? null
              : `Batas maksimum diskon Admin: ${maxDiscountPercent}% (${formatCurrency(discountLimitAmount)}).${isPercentInput ? ` Setara ${formatCurrency(nominalDiscount)}.` : ''}`}
            rules={[
              { required: true, message: isPercentInput ? 'Persentase diskon wajib diisi' : 'Nominal diskon wajib diisi' },
              {
                validator: (_, value) => {
                  if (value == null) return Promise.resolve();
                  if (maxDiscountPercent !== null && isPercentInput && Math.round(Number(value) * 100) > Math.round(maxDiscountPercent * 100)) {
                    return Promise.reject(new Error(`Diskon melebihi batas maksimum untuk Admin (${maxDiscountPercent}%).`));
                  }
                  if (maxDiscountPercent !== null && !isPercentInput && Math.round(Number(value) * 100) > Math.round(discountLimitAmount * 100)) {
                    return Promise.reject(new Error(`Diskon melebihi batas maksimum untuk Admin (${maxDiscountPercent}%). Maksimal ${formatCurrency(discountLimitAmount)}.`));
                  }
                  if (nominalDiscount > remainingPrincipal) {
                    return Promise.reject(new Error(`Diskon tidak boleh melebihi sisa pokok tagihan (${formatCurrency(remainingPrincipal)}).`));
                  }
                  return Promise.resolve();
                },
              },
            ]}
          >
            {isPercentInput ? (
              <InputNumber min={0} max={100} step={0.01} precision={2} addonAfter="%" style={{ width: '100%' }} />
            ) : (
              <MoneyInput max={remainingPrincipal} />
            )}
          </Form.Item>
          <Form.Item label="Harga Akhir (setelah diskon)">
            <Input value={formatCurrency(finalPrice(discountTarget?.amount, nominalDiscount))} disabled />
          </Form.Item>
          <Form.Item label="Alasan" name="reason" rules={[{ required: true, message: 'Alasan diskon wajib diisi' }]}>
            <Input.TextArea rows={3} placeholder="Contoh: Kompensasi keluhan layanan, diskon karyawan, dll." />
          </Form.Item>
        </Form>
      </Modal>
    </section>
  );
}

function UnitPicker({ value, onChange, clusters = [], statusId }) {
  const [clusterId, setClusterId] = useState(undefined);
  const [search, setSearch] = useState('');
  const debounced = useDebounce(search);
  const units = useQuery({
    queryKey: ['units', 'picker', clusterId, debounced, statusId],
    queryFn: () => api.units.list({ cluster_id: clusterId, status_id: statusId, search: debounced || undefined, per_page: 20 }),
  });

  const clusterOptions = clusters.map((item) => ({ value: item.id, label: item.name }));
  const unitOptions = (units.data?.data || []).map((item) => ({
    value: item.id,
    label: `${item.id} - ${item.resident?.name || 'Belum ada penghuni'} (${item.cluster?.name || item.cluster_id})`,
  }));

  return (
    <Space.Compact style={{ width: '100%' }}>
      <Select
        allowClear
        placeholder="Cluster"
        value={clusterId}
        onChange={(next) => {
          setClusterId(next);
          onChange?.(undefined);
        }}
        options={clusterOptions}
        style={{ width: '35%' }}
      />
      <Select
        showSearch
        allowClear
        placeholder="Cari unit atau nama customer"
        value={value}
        onChange={onChange}
        onSearch={setSearch}
        filterOption={false}
        options={unitOptions}
        loading={units.isFetching}
        notFoundContent={units.isFetching ? 'Mencari...' : 'Tidak ditemukan'}
        style={{ width: '65%' }}
      />
    </Space.Compact>
  );
}

function monthsInRange(start, end) {
  if (!start || !end) return [];

  const months = [];
  let cursor = start.startOf('month');
  const lastValue = end.startOf('month').valueOf();

  // Safety cap (5 tahun) agar tidak pernah menghasilkan daftar periode yang tak terbatas.
  for (let i = 0; cursor.valueOf() <= lastValue && i < 60; i += 1) {
    months.push(cursor);
    cursor = cursor.add(1, 'month');
  }

  return months;
}

function BackPeriodsPreview({ unitId, start, end }) {
  const months = unitId ? monthsInRange(start, end) : [];

  const queries = useQueries({
    queries: months.map((period) => {
      const year = period.year();
      const month = period.month() + 1;

      return {
        queryKey: ['billings', 'back-preview', unitId, year, month],
        queryFn: () => api.billings.previewBackRate({ unit_id: unitId, year, month }),
        enabled: Boolean(unitId && year && month),
        retry: false,
      };
    }),
  });

  const total = queries.reduce((sum, query) => sum + (Number(query.data?.data?.rate) || 0), 0);
  const isCalculating = queries.some((query) => query.isFetching);
  const hasError = queries.some((query) => query.isError);

  return (
    <>
      {months.map((period, index) => {
        const query = queries[index];
        let nominalDisplay = 'Pilih unit & periode';
        if (unitId) {
          if (query.isFetching) nominalDisplay = 'Menghitung...';
          else if (query.isError) nominalDisplay = getApiErrorMessage(query.error, 'Nominal IPL belum tersedia');
          else nominalDisplay = formatCurrency(query.data?.data?.rate ?? 0);
        }

        return (
          <Space key={period.format('YYYY-MM')} align="start" className="period-row">
            <Form.Item label="Periode">
              <Input disabled value={formatPeriod(period.year(), period.month() + 1)} style={{ width: 160 }} />
            </Form.Item>
            <Form.Item label="Nominal IPL (otomatis)">
              <Input disabled value={nominalDisplay} status={query.isError ? 'error' : undefined} style={{ width: 240 }} />
            </Form.Item>
          </Space>
        );
      })}
      <div style={{ marginTop: 8, marginBottom: 16, fontWeight: 600, fontSize: 15 }}>
        Total Nominal IPL ({months.length} periode): {isCalculating ? 'Menghitung...' : formatCurrency(total)}
        {hasError && (
          <span style={{ color: '#ff4d4f', fontWeight: 400, marginLeft: 8 }}>
            (sebagian periode belum bisa dihitung nominalnya)
          </span>
        )}
      </div>
    </>
  );
}

function ResidentFilter({ value, onChange }) {
  const [search, setSearch] = useState('');
  const debounced = useDebounce(search);
  const residents = useQuery({ queryKey: ['residents', 'search', debounced], queryFn: () => api.residents.list({ search: debounced || undefined, per_page: 20 }) });
  const options = (residents.data?.data || []).map((resident) => ({ value: resident.id, label: resident.name }));

  return (
    <Select
      allowClear
      showSearch
      placeholder="Penghuni"
      value={value}
      onChange={onChange}
      onSearch={setSearch}
      filterOption={false}
      options={options}
      loading={residents.isFetching}
      notFoundContent={residents.isFetching ? 'Mencari...' : 'Tidak ditemukan'}
      className="filter-input"
    />
  );
}
