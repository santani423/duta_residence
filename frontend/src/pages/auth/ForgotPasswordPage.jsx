import { MailOutlined } from '@ant-design/icons';
import { Alert, Button, Card, Form, Input, Space, Typography } from 'antd';
import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { api } from '../../services/estateApi.js';

const NEUTRAL_MESSAGE = 'Jika alamat email terdaftar, instruksi untuk mengatur ulang kata sandi akan dikirim.';

export default function ForgotPasswordPage() {
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState(null);
  const [form] = Form.useForm();

  const mutation = useMutation({
    mutationFn: api.auth.forgotPassword,
    onSuccess: () => {
      setError(null);
      setSubmitted(true);
    },
    onError: (err) => {
      // Only surface client-side/network problems (bad email format, rate limit,
      // connectivity). The backend already replies with the same neutral message
      // whether or not the address is registered, so nothing here should hint at that.
      if (err.status === 422) {
        form.setFields([{ name: 'email', errors: [err.errors?.email?.[0] || 'Alamat email tidak valid.'] }]);
        return;
      }
      if (err.status === 429) {
        setError('Terlalu banyak permintaan. Silakan coba lagi beberapa saat lagi.');
        return;
      }
      setError(err.message || 'Gagal mengirim permintaan. Silakan coba lagi.');
    },
  });

  return (
    <main className="login-page">
      <Card className="login-card">
        <Typography.Title level={2}>Lupa Password</Typography.Title>
        <Typography.Paragraph type="secondary">
          Masukkan alamat email akun Anda. Kami akan mengirimkan tautan untuk mengatur ulang kata sandi jika email
          tersebut terdaftar.
        </Typography.Paragraph>

        {submitted ? (
          <Alert type="success" showIcon message="Permintaan terkirim" description={NEUTRAL_MESSAGE} />
        ) : (
          <>
            {error ? <Alert type="error" showIcon message={error} className="section-row" /> : null}
            <Form form={form} layout="vertical" onFinish={mutation.mutate} disabled={mutation.isPending}>
              <Form.Item
                label="Alamat email"
                name="email"
                rules={[
                  { required: true, message: 'Alamat email wajib diisi' },
                  { type: 'email', message: 'Format email tidak valid' },
                ]}
              >
                <Input prefix={<MailOutlined />} autoComplete="email" placeholder="nama@email.com" />
              </Form.Item>
              <Button type="primary" htmlType="submit" loading={mutation.isPending} block>
                Kirim Instruksi Reset
              </Button>
            </Form>
          </>
        )}

        <Space className="login-links">
          <Link to="/login">Kembali ke login</Link>
        </Space>
      </Card>
    </main>
  );
}
