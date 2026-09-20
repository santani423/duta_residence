import { Alert, Button, Card, Descriptions, Drawer, Form, Input, InputNumber, Modal, Radio, Select, Space, Statistic, Tag, Typography, message } from 'antd';
import { CheckOutlined, CloseOutlined, EyeOutlined, PlusOutlined, SendOutlined, WalletOutlined } from '@ant-design/icons';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import PageHeader from '../components/common/PageHeader.jsx';
import ExportPdfButton from '../components/common/ExportPdfButton.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import Can from '../components/common/Can.jsx';
import MoneyInput from '../components/common/MoneyInput.jsx';
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

const PAYMENT_STATUS = {
  unpaid: ['Belum dibayar', 'gold'],
  partial: ['Dibayar sebagian', 'blue'],
  paid: ['Lunas', 'green'],
};

/** Status pembayaran skema yang sudah disetujui, beserta sisa yang masih harus dibayar. */
function PaymentProgress({ scheme }) {
  const [label, color] = PAYMENT_STATUS[scheme.payment_status] || [];

  if (!label) return <Typography.Text type="secondary">-</Typography.Text>;

  return (
    <Space direction="vertical" size={0}>
      <Tag color={color}>{label}</Tag>
      {scheme.payment_status !== 'paid' ? <Typography.Text type="secondary">Sisa {formatCurrency(scheme.outstanding_amount)}</Typography.Text> : null}
    </Space>
  );
}

const percentFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

/** Persentase `part` terhadap `whole`, mis. "12,5%". Kosong (0%) bila tidak ada pembanding. */
function percentOf(part, whole) {
  const base = Number(whole) || 0;
  return `${percentFormat.format(base > 0 ? (Number(part) / base) * 100 : 0)}%`;
}

/** Rupiah beserta persentasenya terhadap sisa pokok, mis. "Rp 100.000 (10%)". */
function moneyWithPercent(part, whole) {
  return `${formatCurrency(part)} (${percentOf(part, whole)})`;
}

/** Ringkasan angka skema (dipakai untuk preview pengajuan maupun detail skema yang sudah ada). */
function SchemeAmounts({ scheme }) {
  return (
    <Space size="large" wrap className="section-row">
      <Statistic title="Pokok Awal" value={formatCurrency(scheme.original_principal)} />
      <Statistic title="Diskon Pokok" value={formatCurrency(scheme.principal_discount)} suffix={<span style={{ fontSize: 14 }}>({percentOf(scheme.principal_discount, scheme.original_principal)})</span>} />
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
        {
          title: 'Periode',
          render: (_, row) => {
            const label = row.period ? formatPeriod(...row.period.split('-')) : formatPeriod(row.billing?.year, row.billing?.month);
            return row.status === 'rejected'
              ? <Space size={4}><Typography.Text delete type="secondary">{label}</Typography.Text><Tag color="red">Ditolak Admin</Tag></Space>
              : label;
          },
        },
        { title: 'Pokok Awal', render: (_, row) => formatCurrency(row.original_principal) },
        { title: 'Diskon', render: (_, row) => moneyWithPercent(row.principal_discount, row.original_principal) },
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
                          <MoneyInput max={penaltyOf(row)} value={reductions[row.id]} onChange={(value) => setReduction(row.id, value)} placeholder="0" />
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
                  : <MoneyInput max={calc?.original_principal} />}
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

function DetailDrawer({ scheme, onClose, onPay }) {
  const rejectedMonths = (scheme?.items || []).filter((item) => item.status === 'rejected').map((item) => formatPeriod(item.billing?.year, item.billing?.month));

  return (
    <Drawer title={scheme ? `Skema Pembayaran #${scheme.id}` : ''} open={Boolean(scheme)} onClose={onClose} width={860} destroyOnHidden>
      {scheme ? (
        <Space direction="vertical" size="large" style={{ width: '100%' }}>
          {scheme.adjusted_at && scheme.requested_snapshot ? (
            <Alert
              type="info"
              showIcon
              message={`Diubah oleh Admin ${scheme.adjuster?.name || ''} saat persetujuan (${formatDateTime(scheme.adjusted_at)})`}
              description={`Usulan loket: diskon ${moneyWithPercent(scheme.requested_snapshot.principal_discount, scheme.requested_snapshot.original_principal ?? scheme.original_principal)}, keringanan denda ${formatCurrency(scheme.requested_snapshot.penalty_reduction)}, total ${formatCurrency(scheme.requested_snapshot.final_amount)}. Yang disetujui: diskon ${moneyWithPercent(scheme.principal_discount, scheme.original_principal)}, keringanan denda ${formatCurrency(scheme.penalty_reduction)}, total ${formatCurrency(scheme.final_amount)}.${rejectedMonths.length ? ` Bulan ditolak: ${rejectedMonths.join(', ')}.` : ''}`}
            />
          ) : null}
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
          {scheme.status === 'approved' ? (
            <Card size="small" title="Pembayaran skema">
              <Space size="large" wrap>
                <PaymentProgress scheme={scheme} />
                <Statistic title="Sudah dibayar" value={formatCurrency(scheme.paid_amount)} />
                <Statistic title="Sisa" value={formatCurrency(scheme.outstanding_amount)} />
                {scheme.payment_status !== 'paid' ? (
                  <Can permission="payments.process">
                    <Button type="primary" icon={<WalletOutlined />} onClick={() => onPay(scheme)}>Bayar Skema</Button>
                  </Can>
                ) : null}
              </Space>
            </Card>
          ) : null}
          <SchemeItemsTable items={scheme.items || []} />
        </Space>
      ) : null}
    </Drawer>
  );
}

/** Admin meninjau usulan loket: boleh mengubah diskon dan keringanan denda per tagihan sebelum menyetujui. */
function ApproveDrawer({ scheme, onClose, onDone }) {
  // Jenis input awal mengikuti pengajuan loket, supaya nilai yang tampil sama persis dengan yang diajukan.
  const [discountType, setDiscountType] = useState(scheme?.discount_type === 'percentage' ? 'percentage' : 'nominal');
  const [discountValue, setDiscountValue] = useState(() => {
    const discount = Number(scheme?.principal_discount) || 0;
    const base = Number(scheme?.original_principal) || 0;

    return scheme?.discount_type === 'percentage' && base > 0 ? Math.round((discount / base) * 1000000) / 10000 : discount;
  });
  const [reductions, setReductions] = useState(() => Object.fromEntries((scheme?.items || []).map((item) => [item.billing_id, Number(item.penalty_reduction) || 0])));
  const [notes, setNotes] = useState('');
  // Bulan yang ditolak Admin (dikeluarkan dari skema); sisanya tetap diproses.
  const [rejectedIds, setRejectedIds] = useState([]);
  const debouncedDiscount = useDebounce(discountValue, 400);
  const debouncedReductions = useDebounce(reductions, 400);
  const debouncedRejected = useDebounce(rejectedIds, 400);

  const build = (discount, reductionMap, rejected) => ({
    discount_type: discountType,
    discount_value: Number(discount) || 0,
    penalty_reductions: Object.fromEntries(
      Object.entries(reductionMap).filter(([id, value]) => Number(value) > 0 && !rejected.includes(Number(id))).map(([id, value]) => [id, Number(value)]),
    ),
    ...(rejected.length ? { rejected_billing_ids: rejected } : {}),
  });
  const adjustments = build(debouncedDiscount, debouncedReductions, debouncedRejected);
  // Angka di layar sudah berubah tetapi hitungan server belum menyusul (debounce).
  const isSettling = JSON.stringify(build(discountValue, reductions, rejectedIds)) !== JSON.stringify(adjustments);

  const preview = useQuery({
    queryKey: ['payment-scheme-adjustment-preview', scheme?.id, adjustments],
    queryFn: () => api.paymentSchemes.previewAdjustment(scheme.id, { adjustments }),
    enabled: Boolean(scheme),
    retry: false,
  });
  const calc = preview.data?.data;
  const previewError = preview.isError ? getApiErrorMessage(preview.error) : null;
  const changed = Boolean(calc) && (
    rejectedIds.length > 0
    || Math.abs(Number(calc.principal_discount) - Number(scheme?.principal_discount)) > 0.001
    || Math.abs(Number(calc.penalty_reduction) - Number(scheme?.penalty_reduction)) > 0.001
    || (calc.items || []).some((row) => {
      const item = scheme.items.find((candidate) => candidate.billing_id === row.billing_id);
      return Math.abs(Number(row.penalty_reduction) - Number(item?.penalty_reduction)) > 0.001;
    })
  );

  // Diskon yang sedang diisi Admin, dalam Rupiah dan persen (apa pun jenis inputnya).
  const includedItems = (scheme?.items || []).filter((item) => !rejectedIds.includes(item.billing_id));
  const principal = includedItems.reduce((sum, item) => sum + (Number(item.original_principal) || 0), 0);
  const currentDiscount = discountType === 'percentage' ? (principal * (Number(discountValue) || 0)) / 100 : Number(discountValue) || 0;
  const currentPercent = principal > 0 ? (currentDiscount / principal) * 100 : 0;

  // Batas diskon yang ditetapkan Super Admin hanya mengikat Admin; Super Admin bebas.
  const limited = Boolean(scheme?.viewer_limited);
  const limitPercent = Number(scheme?.admin_limit_percent) || 0;
  const overLimit = limited && currentPercent > limitPercent + 0.0001;

  const approve = useMutation({
    mutationFn: () => api.paymentSchemes.approve(scheme.id, {
      notes: notes || undefined,
      adjustments: build(discountValue, reductions, rejectedIds),
    }),
    onSuccess: () => {
      message.success(changed ? 'Skema diubah dan disetujui' : 'Skema pembayaran disetujui');
      onDone();
    },
    onError: (error) => {
      message.error(getApiErrorMessage(error));
      // Skema bisa saja sudah dibatalkan otomatis; muat ulang supaya statusnya terbaru.
      onDone(false);
    },
  });

  return (
    <Drawer
      title={scheme ? `Tinjau & Setujui Skema #${scheme.id}` : ''}
      open={Boolean(scheme)}
      onClose={onClose}
      width={980}
      destroyOnHidden
      extra={(
        <Space>
          <Button onClick={onClose}>Batal</Button>
          <Button
            type="primary"
            icon={<CheckOutlined />}
            disabled={!calc || Boolean(previewError) || isSettling || overLimit}
            title={overLimit ? `Diskon melebihi batas Admin (${percentFormat.format(limitPercent)}%). Turunkan diskon hingga batas untuk menyetujui.` : undefined}
            loading={approve.isPending}
            onClick={() => approve.mutate()}
          >
            {changed ? 'Simpan perubahan & Setujui' : 'Setujui'}
          </Button>
        </Space>
      )}
    >
      {scheme ? (
        <Space direction="vertical" size="large" style={{ width: '100%' }}>
          <Alert
            type="info"
            showIcon
            message={`Unit ${scheme.unit_id} — ${scheme.unit?.resident?.name || '-'}`}
            description={`Diajukan ${scheme.submitter?.name || '-'} (${formatDateTime(scheme.submitted_at)}). Alasan: ${scheme.reason || '-'}. Anda boleh mengubah diskon dan keringanan denda di bawah; usulan asli loket tetap tercatat.`}
          />

          {limited && scheme.exceeds_admin_limit ? (
            <Alert
              type="warning"
              showIcon
              message={`Usulan loket melebihi batas diskon Admin (${percentFormat.format(limitPercent)}%)`}
              description={`Admin tidak dapat menyetujui atau menolak skema ini apa adanya. Turunkan diskon hingga maksimal ${percentFormat.format(limitPercent)}% lalu setujui. Kalau diskon tidak diturunkan, skema ini menunggu keputusan Super Admin, yang sudah menerima pengajuan ini.`}
            />
          ) : null}

          <Card size="small" title="Tinjau per bulan: keringanan denda dan penolakan">
            <ResponsiveTable
              data={scheme.items || []}
              pagination={false}
              scrollX={1000}
              rowKey={(row) => row.billing_id}
              columns={[
                {
                  title: 'Periode',
                  render: (_, row) => {
                    const label = formatPeriod(row.billing?.year, row.billing?.month);
                    return rejectedIds.includes(row.billing_id)
                      ? <Space size={4}><Typography.Text delete type="secondary">{label}</Typography.Text><Tag color="red">Ditolak</Tag></Space>
                      : label;
                  },
                },
                { title: 'Sisa Pokok', render: (_, row) => formatCurrency(row.original_principal) },
                { title: 'Denda', render: (_, row) => formatCurrency(row.original_penalty) },
                { title: 'Usulan Loket', render: (_, row) => formatCurrency(row.penalty_reduction) },
                {
                  title: 'Keringanan Denda',
                  width: 240,
                  render: (_, row) => {
                    if (rejectedIds.includes(row.billing_id)) return <Typography.Text type="secondary">Tidak diberi keringanan</Typography.Text>;
                    if (Number(row.original_penalty) <= 0) return <Typography.Text type="secondary">Tidak ada denda</Typography.Text>;
                    return (
                      <Space.Compact style={{ width: '100%' }}>
                        <MoneyInput max={Number(row.original_penalty)} value={reductions[row.billing_id]} onChange={(value) => setReductions((previous) => ({ ...previous, [row.billing_id]: value ?? 0 }))} />
                        <Button title="Hapus seluruh denda tagihan ini" onClick={() => setReductions((previous) => ({ ...previous, [row.billing_id]: Number(row.original_penalty) }))}>Hapus</Button>
                      </Space.Compact>
                    );
                  },
                },
                {
                  title: 'Denda Akhir',
                  render: (_, row) => (rejectedIds.includes(row.billing_id)
                    ? formatCurrency(row.original_penalty)
                    : <strong>{formatCurrency(Math.max(0, Number(row.original_penalty) - Number(reductions[row.billing_id] || 0)))}</strong>),
                },
                {
                  title: 'Keputusan',
                  width: 170,
                  fixed: 'right',
                  render: (_, row) => (rejectedIds.includes(row.billing_id)
                    ? <Button size="small" onClick={() => setRejectedIds((previous) => previous.filter((id) => id !== row.billing_id))}>Batalkan penolakan</Button>
                    : (
                      <Button
                        size="small"
                        danger
                        icon={<CloseOutlined />}
                        disabled={includedItems.length <= 1}
                        title={includedItems.length <= 1 ? 'Minimal satu bulan harus tetap ada. Gunakan Tolak untuk menolak seluruh skema.' : 'Keluarkan bulan ini dari skema'}
                        onClick={() => setRejectedIds((previous) => [...previous, row.billing_id])}
                      >
                        Tolak bulan ini
                      </Button>
                    )),
                },
              ]}
            />
            {rejectedIds.length ? (
              <Alert
                style={{ marginTop: 12 }}
                type="warning"
                showIcon
                message={`${rejectedIds.length} bulan ditolak: ${(scheme.items || []).filter((item) => rejectedIds.includes(item.billing_id)).map((item) => formatPeriod(item.billing?.year, item.billing?.month)).join(', ')}`}
                description="Bulan yang ditolak tidak mendapat diskon maupun keringanan denda dan kembali menjadi tagihan biasa. Loket dapat mengajukan skema baru untuk bulan itu."
              />
            ) : null}
          </Card>

          <Card size="small" title="Diskon pokok">
            <Space direction="vertical" style={{ width: '100%' }}>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: 16 }}>
                <div>
                  <Typography.Text>Nominal (Rp)</Typography.Text>
                  <MoneyInput
                    max={limited ? Math.floor((principal * limitPercent) / 100) : principal}
                    value={discountType === 'nominal' ? discountValue : Math.round(currentDiscount * 100) / 100}
                    onChange={(value) => { setDiscountType('nominal'); setDiscountValue(value ?? 0); }}
                  />
                </div>
                <div>
                  <Typography.Text>Persentase (%)</Typography.Text>
                  <InputNumber
                    min={0}
                    max={limited ? limitPercent : 100}
                    step={0.5}
                    precision={2}
                    addonAfter="%"
                    style={{ width: '100%' }}
                    status={overLimit ? 'error' : undefined}
                    value={discountType === 'percentage' ? discountValue : Math.round(currentPercent * 100) / 100}
                    onChange={(value) => { setDiscountType('percentage'); setDiscountValue(value ?? 0); }}
                  />
                </div>
              </div>
              <Typography.Text strong>
                Diskon saat ini: {formatCurrency(currentDiscount)} ({percentFormat.format(currentPercent)}% dari total sisa pokok {formatCurrency(principal)}{rejectedIds.length ? ` — hanya bulan yang tidak ditolak` : ''})
              </Typography.Text>
              {limited ? (
                <Typography.Text type={overLimit ? 'danger' : 'secondary'}>
                  {overLimit
                    ? `Melebihi batas maksimum Admin ${percentFormat.format(limitPercent)}% yang ditetapkan Super Admin — turunkan diskon untuk dapat menyetujui.`
                    : `Batas maksimum Admin: ${percentFormat.format(limitPercent)}% (ditetapkan Super Admin), setara ${formatCurrency(Math.floor((principal * limitPercent) / 100))}.`}
                </Typography.Text>
              ) : <Typography.Text type="secondary">Anda Super Admin: diskon tidak dibatasi.</Typography.Text>}
              <Typography.Text type="secondary">Isi salah satu, yang lain terhitung otomatis. Usulan loket: {moneyWithPercent(scheme.principal_discount, scheme.original_principal)}. Batas diskon Admin tetap berlaku saat menyetujui.</Typography.Text>
            </Space>
          </Card>

          <Card size="small" title="Ringkasan" loading={preview.isFetching && !calc}>
            {previewError ? <Alert type="error" showIcon message={previewError} /> : null}
            {calc && !previewError ? (
              <>
                <SchemeAmounts scheme={calc} />
                {changed ? (
                  <Alert
                    type="warning"
                    showIcon
                    message={`Berbeda dari usulan loket: total ${formatCurrency(scheme.final_amount)} menjadi ${formatCurrency(calc.final_amount)}`}
                  />
                ) : <Typography.Text type="secondary">Belum ada perubahan dari usulan loket.</Typography.Text>}
              </>
            ) : null}
          </Card>

          <Card size="small" title="Catatan Admin (opsional)">
            <Input.TextArea rows={3} maxLength={500} showCount value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="Contoh: Diskon diturunkan sesuai kebijakan" />
          </Card>
        </Space>
      ) : null}
    </Drawer>
  );
}

export default function PaymentSchemesPage() {
  const table = useTableState();
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const [submitOpen, setSubmitOpen] = useState(false);
  const [detail, setDetail] = useState(null);
  const [approving, setApproving] = useState(null);
  const [rejecting, setRejecting] = useState(null);
  const [decisionForm] = Form.useForm();

  const schemes = useQuery({ queryKey: ['payment-schemes', table.params], queryFn: () => api.paymentSchemes.list(table.params) });

  const reject = useMutation({
    mutationFn: ({ id, notes }) => api.paymentSchemes.reject(id, { notes }),
    onSuccess: () => {
      message.success('Skema pembayaran ditolak');
      closeReject();
      queryClient.invalidateQueries({ queryKey: ['payment-schemes'] });
    },
    onError: (error) => {
      message.error(getApiErrorMessage(error));
      queryClient.invalidateQueries({ queryKey: ['payment-schemes'] });
    },
  });

  function closeReject() {
    setRejecting(null);
    decisionForm.resetFields();
  }

  // Lanjut ke halaman Pembayaran dengan unit dan tagihan skema ini sudah terpilih.
  function paySchema(scheme) {
    navigate(`/payments?pay_unit=${encodeURIComponent(scheme.unit_id)}&pay_scheme=${scheme.id}`);
  }

  function finishApproval(closeDrawer = true) {
    if (closeDrawer) setApproving(null);
    queryClient.invalidateQueries({ queryKey: ['payment-schemes'] });
  }

  return (
    <section>
      <PageHeader
        title="Skema Pembayaran"
        subtitle="Pengajuan diskon pokok dan keringanan denda atas beberapa tagihan, menunggu persetujuan Admin."
        breadcrumbs={[{ label: 'Skema Pembayaran' }]}
        onRefresh={schemes.refetch}
        extra={(
          <Space wrap>
            <ExportPdfButton dataset="payment-schemes" params={{ ...table.filters, search: table.search || undefined }} filename="skema-pembayaran.pdf" permission="payment-schemes.view" />
            <Can permission="payment-schemes.submit"><Button type="primary" icon={<PlusOutlined />} onClick={() => setSubmitOpen(true)}>Ajukan Skema</Button></Can>
          </Space>
        )}
      />
      <FilterBar>
        <Input.Search allowClear placeholder="Cari ID unit / penghuni" value={table.search} onChange={(event) => table.setSearch(event.target.value)} className="filter-input" />
        <Select allowClear placeholder="Status" value={table.filters.status} onChange={(value) => table.setFilters({ ...table.filters, status: value })} className="filter-input" options={STATUS_OPTIONS} />
      </FilterBar>
      <Card>
        <ResponsiveTable
          query={schemes}
          onChange={table.handleTableChange}
          scrollX={1500}
          columns={[
            { title: 'No.', dataIndex: 'id', width: 70 },
            { title: 'Unit', render: (_, row) => `${row.unit_id} — ${row.unit?.cluster?.name || ''} ${row.unit?.block || ''}/${row.unit?.lot_number || ''}` },
            { title: 'Penghuni', render: (_, row) => row.unit?.resident?.name || '-' },
            {
              title: 'Tagihan',
              width: 120,
              render: (_, row) => {
                const rejected = (row.items || []).filter((item) => item.status === 'rejected').length;
                return (
                  <Space direction="vertical" size={0}>
                    <span>{(row.items?.length ?? 0) - rejected} bulan</span>
                    {rejected ? <Tag color="red">{rejected} ditolak</Tag> : null}
                  </Space>
                );
              },
            },
            { title: 'Pokok Awal', render: (_, row) => formatCurrency(row.original_principal) },
            {
              title: 'Diskon',
              render: (_, row) => (
                <Space direction="vertical" size={0}>
                  <span>{formatCurrency(row.principal_discount)}</span>
                  <Tag color={Number(row.principal_discount) > 0 ? 'green' : 'default'}>{percentOf(row.principal_discount, row.original_principal)}</Tag>
                </Space>
              ),
            },
            { title: 'Keringanan Denda', render: (_, row) => formatCurrency(row.penalty_reduction) },
            { title: 'Total Dibayar', render: (_, row) => <strong>{formatCurrency(row.final_amount)}</strong> },
            {
              title: 'Status',
              dataIndex: 'status',
              render: (value, row) => (
                <Space size={4} wrap>
                  <StatusBadge type="paymentScheme" value={value} />
                  {value === 'pending' && row.exceeds_admin_limit ? <Tag color="orange">Di atas batas Admin</Tag> : null}
                  {row.adjusted_at ? <Tag color="blue">Diubah Admin</Tag> : null}
                </Space>
              ),
            },
            { title: 'Pembayaran', render: (_, row) => <PaymentProgress scheme={row} /> },
            { title: 'Diajukan', render: (_, row) => `${row.submitter?.name || '-'} · ${formatDateTime(row.submitted_at)}` },
            {
              title: 'Aksi',
              width: 420,
              fixed: 'right',
              render: (_, row) => {
                // Admin (dibatasi) tidak boleh menolak skema di atas batas diskonnya; itu keputusan Super Admin.
                const cannotReject = row.viewer_limited && Boolean(row.exceeds_admin_limit);

                return (
                  <Space>
                    <Button size="small" icon={<EyeOutlined />} onClick={() => setDetail(row)}>Detail</Button>
                    {row.status === 'approved' && row.payment_status !== 'paid' ? (
                      <Can permission="payments.process">
                        <Button size="small" type="primary" icon={<WalletOutlined />} onClick={() => paySchema(row)}>Bayar</Button>
                      </Can>
                    ) : null}
                    <Can permission="payment-schemes.approve">
                      <Button
                        size="small"
                        icon={<CheckOutlined />}
                        disabled={row.status !== 'pending'}
                        onClick={() => setApproving(row)}
                      >
                        Tinjau & Setujui
                      </Button>
                      <Button
                        size="small"
                        danger
                        icon={<CloseOutlined />}
                        disabled={row.status !== 'pending' || cannotReject}
                        title={cannotReject ? 'Di atas batas diskon Admin: hanya Super Admin yang dapat menolak' : undefined}
                        onClick={() => setRejecting(row)}
                      >
                        Tolak
                      </Button>
                    </Can>
                  </Space>
                );
              },
            },
          ]}
        />
      </Card>

      <SubmitDrawer open={submitOpen} onClose={() => setSubmitOpen(false)} />
      <DetailDrawer scheme={detail} onClose={() => setDetail(null)} onPay={paySchema} />

      {approving ? <ApproveDrawer key={approving.id} scheme={approving} onClose={() => setApproving(null)} onDone={finishApproval} /> : null}

      <Modal
        open={Boolean(rejecting)}
        title={`Tolak skema #${rejecting?.id}?`}
        okText="Tolak"
        okButtonProps={{ danger: true, loading: reject.isPending }}
        cancelText="Batal"
        onCancel={closeReject}
        onOk={() => decisionForm.validateFields().then((values) => reject.mutate({ id: rejecting.id, notes: values.notes }))}
        destroyOnHidden
      >
        {rejecting ? (
          <>
            <Typography.Paragraph>
              Unit <strong>{rejecting.unit_id}</strong> — total dibayar setelah skema <strong>{formatCurrency(rejecting.final_amount)}</strong>
              {' '}(diskon {moneyWithPercent(rejecting.principal_discount, rejecting.original_principal)}, keringanan denda {formatCurrency(rejecting.penalty_reduction)}).
            </Typography.Paragraph>
            <Form form={decisionForm} layout="vertical">
              <Form.Item label="Alasan penolakan" name="notes" rules={[{ required: true, message: 'Alasan penolakan wajib diisi' }]}>
                <Input.TextArea rows={3} maxLength={500} />
              </Form.Item>
            </Form>
          </>
        ) : null}
      </Modal>
    </section>
  );
}
