import {
  LogoutOutlined,
  MenuFoldOutlined,
  MenuOutlined,
  MenuUnfoldOutlined,
  MoonOutlined,
  SettingOutlined,
  SunOutlined,
  UserOutlined,
} from '@ant-design/icons';
import { Avatar, Button, Drawer, Dropdown, Grid, Layout, Menu, Select, Space, Typography, theme } from 'antd';
import { createElement, useMemo, useState } from 'react';
import { Outlet, useLocation, useNavigate } from 'react-router-dom';
import { menuItems } from '../constants/permissions.js';
import { useAuth } from '../state/AuthContext.jsx';
import { useThemeMode } from '../state/ThemeContext.jsx';
import { HelpCenterProvider } from '../state/HelpCenterContext.jsx';
import NotificationBell from './layout/NotificationBell.jsx';
import EmergencyAlertWatcher from './layout/EmergencyAlertWatcher.jsx';
import HelpCenter from './help/HelpCenter.jsx';

const { Header, Sider, Content } = Layout;

function buildMenuItems(canAny, hasRole) {
  return menuItems
    .filter((item) => !item.roles?.length || item.roles.some((role) => hasRole(role)))
    .filter((item) => item.permissions.length === 0 || canAny(item.permissions))
    .map((item) => ({
      key: item.key,
      label: item.label,
      icon: item.icon ? createElement(item.icon) : null,
    }));
}

export default function AppShell() {
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const { token } = theme.useToken();
  const screens = Grid.useBreakpoint();
  const isMobile = !screens.lg;
  const { user, canAny, hasRole, logout } = useAuth();
  const { mode, setMode } = useThemeMode();
  const navigate = useNavigate();
  const location = useLocation();
  const items = useMemo(() => buildMenuItems(canAny, hasRole), [canAny, hasRole]);
  const selectedKey = items.find((item) => item.key !== '/' && location.pathname.startsWith(item.key))?.key || '/';

  const profileItems = [
    { key: hasRole('customer') ? '/resident/profile' : '/profile', icon: <UserOutlined />, label: 'Profil' },
    { key: hasRole('customer') ? '/resident/settings' : '/change-password', icon: <SettingOutlined />, label: hasRole('customer') ? 'Pengaturan' : 'Ganti Password' },
    { type: 'divider' },
    { key: 'logout', icon: <LogoutOutlined />, label: 'Logout' },
  ];

  function onMenuClick({ key }) {
    setMobileOpen(false);
    navigate(key);
  }

  async function onProfileClick({ key }) {
    if (key === 'logout') {
      await logout();
      navigate('/login');
      return;
    }
    navigate(key);
  }

  const navigation = (
    <>
      <div className="brand">
        <span className="brand-mark">GD</span>
        {!collapsed || isMobile ? <span>Grand Duta</span> : null}
      </div>
      <Menu mode="inline" selectedKeys={[selectedKey]} items={items} onClick={onMenuClick} data-tour-id="sidebar-menu" />
    </>
  );

  return (
    <HelpCenterProvider>
      <EmergencyAlertWatcher />
      <Layout className="app-shell">
        {!isMobile ? (
          <Sider width={250} collapsible collapsed={collapsed} trigger={null} className="app-sider">
            {navigation}
          </Sider>
        ) : (
          <Drawer open={mobileOpen} onClose={() => setMobileOpen(false)} placement="left" width={280} className="mobile-nav" title={null}>
            {navigation}
          </Drawer>
        )}
        <Layout>
          <Header className="app-header" style={{ background: token.colorBgContainer }}>
            <Space>
              {isMobile ? (
                <Button type="text" icon={<MenuOutlined />} onClick={() => setMobileOpen(true)} aria-label="Buka menu" />
              ) : (
                <Button
                  type="text"
                  icon={collapsed ? <MenuUnfoldOutlined /> : <MenuFoldOutlined />}
                  onClick={() => setCollapsed((value) => !value)}
                  aria-label="Toggle sidebar"
                />
              )}
              <div>
                <Typography.Text strong>Estate Management</Typography.Text>
              </div>
            </Space>
            <Space className="header-actions">
              <Select
                value={mode}
                onChange={setMode}
                className="theme-select"
                options={[
                  { value: 'light', label: <Space><SunOutlined />Light</Space> },
                  { value: 'dark', label: <Space><MoonOutlined />Dark</Space> },
                  { value: 'system', label: 'System' },
                ]}
              />
              <span data-tour-id="notifications-bell"><NotificationBell /></span>
              <HelpCenter />
              <Dropdown menu={{ items: profileItems, onClick: onProfileClick }} placement="bottomRight">
                <Button className="profile-button" data-tour-id="profile-menu">
                  <Avatar size="small" icon={<UserOutlined />} />
                  <span className="profile-name">{user?.name}</span>
                </Button>
              </Dropdown>
            </Space>
          </Header>
          <Content className="app-content">
            <Outlet />
          </Content>
        </Layout>
      </Layout>
    </HelpCenterProvider>
  );
}
