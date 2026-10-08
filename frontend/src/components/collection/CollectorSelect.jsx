import { Select } from 'antd';
import { useMemo } from 'react';
import { collectorOptionLabel, useCollectorOptions } from './useCollectorOptions.js';

/**
 * Dropdown collector bersama (pengganti api.users.list({role:'collector'}) yang butuh users.view).
 *
 * Props:
 * - value / onChange      : id collector (number) atau array id bila `mode="multiple"`. Bisa langsung jadi child Form.Item.
 * - includeInactive       : sertakan collector non-aktif (untuk filter/laporan/target). Default false (input penugasan).
 * - allowClear            : default true.
 * - mode                  : undefined | 'multiple'.
 * - exclude               : array id yang disembunyikan (mis. collector asal saat reassign).
 * - placeholder           : default 'Pilih kolektor'.
 * - onCollectorChange     : (collector|collector[]|undefined) => void, menerima objek collector lengkap.
 * - sisa props diteruskan ke antd Select (className, style, disabled, size, ...).
 */
export default function CollectorSelect({
  value,
  onChange,
  includeInactive = false,
  allowClear = true,
  mode,
  exclude,
  placeholder = 'Pilih kolektor',
  onCollectorChange,
  ...rest
}) {
  const { collectors, isLoading, isFetching } = useCollectorOptions({ includeInactive });

  const options = useMemo(() => {
    const excluded = new Set((exclude || []).filter((id) => id !== null && id !== undefined).map(Number));
    return collectors
      .filter((collector) => !excluded.has(Number(collector.id)))
      .map((collector) => ({ value: collector.id, label: collectorOptionLabel(collector), collector }));
  }, [collectors, exclude]);

  function handleChange(nextValue, option) {
    onChange?.(nextValue, option);
    if (onCollectorChange) {
      onCollectorChange(Array.isArray(option) ? option.map((item) => item.collector) : option?.collector);
    }
  }

  return (
    <Select
      showSearch
      optionFilterProp="label"
      allowClear={allowClear}
      mode={mode}
      placeholder={placeholder}
      loading={isLoading || isFetching}
      notFoundContent={isLoading ? 'Memuat…' : 'Kolektor tidak ditemukan'}
      options={options}
      value={value === null ? undefined : value}
      onChange={handleChange}
      {...rest}
    />
  );
}
