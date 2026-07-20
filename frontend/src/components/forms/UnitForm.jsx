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
  { value: '3', label: 'Siswa' },
];

export const residentStatusOptions = [
  { value: 'AK', label: 'Aktif' },
  { value: 'RK', label: 'Rumah Kosong' },
  { value: 'TA', label: 'Tidak Aktif' },
];

export default function UnitForm({ form, clusters = [], residents = [], disabledId = false, onFinish, loading }) {
  return (
    <Form
      form={form}
      layout="vertical"
      onFinish={(values) => onFinish({
        ...values,
        handover_date: values.handover_date?.format?.('YYYY-MM-DD') || values.handover_date || null,
      })}
      className="responsive-form"
      initialValues={{
        property_type_id: 'B',
        occupancy_id: '1',
        status_id: 'AK',
        is_penalty_eligible: true,
        is_discount_eligible: false,
      }}
      disabled={loading}
    >
      <Form.Item label="ID Unit" name="id" rules={[{ required: true }, { len: 5 }]} getValueFromEvent={(event) => event.target.value.toUpperCase()}>
        <Input placeholder="GA099" disabled={disabledId} />
      </Form.Item>
      <Form.Item label="Pemilik" name="resident_id" rules={[{ required: true }]}>
        <Select showSearch optionFilterProp="label" options={residents.map((item) => ({ value: item.id, label: `${item.id} - ${item.name}` }))} />
      </Form.Item>
      <Form.Item label="Cluster" name="cluster_id" rules={[{ required: true }]}>
        <Select showSearch optionFilterProp="label" options={clusters.map((item) => ({ value: item.id, label: `${item.id} - ${item.name}` }))} />
      </Form.Item>
      <Form.Item label="Blok" name="block" rules={[{ required: true }]}>
        <Input placeholder="A" />
      </Form.Item>
      <Form.Item label="Nomor Kavling" name="lot_number" rules={[{ required: true }]}>
        <Input placeholder="01" />
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
      <Form.Item label="Tanggal Serah Terima" name="handover_date" getValueProps={(value) => ({ value: value ? dayjs(value) : null })}>
        <DatePicker style={{ width: '100%' }} />
      </Form.Item>
      <Form.Item label="Occupancy" name="occupancy_id">
        <Select options={occupancyOptions} />
      </Form.Item>
      <Form.Item label="Status Unit" name="status_id">
        <Select options={residentStatusOptions} />
      </Form.Item>
      <Form.Item label="Catatan" name="notes" className="full-span">
        <Input.TextArea rows={3} />
      </Form.Item>
      <Form.Item label="Kena Denda" name="is_penalty_eligible" valuePropName="checked">
        <Switch />
      </Form.Item>
      <Form.Item label="Bisa Diskon" name="is_discount_eligible" valuePropName="checked">
        <Switch />
      </Form.Item>
    </Form>
  );
}
