import { DeleteOutlined, InboxOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button, Card, Image, Input, Modal, Upload, message } from 'antd';
import { useState } from 'react';
import { EmptyData, ErrorState, LoadingState } from '../../components/common/ApiState.jsx';
import PageHeader from '../../components/common/PageHeader.jsx';
import { useTableState } from '../../hooks/useTableState.js';
import { api, storageUrl } from '../../services/estateApi.js';
import { getApiErrorMessage } from '../../utils/apiError.js';

const { Dragger } = Upload;

function mediaPreview(media) {
  return storageUrl(media?.variants?.webp_thumb || media?.variants?.webp || media?.path);
}

export default function CmsMediaLibraryPage() {
  const table = useTableState();
  const queryClient = useQueryClient();
  const [pagination, setPagination] = useState({ current: 1, pageSize: 24 });

  const list = useQuery({
    queryKey: ['cms-media', table.search, pagination],
    queryFn: () => api.cms.media.list({ search: table.search || undefined, page: pagination.current, per_page: pagination.pageSize }),
  });

  const upload = useMutation({
    mutationFn: (file) => {
      const formData = new FormData();
      formData.append('file', file);
      return api.cms.media.upload(formData);
    },
    onSuccess: () => {
      message.success('Media berhasil diunggah.');
      queryClient.invalidateQueries({ queryKey: ['cms-media'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const remove = useMutation({
    mutationFn: api.cms.media.remove,
    onSuccess: () => {
      message.success('Media berhasil dihapus.');
      queryClient.invalidateQueries({ queryKey: ['cms-media'] });
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const items = list.data?.data?.data || [];
  const meta = list.data?.data?.meta;

  return (
    <section>
      <PageHeader
        title="Pustaka Media"
        subtitle="Semua gambar yang pernah diunggah untuk konten landing page. Gambar yang masih dipakai tidak dapat dihapus."
        breadcrumbs={[{ label: 'CMS Landing Page' }, { label: 'Pustaka Media' }]}
        onRefresh={list.refetch}
      />

      <Card style={{ marginBottom: 16 }}>
        <Dragger
          multiple
          showUploadList={false}
          accept="image/*"
          beforeUpload={(file) => {
            upload.mutate(file);
            return false;
          }}
        >
          <p className="ant-upload-drag-icon">
            <InboxOutlined />
          </p>
          <p className="ant-upload-text">Klik atau seret gambar ke sini untuk mengunggah</p>
          <p className="ant-upload-hint">Format JPG/PNG/WebP, maksimal 10MB. WebP dan ukuran responsif dibuat otomatis.</p>
        </Dragger>
      </Card>

      <Input.Search
        allowClear
        placeholder="Cari berdasarkan teks alternatif"
        value={table.search}
        onChange={(event) => table.setSearch(event.target.value)}
        style={{ maxWidth: 360, marginBottom: 16 }}
      />

      {list.isLoading ? (
        <LoadingState rows={6} />
      ) : list.isError ? (
        <ErrorState error={list.error} onRetry={list.refetch} />
      ) : items.length === 0 ? (
        <EmptyData description="Belum ada media yang diunggah." />
      ) : (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(160px, 1fr))', gap: 16 }}>
          {items.map((media) => (
            <Card
              key={media.id}
              size="small"
              cover={<Image src={mediaPreview(media)} height={120} style={{ objectFit: 'cover' }} alt={media.alt_text || ''} />}
              actions={[
                <DeleteOutlined
                  key="delete"
                  onClick={() =>
                    Modal.confirm({
                      title: 'Hapus media ini?',
                      content: media.alt_text || media.path,
                      okText: 'Hapus',
                      okButtonProps: { danger: true },
                      onOk: () => remove.mutate(media.id),
                    })
                  }
                />,
              ]}
            >
              <Card.Meta description={<span style={{ fontSize: 12 }}>{media.alt_text || `#${media.id}`}</span>} />
            </Card>
          ))}
        </div>
      )}

      {meta ? (
        <div style={{ marginTop: 16, textAlign: 'right' }}>
          <Button disabled={meta.current_page <= 1} onClick={() => setPagination((p) => ({ ...p, current: p.current - 1 }))}>
            Sebelumnya
          </Button>{' '}
          <Button disabled={meta.current_page >= meta.last_page} onClick={() => setPagination((p) => ({ ...p, current: p.current + 1 }))}>
            Berikutnya
          </Button>
        </div>
      ) : null}
    </section>
  );
}
