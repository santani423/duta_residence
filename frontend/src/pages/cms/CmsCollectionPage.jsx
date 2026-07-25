import { ArrowDownOutlined, ArrowUpOutlined, DeleteOutlined, EditOutlined, MoreOutlined, PlusOutlined } from '@ant-design/icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button, Card, Drawer, Dropdown, Form, Input, Modal, Select, Space, message } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import FilterBar from '../../components/common/FilterBar.jsx';
import PageHeader from '../../components/common/PageHeader.jsx';
import StatusBadge from '../../components/common/StatusBadge.jsx';
import ResponsiveTable from '../../components/tables/ResponsiveTable.jsx';
import { useTableState } from '../../hooks/useTableState.js';
import { getApiErrorMessage, mapValidationErrors } from '../../utils/apiError.js';
import CmsImageField, { pickMediaUrl } from './fields/CmsImageField.jsx';
import { renderCmsField } from './fields/renderCmsField.jsx';

function toFormValues(record, fields) {
  const values = { ...record };
  fields.forEach((field) => {
    if (field.type === 'date' && values[field.name]) {
      values[field.name] = dayjs(values[field.name]);
    }
  });
  return values;
}

function toSubmitValues(values, fields) {
  const payload = { ...values };
  fields.forEach((field) => {
    if (field.type === 'date' && payload[field.name]) {
      payload[field.name] = payload[field.name].toISOString();
    }
  });
  return payload;
}

/**
 * Generic list + create/edit Drawer for every "collection" CMS module (hero
 * slides, services, testimonials, ...). Driven entirely by `config` so each
 * module is a ~20-line wrapper instead of a bespoke page. Modeled on
 * AdminManualBookPage.jsx's structure (Drawer form, Modal.confirm delete,
 * form.setFields(mapValidationErrors(...)) for server-side validation).
 */
export default function CmsCollectionPage({ config }) {
  const table = useTableState();
  const [drawer, setDrawer] = useState({ type: null, record: null });
  const [form] = Form.useForm();
  const queryClient = useQueryClient();
  const queryKey = [config.queryKey, table.params];

  const list = useQuery({ queryKey, queryFn: () => config.resource.list(table.params) });

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: [config.queryKey] });
  }

  const save = useMutation({
    mutationFn: (values) => {
      const payload = toSubmitValues(values, config.fields);
      return drawer.type === 'edit' ? config.resource.update(drawer.record.id, payload) : config.resource.create(payload);
    },
    onSuccess: () => {
      message.success(`${config.titleSingular} berhasil disimpan.`);
      setDrawer({ type: null, record: null });
      form.resetFields();
      invalidate();
    },
    onError: (error) => {
      form.setFields(mapValidationErrors(error));
      message.error(getApiErrorMessage(error));
    },
  });

  const remove = useMutation({
    mutationFn: config.resource.remove,
    onSuccess: () => {
      message.success(`${config.titleSingular} berhasil dihapus.`);
      invalidate();
    },
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  const reorder = useMutation({
    mutationFn: ({ id, direction }) => config.resource.reorder(id, direction),
    onSuccess: invalidate,
    onError: (error) => message.error(getApiErrorMessage(error)),
  });

  function openCreate() {
    form.resetFields();
    form.setFieldsValue(config.initialValues || { is_active: true });
    setDrawer({ type: 'create', record: null });
  }

  function openEdit(record) {
    form.setFieldsValue(toFormValues(record, config.fields));
    setDrawer({ type: 'edit', record });
  }

  const columns = [
    ...config.columns,
    { title: 'Status', dataIndex: 'is_active', width: 100, render: (value) => <StatusBadge type="active" value={value} /> },
    config.hasReorder
      ? {
        title: 'Urutan',
        width: 100,
        render: (_, record) => (
          <Space size={4}>
            <Button size="small" icon={<ArrowUpOutlined />} onClick={() => reorder.mutate({ id: record.id, direction: 'up' })} />
            <Button size="small" icon={<ArrowDownOutlined />} onClick={() => reorder.mutate({ id: record.id, direction: 'down' })} />
          </Space>
        ),
      }
      : null,
    {
      title: 'Aksi',
      fixed: 'right',
      width: 90,
      render: (_, record) => (
        <Dropdown
          menu={{
            items: [
              { key: 'edit', label: 'Edit', icon: <EditOutlined /> },
              { key: 'delete', label: 'Hapus', icon: <DeleteOutlined />, danger: true },
            ],
            onClick: ({ key }) => {
              if (key === 'edit') openEdit(record);
              if (key === 'delete') {
                Modal.confirm({
                  title: `Hapus ${config.titleSingular.toLowerCase()} ini?`,
                  content: config.rowLabel ? config.rowLabel(record) : record.title || record.name,
                  okText: 'Hapus',
                  okButtonProps: { danger: true },
                  onOk: () => remove.mutate(record.id),
                });
              }
            },
          }}
        >
          <Button icon={<MoreOutlined />} />
        </Dropdown>
      ),
    },
  ].filter(Boolean);

  return (
    <section>
      <PageHeader
        title={config.titlePlural}
        subtitle={config.subtitle}
        breadcrumbs={[{ label: 'CMS Landing Page' }, { label: config.titlePlural }]}
        onRefresh={list.refetch}
        extra={
          <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>
            Tambah {config.titleSingular}
          </Button>
        }
      />

      {config.hasSearch || config.extraFilters?.length ? (
        <FilterBar>
          {config.hasSearch ? (
            <Input
              allowClear
              placeholder={config.searchPlaceholder || 'Cari'}
              value={table.search}
              onChange={(event) => table.setSearch(event.target.value)}
              className="filter-input"
            />
          ) : null}
          {(config.extraFilters || []).map((filter) => (
            <Select
              key={filter.name}
              allowClear
              placeholder={filter.label}
              value={table.filters[filter.name]}
              onChange={(value) => table.setFilters({ ...table.filters, [filter.name]: value })}
              options={filter.options}
              className="filter-input"
            />
          ))}
        </FilterBar>
      ) : null}

      <Card>
        <ResponsiveTable query={list} onChange={table.handleTableChange} columns={columns} />
      </Card>

      <CollectionFormDrawer
        config={config}
        drawer={drawer}
        onClose={() => setDrawer({ type: null, record: null })}
        form={form}
        save={save}
      />
    </section>
  );
}

function CollectionFormDrawer({ config, drawer, onClose, form, save }) {
  return (
    <Drawer
      title={drawer.type === 'edit' ? `Edit ${config.titleSingular}` : `Tambah ${config.titleSingular}`}
      open={drawer.type === 'create' || drawer.type === 'edit'}
      onClose={onClose}
      width={640}
      extra={
        <Space>
          <Button onClick={onClose}>Batal</Button>
          <Button type="primary" loading={save.isPending} onClick={() => form.submit()}>
            Simpan
          </Button>
        </Space>
      }
      destroyOnHidden
    >
      <Form form={form} layout="vertical" className="responsive-form" onFinish={save.mutate} initialValues={config.initialValues}>
        {config.fields.map((field) =>
          field.type === 'image' ? (
            <Form.Item
              key={field.name}
              label={field.label}
              name={field.name}
              tooltip={field.tooltip}
              className={field.span === 'full' ? 'full-span' : undefined}
            >
              <CmsImageField previewUrl={pickMediaUrl(drawer.record?.[field.relation])} />
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
      </Form>
    </Drawer>
  );
}
