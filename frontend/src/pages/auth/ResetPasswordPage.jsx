import { Alert, Button, Card, Form, Input, Typography } from 'antd';
import { Link } from 'react-router-dom';

export default function ResetPasswordPage() {
  return (
    <main className="login-page">
      <Card className="login-card">
        <Typography.Title level={2}>Reset Password</Typography.Title>
        <Alert type="warning" showIcon message="Endpoint reset publik belum tersedia" description="Gunakan reset password dari User Management untuk akun internal." />
        <Form layout="vertical" className="section-row">
          <Form.Item label="Token reset"><Input disabled /></Form.Item>
          <Form.Item label="Password baru"><Input.Password disabled /></Form.Item>
          <Button disabled block>Reset belum aktif</Button>
        </Form>
        <Button type="link" block><Link to="/login">Kembali ke login</Link></Button>
      </Card>
    </main>
  );
}
