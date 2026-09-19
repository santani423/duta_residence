import { Alert, Button, Card, Descriptions, Drawer, Form, Input, InputNumber, Modal, Radio, Select, Space, Statistic, Tag, Typography, message } from 'antd';
import { CheckOutlined, CloseOutlined, EyeOutlined, PlusOutlined, SendOutlined } from '@ant-design/icons';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import Can from '../components/common/Can.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { api } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { useDebounce } from '../hooks/useDebounce.js';
import { formatCurrency, formatDateTime, formatPeriod } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';

const STATUS_OPTIONS = [
  { value: 'pending', label: 'Menunggu Admin' },
  { value: 'approved', label: 'Disetujui' },
  { value: 'rejected', label: 'Ditolak' },
  { value: 'cancelled', label: 'Dibatalkan' },
];

const moneyInput = {
  min: 0,
  addonBefore: 'Rp',
  formatter: (value) => `${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, '.'),
  parser: (value) => value?.replace(/\./g, ''),
  style: { width: '100%' },
};

/** Ringkasan angka skema (dipakai untuk preview pengajuan maupun detail skema yang sudah ada). */
function SchemeAmounts({ scheme }) {
  return (
    <Space size="large" wrap className="section-row">
      <Statistic title="Pokok Awal" value={formatCurrency(scheme.original_principal)} />
      <Statistic title="Diskon Pokok" value={formatCurrency(scheme.principal_discount)} />
      <Statistic title="Denda Awal" value={formatCurrency(scheme.original_penalty)} />
      <Statistic title="Keringanan Denda" value={formatCurrency(scheme.penalty_reduction)} />
      <Statistic title="Total Dibayar" value={formatCurrency(scheme.final_amount)} styles={{ content: { color: '#389e0d' } }} />
    </Space>
  );
}

function SchemeItemsTable({ items }) {
  return (
    <ResponsiveTable
      data={items}
      pagination={false}
      scrollX={900}
      rowKey={(row) => row.billing_id}
      columns={[
        { title: 'Periode', render: (_, row) => (row.period ? formatPeriod(...row.period.split('-')) : formatPeriod(row.billing?.year, row.billing?.month)) },
        { title: 'Pokok Awal', render: (_, row) => formatCurrency(row.original_principal) },
        { title: 'Diskon', render: (_, row) => formatCurrency(row.principal_discount) },
        { title: 'Pokok Akhir', render: (_, row) => formatCurrency(row.final_principal) },
        { title: 'Denda Awal', render: (_, row) => formatCurrency(row.original_penalty) },
        { title: 'Keringanan', render: (_, row) => formatCurrency(row.penalty_reduction) },
        { title: 'Denda Akhir', render: (_, row) => formatCurrency(row.final_penalty) },
      ]}
    />
  );
}

/** Drawer pengajuan: cari unit -> pilih tagihan + isi keringanan denda per tagihan -> diskon pokok -> ringkasan -> ajukan ke Admin. */
function SubmitDrawer({ open, onClose }) {
  const queryClient = useQueryClient();
  const [form] = Form.useForm();
  const [unitQuery, setUnitQuery] = useState('');
  const [unitId, setUnitId] = useState(undefined);
  const [selectedIds, setSelectedIds] = useState([]);
  // Keringanan denda per tagihan: { [billingId]: nominal }. Hanya untuk tagihan yang dicentang.
  const [reductions, setReductions] = useState({});
  const debouncedUnitQuery = useDebounce(unitQuery);

  const discountType = Form.useWatch('discount_type', form) ?? 'nominal';
  const watchedDiscount = Form.useWatch('discount_value', form);
  const discountValue = useDebounce(watchedDiscount, 400);
  const debouncedReductions = useDebounce(reductions, 400);

  const unitSearch = debouncedUnitQuery.trim();
  const unitLookup = useQuery({
    queryKey: ['units', 'scheme-search', unitSearch],
    queryFn: () => api.units.list({ search: unitSearch || undefined, per_page: 20 }),
    enabled: open,
    // Hasil pencarian sebelumnya tetap tampil selama pencarian baru dimuat, supaya daftar tidak
    // "hilang" (mis. saat server lambat) setiap kali user mengetik.
    placeholderData: keepPreviousData,
  });
  const unitOptions = (unitLookup.data?.data || []).map((item) => ({
    value: item.id,
    label: `${item.id} — ${item.cluster?.name || ''} ${item.block || ''}/${item.lot_number || ''} — ${item.resident?.name || ''}`,
  }));

  const unit = useQuery({
    queryKey: ['payment-scheme-unit', unitId],
    queryFn: () => api.payments.search({ unit_id: unitId }),
    enabled: Boolean(unitId),
  });
  const pending = useQuery({
    queryKey: ['payment-schemes', 'pending-for-unit', unitId],
    queryFn: () => api.paymentSchemes.list({ unit_id: unitId, status: 'pending', per_page: 100 }),
    enabled: Boolean(unitId),
  });

  const billings = useMemo(() => unit.data?.data?.billings || [], [unit.data]);
  // Tagihan yang sudah punya skema aktif (menunggu atau sudah disetujui) tidak boleh dipilih lagi.
  const lockedIds = useMemo(() => {
    const ids = new Set(billings.filter((billing) => billing.payment_scheme_id).map((billing) => billing.id));
    (pending.data?.data || []).forEach((scheme) => scheme.items?.forEach((item) => ids.add(item.billing_id)));
    return ids;
  }, [billings, pending.data]);
  const penaltyOf = (billing) => Number(billing.penalty_detail?.outstanding_penalty ?? 0);

  function buildPayload(discount, reductionMap) {
    const penalty_reductions = Object.fromEntries(
      Object.entries(reductionMap).filter(([id, value]) => selectedIds.includes(Number(id)) && Number(value) > 0).map(([id, value]) => [id, Number(value)]),
    );
    return {
      unit_id: unitId,
      billing_ids: selectedIds,
      discount_type: discountType,
      discount_value: Number(discount) || 0,
      ...(Object.keys(penalty_reductions).length ? { penalty_reductions } : {}),
    };
  }
  const payload = buildPayload(discountValue, debouncedReductions);
  // Angka di layar sudah berubah tetapi hitungan server belum menyusul (debounce): jangan boleh diajukan dulu.
  const isSettling = JSON.stringify(buildPayload(watchedDiscount, reductions)) !== JSON.stringify(payload);

  const preview = useQuery({
    queryKey: ['payment-scheme-preview', payload],
    queryFn: () => api.paymentSchemes.preview(payload),
    enabled: Boolean(unitId) && selectedIds.length > 0,
    retry: false,
  });
  const calc = selectedIds.length ? preview.data?.data : undefined;

  const submit = useMutation({
    mutationFn: (values) => api.paymentSchemes.create({ ...buildPayload(watchedDiscount, reductions), reason: values.reason }),
    onSuccess: () => {
      message.success('Skema pembayaran berhasil diajukan ke Admin');
      queryClient.invalidateQueries({ queryKey: ['payment-schemes'] });
      close();
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error).filter((field) => field.name === 'reason'));
      message.error(getApiErrorMessage(error));
    },
  });

  function close() {
    setUnitId(undefined);
    setUnitQuery('');
    setSelectedIds([]);
    setReductions({});
    form.resetFields();
    onClose();
  }

  function changeUnit(value) {
    setUnitId(value);
    setSelectedIds([]);
    setReductions({});
    form.setFieldsValue({ discount_value: undefined });
  }

  function changeSelection(keys) {
    setSelectedIds(keys);
    // Keringanan tagihan yang dicentang-ulang (dilepas) tidak boleh ikut terhitung.
    setReductions((previous) => Object.fromEntries(Object.entries(previous).filter(([id]) => keys.includes(Number(id)))));
  }

  function setReduction(billingId, value) {
    setReductions((previous) => ({ ...previous, [billingId]: value ?? 0 }));
  }

  function waiveAllPenalties() {
    setReductions(Object.fromEntries(billings.filter((billing) => selectedIds.includes(billing.id)).map((billing) => [billing.id, penaltyOf(billing)])));
  }

  function confirmSubmit(values) {
    Modal.confirm({
      title: 'Ajukan skema ke Admin?',
      content: `Total yang harus dibayar pelanggan jika disetujui: ${formatCurrency(calc?.final_amount)}. Selama menunggu persetujuan, jangan proses pembayaran untuk tagihan ini — skema akan otomatis dibatalkan.`,
      okText: 'Ajukan',
      cancelText: 'Periksa lagi',
      onOk: () => submit.mutateAsync(values).catch(() => undefined),
    });
  }

  const previewError = preview.isError ? getApiErrorMessage(preview.error) : null;
  const hasPenalty = billings.some((billing) => selectedIds.includes(billing.id) && penaltyOf(billing) > 0);

  return (
    <Drawer
      title="Ajukan Skema Pembayaran"
      open={open}
      onClose={close}
      width={980}
      destroyOnHidden
      extra={(
        <Button type="primary" icon={<SendOutlined />} disabled={!calc || Boolean(previewError) || isSettling} loading={submit.isPending} onClick={() => form.submit()}>
          Ajukan ke Admin
        </Button>
      )}
    >
      <Space direction="vertical" size="large" style={{ width: '100%' }}>
        <Alert
          type="info"
          showIcon
          message="Diskon berlaku setelah Admin menyetujui"
          description="Keringanan denda diisi langsung pada tagihan yang dicentang, diskon diberikan atas total sisa pokok. Semua tagihan yang dipilih digabung menjadi satu kewajiban pembayaran."
        />

        <Card size="small" title="1. Pilih unit">
          <Select
            showSearch
            allowClear
            value={unitId}
            filterOption={false}
            onSearch={setUnitQuery}
            onChange={changeUnit}
            options={unitOptions}
            loading={unitLookup.isFetching}
            notFoundContent={unitLookup.isError
              ? <Typography.Text type="danger">Gagal memuat unit: {getApiErrorMessage(unitLookup.error)}</Typography.Text>
              : (unitLookup.isFetching ? 'Mencari...' : 'Unit tidak ditemukan')}
            placeholder="Cari ID unit, alamat (cluster/blok/kavling), atau nama penghuni"
            style={{ width: '100%' }}
          />
          {unitLookup.isError ? (
            <Alert style={{ marginTop: 12 }} type="error" showIcon message={`Daftar unit gagal dimuat: ${getApiErrorMessage(unitLookup.error)}`} action={<Button size="small" onClick={() => unitLookup.refetch()}>Coba lagi</Button>} />
          ) : null}
        </Card>

        {unitId ? (
          <Card
            size="small"
            title="2. Pilih tagihan, lalu isi keringanan denda per tagihan"
            loading={unit.isLoading}
            extra={<Button size="small" disabled={!hasPenalty} onClick={waiveAllPenalties}>Hapus seluruh denda yang dipilih</Button>}
          >
            {unit.isError ? <Alert type="error" showIcon message={getApiErrorMessage(unit.error)} /> : (
              <ResponsiveTable
                data={billings}
                pagination={false}
                scrollX={1000}
                rowSelection={{
                  selectedRowKeys: selectedIds,
                  onChange: changeSelection,
                  getCheckboxProps: (row) => ({ disabled: lockedIds.has(row.id) }),
                }}
                columns={[
                  { title: 'Periode', render: (_, row) => formatPeriod(row.year, row.month) },
                  { title: 'Sisa Pokok', render: (_, row) => formatCurrency(row.penalty_detail?.outstanding_principal) },
                  { title: 'Denda', render: (_, row) => formatCurrency(penaltyOf(row)) },
                  {
                    title: 'Keringanan Denda',
                    width: 240,
                    render: (_, row) => {
                      if (lockedIds.has(row.id)) return <Tag color="gold">Sudah dalam skema</Tag>;
                      if (!selectedIds.includes(row.id)) return <Typography.Text type="secondary">Centang tagihan untuk mengisi</Typography.Text>;
                      if (penaltyOf(row) <= 0) return <Typography.Text type="secondary">Tidak ada denda</Typography.Text>;
                      return (
                        <Space.Compact style={{ width: '100%' }}>
                          <InputNumber {...moneyInput} max={penaltyOf(row)} value={reductions[row.id]} onChange={(value) => setReduction(row.id, value)} placeholder="0" />
                          <Button title="Hapus seluruh denda tagihan ini" onClick={() => setReduction(row.id, penaltyOf(row))}>Hapus</Button>
                        </Space.Compact>
                      );
                    },
                  },
                  {
                    title: 'Denda Akhir',
                    render: (_, row) => (selectedIds.includes(row.id) ? <strong>{formatCurrency(Math.max(0, penaltyOf(row) - Number(reductions[row.id] || 0)))}</strong> : '-'),
                  },
                  { title: 'Sisa Tagihan', render: (_, row) => formatCurrency(row.penalty_detail?.total_outstanding) },
                ]}
              />
            )}
          </Card>
        ) : null}

        {selectedIds.length ? (
          <Form form={form} layout="vertical" onFinish={confirmSubmit} initialValues={{ discount_type: 'nominal' }}>
            <Card size="small" title="3. Diskon pokok">
              <Form.Item label="Jenis diskon" name="discount_type">
                <Radio.Group
                  optionType="button"
                  buttonStyle="solid"
                  options={[{ value: 'nominal', label: 'Nominal (Rp)' }, { value: 'percentage', label: 'Persentase (%)' }]}
                  onChange={() => form.setFieldValue('discount_value', undefined)}
                />
              </Form.Item>
              <Form.Item label="Diskon atas total sisa pokok" name="discount_value" style={{ marginBottom: 0 }}>
                {discountType === 'percentage'
                  ? <InputNumber min={0} max={100} step={0.5} precision={2} addonAfter="%" style={{ width: '100%' }} />
                  : <InputNumber {...moneyInput} max={calc?.original_principal} />}
              </Form.Item>
            </Card>

            <Card size="small" title="4. Ringkasan" style={{ marginTop: 16 }} loading={preview.isFetching && !calc}>
              {previewError ? <Alert type="error" showIcon message={previewError} /> : null}
              {calc && !previewError ? <SchemeAmounts scheme={calc} /> : null}
            </Card>

            <Card size="small" title="5. Alasan pengajuan" style={{ marginTop: 16 }}>
              <Form.Item name="reason" rules={[{ required: true, message: 'Alasan wajib diisi' }]} style={{ marginBottom: 0 }}>
                <Input.TextArea rows={3} maxLength={500} showCount placeholder="Contoh: Pelanggan minta keringanan dan bersedia melunasi hari ini" />
              </Form.Item>
            </Card>
          </Form>
        ) : null}
      </Space>
    </Drawer>
  );
}

function DetailDrawer({ scheme, onClose }) {
  return (
    <Drawer title={scheme ? `Skema Pembayaran #${scheme.id}` : ''} open={Boolean(scheme)} onClose={onClose} width={860} destroyOnHidden>
      {scheme ? (
        <Space direction="vertical" size="large" style={{ width: '100%' }}>
          {scheme.status === 'cancelled' ? (
            <Alert type="warning" showIcon message="Skema dibatalkan otomatis" description={`${scheme.cancellation_reason || '-'} Ajukan skema baru jika pelanggan masih membutuhkan diskon.`} />
          ) : null}
          <Descriptions
            size="small"
            column={2}
            items={[
              { key: 'unit', label: 'Unit', children: `${scheme.unit_id} — ${scheme.unit?.cluster?.name || ''} ${scheme.unit?.block || ''}/${scheme.unit?.lot_number || ''}` },
              { key: 'resident', label: 'Penghuni', children: scheme.unit?.resident?.name || '-' },
              { key: 'status', label: 'Status', children: <StatusBadge type="paymentScheme" value={scheme.status} /> },
              { key: 'submitted', label: 'Diajukan', children: `${scheme.submitter?.name || '-'} · ${formatDateTime(scheme.submitted_at)}` },
              { key: 'decided', label: 'Diputuskan', children: scheme.decided_at ? `${scheme.decider?.name || '-'} · ${formatDateTime(scheme.decided_at)}` : '-' },
              { key: 'reason', label: 'Alasan', children: scheme.reason || '-' },
              { key: 'notes', label: 'Catatan Admin', children: scheme.review_notes || '-' },
            ]}
          />
          <SchemeAmounts scheme={scheme} />
          <SchemeItemsTable items={scheme.items || []} />
        </Space>
      ) : null}
    </Drawer>
  );
}

export default function PaymentSchemesPage() {
  const table = useTableState();
  const queryClient = useQueryClient();
  const [submitOpen, setSubmitOpen] = useState(false);
  const [detail, setDetail] = useState(null);
  const [decision, setDecision] = useState(null);
  const [decisionForm] = Form.useForm();

  const schemes = useQuery({ queryKey: ['payment-schemes', table.params], queryFn: () => api.paymentSchemes.list(table.params) });

  const decide = useMutation({
    mutationFn: ({ id, action, notes }) => (action === 'approve'
      ? api.paymentSchemes.approve(id, { notes })
      : api.paymentSchemes.reject(id, { notes })),
    onSuccess: (_, { action }) => {
      message.success(action === 'approve' ? 'Skema pembayaran disetujui' : 'Skema pembayaran ditolak');
      closeDecision();
      queryClient.invalidateQueries({ queryKey: ['payment-schemes'] });
    },
    onError: (error) => {
      message.error(getApiErrorMessage(error));
      // Skema bisa saja sudah dibatalkan otomatis; muat ulang supaya statusnya terbaru.
      queryClient.invalidateQueries({ queryKey: ['payment-schemes'] });
    },
  });

  function closeDecision() {
    setDecision(null);
    decisionForm.resetFields();
  }

  return (
    <section>
      <PageHeader
        title="Skema Pembayaran"
        subtitle="Pengajuan diskon pokok dan keringanan denda atas beberapa tagihan, menunggu persetujuan Admin."
        breadcrumbs={[{ label: 'Skema Pembayaran' }]}
        onRefresh={schemes.refetch}
        extra={<Can permission="payment-schemes.submit"><Button type="primary" icon={<PlusOutlined />} onClick={() => setSubmitOpen(true)}>Ajukan Skema</Button></Can>}
      />
      <FilterBar>
        <Input.Search allowClear placeholder="Cari ID unit / penghuni" value={table.search} onChange={(event) => table.setSearch(event.target.value)} className="filter-input" />
        <Select allowClear placeholder="Status" value={table.filters.status} onChange={(value) => table.setFilters({ ...table.filters, status: value })} className="filter-input" options={STATUS_OPTIONS} />
      </FilterBar>
      <Card>
        <ResponsiveTable
          query={schemes}
          onChange={table.handleTableChange}
          scrollX={1300}
          columns={[
            { title: 'No.', dataIndex: 'id', width: 70 },
            { title: 'Unit', render: (_, row) => `${row.unit_id} — ${row.unit?.cluster?.name || ''} ${row.unit?.block || ''}/${row.unit?.lot_number || ''}` },
            { title: 'Penghuni', render: (_, row) => row.unit?.resident?.name || '-' },
            { title: 'Tagihan', width: 90, render: (_, row) => `${row.items?.length ?? 0} bulan` },
            { title: 'Pokok Awal', render: (_, row) => formatCurrency(row.original_principal) },
            { title: 'Diskon', render: (_, row) => formatCurrency(row.principal_discount) },
            { title: 'Keringanan Denda', render: (_, row) => formatCurrency(row.penalty_reduction) },
            { title: 'Total Dibayar', render: (_, row) => <strong>{formatCurrency(row.final_amount)}</strong> },
            { title: 'Status', dataIndex: 'status', render: (value) => <StatusBadge type="paymentScheme" value={value} /> },
            { title: 'Diajukan', render: (_, row) => `${row.submitter?.name || '-'} · ${formatDateTime(row.submitted_at)}` },
            {
              title: 'Aksi',
              width: 260,
              fixed: 'right',
              render: (_, row) => (
                <Space>
                  <Button size="small" icon={<EyeOutlined />} onClick={() => setDetail(row)}>Detail</Button>
                  <Can permission="payment-schemes.approve">
                    <Button size="small" icon={<CheckOutlined />} disabled={row.status !== 'pending'} onClick={() => setDecision({ row, action: 'approve' })}>Setujui</Button>
                    <Button size="small" danger icon={<CloseOutlined />} disabled={row.status !== 'pending'} onClick={() => setDecision({ row, action: 'reject' })}>Tolak</Button>
                  </Can>
                </Space>
              ),
            },
          ]}
        />
      </Card>

      <SubmitDrawer open={submitOpen} onClose={() => setSubmitOpen(false)} />
      <DetailDrawer scheme={detail} onClose={() => setDetail(null)} />

      <Modal
        open={Boolean(decision)}
        title={decision?.action === 'approve' ? `Setujui skema #${decision?.row.id}?` : `Tolak skema #${decision?.row.id}?`}
        okText={decision?.action === 'approve' ? 'Setujui' : 'Tolak'}
        okButtonProps={{ danger: decision?.action === 'reject', loading: decide.isPending }}
        cancelText="Batal"
        onCancel={closeDecision}
        onOk={() => decisionForm.validateFields().then((values) => decide.mutate({ id: decision.row.id, action: decision.action, notes: values.notes }))}
        destroyOnHidden
      >
        {decision ? (
          <>
            <Typography.Paragraph>
              Unit <strong>{decision.row.unit_id}</strong> — total dibayar setelah skema <strong>{formatCurrency(decision.row.final_amount)}</strong>
              {' '}(diskon {formatCurrency(decision.row.principal_discount)}, keringanan denda {formatCurrency(decision.row.penalty_reduction)}).
            </Typography.Paragraph>
            <Form form={decisionForm} layout="vertical">
              <Form.Item label="Catatan" name="notes" rules={decision.action === 'reject' ? [{ required: true, message: 'Alasan penolakan wajib diisi' }] : []}>
                <Input.TextArea rows={3} maxLength={500} />
              </Form.Item>
            </Form>
          </>
        ) : null}
      </Modal>
    </section>
  );
}
