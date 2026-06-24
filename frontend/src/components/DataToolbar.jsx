import { Button, Input, Space } from 'antd';
import { PlusOutlined, ReloadOutlined, SearchOutlined } from '@ant-design/icons';

export default function DataToolbar({ search, onSearch, onReload, onCreate, canCreate }) {
  return (
    <Space className="data-toolbar" wrap>
      <Input
        allowClear
        prefix={<SearchOutlined />}
        placeholder="Cari data"
        value={search}
        onChange={(event) => onSearch(event.target.value)}
        style={{ width: 260 }}
      />
      <Button icon={<ReloadOutlined />} onClick={onReload} />
      {canCreate ? <Button type="primary" icon={<PlusOutlined />} onClick={onCreate}>Tambah</Button> : null}
    </Space>
  );
}
