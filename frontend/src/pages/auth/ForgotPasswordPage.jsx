import { Alert, Button, Card, Form, Input, Typography } from 'antd';
import { Link } from 'react-router-dom';

export default function ForgotPasswordPage() {
  return (
    <main className="login-page">
      <Card className="login-card">
        <Typography.Title level={2}>Lupa Password</Typography.Title>
        <Typography.Paragraph type="secondary">
          Reset password publik belum tersedia di API. Untuk saat ini, minta admin dengan permission `users.reset-password` melakukan reset dari halaman User.
        </Typography.Paragraph>
        <Alert type="info" showIcon message="Aksi aman" description="Form ini tidak mengirim data karena endpoint reset publik belum disediakan backend." />
        <Form layout="vertical" className="section-row">
          <Form.Item label="Username atau email">
            <Input disabled placeholder="Hubungi admin sistem" />
          </Form.Item>
          <Button disabled block>Request reset belum aktif</Button>
        </Form>
        <Button type="link" block><Link to="/login">Kembali ke login</Link></Button>
      </Card>
    </main>
  );
}
