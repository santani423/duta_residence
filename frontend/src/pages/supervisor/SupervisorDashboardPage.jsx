import { Card, Col, Row, Statistic } from 'antd';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import PageHeader from '../../components/common/PageHeader.jsx';
import DashboardRangePicker from '../../components/common/DashboardRangePicker.jsx';
import { ErrorState, LoadingState } from '../../components/common/ApiState.jsx';
import useDashboardRange from '../../hooks/useDashboardRange.js';
import { api } from '../../services/estateApi.js';
import { formatCurrency } from '../../utils/format.js';

export default function SupervisorDashboardPage() {
  const { range, setRange, start, end, presets } = useDashboardRange();
  const summary = useQuery({
    queryKey: ['supervisor-dashboard', start, end],
    queryFn: () => api.supervisorDashboard.summary({ start, end }),
    placeholderData: keepPreviousData,
  });

  if (summary.isLoading) return <LoadingState rows={8} />;
  if (summary.isError) return <ErrorState error={summary.error} onRetry={summary.refetch} />;

  const data = summary.data?.data || {};
  const collectors = data.collectors || {};
  const billing = data.billing || {};
  const payments = data.payments || {};
  const promises = data.promises || {};

  return (
    <section>
      <PageHeader
        title="Dashboard Supervisor"
        subtitle="Ringkasan kinerja kolektor dan kondisi tunggakan di wilayah yang Anda awasi."
        breadcrumbs={[{ label: 'Supervisor' }, { label: 'Dashboard' }]}
        onRefresh={summary.refetch}
        extra={<DashboardRangePicker value={range} onChange={setRange} presets={presets} />}
      />

      <Row gutter={[16, 16]}>
        <Col xs={24} sm={12} md={6}><Card><Statistic title="Kolektor Aktif" value={collectors.active ?? 0} /></Card></Col>
        <Col xs={24} sm={12} md={6}><Card><Statistic title="Kolektor Nonaktif" value={collectors.inactive ?? 0} /></Card></Col>
        <Col xs={24} sm={12} md={6}><Card><Statistic title="Sedang Bertugas Hari Ini" value={collectors.on_duty_today ?? 0} /></Card></Col>
        <Col xs={24} sm={12} md={6}><Card><Statistic title="Tidak Mengirim Lokasi" value={collectors.not_sending_location ?? 0} valueStyle={{ color: collectors.not_sending_location ? '#cf1322' : undefined }} /></Card></Col>

        <Col xs={24} sm={12} md={6}><Card><Statistic title="Target Penghuni" value={billing.target_residents ?? 0} /></Card></Col>
        <Col xs={24} sm={12} md={6}><Card><Statistic title="Unit Belum Bayar" value={billing.unpaid_units ?? 0} /></Card></Col>
        <Col xs={24} sm={12} md={12}><Card><Statistic title="Total Piutang" value={formatCurrency(billing.total_receivables ?? 0)} /></Card></Col>

        <Col xs={24} sm={12} md={6}><Card><Statistic title="Pembayaran Sukses" value={payments.successful_period ?? 0} /></Card></Col>
        <Col xs={24} sm={12} md={6}><Card><Statistic title="Pembayaran On-Site" value={payments.on_site_period ?? 0} /></Card></Col>
        <Col xs={24} sm={12} md={6}><Card><Statistic title="PTP Aktif" value={promises.active_ptp ?? 0} /></Card></Col>
        <Col xs={24} sm={12} md={6}><Card><Statistic title="Broken Promise" value={promises.broken_promise ?? 0} valueStyle={{ color: promises.broken_promise ? '#cf1322' : undefined }} /></Card></Col>

        <Col xs={24} sm={12} md={6}><Card><Statistic title="Komplain Terbuka" value={data.complaints ?? 0} /></Card></Col>
        <Col xs={24} sm={12} md={6}><Card><Statistic title="Kondisi Darurat Aktif" value={data.active_emergencies ?? 0} valueStyle={{ color: data.active_emergencies ? '#cf1322' : undefined }} /></Card></Col>
        <Col xs={24} sm={12} md={6}><Card><Statistic title="Kunjungan" value={data.visits_period ?? 0} /></Card></Col>
        <Col xs={24} sm={12} md={6}><Card><Statistic title="Persetujuan Menunggu" value={data.pending_approvals ?? 0} /></Card></Col>

        <Col xs={24} sm={12} md={6}><Card><Statistic title="Notifikasi Belum Ditangani" value={data.unhandled_notifications ?? 0} /></Card></Col>
      </Row>
    </section>
  );
}
