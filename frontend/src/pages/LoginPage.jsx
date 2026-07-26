import { LockOutlined, UserOutlined } from '@ant-design/icons';
import { Alert, Button, Card, Form, Input, Space, Typography } from 'antd';
import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../state/AuthContext.jsx';

export default function LoginPage() {
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  async function submit(values) {
      setError(null);
      setLoading(true);
    try {
      await login(values.username, values.password);
      navigate(location.state?.from?.pathname || '/');
    } catch (err) {
      setError(err.message || 'Login gagal.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="login-page">
      <Card className="login-card">
        <Typography.Title level={2}>Grand Duta</Typography.Title>
        <Typography.Paragraph type="secondary">Estate Management</Typography.Paragraph>
        {error ? <Alert type="error" message={error} showIcon /> : null}
        <Form layout="vertical" onFinish={submit}>
          <Form.Item label="Username atau Email" name="username" rules={[{ required: true, message: 'Username atau email wajib diisi' }]}>
            <Input prefix={<UserOutlined />} autoComplete="username" />
          </Form.Item>
          <Form.Item label="Password" name="password" rules={[{ required: true, message: 'Password wajib diisi' }]}>
            <Input.Password prefix={<LockOutlined />} autoComplete="current-password" />
          </Form.Item>
          <Button type="primary" htmlType="submit" loading={loading} block>
            Login
          </Button>
        </Form>
        <Space className="login-links">
          <Link to="/forgot-password">Lupa password</Link>
        </Space>
      </Card>
    </main>
  );
}
