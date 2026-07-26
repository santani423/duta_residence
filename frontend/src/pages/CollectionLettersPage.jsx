import { Button, Card, Form, Input, Modal, Select, Tag, message } from 'antd';
import { DownloadOutlined, PlusOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import dayjs from 'dayjs';
import PageHeader from '../components/common/PageHeader.jsx';
import Can from '../components/common/Can.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { useTableState } from '../hooks/useTableState.js';
import { useDebounce } from '../hooks/useDebounce.js';
import { useAuth } from '../state/AuthContext.jsx';
import { api } from '../services/estateApi.js';
import { downloadBlob } from '../utils/download.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';

const LETTER_TYPE_LABELS = { reminder: 'Pengingat', warning: 'Peringatan', final_notice: 'Peringatan Terakhir' };

export default function CollectionLettersPage() {
  const { hasRole } = useAuth();
  const isCollector = hasRole('collector');
  const table = useTableState();
  const [open, setOpen] = useState(false);
  const [form] = Form.useForm();
  const queryClient = useQueryClient();

  const list = useQuery({ queryKey: ['collection-letters', table.params], queryFn: () => api.collectionLetters.list(table.params) });

  const create = useMutation({
    mutationFn: (values) => api.collectionLetters.create(values),
    onSuccess: () => {
      message.success('Surat penagihan berhasil dibuat.');
      setOpen(false);
      form.resetFields();
      queryClient.invalidateQueries({ queryKey: ['collection-letters'] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  async function handleDownload(record) {
    try {
      const response = await api.collectionLetters.download(record.id);
      downloadBlob(response.data, `Surat-Penagihan-${record.id}.pdf`);
    } catch (error) {
      message.error(getApiErrorMessage(error));
    }
  }

  return (
    <section>
      <PageHeader
        title="Surat Penagihan"
        subtitle="Buat dan unduh surat pengingat, peringatan, atau peringatan terakhir untuk penghuni yang menunggak."
        breadcrumbs={isCollector ? [{ label: 'Surat Penagihan' }] : [{ label: 'Manajemen Kolektor' }, { label: 'Surat Penagihan' }]}
        onRefresh={list.refetch}
        extra={
          <Can permission="collection-letters.create">
            <Button type="primary" icon={<PlusOutlined />} onClick={() => { form.resetFields(); setOpen(true); }}>Buat Surat</Button>
          </Can>
        }
      />

      <Card>
        <ResponsiveTable
          query={list}
          onChange={table.handleTableChange}
          columns={[
            { title: 'Penghuni', dataIndex: ['resident', 'name'] },
            { title: 'Unit', dataIndex: 'unit_id' },
            { title: 'Jenis Surat', render: (_, record) => <Tag>{LETTER_TYPE_LABELS[record.letter_type]}</Tag> },
            { title: 'Dibuat Oleh', dataIndex: ['generated_by', 'name'] },
            { title: 'Tanggal', dataIndex: 'generated_at', render: (value) => dayjs(value).format('DD MMM YYYY HH:mm') },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 120,
              render: (_, record) => (
                <Can permission="collection-letters.download">
                  <Button size="small" icon={<DownloadOutlined />} onClick={() => handleDownload(record)}>Unduh</Button>
                </Can>
              ),
            },
          ]}
        />
      </Card>

      <Modal
        title="Buat Surat Penagihan"
        open={open}
        onCancel={() => setOpen(false)}
        onOk={() => form.submit()}
        confirmLoading={create.isPending}
        destroyOnHidden
      >
        <Form form={form} layout="vertical" onFinish={create.mutate}>
          <RemoteUnitField />
          <Form.Item label="Jenis Surat" name="letter_type" rules={[{ required: true, message: 'Pilih jenis surat' }]}>
            <Select options={Object.entries(LETTER_TYPE_LABELS).map(([value, label]) => ({ value, label }))} />
          </Form.Item>
          <Form.Item label="Isi Surat" name="content" rules={[{ required: true, message: 'Isi surat wajib diisi' }]}>
            <Input.TextArea rows={6} placeholder="Tuliskan isi surat penagihan..." />
          </Form.Item>
        </Form>
      </Modal>
    </section>
  );
}

function RemoteUnitField() {
  const [search, setSearch] = useState('');
  const debounced = useDebounce(search);
  const units = useQuery({ queryKey: ['units', 'search', debounced], queryFn: () => api.units.list({ search: debounced || undefined, per_page: 20 }) });
  const options = (units.data?.data || []).map((unit) => ({ value: unit.id, label: `${unit.id} — ${unit.resident?.name || ''}` }));

  return (
    <Form.Item label="Unit" name="unit_id" rules={[{ required: true, message: 'Pilih unit' }]}>
      <Select showSearch filterOption={false} onSearch={setSearch} options={options} loading={units.isFetching} notFoundContent={units.isFetching ? 'Mencari...' : 'Tidak ditemukan'} />
    </Form.Item>
  );
}
