import { DatePicker, Form, Input, InputNumber, Select, Switch } from 'antd';
import dayjs from 'dayjs';

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
// ada/tidaknya penghuni aktif - satu-satunya sumber label status unit di seluruh frontend,
// jangan buat varian/singkatan lain untuk ketiga status ini.
export const unitOccupancyStatusOptions = [
  { value: 'ready_stock', label: 'Ready Stock' },
  { value: 'tanah_kosong', label: 'Tanah Kosong' },
  { value: 'occupied', label: 'Occupied' },
];

export default function UnitForm({ form, clusters = [], residents = [], isEdit = false, onFinish, loading }) {
  return (
    <Form
      form={form}
      layout="vertical"
      onFinish={(values) => onFinish({
        ...values,
        lot_number: values.lot_number != null ? String(values.lot_number) : values.lot_number,
        handover_date: values.handover_date?.format?.('YYYY-MM-DD') || values.handover_date || null,
      })}
      className="responsive-form"
      initialValues={{
        property_type_id: 'B',
        status_id: 'TA',
        is_penalty_eligible: true,
        is_discount_eligible: false,
      }}
      disabled={loading}
    >
      {isEdit && (
        <Form.Item label="ID Unit" name="id" tooltip="Dibuat otomatis oleh sistem dan tidak dapat diubah.">
          <Input disabled />
        </Form.Item>
      )}
      {isEdit && (
        <Form.Item
          label="Nomor Virtual Account (VA)"
          name="va_number"
          rules={[{ max: 32, message: 'Maksimal 32 karakter' }]}
          tooltip="Digunakan sebagai identitas pembayaran tagihan bulanan unit ini. Kosongkan jika belum tersedia; jika diisi harus unik untuk setiap unit."
        >
          <Input placeholder="8801000123" />
        </Form.Item>
      )}
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
      <Form.Item label="Tipe Properti" name="property_type_id" rules={[{ required: true }]}>
        <Select options={propertyTypeOptions} />
      </Form.Item>
      <Form.Item label="Luas Bangunan" name="building_area">
        <InputNumber min={0} addonAfter="m2" style={{ width: '100%' }} />
      </Form.Item>
      <Form.Item label="Luas Tanah" name="land_area">
        <InputNumber min={0} addonAfter="m2" style={{ width: '100%' }} />
      </Form.Item>
      {isEdit && (
        <Form.Item
          label="Tanggal Serah Terima"
          name="handover_date"
          tooltip="Otomatis tercatat saat status unit diubah menjadi Aktif."
          getValueProps={(value) => ({ value: value ? dayjs(value) : null })}
        >
          <DatePicker style={{ width: '100%' }} />
        </Form.Item>
      )}
      <Form.Item label="Occupancy" name="occupancy_id">
        <Select allowClear placeholder="Belum ditentukan" options={occupancyOptions} />
      </Form.Item>
      {isEdit ? (
        <Form.Item
          label="Status Penghuni"
          name="status_id"
          tooltip="Status keaktifan penghuni yang terdaftar. Status Unit (Ready Stock/Tanah Kosong/Occupied) dihitung otomatis dari field ini dan tidak bisa dipilih manual."
        >
          <Select options={residentStatusOptions} />
        </Form.Item>
      ) : (
        <Form.Item name="status_id" hidden>
          <Input />
        </Form.Item>
      )}
      <Form.Item label="Catatan" name="notes" className="full-span">
        <Input.TextArea rows={3} />
      </Form.Item>
      {isEdit && (
        <>
          <Form.Item label="Kena Denda" name="is_penalty_eligible" valuePropName="checked">
            <Switch />
          </Form.Item>
          <Form.Item label="Bisa Diskon" name="is_discount_eligible" valuePropName="checked">
            <Switch />
          </Form.Item>
        </>
      )}
    </Form>
  );
}
