import { Form, Input, Select } from 'antd';

export default function ResidentForm({ form, districts = [], onFinish, loading }) {
  return (
    <Form
      form={form}
      layout="vertical"
      onFinish={onFinish}
      className="responsive-form"
      disabled={loading}
    >
      <Form.Item label="Nama" name="name" rules={[{ required: true, message: 'Nama wajib diisi' }]}>
        <Input placeholder="Nama pemilik atau penghuni" />
      </Form.Item>
      <Form.Item label="Nomor HP" name="phone">
        <Input placeholder="08..." />
      </Form.Item>
      <Form.Item label="Telepon" name="telephone">
        <Input />
      </Form.Item>
      <Form.Item label="Email" name="email" rules={[{ type: 'email' }]}>
        <Input />
      </Form.Item>
      <Form.Item label="Kabupaten/Kota" name="district_id">
        <Select allowClear showSearch optionFilterProp="label" options={districts.map((item) => ({ value: item.id, label: item.name }))} />
      </Form.Item>
      <Form.Item label="Alamat KTP" name="id_card_address" className="full-span">
        <Input.TextArea rows={2} />
      </Form.Item>
    </Form>
  );
}
