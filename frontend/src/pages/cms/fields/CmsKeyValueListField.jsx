import { DeleteOutlined, PlusOutlined } from '@ant-design/icons';
import { Button, Input, Space } from 'antd';

// Controlled `value`/`onChange` widget for a small array of {label, value}
// pairs (business hours, etc.) — compatible with antd Form.Item like any
// other field, no Form.List indirection needed since the array is tiny.
export default function CmsKeyValueListField({ value = [], onChange, labelPlaceholder = 'Label', valuePlaceholder = 'Nilai' }) {
  function update(index, key, next) {
    const rows = value.map((row, rowIndex) => (rowIndex === index ? { ...row, [key]: next } : row));
    onChange?.(rows);
  }

  function addRow() {
    onChange?.([...value, { label: '', value: '' }]);
  }

  function removeRow(index) {
    onChange?.(value.filter((_, rowIndex) => rowIndex !== index));
  }

  return (
    <Space direction="vertical" style={{ width: '100%' }}>
      {value.map((row, index) => (
        <Space key={index} style={{ display: 'flex' }}>
          <Input
            placeholder={labelPlaceholder}
            value={row.label}
            onChange={(event) => update(index, 'label', event.target.value)}
            style={{ width: 180 }}
          />
          <Input
            placeholder={valuePlaceholder}
            value={row.value}
            onChange={(event) => update(index, 'value', event.target.value)}
            style={{ width: 240 }}
          />
          <Button icon={<DeleteOutlined />} danger type="text" onClick={() => removeRow(index)} />
        </Space>
      ))}
      <Button type="dashed" icon={<PlusOutlined />} onClick={addRow}>
        Tambah Baris
      </Button>
    </Space>
  );
}
