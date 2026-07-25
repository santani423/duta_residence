import { SaveOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button, Card, Form, message } from 'antd';
import dayjs from 'dayjs';
import { useEffect } from 'react';
import { ErrorState, LoadingState } from '../../components/common/ApiState.jsx';
import PageHeader from '../../components/common/PageHeader.jsx';
import { getApiErrorMessage, mapValidationErrors } from '../../utils/apiError.js';
import CmsImageField, { pickMediaUrl } from './fields/CmsImageField.jsx';
import { renderCmsField } from './fields/renderCmsField.jsx';

/**
 * Generic single-record settings form for every "singleton" CMS module
 * (Header, About, Contact, Footer, SEO, General). Modeled directly on
 * AdminPaymentGatewaySettingsPage.jsx, driven by a `config` field-schema.
 */
export default function CmsSettingsPage({ config }) {
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: [config.queryKey], queryFn: config.resource.show });
  const record = query.data?.data;

  useEffect(() => {
    if (!record) return;
    const values = { ...record };
    config.fields.forEach((field) => {
      if (field.type === 'date' && values[field.name]) {
        values[field.name] = dayjs(values[field.name]);
      }
    });
    form.setFieldsValue(values);
  }, [record, form, config.fields]);

  const save = useMutation({
    mutationFn: (values) => {
      const payload = { ...values };
      config.fields.forEach((field) => {
        if (field.type === 'date' && payload[field.name]) {
          payload[field.name] = payload[field.name].toISOString();
        }
      });
      return config.resource.update(payload);
    },
    onSuccess: () => {
      message.success(`${config.title} berhasil disimpan.`);
      queryClient.invalidateQueries({ queryKey: [config.queryKey] });
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  return (
    <section>
      <PageHeader
        title={config.title}
        subtitle={config.subtitle}
        breadcrumbs={[{ label: 'CMS Landing Page' }, { label: 'Pengaturan' }, { label: config.title }]}
        onRefresh={query.refetch}
      />

      {query.isLoading ? (
        <LoadingState rows={8} />
      ) : query.isError ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <Card className="narrow-card" style={{ maxWidth: 760 }}>
          <Form form={form} layout="vertical" className="responsive-form" onFinish={save.mutate}>
            {config.fields.map((field) =>
              field.type === 'image' ? (
                <Form.Item
                  key={field.name}
                  label={field.label}
                  name={field.name}
                  tooltip={field.tooltip}
                  className={field.span === 'full' ? 'full-span' : undefined}
                >
                  <CmsImageField previewUrl={pickMediaUrl(record?.[field.relation])} />
                </Form.Item>
              ) : (
                <Form.Item
                  key={field.name}
                  label={field.label}
                  name={field.name}
                  tooltip={field.tooltip}
                  rules={field.rules}
                  valuePropName={field.type === 'switch' ? 'checked' : 'value'}
                  className={field.span === 'full' ? 'full-span' : undefined}
                >
                  {renderCmsField(field)}
                </Form.Item>
              ),
            )}
            <Form.Item className="full-span">
              <Button type="primary" htmlType="submit" icon={<SaveOutlined />} loading={save.isPending}>
                Simpan Pengaturan
              </Button>
            </Form.Item>
          </Form>
        </Card>
      )}
    </section>
  );
}
