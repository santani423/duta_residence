import { Input, Select, Space } from 'antd';
import { useEffect, useState } from 'react';
import { useDebounce } from '../../hooks/useDebounce.js';
import { useBlockOptions, useClusterOptions, useUnitOptions } from '../../hooks/useUnitLookups.js';

// Input teks yang baru mengirim nilai setelah pengguna berhenti mengetik, supaya daftar tidak di-query per huruf.
function DebouncedInput({ value, onChange, ...props }) {
  const [text, setText] = useState(value || '');
  const [syncedValue, setSyncedValue] = useState(value);
  const debounced = useDebounce(text);

  // Nilai diubah dari luar (mis. filter di-reset): tampilkan nilai baru itu. Nilai yang baru saja
  // dikirim input ini sendiri tidak ditimpa, supaya ketikan lanjutan tidak hilang.
  if (value !== syncedValue) {
    setSyncedValue(value);
    if ((value || '') !== (debounced || '')) setText(value || '');
  }

  useEffect(() => {
    if ((debounced || undefined) !== (value || undefined)) onChange(debounced || undefined);
  }, [debounced]); // eslint-disable-line react-hooks/exhaustive-deps

  return <Input allowClear value={text} onChange={(event) => setText(event.target.value)} {...props} />;
}

/**
 * Kontrol filter untuk FilterBar. `value` = objek filter tabel, `onChange` menerima objek filter baru.
 * Mengganti Cluster mengosongkan Blok & Unit; mengganti Blok mengosongkan Unit.
 * `hide` menyembunyikan kontrol tertentu, mis. ['unit_id'] bila unit dipilih di form terpisah.
 */
export function UnitFilterFields({ value = {}, onChange, hide = [] }) {
  const clusters = useClusterOptions();
  const blocks = useBlockOptions(value.cluster_id);
  const units = useUnitOptions({ cluster_id: value.cluster_id, block: value.block, customer: value.customer, address: value.address });
  const update = (patch) => onChange({ ...value, ...patch });
  const shown = (key) => !hide.includes(key);

  return (
    <>
      {shown('cluster_id') ? (
        <Select
          allowClear
          showSearch
          optionFilterProp="label"
          placeholder="Cluster"
          value={value.cluster_id}
          onChange={(next) => update({ cluster_id: next, block: undefined, unit_id: undefined })}
          options={clusters.options}
          loading={clusters.loading}
          className="filter-input"
        />
      ) : null}
      {shown('block') ? (
        <Select
          allowClear
          showSearch
          optionFilterProp="label"
          placeholder={value.cluster_id ? 'Blok' : 'Blok (semua cluster)'}
          value={value.block}
          onChange={(next) => update({ block: next, unit_id: undefined })}
          options={blocks.options}
          loading={blocks.loading}
          notFoundContent={blocks.notFoundContent}
          className="filter-input"
        />
      ) : null}
      {shown('unit_id') ? (
        <Select
          allowClear
          showSearch
          filterOption={false}
          placeholder="Unit"
          value={value.unit_id}
          onChange={(next) => update({ unit_id: next })}
          onSearch={units.onSearch}
          options={units.options}
          loading={units.loading}
          notFoundContent={units.loading ? 'Mencari...' : 'Tidak ditemukan'}
          popupMatchSelectWidth={360}
          className="filter-input"
        />
      ) : null}
      {shown('customer') ? (
        <DebouncedInput placeholder="Customer (nama penghuni)" value={value.customer} onChange={(next) => update({ customer: next })} className="filter-input" />
      ) : null}
      {shown('address') ? (
        <DebouncedInput placeholder="Alamat (cluster/blok/no, mis. A/12)" value={value.address} onChange={(next) => update({ address: next })} className="filter-input" />
      ) : null}
    </>
  );
}

/**
 * Pemilih unit untuk form (dipakai sebagai child Form.Item): Cluster → Blok mempersempit daftar unit,
 * dan kolom unit bisa dicari berdasarkan ID, nama customer, atau alamat.
 */
export function UnitPicker({ value, onChange, statusId, placeholder = 'Cari ID unit, customer, atau alamat' }) {
  const [clusterId, setClusterId] = useState(undefined);
  const [block, setBlock] = useState(undefined);
  const clusters = useClusterOptions();
  const blocks = useBlockOptions(clusterId);
  const units = useUnitOptions({ cluster_id: clusterId, block, status_id: statusId });

  return (
    <Space.Compact style={{ width: '100%' }}>
      <Select
        allowClear
        showSearch
        optionFilterProp="label"
        placeholder="Cluster"
        value={clusterId}
        onChange={(next) => {
          setClusterId(next);
          setBlock(undefined);
          onChange?.(undefined);
        }}
        options={clusters.options}
        loading={clusters.loading}
        style={{ width: '28%' }}
      />
      <Select
        allowClear
        showSearch
        optionFilterProp="label"
        placeholder="Blok"
        value={block}
        onChange={(next) => {
          setBlock(next);
          onChange?.(undefined);
        }}
        options={blocks.options}
        loading={blocks.loading}
        notFoundContent={blocks.notFoundContent}
        style={{ width: '22%' }}
      />
      <Select
        showSearch
        allowClear
        filterOption={false}
        placeholder={placeholder}
        value={value}
        onChange={onChange}
        onSearch={units.onSearch}
        options={units.options}
        loading={units.loading}
        notFoundContent={units.loading ? 'Mencari...' : 'Tidak ditemukan'}
        popupMatchSelectWidth={360}
        style={{ width: '50%' }}
      />
    </Space.Compact>
  );
}
