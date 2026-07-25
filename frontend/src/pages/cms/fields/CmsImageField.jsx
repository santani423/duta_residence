import { DeleteOutlined, UploadOutlined } from '@ant-design/icons';
import { useMutation } from '@tanstack/react-query';
import { Button, Image, Space, Upload, message } from 'antd';
import { useState } from 'react';
import { api, storageUrl } from '../../../services/estateApi.js';
import { getApiErrorMessage } from '../../../utils/apiError.js';

export function pickMediaUrl(media) {
  const path = media?.variants?.webp_medium || media?.variants?.webp || media?.path;
  return storageUrl(path);
}

export default function CmsImageField({ value, onChange, previewUrl }) {
  const [preview, setPreview] = useState(previewUrl || null);
  const [trackedPreviewUrl, setTrackedPreviewUrl] = useState(previewUrl);

  // Settings pages pass previewUrl in asynchronously once their query
  // resolves (this component doesn't remount when that happens), so it needs
  // to react to that change - done during render (React's documented
  // pattern) rather than an effect, to avoid an extra commit.
  if (previewUrl !== trackedPreviewUrl) {
    setTrackedPreviewUrl(previewUrl);
    setPreview(previewUrl || null);
  }

  const upload = useMutation({
    mutationFn: (file) => {
      const formData = new FormData();
      formData.append('file', file);
      return api.cms.media.upload(formData);
    },
    onSuccess: (response) => {
      const media = response.data;
      setPreview(pickMediaUrl(media));
      onChange?.(media.id);
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  return (
    <Space direction="vertical" size="small">
      {preview ? (
        <Image src={preview} width={140} height={100} style={{ objectFit: 'cover', borderRadius: 8 }} alt="" />
      ) : null}
      <Space>
        <Upload
          accept="image/*"
          showUploadList={false}
          beforeUpload={(file) => {
            upload.mutate(file);
            return false;
          }}
        >
          <Button icon={<UploadOutlined />} loading={upload.isPending} size="small">
            {preview ? 'Ganti Gambar' : 'Unggah Gambar'}
          </Button>
        </Upload>
        {value ? (
          <Button
            icon={<DeleteOutlined />}
            size="small"
            danger
            type="text"
            onClick={() => {
              setPreview(null);
              onChange?.(null);
            }}
          >
            Hapus
          </Button>
        ) : null}
      </Space>
    </Space>
  );
}
