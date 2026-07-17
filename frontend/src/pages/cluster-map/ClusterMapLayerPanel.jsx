import { Button, Space, Typography } from 'antd';
import { ArrowDownOutlined, ArrowUpOutlined, EyeInvisibleOutlined, EyeOutlined, LockOutlined, UnlockOutlined } from '@ant-design/icons';

const CATEGORY_LABELS = { unit: 'Unit', component: 'Fasilitas', label: 'Label' };

function objectLabel(object) {
  if (object.object_category === 'unit') return `Unit ${object.unit_id}`;
  return object.componentType?.name || object.label_text || 'Objek';
}

export default function ClusterMapLayerPanel({ objects, selectedIds, hiddenIds, onSelect, onToggleHidden, onToggleLock, onMoveLayer, editable }) {
  const sorted = [...objects].sort((a, b) => (b.layer_order || 0) - (a.layer_order || 0));
  const grouped = { unit: [], component: [], label: [] };
  sorted.forEach((object) => { grouped[object.object_category]?.push(object); });

  return (
    <div style={{ padding: 8 }}>
      {Object.entries(grouped).map(([category, items]) => (
        items.length > 0 && (
          <div key={category} style={{ marginBottom: 12 }}>
            <Typography.Text type="secondary" style={{ fontSize: 12 }}>{CATEGORY_LABELS[category]} ({items.length})</Typography.Text>
            {items.map((object) => (
              <div
                key={object.id}
                onClick={() => onSelect([object.id])}
                style={{
                  display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                  padding: '4px 6px', borderRadius: 4, cursor: 'pointer',
                  background: selectedIds.includes(object.id) ? '#e6f4ff' : 'transparent',
                }}
              >
                <Typography.Text
                  ellipsis
                  style={{ maxWidth: 110, opacity: hiddenIds.includes(object.id) ? 0.4 : 1 }}
                >
                  {objectLabel(object)}
                </Typography.Text>
                <Space size={2}>
                  {editable && (
                    <>
                      <Button size="small" type="text" icon={<ArrowUpOutlined />} onClick={(e) => { e.stopPropagation(); onMoveLayer(object.id, 'up'); }} />
                      <Button size="small" type="text" icon={<ArrowDownOutlined />} onClick={(e) => { e.stopPropagation(); onMoveLayer(object.id, 'down'); }} />
                      <Button
                        size="small" type="text"
                        icon={object.is_locked ? <LockOutlined /> : <UnlockOutlined />}
                        onClick={(e) => { e.stopPropagation(); onToggleLock(object.id); }}
                      />
                    </>
                  )}
                  <Button
                    size="small" type="text"
                    icon={hiddenIds.includes(object.id) ? <EyeInvisibleOutlined /> : <EyeOutlined />}
                    onClick={(e) => { e.stopPropagation(); onToggleHidden(object.id); }}
                  />
                </Space>
              </div>
            ))}
          </div>
        )
      ))}
    </div>
  );
}
