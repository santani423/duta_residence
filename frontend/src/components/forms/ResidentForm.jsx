import { Form, Input, Select, Space } from 'antd';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { api } from '../../services/estateApi.js';
import InfoIcon from '../help/InfoIcon.jsx';
import { VaSuffixInput, vaSuffixFromNumber } from './UnitForm.jsx';

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

export default function ResidentForm({ form, districts = [], clusters = [], onFinish, loading, editing = false, residentId = null, fixedUnit = null }) {
  const [unitClusterFilter, setUnitClusterFilter] = useState(undefined);
  const [unitBlockFilter, setUnitBlockFilter] = useState(undefined);

  const vaFormat = useQuery({ queryKey: ['units-va-format'], queryFn: api.units.vaFormat }).data?.data;
  // Nomor VA melekat di unit; begitu penghuni ditautkan ke unit, VA + kontak (HP & email) wajib diisi.
  const unitId = Form.useWatch('unit_id', form);
  const linkedToUnit = !editing && Boolean(unitId);

  const vaField = linkedToUnit ? (
    <VaSuffixInput
      vaFormat={vaFormat}
      required
      extraRules={[{
        validator: async (_, value) => {
          // Cek unik hanya untuk nomor yang sudah lengkap, supaya tidak memanggil server di setiap ketikan.
          if (!vaFormat?.prefix || !new RegExp(`^\\d{${vaFormat.suffix_length}}$`).test(value || '')) return;
          await checkAvailability('va_suffix', value, unitId, 'Nomor virtual account');
        },
      }]}
    />
  ) : null;

  const availableUnits = useQuery({
    queryKey: ['available-units', unitClusterFilter, unitBlockFilter],
    queryFn: () => api.units.list({ unassigned: 1, cluster_id: unitClusterFilter, block: unitBlockFilter, per_page: 200 }),
    enabled: !editing && !fixedUnit,
  });

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
      {!editing && fixedUnit ? (
        <>
          <Form.Item name="unit_id" hidden>
            <Input />
          </Form.Item>
          <Form.Item label="Unit" className="full-span">
            <Input readOnly value={`${fixedUnit.id} - ${fixedUnit.cluster?.name || fixedUnit.cluster_id} Blok ${fixedUnit.block} No ${fixedUnit.lot_number}`} />
          </Form.Item>
          {vaField}
        </>
      ) : null}
      {!editing && !fixedUnit ? (
        <>
          <Form.Item label="Filter Unit Kosong" className="full-span" tooltip="Persempit daftar unit kosong di bawah berdasarkan cluster dan blok.">
            <Space wrap>
              <Select
                allowClear
                showSearch
                optionFilterProp="label"
                placeholder="Cluster"
                style={{ width: 180 }}
                value={unitClusterFilter}
                onChange={setUnitClusterFilter}
                options={clusters.map((item) => ({ value: item.id, label: `${item.id} - ${item.name}` }))}
              />
              <Input
                allowClear
                placeholder="Blok"
                style={{ width: 120 }}
                value={unitBlockFilter}
                onChange={(event) => setUnitBlockFilter(event.target.value || undefined)}
              />
            </Space>
          </Form.Item>
          <Form.Item
            label={<Space size={4}>Unit<InfoIcon scope={{ module: 'residents', component: 'resident-unit-field' }} tooltip="Opsional. Pilih unit yang belum memiliki penghuni untuk langsung ditautkan ke penghuni baru ini." module="residents" slug="kelola-data-penghuni" /></Space>}
            name="unit_id"
            className="full-span"
          >
            <Select
              allowClear
              showSearch
              optionFilterProp="label"
              placeholder="Belum ditautkan ke unit manapun"
              onChange={(value) => {
                const unit = (availableUnits.data?.data || []).find((item) => item.id === value);
                form.setFieldValue('va_suffix', vaSuffixFromNumber(unit?.va_number, vaFormat?.prefix));
              }}
              loading={availableUnits.isFetching}
              notFoundContent={availableUnits.isFetching ? 'Memuat...' : 'Tidak ada unit kosong'}
              options={(availableUnits.data?.data || []).map((unit) => ({
                value: unit.id,
                label: `${unit.id} - ${unit.cluster?.name || unit.cluster_id} Blok ${unit.block} No ${unit.lot_number}`,
              }))}
            />
          </Form.Item>
          {vaField}
        </>
      ) : null}
      <Form.Item
        label="Nomor HP"
        name="phone"
        validateTrigger="onBlur"
        rules={[
          { required: linkedToUnit, message: 'Nomor HP wajib diisi' },
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
          { required: linkedToUnit, message: 'Email wajib diisi' },
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
