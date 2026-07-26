import { Button, Card, Empty, Form, Input, List, Select, Space, Tag, Typography, message } from 'antd';
import { WhatsAppOutlined } from '@ant-design/icons';
import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import dayjs from 'dayjs';
import PageHeader from '../../components/common/PageHeader.jsx';
import { useDebounce } from '../../hooks/useDebounce.js';
import { api } from '../../services/estateApi.js';
import { buildWhatsAppLink } from '../../utils/whatsapp.js';
import { getApiErrorMessage } from '../../utils/apiError.js';

const TEMPLATE = (unit) => `Yth. ${unit?.resident?.name || 'Bapak/Ibu'},\n\nKami informasikan bahwa unit ${unit?.id || ''} memiliki tagihan yang belum diselesaikan. Mohon segera melakukan pembayaran. Terima kasih.`;

export default function CollectorRemindersPage() {
  const [form] = Form.useForm();
  const [search, setSearch] = useState('');
  const [selectedUnit, setSelectedUnit] = useState(null);
  const debounced = useDebounce(search);

  const units = useQuery({ queryKey: ['units', 'search', debounced], queryFn: () => api.units.list({ search: debounced || undefined, per_page: 20 }) });
  const options = (units.data?.data || []).map((unit) => ({ value: unit.id, label: `${unit.id} — ${unit.resident?.name || ''}`, unit }));

  const history = useQuery({ queryKey: ['collector-reminders', selectedUnit?.id], queryFn: () => api.collectorReminders.list({ unit_id: selectedUnit.id }), enabled: Boolean(selectedUnit) });

  const logReminder = useMutation({
    mutationFn: (payload) => api.collectorReminders.create(payload),
    onSuccess: () => {
      message.success('Pengingat berhasil dicatat.');
      history.refetch();
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  function handleSelectUnit(unitId, option) {
    const unit = option?.unit;
    setSelectedUnit(unit || null);
    form.setFieldsValue({ phone: unit?.resident?.phone, message: TEMPLATE(unit) });
  }

  function handleSend() {
    const values = form.getFieldsValue();
    if (!selectedUnit) {
      message.warning('Pilih unit terlebih dahulu.');
      return;
    }
    const link = buildWhatsAppLink(values.phone, values.message);
    if (!link) {
      message.error('Nomor telepon penghuni tidak valid.');
      return;
    }
    window.open(link, '_blank', 'noopener');
    logReminder.mutate({ unit_id: selectedUnit.id, message: values.message, phone: values.phone });
  }

  return (
    <section>
      <PageHeader
        title="Pengingat WhatsApp"
        subtitle="Kirim pengingat pembayaran melalui WhatsApp ke penghuni yang menjadi tanggung jawab Anda."
        breadcrumbs={[{ label: 'Pengingat WhatsApp' }]}
      />
      <Card style={{ marginBottom: 16 }}>
        <Form form={form} layout="vertical">
          <Form.Item label="Unit">
            <Select
              showSearch
              filterOption={false}
              onSearch={setSearch}
              onChange={handleSelectUnit}
              options={options}
              loading={units.isFetching}
              placeholder="Cari unit atau nama penghuni"
              notFoundContent={units.isFetching ? 'Mencari...' : 'Tidak ditemukan'}
            />
          </Form.Item>
          <Form.Item label="Nomor WhatsApp" name="phone" rules={[{ required: true, message: 'Nomor telepon wajib diisi' }]}>
            <Input placeholder="08xxxxxxxxxx" />
          </Form.Item>
          <Form.Item label="Pesan" name="message" rules={[{ required: true, message: 'Pesan wajib diisi' }]}>
            <Input.TextArea rows={5} />
          </Form.Item>
          <Button type="primary" icon={<WhatsAppOutlined />} onClick={handleSend} loading={logReminder.isPending}>
            Buka WhatsApp & Catat
          </Button>
        </Form>
        <Typography.Paragraph type="secondary" style={{ marginTop: 12 }}>
          Tombol ini membuka WhatsApp dengan pesan yang sudah disiapkan (tautan wa.me), lalu mencatat riwayat pengiriman di bawah.
        </Typography.Paragraph>
      </Card>

      {selectedUnit ? (
        <Card title={`Riwayat Pengingat — ${selectedUnit.id}`}>
          {(history.data?.data || []).length ? (
            <List
              dataSource={history.data.data}
              renderItem={(item) => (
                <List.Item>
                  <List.Item.Meta
                    title={<Space><Tag>{dayjs(item.sent_at).format('DD MMM YYYY HH:mm')}</Tag>{item.sender?.name}</Space>}
                    description={item.message}
                  />
                </List.Item>
              )}
            />
          ) : (
            <Empty description="Belum ada pengingat untuk unit ini." />
          )}
        </Card>
      ) : null}
    </section>
  );
}
