import {
  AutoComplete, Button, Card, Divider, Drawer, Dropdown, Form, Image, Input, InputNumber,
  Modal, Select, Space, Switch, Tag, Typography, Upload, message,
} from 'antd';
import {
  DeleteOutlined, EditOutlined, MinusCircleOutlined, MoreOutlined, PlusOutlined, UploadOutlined,
} from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import PageHeader from '../components/common/PageHeader.jsx';
import FilterBar from '../components/common/FilterBar.jsx';
import StatusBadge from '../components/common/StatusBadge.jsx';
import ResponsiveTable from '../components/tables/ResponsiveTable.jsx';
import { api, storageUrl } from '../services/estateApi.js';
import { useTableState } from '../hooks/useTableState.js';
import { formatDateTime } from '../utils/format.js';
import { getApiErrorMessage, mapValidationErrors } from '../utils/apiError.js';
import { MODULE_LABELS, moduleLabel } from '../utils/helpModules.js';

const moduleOptions = Object.entries(MODULE_LABELS).map(([value, label]) => ({ value, label: `${label} (${value})` }));

export default function AdminManualBookPage() {
  const table = useTableState();
  const [drawer, setDrawer] = useState({ type: null, record: null });
  const [form] = Form.useForm();
  const queryClient = useQueryClient();

  const sections = useQuery({ queryKey: ['admin-manual-book', table.params], queryFn: () => api.manualBook.adminList(table.params) });
  const roles = useQuery({ queryKey: ['lookup-roles'], queryFn: api.lookup.roles });
  const detail = useQuery({
    queryKey: ['admin-manual-book', drawer.record?.id],
    queryFn: () => api.manualBook.adminDetail(drawer.record.id),
    enabled: Boolean(drawer.record?.id),
  });

  const save = useMutation({
    mutationFn: (values) => (drawer.type === 'edit' ? api.manualBook.update(drawer.record.id, values) : api.manualBook.create(values)),
    onSuccess: () => {
      message.success('Panduan berhasil disimpan');
      setDrawer({ type: null, record: null });
      form.resetFields();
      queryClient.invalidateQueries({ queryKey: ['admin-manual-book'] });
      queryClient.invalidateQueries({ queryKey: ['manual-book-sections'] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const remove = useMutation({
    mutationFn: api.manualBook.remove,
    onSuccess: () => {
      message.success('Panduan berhasil dihapus');
      queryClient.invalidateQueries({ queryKey: ['admin-manual-book'] });
      queryClient.invalidateQueries({ queryKey: ['manual-book-sections'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const uploadImage = useMutation({
    mutationFn: ({ id, formData }) => api.manualBook.uploadImage(id, formData),
    onSuccess: () => {
      message.success('Gambar berhasil diunggah');
      queryClient.invalidateQueries({ queryKey: ['admin-manual-book', drawer.record?.id] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const removeImage = useMutation({
    mutationFn: (imageId) => api.manualBook.removeImage(drawer.record.id, imageId),
    onSuccess: () => {
      message.success('Gambar berhasil dihapus');
      queryClient.invalidateQueries({ queryKey: ['admin-manual-book', drawer.record?.id] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  function openCreate() {
    form.resetFields();
    setDrawer({ type: 'create', record: null });
  }

  function openEdit(record) {
    form.setFieldsValue(record);
    setDrawer({ type: 'edit', record });
  }

  const section = detail.data?.data;

  return (
    <section>
      <PageHeader
        title="Kelola Manual Book"
        subtitle="Tambah, ubah, atur urutan, dan pilih role yang bisa melihat setiap panduan."
        breadcrumbs={[{ label: 'Admin' }, { label: 'Manual Book' }]}
        helpModule="general"
        onRefresh={sections.refetch}
        extra={<Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Tambah Panduan</Button>}
      />

      <FilterBar>
        <Input allowClear placeholder="Cari judul atau modul" value={table.search} onChange={(event) => table.setSearch(event.target.value)} className="filter-input" />
        <Select allowClear placeholder="Modul" value={table.filters.module} onChange={(value) => table.setFilters({ ...table.filters, module: value })} options={moduleOptions} className="filter-input" />
      </FilterBar>

      <Card>
        <ResponsiveTable
          query={sections}
          onChange={table.handleTableChange}
          scrollX={900}
          columns={[
            { title: 'Modul', dataIndex: 'module', render: moduleLabel, width: 150 },
            { title: 'Judul', dataIndex: 'title', width: 260 },
            { title: 'Role', dataIndex: 'roles', render: (value) => (value?.length ? value.map((role) => <Tag key={role}>{role}</Tag>) : <Tag color="blue">Semua Role</Tag>) },
            { title: 'Urutan', dataIndex: 'order', width: 90 },
            { title: 'Status', dataIndex: 'is_active', render: (value) => <StatusBadge type="active" value={value} />, width: 100 },
            { title: 'Diperbarui', dataIndex: 'updated_at', render: formatDateTime, width: 170 },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 90,
              render: (_, record) => (
                <Dropdown menu={{ items: [{ key: 'edit', label: 'Edit', icon: <EditOutlined /> }, { key: 'delete', label: 'Hapus', icon: <DeleteOutlined />, danger: true }], onClick: ({ key }) => {
                  if (key === 'edit') openEdit(record);
                  if (key === 'delete') Modal.confirm({ title: 'Hapus panduan ini?', content: record.title, okText: 'Hapus', okButtonProps: { danger: true }, onOk: () => remove.mutate(record.id) });
                } }}
                >
                  <Button icon={<MoreOutlined />} />
                </Dropdown>
              ),
            },
          ]}
        />
      </Card>

      <Drawer
        title={drawer.type === 'edit' ? 'Edit Panduan' : 'Tambah Panduan'}
        open={drawer.type === 'create' || drawer.type === 'edit'}
        onClose={() => setDrawer({ type: null, record: null })}
        width={720}
        extra={<Space><Button onClick={() => setDrawer({ type: null, record: null })}>Batal</Button><Button type="primary" loading={save.isPending} onClick={() => form.submit()}>Simpan</Button></Space>}
        destroyOnHidden
      >
        <Form
          form={form}
          layout="vertical"
          className="responsive-form"
          onFinish={save.mutate}
          initialValues={{ is_active: true, order: 0, roles: [] }}
        >
          <Form.Item label="Modul" name="module" rules={[{ required: true, message: 'Modul wajib diisi' }]}>
            <AutoComplete options={moduleOptions} placeholder="mis. residents" />
          </Form.Item>
          <Form.Item label="Slug" name="slug" rules={[{ required: true }, { pattern: /^[a-z0-9-]+$/, message: 'Huruf kecil, angka, dan tanda - saja' }]}>
            <Input placeholder="mis. kelola-data-penghuni" />
          </Form.Item>
          <Form.Item label="Judul" name="title" rules={[{ required: true }]} className="full-span">
            <Input />
          </Form.Item>
          <Form.Item label="Ringkasan" name="summary" className="full-span">
            <Input.TextArea rows={2} maxLength={500} showCount />
          </Form.Item>
          <Form.Item
            label="Isi Panduan"
            name="content"
            className="full-span"
            tooltip="Pisahkan paragraf dengan baris kosong. Gunakan **teks** untuk tebal, [label](url) untuk tautan, dan baris berawalan '- ' untuk daftar."
          >
            <Input.TextArea rows={6} />
          </Form.Item>
          <Form.Item
            label="Role yang Bisa Melihat"
            name="roles"
            className="full-span"
            tooltip="Kosongkan supaya panduan ini terlihat oleh semua role."
          >
            <Select mode="multiple" allowClear placeholder="Semua role (kosongkan jika untuk semua)" options={(roles.data?.data || []).map((role) => ({ value: role, label: role }))} />
          </Form.Item>

          <Form.Item label="Urutan" name="order">
            <InputNumber min={0} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Status" name="is_active" valuePropName="checked">
            <Switch checkedChildren="Aktif" unCheckedChildren="Nonaktif" />
          </Form.Item>

          <Divider className="full-span">Langkah-langkah</Divider>
          <Form.List name="steps">
            {(fields, { add, remove: removeStep }) => (
              <div className="full-span">
                {fields.map((field) => (
                  <Space key={field.key} align="start" style={{ display: 'flex', marginBottom: 8 }} className="full-span">
                    <Form.Item name={[field.name, 'title']} rules={[{ required: true, message: 'Judul langkah wajib diisi' }]} style={{ minWidth: 200 }}>
                      <Input placeholder="Judul langkah" />
                    </Form.Item>
                    <Form.Item name={[field.name, 'description']} style={{ minWidth: 280 }}>
                      <Input placeholder="Penjelasan (opsional)" />
                    </Form.Item>
                    <MinusCircleOutlined onClick={() => removeStep(field.name)} />
                  </Space>
                ))}
                <Button type="dashed" onClick={() => add()} icon={<PlusOutlined />}>Tambah Langkah</Button>
              </div>
            )}
          </Form.List>

          <Divider className="full-span">Tips</Divider>
          <Form.Item name="tips" className="full-span">
            <Select mode="tags" open={false} placeholder="Ketik lalu Enter untuk menambah tips" tokenSeparators={['\n']} />
          </Form.Item>

          <Divider className="full-span">Peringatan</Divider>
          <Form.Item name="warnings" className="full-span">
            <Select mode="tags" open={false} placeholder="Ketik lalu Enter untuk menambah peringatan" tokenSeparators={['\n']} />
          </Form.Item>

          <Divider className="full-span">Pertanyaan Umum (FAQ)</Divider>
          <Form.List name="faqs">
            {(fields, { add, remove: removeFaq }) => (
              <div className="full-span">
                {fields.map((field) => (
                  <Space key={field.key} align="start" style={{ display: 'flex', marginBottom: 8 }} className="full-span">
                    <Form.Item name={[field.name, 'question']} rules={[{ required: true, message: 'Pertanyaan wajib diisi' }]} style={{ minWidth: 220 }}>
                      <Input placeholder="Pertanyaan" />
                    </Form.Item>
                    <Form.Item name={[field.name, 'answer']} rules={[{ required: true, message: 'Jawaban wajib diisi' }]} style={{ minWidth: 260 }}>
                      <Input.TextArea rows={1} placeholder="Jawaban" />
                    </Form.Item>
                    <MinusCircleOutlined onClick={() => removeFaq(field.name)} />
                  </Space>
                ))}
                <Button type="dashed" onClick={() => add()} icon={<PlusOutlined />}>Tambah FAQ</Button>
              </div>
            )}
          </Form.List>

          {drawer.type === 'edit' ? (
            <>
              <Divider className="full-span">Gambar Pendukung</Divider>
              <div className="full-span">
                <Space wrap className="section-row">
                  {(section?.images || []).map((image) => (
                    <div key={image.id} style={{ position: 'relative' }}>
                      <Image src={storageUrl(image.path)} width={100} height={100} style={{ objectFit: 'cover' }} />
                      <Button size="small" danger icon={<DeleteOutlined />} style={{ position: 'absolute', top: 2, right: 2 }} onClick={() => removeImage.mutate(image.id)} />
                    </div>
                  ))}
                </Space>
                <Upload
                  beforeUpload={(file) => {
                    const formData = new FormData();
                    formData.append('image', file);
                    uploadImage.mutate({ id: drawer.record.id, formData });
                    return false;
                  }}
                  showUploadList={false}
                  accept="image/*"
                >
                  <Button icon={<UploadOutlined />} loading={uploadImage.isPending}>Unggah Gambar</Button>
                </Upload>
              </div>
            </>
          ) : (
            <Typography.Text type="secondary" className="full-span">Simpan panduan terlebih dahulu untuk bisa menambahkan gambar pendukung.</Typography.Text>
          )}
        </Form>
      </Drawer>
    </section>
  );
}
