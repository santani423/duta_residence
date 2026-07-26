import { Alert, Card, Col, DatePicker, Form, Progress, Row, Select, Statistic } from 'antd';
import { useQuery } from '@tanstack/react-query';
import dayjs from 'dayjs';
import { useState } from 'react';
import PageHeader from '../components/common/PageHeader.jsx';
import { useAuth } from '../state/AuthContext.jsx';
import { api } from '../services/estateApi.js';
import { formatCurrency } from '../utils/format.js';

const PERIOD_LABELS = { daily: 'Harian', weekly: 'Mingguan', monthly: 'Bulanan' };

export default function CollectorPerformancePage() {
  const { hasRole } = useAuth();
  const isCollector = hasRole('collector');

  const [collectorId, setCollectorId] = useState();
  const [periodType, setPeriodType] = useState('monthly');
  const [periodStart, setPeriodStart] = useState(dayjs().startOf('month'));

  const collectors = useQuery({
    queryKey: ['users', 'collector'],
    queryFn: () => api.users.list({ role: 'collector', per_page: 100 }),
    enabled: !isCollector,
  });
  const collectorOptions = (collectors.data?.data?.data || collectors.data?.data || []).map((user) => ({ value: user.id, label: user.name }));

  const params = { period_type: periodType, period_start: periodStart.format('YYYY-MM-DD') };
  const enabled = isCollector || Boolean(collectorId);

  const performance = useQuery({
    queryKey: ['collector-performance', isCollector ? 'me' : collectorId, params],
    queryFn: () => (isCollector ? api.collectorPerformance.mine(params) : api.collectorPerformance.forCollector({ ...params, collector_id: collectorId })),
    enabled,
  });

  const data = performance.data?.data;

  return (
    <section>
      <PageHeader
        title="Target & Performa Kolektor"
        subtitle="Bandingkan hasil penagihan kolektor terhadap target yang ditetapkan."
        breadcrumbs={isCollector ? [{ label: 'Target & Performa' }] : [{ label: 'Manajemen Kolektor' }, { label: 'Performa' }]}
        onRefresh={performance.refetch}
        loading={performance.isFetching}
      />

      <Card style={{ marginBottom: 16 }}>
        <Row gutter={16}>
          {!isCollector && (
            <Col xs={24} md={8}>
              <Form.Item label="Kolektor" style={{ marginBottom: 0 }}>
                <Select showSearch optionFilterProp="label" options={collectorOptions} value={collectorId} onChange={setCollectorId} loading={collectors.isLoading} placeholder="Pilih kolektor" />
              </Form.Item>
            </Col>
          )}
          <Col xs={24} md={8}>
            <Form.Item label="Jenis Periode" style={{ marginBottom: 0 }}>
              <Select options={Object.entries(PERIOD_LABELS).map(([value, label]) => ({ value, label }))} value={periodType} onChange={setPeriodType} />
            </Form.Item>
          </Col>
          <Col xs={24} md={8}>
            <Form.Item label="Tanggal Mulai Periode" style={{ marginBottom: 0 }}>
              <DatePicker style={{ width: '100%' }} value={periodStart} onChange={(value) => value && setPeriodStart(value)} />
            </Form.Item>
          </Col>
        </Row>
      </Card>

      {!enabled ? (
        <Alert type="info" showIcon message="Pilih kolektor terlebih dahulu untuk melihat performanya." />
      ) : data ? (
        <Row gutter={16}>
          <Col xs={24} md={8}>
            <Card>
              <Statistic title="Terkumpul" value={data.collected_amount} formatter={(value) => formatCurrency(value)} />
              <Statistic title="Target" value={data.target_amount} formatter={(value) => formatCurrency(value)} style={{ marginTop: 12 }} />
            </Card>
          </Col>
          <Col xs={24} md={8}>
            <Card>
              <Statistic title="Pencapaian" value={data.achievement_percent ?? 0} suffix="%" />
              <Progress percent={data.achievement_percent ?? 0} status={(data.achievement_percent ?? 0) >= 100 ? 'success' : 'active'} style={{ marginTop: 12 }} />
            </Card>
          </Col>
          <Col xs={24} md={8}>
            <Card>
              <Statistic title="Jumlah Kunjungan" value={data.visit_count} />
              <Statistic title="Target Kunjungan" value={data.target_visit_count ?? '-'} style={{ marginTop: 12 }} />
            </Card>
          </Col>
        </Row>
      ) : null}
    </section>
  );
}
