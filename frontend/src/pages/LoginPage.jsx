import { LockOutlined, UserOutlined } from '@ant-design/icons';
import { Alert, Button, Card, Form, Input, Space, Typography } from 'antd';
import { useState } from 'react';
import { Link, Navigate, useLocation } from 'react-router-dom';
import { useSiteIdentity } from '../hooks/useSiteIdentity.js';
import { useAuth } from '../state/AuthContext.jsx';

export default function LoginPage() {
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);
  const { login, token } = useAuth();
  const siteName = useSiteIdentity();
  const location = useLocation();

  async function submit(values) {
      setError(null);
      setLoading(true);
    try {
      await login(values.username, values.password);
    } catch (err) {
      setError(err.message || 'Login gagal.');
    } finally {
      setLoading(false);
    }
  }

  // Redirect declaratively off `token` instead of calling navigate() right
  // after login() resolves: that call landed in a separate microtask from
  // the setToken/setUser updates inside login(), so the destination route's
  // permission check could occasionally render one tick before the auth
  // context had caught up and bounce to /403. Deriving the redirect from
  // the same render that observes the updated `token` guarantees the whole
  // tree — including Protected's canAny/hasRole checks — sees it together.
  if (token) {
    return <Navigate to={location.state?.from?.pathname || '/'} replace />;
  }

  return (
    <main className="login-page">
      <Card className="login-card">
        <Typography.Title level={2}>{siteName}</Typography.Title>
        <Typography.Paragraph type="secondary">Estate Management</Typography.Paragraph>
        {error ? <Alert type="error" message={error} showIcon /> : null}
        <Form layout="vertical" onFinish={submit}>
          <Form.Item label="Username, Email, atau No. HP" name="username" rules={[{ required: true, message: 'Username, email, atau no. HP wajib diisi' }]}>
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
