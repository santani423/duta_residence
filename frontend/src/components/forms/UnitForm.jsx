import { Form, Input, InputNumber, Select } from 'antd';

export const propertyTypeOptions = [
  { value: 'B', label: 'Bangunan' },
  { value: 'K', label: 'Kavling Developer' },
  { value: 'P', label: 'Kavling Penghuni' },
  { value: 'R', label: 'Ruko' },
];

export const occupancyOptions = [
  { value: '1', label: 'Dihuni' },
  { value: '2', label: 'Kosong' },
  { value: '3', label: 'Sewa' },
  { value: '4', label: 'Booked' },
];

export const residentStatusOptions = [
  { value: 'AK', label: 'Aktif' },
  { value: 'RK', label: 'Properti Kosong' },
  { value: 'TA', label: 'Tidak Aktif' },
];

// Status unit yang dihitung backend (Unit::getOccupancyStatusAttribute) dari tipe unit +
// ada/tidaknya penghuni - satu-satunya sumber label status unit di seluruh frontend,
// jangan buat varian/singkatan lain untuk keempat status ini.
export const unitOccupancyStatusOptions = [
  { value: 'ready_stock', label: 'Ready Stock' },
  { value: 'tanah_kosong', label: 'Tanah Kosong' },
  { value: 'booked', label: 'Booked' },
  { value: 'occupied', label: 'Occupied' },
];

// Nomor VA = prefix (kode bank + kode perusahaan dari Pengaturan Payment Gateway) + nomor unik.
// Form hanya menginput nomor unik; prefix ditambahkan backend.
export function vaSuffixFromNumber(vaNumber, prefix) {
  return vaNumber && prefix && vaNumber.startsWith(prefix) ? vaNumber.slice(prefix.length) : undefined;
}

export function VaSuffixInput({ vaFormat, currentVaNumber, required = false, extraRules = [] }) {
  const prefix = vaFormat?.prefix || '';
  const length = vaFormat?.suffix_length || 9;
  const legacy = currentVaNumber && !vaSuffixFromNumber(currentVaNumber, prefix);

  let extra;
  if (!prefix) extra = 'Kode bank dan kode perusahaan VA belum diatur di Pengaturan Payment Gateway.';
  else if (legacy) extra = `Nomor VA saat ini ${currentVaNumber} (format lama). Isi untuk menggantinya dengan format baru.`;

  return (
    <Form.Item
      label="Nomor Virtual Account"
      name="va_suffix"
      extra={extra}
      rules={[
        { required, message: 'Nomor virtual account wajib diisi' },
        {
          // Divalidasi langsung saat mengetik (bukan menunggu blur/simpan) supaya kurang digit langsung terlihat.
          validator: (_, value) => {
            if (!value) return Promise.resolve();
            if (!/^\d+$/.test(value)) return Promise.reject(new Error('Nomor virtual account hanya boleh angka'));
            if (value.length < length) return Promise.reject(new Error(`Nomor virtual account kurang ${length - value.length} digit (minimal ${length} digit, terisi ${value.length})`));
            return Promise.resolve();
          },
        },
        ...extraRules,
      ]}
    >
      <Input addonBefore={prefix || undefined} maxLength={length} inputMode="numeric" placeholder={`${length} digit`} disabled={!prefix} />
    </Form.Item>
  );
}

export default function UnitForm({ form, clusters = [], residents = [], vaFormat, currentVaNumber, onFinish, loading }) {
  return (
    <Form
      form={form}
      layout="vertical"
      onFinish={(values) => onFinish({
        ...values,
        lot_number: values.lot_number != null ? String(values.lot_number) : values.lot_number,
      })}
      className="responsive-form"
      initialValues={{
        property_type_id: 'B',
        status_id: 'TA',
      }}
      disabled={loading}
    >
      <Form.Item
        label="Pemilik / Penghuni"
        name="resident_id"
        tooltip="Opsional. Unit dapat dibuat terlebih dahulu tanpa penghuni, lalu ditambahkan kemudian melalui menu Edit."
      >
        <Select allowClear showSearch optionFilterProp="label" placeholder="Belum ada penghuni" options={residents.map((item) => ({ value: item.id, label: `${item.id} - ${item.name}` }))} />
      </Form.Item>
      <Form.Item label="Cluster" name="cluster_id" rules={[{ required: true }]}>
        <Select showSearch optionFilterProp="label" options={clusters.map((item) => ({ value: item.id, label: `${item.id} - ${item.name}` }))} />
      </Form.Item>
      <Form.Item label="Blok" name="block" rules={[{ required: true }]}>
        <Input placeholder="A" />
      </Form.Item>
      <Form.Item
        label="Nomor Unit"
        name="lot_number"
        rules={[{ required: true, message: 'Nomor unit wajib diisi' }]}
        tooltip="Gunakan angka saja, contoh: 1 (bukan 01 atau 001), agar tidak terjadi duplikasi akibat perbedaan format."
      >
        <InputNumber min={1} precision={0} placeholder="1" style={{ width: '100%' }} />
      </Form.Item>
      <VaSuffixInput vaFormat={vaFormat} currentVaNumber={currentVaNumber} />
      <Form.Item label="Tipe Properti" name="property_type_id" rules={[{ required: true }]}>
        <Select options={propertyTypeOptions} />
      </Form.Item>
      <Form.Item label="Luas Bangunan" name="building_area">
        <InputNumber min={0} addonAfter="m2" style={{ width: '100%' }} />
      </Form.Item>
      <Form.Item label="Luas Tanah" name="land_area">
        <InputNumber min={0} addonAfter="m2" style={{ width: '100%' }} />
      </Form.Item>
      <Form.Item label="Occupancy" name="occupancy_id">
        <Select allowClear placeholder="Belum ditentukan" options={occupancyOptions} />
      </Form.Item>
      <Form.Item name="status_id" hidden>
        <Input />
      </Form.Item>
      <Form.Item label="Catatan" name="notes" className="full-span">
        <Input.TextArea rows={3} />
      </Form.Item>
    </Form>
  );
}
