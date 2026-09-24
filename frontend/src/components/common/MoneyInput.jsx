import { Button, InputNumber, Space } from 'antd';
import { MinusOutlined, PlusOutlined } from '@ant-design/icons';

// Format Rupiah Indonesia: ribuan dipisah titik, desimal (jika ada) dipisah koma.
export function formatRupiahInput(value) {
  if (value === undefined || value === null || value === '') return '';
  const [integer, decimal] = String(value).split('.');
  const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  return decimal === undefined ? grouped : `${grouped},${decimal}`;
}

export function parseRupiahInput(value) {
  return String(value ?? '').replace(/[^\d,-]/g, '').replace(',', '.');
}

export default function MoneyInput({ min = 0, max, step = 1000, value, onChange, disabled, style, ...props }) {
  function clamp(next) {
    let result = next;
    if (min !== undefined && min !== null) result = Math.max(result, min);
    if (max !== undefined && max !== null) result = Math.min(result, max);
    return result;
  }

  function bump(delta) {
    onChange?.(clamp((Number(value) || 0) + delta));
  }

  const current = Number(value) || 0;
  const atMin = min !== undefined && min !== null && current <= min;
  const atMax = max !== undefined && max !== null && current >= max;

  return (
    <Space.Compact style={{ width: '100%', ...style }}>
      <Button icon={<MinusOutlined />} disabled={disabled || atMin} onClick={() => bump(-step)} />
      <InputNumber
        min={min}
        max={max}
        step={step}
        addonBefore="Rp"
        formatter={formatRupiahInput}
        parser={parseRupiahInput}
        controls={false}
        value={value}
        onChange={onChange}
        disabled={disabled}
        style={{ flex: 1, minWidth: 0 }}
        {...props}
      />
      <Button icon={<PlusOutlined />} disabled={disabled || atMax} onClick={() => bump(step)} />
    </Space.Compact>
  );
}
