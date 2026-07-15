import { Form, Input, Select, Space } from 'antd';
import { api } from '../../services/estateApi.js';
import InfoIcon from '../help/InfoIcon.jsx';

export const identityTypeOptions = [
  { value: 'KTP', label: 'KTP' },
  { value: 'SIM', label: 'SIM' },
  { value: 'Paspor', label: 'Paspor' },
  { value: 'KITAS', label: 'KITAS' },
];

async function checkAvailability(field, value, excludeId, label) {
  if (!value) return;
  try {
    const response = await api.residents.checkAvailability({ field, value, exclude_id: excludeId || undefined });
    if (response.data?.taken) {
      const owner = response.data?.owner_name;
      throw new Error(owner ? `${label} sudah terdaftar atas nama ${owner}` : `${label} sudah terdaftar`);
    }
  } catch (error) {
    if (error instanceof Error) throw error;
    // gagal cek ke server (mis. jaringan) tidak menghalangi pengisian form; validasi akhir tetap dicek ulang saat Simpan
  }
}

export default function ResidentForm({ form, districts = [], onFinish, loading, editing = false, residentId = null }) {
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
      <Form.Item
        label="Nomor HP"
        name="phone"
        validateTrigger="onBlur"
        rules={[
          { pattern: /^(\+62|62|0)8[1-9][0-9]{6,10}$/, message: 'Nomor HP tidak valid (contoh: 08123456789)' },
          { validator: (_, value) => checkAvailability('phone', value, residentId, 'Nomor HP') },
        ]}
      >
        <Input placeholder="08..." />
      </Form.Item>
      <Form.Item label="Telepon" name="telephone">
        <Input />
      </Form.Item>
      <Form.Item
        label="Email"
        name="email"
        validateTrigger="onBlur"
        rules={[
          { type: 'email', message: 'Format email tidak valid' },
          { validator: (_, value) => checkAvailability('email', value, residentId, 'Email') },
        ]}
      >
        <Input />
      </Form.Item>
      {!editing ? (
        <Form.Item
          label={<Space size={4}>Username Akun Login<InfoIcon scope={{ module: 'residents', component: 'resident-username-field' }} tooltip="Username akun customer (untuk login penghuni). Kosongkan untuk digenerate otomatis." module="residents" slug="kelola-data-penghuni" /></Space>}
          name="username"
          validateTrigger="onBlur"
          rules={[
            { min: 4, message: 'Minimal 4 karakter' },
            { pattern: /^[a-zA-Z0-9_.-]+$/, message: 'Hanya huruf, angka, titik, - dan _' },
            { validator: (_, value) => checkAvailability('username', value, null, 'Username') },
          ]}
        >
          <Input placeholder="Kosongkan untuk auto-generate" autoComplete="off" />
        </Form.Item>
      ) : null}
      <Form.Item label="Jenis Identitas" name="identity_type">
        <Select allowClear options={identityTypeOptions} />
      </Form.Item>
      <Form.Item label="Nomor Identitas" name="identity_number">
        <Input />
      </Form.Item>
      <Form.Item label="Kabupaten/Kota" name="district_id">
        <Select allowClear showSearch optionFilterProp="label" options={districts.map((item) => ({ value: item.id, label: item.name }))} />
      </Form.Item>
      <Form.Item label="Alamat KTP" name="id_card_address" className="full-span">
        <Input.TextArea rows={2} />
      </Form.Item>
      <Form.Item label="Nama Kontak Darurat" name="emergency_contact_name">
        <Input />
      </Form.Item>
      <Form.Item label="Telepon Kontak Darurat" name="emergency_contact_phone">
        <Input />
      </Form.Item>
      <Form.Item label="Catatan" name="notes" className="full-span">
        <Input.TextArea rows={2} />
      </Form.Item>
    </Form>
  );
}
