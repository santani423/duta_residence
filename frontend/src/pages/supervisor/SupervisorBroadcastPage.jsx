import { Alert, Button, Card, Form, Input, Select, Tag, message } from 'antd';
import { SendOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import PageHeader from '../../components/common/PageHeader.jsx';
import ResponsiveTable from '../../components/tables/ResponsiveTable.jsx';
import { useTableState } from '../../hooks/useTableState.js';
import { api } from '../../services/estateApi.js';
import { getApiErrorMessage } from '../../utils/apiError.js';
import { formatDateTime } from '../../utils/format.js';

const TYPE_LABELS = {
  collector: 'WhatsApp ke Kolektor',
  resident: 'WhatsApp ke Penghuni',
  announcement: 'Pengumuman Internal',
};

export default function SupervisorBroadcastPage() {
  const table = useTableState();
  const [form] = Form.useForm();
  const type = Form.useWatch('type', form);
  const queryClient = useQueryClient();

  const list = useQuery({ queryKey: ['broadcasts', table.params], queryFn: () => api.broadcasts.list(table.params) });
  const clusters = useQuery({ queryKey: ['clusters'], queryFn: () => api.clusters.list() });

  const send = useMutation({
    mutationFn: (values) => api.broadcasts.send(values),
    onSuccess: (response) => {
      const data = response.data?.data || {};
      message.success(`Broadcast terkirim: ${data.success_count ?? 0} berhasil, ${data.fail_count ?? 0} gagal.`);
      form.resetFields();
      queryClient.invalidateQueries({ queryKey: ['broadcasts'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const clusterOptions = (clusters.data?.data || []).map((cluster) => ({ value: cluster.id, label: `${cluster.id} - ${cluster.name}` }));

  return (
    <section>
      <PageHeader
        title="Broadcast & Pengumuman"
        subtitle="Kirim pesan WhatsApp ke kolektor/penghuni atau pengumuman internal, tersegmentasi berdasarkan wilayah."
        breadcrumbs={[{ label: 'Supervisor' }, { label: 'Broadcast' }]}
        onRefresh={list.refetch}
      />

      <Card title="Kirim Broadcast Baru" style={{ marginBottom: 16 }}>
        <Alert
          type="info"
          showIcon
          style={{ marginBottom: 16 }}
          message="Pengiriman WhatsApp menggunakan provider Fonnte (dikonfigurasi lewat environment, bukan kode sumber). Jika token belum dikonfigurasi di server, pesan akan tercatat gagal terkirim per-penerima tanpa mengganggu proses lainnya."
        />
        <Form form={form} layout="vertical" onFinish={send.mutate}>
          <Form.Item label="Jenis Broadcast" name="type" rules={[{ required: true, message: 'Pilih jenis broadcast' }]}>
            <Select options={Object.entries(TYPE_LABELS).map(([value, label]) => ({ value, label }))} />
          </Form.Item>
          {type !== 'announcement' && (
            <Form.Item label="Cluster Tujuan (opsional, kosongkan untuk semua dalam wilayah Anda)" name="cluster_id">
              <Select allowClear options={clusterOptions} loading={clusters.isLoading} />
            </Form.Item>
          )}
          <Form.Item label="Pesan" name="message" rules={[{ required: true, message: 'Pesan wajib diisi' }, { max: 1000 }]}>
            <Input.TextArea rows={4} placeholder="Tulis pesan broadcast..." />
          </Form.Item>
          <Button type="primary" icon={<SendOutlined />} htmlType="submit" loading={send.isPending}>Kirim Broadcast</Button>
        </Form>
      </Card>

      <Card title="Riwayat Broadcast">
        <ResponsiveTable
          query={list}
          onChange={table.handleTableChange}
          columns={[
            { title: 'Jenis', dataIndex: 'type', render: (v) => TYPE_LABELS[v] || v },
            { title: 'Pesan', dataIndex: 'message', ellipsis: true },
            { title: 'Pengirim', dataIndex: ['sender', 'name'] },
            { title: 'Penerima', dataIndex: 'recipient_count' },
            { title: 'Berhasil', dataIndex: 'success_count', render: (v) => <Tag color="green">{v}</Tag> },
            { title: 'Gagal', dataIndex: 'fail_count', render: (v) => <Tag color={v ? 'red' : 'default'}>{v}</Tag> },
            { title: 'Waktu', dataIndex: 'created_at', render: formatDateTime, width: 160 },
          ]}
        />
      </Card>
    </section>
  );
}
