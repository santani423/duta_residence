import { useMemo, useState } from 'react';
import { Empty, Input, List, Modal, Typography } from 'antd';

export default function ClusterMapAddUnitModal({ open, onClose, availableUnits, onAdd, getContainer }) {
  const [search, setSearch] = useState('');

  const filtered = useMemo(() => {
    const term = search.trim().toLowerCase();
    if (!term) return availableUnits;
    return availableUnits.filter((unit) => (
      unit.id.toLowerCase().includes(term)
      || (unit.block || '').toLowerCase().includes(term)
      || (unit.lot_number || '').toLowerCase().includes(term)
    ));
  }, [availableUnits, search]);

  return (
    <Modal
      title="Tambah Unit ke Peta"
      open={open}
      onCancel={onClose}
      footer={null}
      destroyOnHidden
      getContainer={getContainer}
      zIndex={2000}
    >
      <Input.Search
        placeholder="Cari ID unit, blok, atau kavling"
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        style={{ marginBottom: 12 }}
        allowClear
      />
      {filtered.length === 0
        ? <Empty description="Semua unit sudah ditempatkan di peta" />
        : (
          <List
            size="small"
            style={{ maxHeight: 400, overflowY: 'auto' }}
            dataSource={filtered}
            renderItem={(unit) => (
              <List.Item
                onClick={() => { onAdd(unit); onClose(); }}
                style={{ cursor: 'pointer' }}
              >
                <Typography.Text strong>{unit.id}</Typography.Text>
                &nbsp;— Blok {unit.block} / Kavling {unit.lot_number}
              </List.Item>
            )}
          />
        )}
    </Modal>
  );
}
