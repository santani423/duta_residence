import { Breadcrumb, Button, Space, Typography } from 'antd';
import { ReloadOutlined } from '@ant-design/icons';
import { Link, useLocation } from 'react-router-dom';
import PageHelpButton from '../help/PageHelpButton.jsx';
import { resolveHelpModule } from '../../utils/helpModules.js';

export default function PageHeader({ title, subtitle, breadcrumbs = [], extra, onRefresh, loading, helpModule }) {
  const location = useLocation();
  const items = [{ title: <Link to="/">Dashboard</Link> }, ...breadcrumbs.map((item) => ({ title: item.to ? <Link to={item.to}>{item.label}</Link> : item.label }))];
  const module = helpModule || resolveHelpModule(location.pathname);

  return (
    <div className="page-header">
      <div className="page-header-main">
        <Breadcrumb items={items} />
        <Space align="center" size="small">
          <Typography.Title level={2}>{title}</Typography.Title>
          <PageHelpButton module={module} />
        </Space>
        {subtitle ? <Typography.Paragraph type="secondary">{subtitle}</Typography.Paragraph> : null}
      </div>
      <Space wrap>
        {onRefresh ? <Button icon={<ReloadOutlined />} onClick={onRefresh} loading={loading}>Refresh</Button> : null}
        {extra}
      </Space>
    </div>
  );
}
