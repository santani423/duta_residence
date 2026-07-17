import { useMemo, useState } from 'react';
import { Button, Empty, Space, Tooltip, Typography } from 'antd';
import { MenuFoldOutlined, MenuUnfoldOutlined, PlusOutlined, SettingOutlined } from '@ant-design/icons';
import Can from '../../components/common/Can.jsx';
import ClusterMapComponentTypeManager from './ClusterMapComponentTypeManager.jsx';

export default function ClusterMapComponentPalette({ componentTypes, onPlace, onComponentTypesChange, getContainer }) {
  const [managerOpen, setManagerOpen] = useState(false);
  const [collapsed, setCollapsed] = useState(false);

  const grouped = useMemo(() => {
    const active = componentTypes.filter((type) => type.is_active);
    const byCategory = {};
    active.forEach((type) => {
      const key = type.category || 'Lainnya';
      byCategory[key] = byCategory[key] || [];
      byCategory[key].push(type);
    });
    return byCategory;
  }, [componentTypes]);

  if (collapsed) {
    return (
      <div style={{ flexShrink: 0, width: 36, borderRight: '1px solid #f0f0f0', display: 'flex', justifyContent: 'center', paddingTop: 12 }}>
        <Tooltip title="Tampilkan panel komponen" placement="right">
          <Button size="small" type="text" icon={<MenuUnfoldOutlined />} onClick={() => setCollapsed(false)} />
        </Tooltip>
      </div>
    );
  }

  return (
    <div style={{ flexShrink: 0, width: 220, borderRight: '1px solid #f0f0f0', padding: 12, overflowY: 'auto' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
        <Typography.Text strong>Komponen</Typography.Text>
        <Space size={0}>
          <Can permission="cluster-map-components.manage">
            <Tooltip title="Kelola Komponen">
              <Button size="small" type="text" icon={<SettingOutlined />} onClick={() => setManagerOpen(true)} />
            </Tooltip>
          </Can>
          <Tooltip title="Sembunyikan panel">
            <Button size="small" type="text" icon={<MenuFoldOutlined />} onClick={() => setCollapsed(true)} />
          </Tooltip>
        </Space>
      </div>

      {Object.keys(grouped).length === 0 && <Empty description="Belum ada komponen" image={Empty.PRESENTED_IMAGE_SIMPLE} />}

      {Object.entries(grouped).map(([category, types]) => (
        <div key={category} style={{ marginBottom: 12 }}>
          <Typography.Text type="secondary" style={{ fontSize: 12 }}>{category}</Typography.Text>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 4, marginTop: 4 }}>
            {types.map((type) => (
              <Button
                key={type.id}
                size="small"
                style={{ width: '100%', textAlign: 'left', display: 'flex', alignItems: 'center', gap: 8 }}
                onClick={() => onPlace(type)}
              >
                <span style={{
                  flexShrink: 0, display: 'inline-block', width: 12, height: 12, borderRadius: 2,
                  background: type.fill_color, border: `1px solid ${type.stroke_color}`,
                }}
                />
                <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{type.name}</span>
              </Button>
            ))}
          </div>
        </div>
      ))}

      <Can permission="cluster-map-components.manage">
        <Button block size="small" icon={<PlusOutlined />} onClick={() => setManagerOpen(true)}>
          Buat Komponen Baru
        </Button>
      </Can>

      <ClusterMapComponentTypeManager
        open={managerOpen}
        onClose={() => setManagerOpen(false)}
        componentTypes={componentTypes}
        onChange={onComponentTypesChange}
        getContainer={getContainer}
      />
    </div>
  );
}
