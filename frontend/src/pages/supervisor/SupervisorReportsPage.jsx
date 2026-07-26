import { Button, Card, Form, Select, Space, Tag, message } from 'antd';
import { DownloadOutlined, PlusOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import PageHeader from '../../components/common/PageHeader.jsx';
import ResponsiveTable from '../../components/tables/ResponsiveTable.jsx';
import { useTableState } from '../../hooks/useTableState.js';
import { api } from '../../services/estateApi.js';
import { getApiErrorMessage } from '../../utils/apiError.js';
import { formatDateTime } from '../../utils/format.js';

const STATUS_COLORS = { queued: 'default', processing: 'blue', completed: 'green', failed: 'red' };

export default function SupervisorReportsPage() {
  const table = useTableState();
  const [form] = Form.useForm();
  const queryClient = useQueryClient();

  const list = useQuery({
    queryKey: ['report-exports', table.params],
    queryFn: () => api.reportExports.list(table.params),
    refetchInterval: 10000,
  });

  const request = useMutation({
    mutationFn: (values) => api.reportExports.create(values),
    onSuccess: () => {
      message.success('Permintaan laporan sedang diproses di latar belakang.');
      queryClient.invalidateQueries({ queryKey: ['report-exports'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  async function download(record) {
    try {
      const response = await api.reportExports.download(record.id);
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = `laporan-${record.type}-${record.id}.csv`;
      link.click();
      window.URL.revokeObjectURL(url);
    } catch (error) {
      message.error(getApiErrorMessage(error));
    }
  }

  return (
    <section>
      <PageHeader
        title="Laporan & Ekspor"
        subtitle="Permintaan laporan besar diproses di latar belakang (antrean) - halaman ini akan otomatis diperbarui saat status berubah."
        breadcrumbs={[{ label: 'Supervisor' }, { label: 'Laporan' }]}
        onRefresh={list.refetch}
      />

      <Card title="Minta Laporan Baru" style={{ marginBottom: 16 }}>
        <Form form={form} layout="inline" onFinish={request.mutate}>
          <Form.Item label="Jenis Laporan" name="type" initialValue="collector_performance" rules={[{ required: true }]}>
            <Select style={{ width: 220 }} options={[{ value: 'collector_performance', label: 'Performa Kolektor' }]} />
          </Form.Item>
          <Form.Item label="Periode" name="period_type" initialValue="monthly">
            <Select style={{ width: 150 }} options={[
              { value: 'daily', label: 'Harian' },
              { value: 'weekly', label: 'Mingguan' },
              { value: 'monthly', label: 'Bulanan' },
            ]}
            />
          </Form.Item>
          <Form.Item>
            <Button type="primary" icon={<PlusOutlined />} htmlType="submit" loading={request.isPending}>Buat Laporan</Button>
          </Form.Item>
        </Form>
      </Card>

      <Card title="Riwayat Ekspor">
        <ResponsiveTable
          query={list}
          onChange={table.handleTableChange}
          columns={[
            { title: 'Jenis', dataIndex: 'type' },
            { title: 'Status', dataIndex: 'status', render: (v) => <Tag color={STATUS_COLORS[v]}>{v}</Tag> },
            { title: 'Jumlah Baris', dataIndex: 'row_count' },
            { title: 'Diminta', dataIndex: 'created_at', render: formatDateTime, width: 160 },
            {
              title: 'Aksi',
              render: (_, record) => (
                <Space>
                  {record.status === 'completed' && (
                    <Button size="small" icon={<DownloadOutlined />} onClick={() => download(record)}>Unduh</Button>
                  )}
                  {record.status === 'failed' && <Tag color="red">{record.failed_reason || 'Gagal diproses'}</Tag>}
                </Space>
              ),
            },
          ]}
        />
      </Card>
    </section>
  );
}
