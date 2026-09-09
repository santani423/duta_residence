import { SaveOutlined, UploadOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button, Card, Form, Image, Input, Segmented, Select, Slider, Space, Switch, Typography, Upload, message } from 'antd';
import { useEffect, useState } from 'react';
import PageHeader from '../components/common/PageHeader.jsx';
import { ErrorState, LoadingState } from '../components/common/ApiState.jsx';
import WatermarkLayer from '../components/layout/WatermarkLayer.jsx';
import { api } from '../services/estateApi.js';
import { WATERMARK_SETTINGS_QUERY_KEY } from '../hooks/useWatermarkSettings.js';
import { cmsImageUrl } from '../utils/cmsMedia.js';
import { getApiErrorMessage } from '../utils/apiError.js';

const POSITION_OPTIONS = [
  { value: 'top-left', label: 'Kiri Atas' },
  { value: 'top-center', label: 'Tengah Atas' },
  { value: 'top-right', label: 'Kanan Atas' },
  { value: 'center', label: 'Tengah' },
  { value: 'bottom-left', label: 'Kiri Bawah' },
  { value: 'bottom-center', label: 'Tengah Bawah' },
  { value: 'bottom-right', label: 'Kanan Bawah' },
];

const DEFAULT_VALUES = {
  enabled: false,
  type: 'text',
  text_content: 'Duta Indah Residences',
  opacity: 30,
  mode: 'single',
  size: 200,
  position: 'bottom-right',
  spacing: 150,
};

function toFormValues(record) {
  if (!record) return DEFAULT_VALUES;
  return {
    enabled: record.enabled,
    type: record.type,
    text_content: record.text_content || '',
    media_id: record.media_id || null,
    opacity: record.opacity,
    mode: record.mode,
    size: record.size,
    position: record.position,
    spacing: record.spacing,
  };
}

export default function AdminWatermarkSettingsPage() {
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ['admin-watermark-settings'], queryFn: api.watermarkSettings.show });
  const record = query.data?.data;

  const [values, setValues] = useState(DEFAULT_VALUES);
  const [logoPreviewUrl, setLogoPreviewUrl] = useState(null);
  const [trackedRecord, setTrackedRecord] = useState(record);

  // Sync local preview/form state once the settings query resolves, without
  // remounting this component - adjusting state during render (React's
  // documented pattern) rather than an effect, to avoid an extra commit.
  if (record && record !== trackedRecord) {
    setTrackedRecord(record);
    setValues(toFormValues(record));
    setLogoPreviewUrl(cmsImageUrl(record.media));
  }

  useEffect(() => {
    if (record) form.setFieldsValue(toFormValues(record));
  }, [record, form]);

  const uploadLogo = useMutation({
    mutationFn: (file) => {
      const formData = new FormData();
      formData.append('file', file);
      return api.watermarkSettings.uploadLogo(formData);
    },
    onSuccess: (response) => {
      const media = response.data;
      setLogoPreviewUrl(cmsImageUrl(media));
      form.setFieldValue('media_id', media.id);
      setValues((current) => ({ ...current, media_id: media.id }));
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const save = useMutation({
    mutationFn: (payload) => api.watermarkSettings.update(payload),
    onSuccess: () => {
      message.success('Pengaturan watermark berhasil disimpan.');
      queryClient.invalidateQueries({ queryKey: ['admin-watermark-settings'] });
      queryClient.invalidateQueries({ queryKey: WATERMARK_SETTINGS_QUERY_KEY });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  function handleValuesChange(_, allValues) {
    setValues(allValues);
  }

  const previewConfig = {
    enabled: true, // preview always shows the current draft, regardless of the enabled toggle
    type: values.type,
    textContent: values.text_content,
    imageUrl: values.type === 'image' ? logoPreviewUrl : null,
    opacity: values.opacity,
    mode: values.mode,
    size: values.size,
    position: values.position,
    spacing: values.spacing,
  };

  if (query.isLoading) return <LoadingState rows={8} />;
  if (query.isError) return <ErrorState error={query.error} onRetry={query.refetch} />;

  return (
    <section>
      <PageHeader
        title="Watermark Management"
        subtitle="Atur watermark global yang ditampilkan sebagai overlay non-interaktif di seluruh halaman aplikasi (khusus Super Admin)."
        breadcrumbs={[{ label: 'Settings' }, { label: 'Watermark Management' }]}
        helpModule="general"
        onRefresh={query.refetch}
      />

      <div className="section-row" style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1fr) minmax(0, 1fr)', gap: 16 }}>
        <Card title="Pengaturan Watermark">
          <Form
            form={form}
            layout="vertical"
            className="responsive-form"
            initialValues={DEFAULT_VALUES}
            onValuesChange={handleValuesChange}
            onFinish={(formValues) => save.mutate(formValues)}
          >
            <Form.Item name="enabled" label="Aktifkan Watermark" valuePropName="checked" className="full-span">
              <Switch checkedChildren="ON" unCheckedChildren="OFF" />
            </Form.Item>

            <Form.Item name="type" label="Jenis Watermark" className="full-span">
              <Segmented
                options={[
                  { value: 'text', label: 'Teks' },
                  { value: 'image', label: 'Logo / Gambar' },
                ]}
              />
            </Form.Item>

            {values.type === 'text' ? (
              <Form.Item
                name="text_content"
                label="Isi Teks Watermark"
                className="full-span"
                rules={[{ required: true, message: 'Isi teks watermark wajib diisi' }]}
              >
                <Input placeholder="mis. Duta Indah Residences" maxLength={100} showCount />
              </Form.Item>
            ) : (
              <Form.Item
                name="media_id"
                label="Logo / Gambar Watermark"
                className="full-span"
                rules={[{ required: true, message: 'Unggah logo/gambar watermark terlebih dahulu' }]}
              >
                <Space direction="vertical" size="small">
                  {logoPreviewUrl ? (
                    <Image src={logoPreviewUrl} width={140} height={100} style={{ objectFit: 'contain', background: '#f5f5f5', borderRadius: 8 }} alt="" />
                  ) : null}
                  <Upload
                    accept="image/*"
                    showUploadList={false}
                    beforeUpload={(file) => {
                      uploadLogo.mutate(file);
                      return false;
                    }}
                  >
                    <Button icon={<UploadOutlined />} loading={uploadLogo.isPending} size="small">
                      {logoPreviewUrl ? 'Ganti Gambar' : 'Unggah Gambar'}
                    </Button>
                  </Upload>
                </Space>
              </Form.Item>
            )}

            <Form.Item name="opacity" label={`Transparansi (Opacity): ${values.opacity}%`} className="full-span">
              <Slider min={10} max={100} tooltip={{ formatter: (value) => `${value}%` }} />
            </Form.Item>

            <Form.Item name="mode" label="Tata Letak Watermark" className="full-span">
              <Segmented
                options={[
                  { value: 'single', label: 'Single' },
                  { value: 'multiple', label: 'Multiple' },
                ]}
              />
            </Form.Item>

            <Form.Item name="size" label={`Ukuran Watermark: ${values.size}px`} className="full-span">
              <Slider min={40} max={600} tooltip={{ formatter: (value) => `${value}px` }} />
            </Form.Item>

            {values.mode === 'single' ? (
              <Form.Item name="position" label="Posisi Watermark" className="full-span">
                <Select options={POSITION_OPTIONS} />
              </Form.Item>
            ) : (
              <Form.Item name="spacing" label={`Jarak Antar Watermark: ${values.spacing}px`} className="full-span">
                <Slider min={20} max={500} tooltip={{ formatter: (value) => `${value}px` }} />
              </Form.Item>
            )}

            <Form.Item className="full-span">
              <Button type="primary" htmlType="submit" icon={<SaveOutlined />} loading={save.isPending}>
                Simpan Pengaturan
              </Button>
            </Form.Item>
          </Form>
        </Card>

        <Card title="Live Preview">
          <Typography.Paragraph type="secondary">
            Pratinjau ini memperbarui secara realtime mengikuti perubahan pengaturan di atas, tanpa perlu menyimpan atau reload halaman.
          </Typography.Paragraph>
          <div
            style={{
              position: 'relative',
              height: 420,
              borderRadius: 8,
              border: '1px solid rgba(0,0,0,0.1)',
              overflow: 'hidden',
              background:
                'repeating-linear-gradient(0deg, rgba(0,0,0,0.03) 0px, rgba(0,0,0,0.03) 1px, transparent 1px, transparent 32px), repeating-linear-gradient(90deg, rgba(0,0,0,0.03) 0px, rgba(0,0,0,0.03) 1px, transparent 1px, transparent 32px)',
            }}
          >
            <Typography.Text type="secondary" style={{ position: 'absolute', top: 12, left: 12 }}>
              Simulasi halaman aplikasi
            </Typography.Text>
            <WatermarkLayer config={previewConfig} />
          </div>
        </Card>
      </div>
    </section>
  );
}
