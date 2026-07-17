import { useEffect, useState } from 'react';
import { Input, Select, Space } from 'antd';
import { residentStatusOptions, occupancyOptions } from '../../components/forms/UnitForm.jsx';

const paymentOptions = [
  { value: 'lunas', label: 'Lunas' },
  { value: 'tunggakan', label: 'Ada Tunggakan' },
];

export default function ClusterMapSearchBar({ objects, onFocusUnit, onDimmedChange }) {
  const [search, setSearch] = useState('');
  const [statusId, setStatusId] = useState();
  const [occupancyId, setOccupancyId] = useState();
  const [paymentStatus, setPaymentStatus] = useState();

  useEffect(() => {
    if (!statusId && !occupancyId && !paymentStatus) {
      onDimmedChange([]);
      return;
    }
    const dimmed = objects
      .filter((object) => {
        if (object.object_category !== 'unit') return false;
        const detail = object.unit_detail;
        if (!detail) return false;
        if (statusId && detail.status_id !== statusId) return true;
        if (occupancyId && String(detail.occupancy_id) !== occupancyId) return true;
        if (paymentStatus === 'lunas' && detail.has_arrears) return true;
        if (paymentStatus === 'tunggakan' && !detail.has_arrears) return true;
        return false;
      })
      .map((object) => object.id);
    onDimmedChange(dimmed);
  }, [statusId, occupancyId, paymentStatus, objects, onDimmedChange]);

  function handleSearch(value) {
    const term = value.trim().toLowerCase();
    if (!term) return;
    const match = objects.find((object) => {
      if (object.object_category !== 'unit') return false;
      const detail = object.unit_detail;
      return object.unit_id?.toLowerCase().includes(term)
        || detail?.block?.toLowerCase().includes(term)
        || detail?.resident_name?.toLowerCase().includes(term);
    });
    if (match) onFocusUnit(match.id);
  }

  return (
    <Space wrap style={{ padding: '8px 12px', borderBottom: '1px solid #f0f0f0' }}>
      <Input.Search
        placeholder="Cari nomor unit, blok, atau nama penghuni"
        style={{ width: 260 }}
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        onSearch={handleSearch}
        allowClear
      />
      <Select allowClear placeholder="Status Unit" options={residentStatusOptions} value={statusId} onChange={setStatusId} style={{ width: 140 }} />
      <Select allowClear placeholder="Okupansi" options={occupancyOptions} value={occupancyId} onChange={setOccupancyId} style={{ width: 140 }} />
      <Select allowClear placeholder="Status Bayar" options={paymentOptions} value={paymentStatus} onChange={setPaymentStatus} style={{ width: 150 }} />
    </Space>
  );
}
