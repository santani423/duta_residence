import { Button, ColorPicker, Descriptions, Divider, Empty, Input, InputNumber, Select, Slider, Space, Switch, Tag, Typography, theme } from 'antd';
import { DeleteOutlined } from '@ant-design/icons';
import { Link } from 'react-router-dom';
import { formatCurrency } from '../../utils/format.js';
import { defaultPointsFor, deriveUnitColor, POLYGON_LIKE_SHAPES, SHAPE_TYPES } from './mapConstants.js';

function toHex(value) {
  return typeof value === 'string' ? value : value?.toHexString?.();
}

const shapeOptions = SHAPE_TYPES.map((value) => ({ value, label: value }));

export default function ClusterMapObjectPanel({ object, editable, onChange, onDelete }) {
  const { token } = theme.useToken();

  if (!object) {
    return (
      <div style={{ flexShrink: 0, width: 280, borderLeft: `1px solid ${token.colorBorderSecondary}`, background: token.colorBgContainer, padding: 16 }}>
        <Empty description="Pilih objek pada peta" image={Empty.PRESENTED_IMAGE_SIMPLE} />
      </div>
    );
  }

  const isUnit = object.object_category === 'unit';
  const detail = object.unit_detail;
  const autoColor = isUnit && object.is_color_auto !== false;

  function handleShapeChange(shapeType) {
    const isPolygonLike = POLYGON_LIKE_SHAPES.includes(shapeType);
    onChange({
      shape_type: shapeType,
      points: isPolygonLike ? defaultPointsFor(shapeType, object.width, object.height) : null,
    });
  }

  return (
    <div style={{ flexShrink: 0, width: 280, borderLeft: `1px solid ${token.colorBorderSecondary}`, background: token.colorBgContainer, padding: 16, overflowY: 'auto' }}>
      <Typography.Text strong>{isUnit ? `Unit ${object.unit_id}` : (object.componentType?.name || object.label_text || 'Komponen')}</Typography.Text>

      <div style={{ marginTop: 12, marginBottom: 12 }}>
        <Typography.Text type="secondary" style={{ fontSize: 12 }}>Bentuk</Typography.Text>
        <Select
          style={{ width: '100%' }}
          disabled={!editable}
          value={object.shape_type || 'rect'}
          options={shapeOptions}
          onChange={handleShapeChange}
        />
      </div>

      {isUnit && detail && (
        <>
          <Descriptions size="small" column={1} style={{ marginTop: 12 }}>
            <Descriptions.Item label="Blok/Kavling">{detail.block} / {detail.lot_number}</Descriptions.Item>
            <Descriptions.Item label="Penghuni">{detail.resident_name || '-'}</Descriptions.Item>
            <Descriptions.Item label="Okupansi">{detail.occupancy_name || '-'}</Descriptions.Item>
            <Descriptions.Item label="Status">{detail.status_name || '-'}</Descriptions.Item>
            <Descriptions.Item label="Tunggakan">
              {detail.has_arrears
                ? <Tag color="red">{formatCurrency(detail.total_outstanding)}</Tag>
                : <Tag color="green">Lunas</Tag>}
            </Descriptions.Item>
          </Descriptions>
          <Link to={`/units/${object.unit_id}`}>Lihat detail unit</Link>
          <Divider style={{ margin: '12px 0' }} />
        </>
      )}

      {!isUnit && (
        <div style={{ marginTop: 12 }}>
          <Typography.Text type="secondary" style={{ fontSize: 12 }}>Teks Label</Typography.Text>
          <Input
            value={object.label_text || ''}
            disabled={!editable}
            onChange={(e) => onChange({ label_text: e.target.value })}
            placeholder="Tulis label..."
          />
          <Divider style={{ margin: '12px 0' }} />
        </div>
      )}

      {isUnit && (
        <div style={{ marginBottom: 12 }}>
          <Space>
            <Switch size="small" checked={autoColor} disabled={!editable} onChange={(checked) => onChange({ is_color_auto: checked })} />
            <Typography.Text style={{ fontSize: 12 }}>Warna otomatis (berdasarkan status)</Typography.Text>
          </Space>
        </div>
      )}

      {!autoColor && (
        <>
          <Typography.Text type="secondary" style={{ fontSize: 12 }}>Warna Isi</Typography.Text>
          <div style={{ marginBottom: 8 }}>
            <ColorPicker
              disabled={!editable}
              value={object.fill_color || (isUnit ? deriveUnitColor(detail).fill : '#91caff')}
              onChange={(color) => onChange({ fill_color: toHex(color) })}
            />
          </div>
          <Typography.Text type="secondary" style={{ fontSize: 12 }}>Warna Garis Tepi</Typography.Text>
          <div style={{ marginBottom: 8 }}>
            <ColorPicker
              disabled={!editable}
              value={object.stroke_color || (isUnit ? deriveUnitColor(detail).stroke : '#1677ff')}
              onChange={(color) => onChange({ stroke_color: toHex(color) })}
            />
          </div>
        </>
      )}

      <Typography.Text type="secondary" style={{ fontSize: 12 }}>Ketebalan Garis</Typography.Text>
      <InputNumber
        min={0} max={20} style={{ width: '100%', marginBottom: 8 }}
        value={object.stroke_width ?? 1}
        disabled={!editable}
        onChange={(value) => onChange({ stroke_width: value ?? 1 })}
      />

      <Typography.Text type="secondary" style={{ fontSize: 12 }}>Transparansi</Typography.Text>
      <Slider
        min={0} max={1} step={0.05}
        value={object.opacity ?? 1}
        disabled={!editable}
        onChange={(value) => onChange({ opacity: value })}
      />

      <Typography.Text type="secondary" style={{ fontSize: 12 }}>Urutan Layer</Typography.Text>
      <InputNumber
        style={{ width: '100%', marginBottom: 8 }}
        value={object.layer_order ?? 0}
        disabled={!editable}
        onChange={(value) => onChange({ layer_order: value ?? 0 })}
      />

      <Space style={{ marginTop: 8, marginBottom: 8 }}>
        <Switch size="small" checked={!!object.is_locked} disabled={!editable} onChange={(checked) => onChange({ is_locked: checked })} />
        <Typography.Text style={{ fontSize: 12 }}>Kunci posisi &amp; ukuran</Typography.Text>
      </Space>

      {editable && (
        <Button danger block icon={<DeleteOutlined />} onClick={onDelete}>Hapus Objek</Button>
      )}
    </div>
  );
}
