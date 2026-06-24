import { Form, Input, Select, Switch } from 'antd';
import { roles } from '../../constants/permissions.js';

export default function UserForm({ form, onFinish, editing = false, loading = false }) {
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
      <Form.Item label="Role" name="role" rules={[{ required: true }]}>
        <Select options={roles.map((role) => ({ value: role, label: role }))} />
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
