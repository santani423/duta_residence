import { Form, Input } from 'antd';
import { PAYER_NAME_HINT, PAYER_NAME_MAX_LENGTH, PAYER_NAME_PLACEHOLDER } from '../../utils/payerName.js';

// Input "Nama Pembayar" saja (tanpa Form.Item), untuk dipakai di luar Form antd.
export function PayerNameInput({ style, ...props }) {
  return (
    <Input
      placeholder={PAYER_NAME_PLACEHOLDER}
      maxLength={PAYER_NAME_MAX_LENGTH}
      allowClear
      style={style}
      {...props}
    />
  );
}

// Field "Nama Pembayar" untuk semua form pembayaran (loket, transfer/gateway, bukti pembayaran).
export default function PayerNameField({ name = 'payer_name', label = 'Nama Pembayar', className, style, inputStyle }) {
  return (
    <Form.Item
      label={label}
      name={name}
      tooltip={PAYER_NAME_HINT}
      className={className}
      style={style}
      rules={[{ max: PAYER_NAME_MAX_LENGTH, message: `Maksimal ${PAYER_NAME_MAX_LENGTH} karakter` }]}
    >
      <PayerNameInput style={inputStyle} />
    </Form.Item>
  );
}
