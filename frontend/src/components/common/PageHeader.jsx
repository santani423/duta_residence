import { Breadcrumb, Button, Space, Typography } from 'antd';
import { ReloadOutlined } from '@ant-design/icons';
import { Link } from 'react-router-dom';

export default function PageHeader({ title, subtitle, breadcrumbs = [], extra, onRefresh, loading }) {
  const items = [{ title: <Link to="/">Dashboard</Link> }, ...breadcrumbs.map((item) => ({ title: item.to ? <Link to={item.to}>{item.label}</Link> : item.label }))];

  return (
    <div className="page-header">
      <div className="page-header-main">
        <Breadcrumb items={items} />
        <Typography.Title level={2}>{title}</Typography.Title>
        {subtitle ? <Typography.Paragraph type="secondary">{subtitle}</Typography.Paragraph> : null}
      </div>
      <Space wrap>
        {onRefresh ? <Button icon={<ReloadOutlined />} onClick={onRefresh} loading={loading}>Refresh</Button> : null}
        {extra}
      </Space>
    </div>
  );
}
