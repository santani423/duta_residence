import { DeleteOutlined, PlusOutlined } from '@ant-design/icons';
import { Button, Input, Select, Space } from 'antd';
import { ICON_OPTIONS, LandingIcon } from '../../../constants/landingIcons.jsx';

const { TextArea } = Input;

// Small repeatable {icon, title, description} row editor - used for the About
// section's trust-badge "pillars" and any similar small fixed-ish list stored
// as a JSON column on a singleton settings record.
export default function CmsIconTextListField({ value = [], onChange }) {
  function update(index, key, next) {
    const rows = value.map((row, rowIndex) => (rowIndex === index ? { ...row, [key]: next } : row));
    onChange?.(rows);
  }

  function addRow() {
    onChange?.([...value, { icon: '', title: '', description: '' }]);
  }

  function removeRow(index) {
    onChange?.(value.filter((_, rowIndex) => rowIndex !== index));
  }

  return (
    <Space direction="vertical" style={{ width: '100%' }}>
      {value.map((row, index) => (
        <Space key={index} align="start" style={{ display: 'flex', width: '100%' }}>
          <Select
            showSearch
            placeholder="Ikon"
            style={{ width: 150 }}
            value={row.icon || undefined}
            onChange={(next) => update(index, 'icon', next)}
            options={ICON_OPTIONS.map((option) => ({
              value: option.value,
              label: (
                <Space size={4}>
                  <LandingIcon name={option.value} /> {option.value}
                </Space>
              ),
            }))}
          />
          <Input placeholder="Judul" value={row.title} onChange={(event) => update(index, 'title', event.target.value)} style={{ width: 160 }} />
          <TextArea
            placeholder="Deskripsi"
            value={row.description}
            onChange={(event) => update(index, 'description', event.target.value)}
            rows={1}
            style={{ width: 260 }}
          />
          <Button icon={<DeleteOutlined />} danger type="text" onClick={() => removeRow(index)} />
        </Space>
      ))}
      <Button type="dashed" icon={<PlusOutlined />} onClick={addRow}>
        Tambah Poin
      </Button>
    </Space>
  );
}
