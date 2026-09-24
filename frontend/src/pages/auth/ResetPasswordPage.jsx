import { LockOutlined } from '@ant-design/icons';
import { Alert, Button, Card, Form, Input, Space, Spin, Typography } from 'antd';
import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { api } from '../../services/estateApi.js';
import { getApiErrorMessage } from '../../utils/apiError.js';

export default function ResetPasswordPage() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const [form] = Form.useForm();
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState(null);

  const email = searchParams.get('email') || '';
  const token = searchParams.get('token') || '';
  const hasParams = Boolean(email && token);

  const tokenCheck = useQuery({
    queryKey: ['reset-password-validate', email, token],
    queryFn: () => api.auth.validateResetToken({ email, token }),
    enabled: hasParams,
    retry: false,
  });

  const mutation = useMutation({
    mutationFn: (values) => api.auth.resetPassword({ email, token, password: values.password, password_confirmation: values.password_confirmation }),
    onSuccess: () => {
      setError(null);
      setSubmitted(true);
    },
    onError: (err) => {
      setError(getApiErrorMessage(err, 'Gagal mengatur ulang password. Silakan coba lagi.'));
    },
  });

  useEffect(() => {
    if (!submitted) return undefined;
    const timer = setTimeout(() => navigate('/login', { replace: true }), 2500);
    return () => clearTimeout(timer);
  }, [submitted, navigate]);

  if (!hasParams) {
    return (
      <main className="login-page">
        <Card className="login-card">
          <Typography.Title level={2}>Reset Password</Typography.Title>
          <Alert
            type="error"
            showIcon
            message="Tautan tidak lengkap"
            description="Tautan reset password tidak valid. Silakan minta tautan baru."
          />
          <Space className="login-links">
            <Link to="/forgot-password">Minta tautan baru</Link>
          </Space>
        </Card>
      </main>
    );
  }

  const isValidatingToken = hasParams && tokenCheck.isLoading;
  const tokenInvalid = hasParams && !tokenCheck.isLoading && (tokenCheck.isError || tokenCheck.data?.data?.valid === false);

  return (
    <main className="login-page">
      <Card className="login-card">
        <Typography.Title level={2}>Reset Password</Typography.Title>

        {isValidatingToken ? (
          <Space className="section-row" align="center">
            <Spin size="small" /> <span>Memeriksa tautan reset...</span>
          </Space>
        ) : null}

        {!isValidatingToken && tokenInvalid ? (
          <>
            <Alert
              type="error"
              showIcon
              message="Tautan tidak valid"
              description="Tautan reset password tidak valid, sudah kedaluwarsa, atau sudah digunakan. Silakan minta tautan baru."
            />
            <Space className="login-links">
              <Link to="/forgot-password">Minta tautan baru</Link>
            </Space>
          </>
        ) : null}

        {!isValidatingToken && !tokenInvalid && submitted ? (
          <Alert
            type="success"
            showIcon
            message="Password berhasil direset"
            description="Anda akan diarahkan ke halaman masuk untuk login dengan password baru."
          />
        ) : null}

        {!isValidatingToken && !tokenInvalid && !submitted ? (
          <>
            <Typography.Paragraph type="secondary">
              Masukkan password baru untuk akun <strong>{email}</strong>.
            </Typography.Paragraph>
            {error ? <Alert type="error" showIcon message={error} className="section-row" /> : null}
            <Form form={form} layout="vertical" onFinish={mutation.mutate} disabled={mutation.isPending}>
              <Form.Item
                label="Password baru"
                name="password"
                rules={[
                  { required: true, message: 'Password baru wajib diisi' },
                  { min: 12, message: 'Password minimal 12 karakter' },
                ]}
              >
                <Input.Password prefix={<LockOutlined />} autoComplete="new-password" />
              </Form.Item>
              <Form.Item
                label="Konfirmasi password baru"
                name="password_confirmation"
                dependencies={['password']}
                rules={[
                  { required: true, message: 'Konfirmasi password wajib diisi' },
                  ({ getFieldValue }) => ({
                    validator(_, value) {
                      if (!value || getFieldValue('password') === value) return Promise.resolve();
                      return Promise.reject(new Error('Konfirmasi password tidak sama'));
                    },
                  }),
                ]}
              >
                <Input.Password prefix={<LockOutlined />} autoComplete="new-password" />
              </Form.Item>
              <Button type="primary" htmlType="submit" loading={mutation.isPending} block>
                Reset Password
              </Button>
            </Form>
          </>
        ) : null}

        <Space className="login-links">
          <Link to="/login">Kembali ke login</Link>
        </Space>
      </Card>
    </main>
  );
}
