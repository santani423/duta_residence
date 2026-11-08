import { DatePicker } from 'antd';

// Pemilih rentang tanggal yang dipakai semua dashboard: preset cepat + rentang bebas.
export default function DashboardRangePicker({ value, onChange, presets }) {
  return (
    <DatePicker.RangePicker
      allowClear={false}
      format="YYYY-MM-DD"
      presets={presets}
      value={value}
      onChange={(dates) => {
        if (dates?.[0] && dates?.[1]) {
          onChange([dates[0].startOf('day'), dates[1].startOf('day')]);
        }
      }}
    />
  );
}
