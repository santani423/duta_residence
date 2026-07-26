import { Form, Input, Select, Space, Switch } from 'antd';
import { roles } from '../../constants/permissions.js';
import InfoIcon from '../help/InfoIcon.jsx';

export default function UserForm({ form, onFinish, editing = false, loading = false, units = [] }) {
  const unitOptions = units.map((unit) => ({
    value: unit.id,
    label: `${unit.id} - ${unit.resident?.name || 'Tanpa penghuni'}`,
  }));

  return (
    <Form
      form={form}
      layout="vertical"
      onFinish={onFinish}
      className="responsive-form"
      initialValues={{ role: 'cs', is_active: true, theme_preference: 'system' }}
      disabled={loading}
    >
      <Form.Item label="Nama" name="name" rules={[{ required: true }]}>
        <Input />
      </Form.Item>
      <Form.Item label="Username" name="username" rules={[{ required: true }]}>
        <Input autoComplete="username" />
      </Form.Item>
      <Form.Item label="Email" name="email" rules={[{ type: 'email' }]}>
        <Input />
      </Form.Item>
      <Form.Item label="Telepon" name="phone">
        <Input />
      </Form.Item>
      {!editing ? (
        <Form.Item label="Password" name="password" rules={[{ required: true }, { min: 8 }]}>
          <Input.Password autoComplete="new-password" />
        </Form.Item>
      ) : null}
      <Form.Item
        label="Role"
        name="role"
        rules={[{ required: true }]}
        tooltip="Akun kolektor dikelola khusus lewat menu Manajemen Kolektor agar profil, penugasan, dan target-nya selalu lengkap."
      >
        <Select options={roles.filter((role) => role !== 'collector').map((role) => ({ value: role, label: role }))} />
      </Form.Item>
      <Form.Item noStyle shouldUpdate={(prev, next) => prev.role !== next.role}>
        {({ getFieldValue }) => {
          const isCustomer = getFieldValue('role') === 'customer';
          return (
            <Form.Item
              label={<Space size={4}>Unit / Penghuni<InfoIcon scope={{ module: 'users', component: 'user-unit-field' }} tooltip="Menghubungkan akun login ini ke data penghuni pemilik unit. Wajib untuk role customer agar penghuni bisa login melihat data miliknya." module="users" slug="kelola-user" /></Space>}
              name="unit_id"
              rules={[{ required: isCustomer, message: 'Unit wajib dipilih untuk role customer' }]}
            >
              <Select
                showSearch
                allowClear
                optionFilterProp="label"
                placeholder="Pilih unit penghuni"
                options={unitOptions}
              />
            </Form.Item>
          );
        }}
      </Form.Item>
      <Form.Item label="Theme" name="theme_preference">
        <Select options={[
          { value: 'system', label: 'System' },
          { value: 'light', label: 'Light' },
          { value: 'dark', label: 'Dark' },
        ]} />
      </Form.Item>
      <Form.Item label="Aktif" name="is_active" valuePropName="checked">
        <Switch />
      </Form.Item>
    </Form>
  );
}
