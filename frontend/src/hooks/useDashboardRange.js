import { useState } from 'react';
import dayjs from 'dayjs';

// Rentang tanggal dashboard. Default: awal bulan ini sampai hari ini.
// `start`/`end` (YYYY-MM-DD) dikirim ke API; `range` dipakai pemilih tanggal.
export default function useDashboardRange() {
  const today = dayjs().startOf('day');
  const [range, setRange] = useState(() => [today.startOf('month'), today]);
  const [start, end] = range.map((date) => date.format('YYYY-MM-DD'));
  const lastMonth = today.subtract(1, 'month');

  const presets = [
    { label: 'Hari ini', value: [today, today] },
    { label: '7 hari terakhir', value: [today.subtract(6, 'day'), today] },
    { label: 'Bulan ini', value: [today.startOf('month'), today] },
    { label: 'Bulan lalu', value: [lastMonth.startOf('month'), lastMonth.endOf('month').startOf('day')] },
  ];

  return { range, setRange, start, end, presets };
}
