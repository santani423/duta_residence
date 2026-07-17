import { useEffect, useState } from 'react';
import { Button, ColorPicker, Form, Input, Modal, Popconfirm, Select, Space, Switch, Table, message } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import { useMutation } from '@tanstack/react-query';
import { api } from '../../services/estateApi.js';
import { getApiErrorMessage, mapValidationErrors } from '../../utils/apiError.js';
import { SHAPE_TYPES } from './mapConstants.js';

const shapeOptions = SHAPE_TYPES.map((value) => ({ value, label: value }));

export default function ClusterMapComponentTypeManager({ open, onClose, componentTypes, onChange, getContainer }) {
  const [editing, setEditing] = useState(null);
  const [form] = Form.useForm();

  useEffect(() => {
    if (editing) {
      form.setFieldsValue(editing);
    } else {
      form.resetFields();
      form.setFieldsValue({ is_active: true, default_shape_type: 'rect', fill_color: '#91caff', stroke_color: '#1677ff' });
    }
  }, [editing, form]);

  const save = useMutation({
    mutationFn: (values) => (editing
      ? api.clusterMapComponentTypes.update(editing.id, values)
      : api.clusterMapComponentTypes.create(values)),
    onSuccess: () => {
      message.success('Komponen berhasil disimpan.');
      setEditing(null);
      form.resetFields();
      onChange?.();
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const remove = useMutation({
    mutationFn: (id) => api.clusterMapComponentTypes.remove(id),
    onSuccess: () => {
      message.success('Komponen berhasil dihapus.');
      onChange?.();
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  return (
    <Modal title="Kelola Komponen Peta" open={open} onCancel={onClose} footer={null} width={760} destroyOnHidden getContainer={getContainer} zIndex={2000}>
      <Form
        form={form}
        layout="inline"
        style={{ marginBottom: 16, rowGap: 8 }}
        onFinish={(values) => save.mutate({
          ...values,
          fill_color: typeof values.fill_color === 'string' ? values.fill_color : values.fill_color?.toHexString?.(),
          stroke_color: typeof values.stroke_color === 'string' ? values.stroke_color : values.stroke_color?.toHexString?.(),
        })}
      >
        <Form.Item name="name" rules={[{ required: true, message: 'Nama wajib diisi' }]}>
          <Input placeholder="Nama komponen" style={{ width: 160 }} />
        </Form.Item>
        {!editing && (
          <Form.Item name="code" rules={[{ required: true, message: 'Kode wajib diisi' }]}>
            <Input placeholder="kode-unik" style={{ width: 140 }} />
          </Form.Item>
        )}
        <Form.Item name="category">
          <Input placeholder="Kategori" style={{ width: 120 }} />
        </Form.Item>
        <Form.Item name="default_shape_type" rules={[{ required: true }]}>
          <Select options={shapeOptions} style={{ width: 110 }} />
        </Form.Item>
        <Form.Item name="fill_color" valuePropName="value">
          <ColorPicker />
        </Form.Item>
        <Form.Item name="stroke_color" valuePropName="value">
          <ColorPicker />
        </Form.Item>
        <Form.Item name="is_active" valuePropName="checked">
          <Switch checkedChildren="Aktif" unCheckedChildren="Nonaktif" />
        </Form.Item>
        <Form.Item name="description">
          <Input placeholder="Keterangan (opsional)" style={{ width: 180 }} />
        </Form.Item>
        <Form.Item>
          <Space>
            <Button type="primary" htmlType="submit" icon={<PlusOutlined />} loading={save.isPending}>
              {editing ? 'Simpan Perubahan' : 'Tambah'}
            </Button>
            {editing && <Button onClick={() => setEditing(null)}>Batal</Button>}
          </Space>
        </Form.Item>
      </Form>

      <Table
        size="small"
        rowKey="id"
        dataSource={componentTypes || []}
        pagination={false}
        columns={[
          {
            title: 'Warna',
            width: 60,
            render: (_, row) => (
              <span style={{
                display: 'inline-block', width: 16, height: 16, borderRadius: 3,
                background: row.fill_color, border: `1px solid ${row.stroke_color}`,
              }}
              />
            ),
          },
          { title: 'Nama', dataIndex: 'name' },
          { title: 'Kode', dataIndex: 'code' },
          { title: 'Kategori', dataIndex: 'category', render: (value) => value || '-' },
          { title: 'Bentuk', dataIndex: 'default_shape_type' },
          { title: 'Status', dataIndex: 'is_active', render: (value) => (value ? 'Aktif' : 'Nonaktif') },
          {
            title: 'Aksi',
            width: 100,
            render: (_, row) => (
              <Space>
                <Button size="small" icon={<EditOutlined />} onClick={() => setEditing(row)} />
                <Popconfirm title="Hapus komponen ini?" onConfirm={() => remove.mutate(row.id)}>
                  <Button size="small" danger icon={<DeleteOutlined />} />
                </Popconfirm>
              </Space>
            ),
          },
        ]}
      />
    </Modal>
  );
}
