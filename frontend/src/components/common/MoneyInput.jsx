import { InputNumber } from 'antd';

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

export default function MoneyInput({ min = 0, style, ...props }) {
  return (
    <InputNumber
      min={min}
      addonBefore="Rp"
      formatter={formatRupiahInput}
      parser={parseRupiahInput}
      style={{ width: '100%', ...style }}
      {...props}
    />
  );
}
