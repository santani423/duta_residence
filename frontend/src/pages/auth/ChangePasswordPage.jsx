import { Button, Card, Form, Input, message } from 'antd';
import { useMutation } from '@tanstack/react-query';
import PageHeader from '../../components/common/PageHeader.jsx';
import { api } from '../../services/estateApi.js';
import { getApiErrorMessage, mapValidationErrors } from '../../utils/apiError.js';

export default function ChangePasswordPage() {
  const [form] = Form.useForm();
  const mutation = useMutation({
    mutationFn: api.auth.changePassword,
    onSuccess: () => {
      message.success('Password berhasil diganti');
      form.resetFields();
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  return (
    <section>
      <PageHeader title="Ganti Password" breadcrumbs={[{ label: 'Ganti Password' }]} />
      <Card className="narrow-card">
        <Form form={form} layout="vertical" onFinish={mutation.mutate}>
          <Form.Item label="Password saat ini" name="current_password" rules={[{ required: true }]}>
            <Input.Password autoComplete="current-password" />
          </Form.Item>
          <Form.Item label="Password baru" name="new_password" rules={[{ required: true }, { min: 8 }]}>
            <Input.Password autoComplete="new-password" />
          </Form.Item>
          <Form.Item label="Konfirmasi password baru" name="new_password_confirmation" dependencies={['new_password']} rules={[
            { required: true },
            ({ getFieldValue }) => ({
              validator(_, value) {
                if (!value || getFieldValue('new_password') === value) return Promise.resolve();
                return Promise.reject(new Error('Konfirmasi password tidak sama'));
              },
            }),
          ]}>
            <Input.Password autoComplete="new-password" />
          </Form.Item>
          <Button type="primary" htmlType="submit" loading={mutation.isPending}>Simpan Password</Button>
        </Form>
      </Card>
    </section>
  );
}
