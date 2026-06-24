import { Button, Card, Drawer, Grid, Space } from 'antd';
import { FilterOutlined } from '@ant-design/icons';
import { useState } from 'react';

export default function FilterBar({ children, extra }) {
  const screens = Grid.useBreakpoint();
  const [open, setOpen] = useState(false);
  const content = <div className="filter-grid">{children}</div>;

  if (!screens.md) {
    return (
      <div className="filter-mobile">
        <Space wrap>
          <Button icon={<FilterOutlined />} onClick={() => setOpen(true)}>Filter</Button>
          {extra}
        </Space>
        <Drawer title="Filter" open={open} onClose={() => setOpen(false)} placement="bottom" height="auto">
          {content}
        </Drawer>
      </div>
    );
  }

  return (
    <Card className="filter-card">
      <Space align="start" wrap className="filter-space">
        {content}
        {extra}
      </Space>
    </Card>
  );
}
