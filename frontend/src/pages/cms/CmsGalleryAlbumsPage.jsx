import {
  ArrowDownOutlined, ArrowUpOutlined, DeleteOutlined, EditOutlined, MoreOutlined, PictureOutlined, PlusOutlined,
} from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button, Card, Drawer, Dropdown, Form, Image, Input, Modal, Select, Space, message } from 'antd';
import { useState } from 'react';
import FilterBar from '../../components/common/FilterBar.jsx';
import PageHeader from '../../components/common/PageHeader.jsx';
import StatusBadge from '../../components/common/StatusBadge.jsx';
import ResponsiveTable from '../../components/tables/ResponsiveTable.jsx';
import { useTableState } from '../../hooks/useTableState.js';
import { api } from '../../services/estateApi.js';
import { getApiErrorMessage, mapValidationErrors } from '../../utils/apiError.js';
import CmsImageField, { pickMediaUrl } from './fields/CmsImageField.jsx';

/**
 * Gallery albums are a config-driven CmsCollectionPage everywhere except one
 * thing: each row expands into a nested item manager (its own small CRUD),
 * so this stays a bespoke page rather than forcing that shape into the
 * generic component - closer to AdminManualBookPage.jsx's nested Form.List.
 */
export default function CmsGalleryAlbumsPage() {
  const table = useTableState();
  const categories = useQuery({ queryKey: ['cms-categories', 'gallery'], queryFn: () => api.cms.categories.list({ group: 'gallery', per_page: 100 }) });
  const categoryOptions = (categories.data?.data?.data || []).map((category) => ({ value: category.id, label: category.name }));
  const [albumDrawer, setAlbumDrawer] = useState({ type: null, record: null });
  const [itemsDrawer, setItemsDrawer] = useState({ open: false, album: null });
  const [itemForm] = Form.useForm();
  const [albumForm] = Form.useForm();
  const queryClient = useQueryClient();

  const albums = useQuery({ queryKey: ['cms-gallery-albums', table.params], queryFn: () => api.cms.galleryAlbums.list(table.params) });

  function invalidateAlbums() {
    queryClient.invalidateQueries({ queryKey: ['cms-gallery-albums'] });
  }

  const saveAlbum = useMutation({
    mutationFn: (values) => (albumDrawer.type === 'edit' ? api.cms.galleryAlbums.update(albumDrawer.record.id, values) : api.cms.galleryAlbums.create(values)),
    onSuccess: () => {
      message.success('Album berhasil disimpan.');
      setAlbumDrawer({ type: null, record: null });
      albumForm.resetFields();
      invalidateAlbums();
    },
    onError: (error) => {
      albumForm.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const removeAlbum = useMutation({
    mutationFn: api.cms.galleryAlbums.remove,
    onSuccess: () => {
      message.success('Album berhasil dihapus.');
      invalidateAlbums();
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const reorderAlbum = useMutation({
    mutationFn: ({ id, direction }) => api.cms.galleryAlbums.reorder(id, direction),
    onSuccess: invalidateAlbums,
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const items = useQuery({
    queryKey: ['cms-gallery-items', itemsDrawer.album?.id],
    queryFn: () => api.cms.galleryAlbums.items.list(itemsDrawer.album.id),
    enabled: Boolean(itemsDrawer.album?.id),
  });

  function invalidateItems() {
    queryClient.invalidateQueries({ queryKey: ['cms-gallery-items', itemsDrawer.album?.id] });
    invalidateAlbums();
  }

  const saveItem = useMutation({
    mutationFn: (values) => api.cms.galleryAlbums.items.create(itemsDrawer.album.id, values),
    onSuccess: () => {
      message.success('Item galeri berhasil ditambahkan.');
      itemForm.resetFields();
      invalidateItems();
    },
    onError: (error) => {
      itemForm.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const removeItem = useMutation({
    mutationFn: api.cms.galleryAlbums.items.remove,
    onSuccess: () => {
      message.success('Item galeri berhasil dihapus.');
      invalidateItems();
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const reorderItem = useMutation({
    mutationFn: ({ id, direction }) => api.cms.galleryAlbums.items.reorder(id, direction),
    onSuccess: invalidateItems,
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  function openCreateAlbum() {
    albumForm.resetFields();
    albumForm.setFieldsValue({ is_active: true });
    setAlbumDrawer({ type: 'create', record: null });
  }

  function openEditAlbum(record) {
    albumForm.setFieldsValue(record);
    setAlbumDrawer({ type: 'edit', record });
  }

  const albumType = Form.useWatch('type', itemForm);

  return (
    <section>
      <PageHeader
        title="Galeri"
        subtitle="Kelola album dan foto/video galeri kawasan yang tampil di landing page."
        breadcrumbs={[{ label: 'CMS Landing Page' }, { label: 'Galeri' }]}
        onRefresh={albums.refetch}
        extra={
          <Button type="primary" icon={<PlusOutlined />} onClick={openCreateAlbum}>
            Tambah Album
          </Button>
        }
      />

      <FilterBar>
        <Input allowClear placeholder="Cari album" value={table.search} onChange={(event) => table.setSearch(event.target.value)} className="filter-input" />
      </FilterBar>

      <Card>
        <ResponsiveTable
          query={albums}
          onChange={table.handleTableChange}
          columns={[
            {
              title: 'Sampul',
              width: 80,
              render: (_, record) => (
                <Image src={pickMediaUrl(record.cover)} width={56} height={40} style={{ objectFit: 'cover', borderRadius: 6 }} fallback="" />
              ),
            },
            { title: 'Judul Album', dataIndex: 'title' },
            { title: 'Jumlah Item', dataIndex: 'items_count', width: 110 },
            { title: 'Status', dataIndex: 'is_active', width: 100, render: (value) => <StatusBadge type="active" value={value} /> },
            {
              title: 'Urutan',
              width: 100,
              render: (_, record) => (
                <Space size={4}>
                  <Button size="small" icon={<ArrowUpOutlined />} onClick={() => reorderAlbum.mutate({ id: record.id, direction: 'up' })} />
                  <Button size="small" icon={<ArrowDownOutlined />} onClick={() => reorderAlbum.mutate({ id: record.id, direction: 'down' })} />
                </Space>
              ),
            },
            {
              title: 'Aksi',
              fixed: 'right',
              width: 130,
              render: (_, record) => (
                <Space size={4}>
                  <Button icon={<PictureOutlined />} size="small" onClick={() => setItemsDrawer({ open: true, album: record })}>
                    Item
                  </Button>
                  <Dropdown
                    menu={{
                      items: [
                        { key: 'edit', label: 'Edit', icon: <EditOutlined /> },
                        { key: 'delete', label: 'Hapus', icon: <DeleteOutlined />, danger: true },
                      ],
                      onClick: ({ key }) => {
                        if (key === 'edit') openEditAlbum(record);
                        if (key === 'delete') {
                          Modal.confirm({
                            title: 'Hapus album ini?',
                            content: `${record.title} beserta seluruh itemnya akan dihapus.`,
                            okText: 'Hapus',
                            okButtonProps: { danger: true },
                            onOk: () => removeAlbum.mutate(record.id),
                          });
                        }
                      },
                    }}
                  >
                    <Button size="small" icon={<MoreOutlined />} />
                  </Dropdown>
                </Space>
              ),
            },
          ]}
        />
      </Card>

      <Drawer
        title={albumDrawer.type === 'edit' ? 'Edit Album' : 'Tambah Album'}
        open={albumDrawer.type === 'create' || albumDrawer.type === 'edit'}
        onClose={() => setAlbumDrawer({ type: null, record: null })}
        width={520}
        extra={
          <Space>
            <Button onClick={() => setAlbumDrawer({ type: null, record: null })}>Batal</Button>
            <Button type="primary" loading={saveAlbum.isPending} onClick={() => albumForm.submit()}>
              Simpan
            </Button>
          </Space>
        }
        destroyOnHidden
      >
        <Form form={albumForm} layout="vertical" onFinish={saveAlbum.mutate}>
          <Form.Item label="Judul Album" name="title" rules={[{ required: true, message: 'Judul wajib diisi' }]}>
            <Input />
          </Form.Item>
          <Form.Item label="Slug" name="slug" rules={[{ required: true, message: 'Slug wajib diisi' }]} tooltip="URL ramah-mesin-pencari, contoh: fasilitas-kawasan">
            <Input />
          </Form.Item>
          <Form.Item label="Sampul Album" name="cover_media_id">
            <CmsImageField previewUrl={pickMediaUrl(albumDrawer.record?.cover)} />
          </Form.Item>
          <Form.Item label="Kategori" name="category_id">
            <Select allowClear options={categoryOptions} placeholder="Pilih kategori" />
          </Form.Item>
          <Form.Item label="Status" name="is_active" valuePropName="checked">
            <Select
              options={[
                { value: true, label: 'Aktif' },
                { value: false, label: 'Nonaktif' },
              ]}
            />
          </Form.Item>
        </Form>
      </Drawer>

      <Drawer
        title={`Item Galeri — ${itemsDrawer.album?.title || ''}`}
        open={itemsDrawer.open}
        onClose={() => setItemsDrawer({ open: false, album: null })}
        width={560}
        destroyOnHidden
      >
        <Space direction="vertical" style={{ width: '100%' }} size="middle">
          {(items.data?.data || []).map((item) => (
            <Card key={item.id} size="small">
              <Space align="start" style={{ width: '100%', justifyContent: 'space-between' }}>
                <Space>
                  {item.type === 'image' ? (
                    <Image src={pickMediaUrl(item.media)} width={64} height={48} style={{ objectFit: 'cover', borderRadius: 6 }} />
                  ) : (
                    <div style={{ width: 64, height: 48, background: '#f1f5f9', borderRadius: 6, display: 'grid', placeItems: 'center', fontSize: 11 }}>
                      Video
                    </div>
                  )}
                  <div>
                    <div>{item.caption || <span style={{ color: '#94a3b8' }}>(tanpa keterangan)</span>}</div>
                    <StatusBadge type="active" value={item.is_active} />
                  </div>
                </Space>
                <Space size={4}>
                  <Button size="small" icon={<ArrowUpOutlined />} onClick={() => reorderItem.mutate({ id: item.id, direction: 'up' })} />
                  <Button size="small" icon={<ArrowDownOutlined />} onClick={() => reorderItem.mutate({ id: item.id, direction: 'down' })} />
                  <Button
                    size="small"
                    danger
                    icon={<DeleteOutlined />}
                    onClick={() => Modal.confirm({ title: 'Hapus item ini?', okText: 'Hapus', okButtonProps: { danger: true }, onOk: () => removeItem.mutate(item.id) })}
                  />
                </Space>
              </Space>
            </Card>
          ))}

          <Card size="small" title="Tambah Item">
            <Form form={itemForm} layout="vertical" onFinish={saveItem.mutate} initialValues={{ type: 'image', is_active: true }}>
              <Form.Item label="Tipe" name="type" rules={[{ required: true }]}>
                <Select options={[{ value: 'image', label: 'Gambar' }, { value: 'video', label: 'Video (URL)' }]} />
              </Form.Item>
              {albumType === 'video' ? (
                <Form.Item label="URL Video" name="video_url" rules={[{ required: true, message: 'URL video wajib diisi' }]}>
                  <Input placeholder="https://youtube.com/watch?v=..." />
                </Form.Item>
              ) : (
                <Form.Item label="Gambar" name="media_id" rules={[{ required: true, message: 'Gambar wajib diunggah' }]}>
                  <CmsImageField />
                </Form.Item>
              )}
              <Form.Item label="Keterangan" name="caption">
                <Input />
              </Form.Item>
              <Button type="primary" htmlType="submit" loading={saveItem.isPending} icon={<PlusOutlined />}>
                Tambah Item
              </Button>
            </Form>
          </Card>
        </Space>
      </Drawer>
    </section>
  );
}
