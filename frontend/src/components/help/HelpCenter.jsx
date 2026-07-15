import { Button, Descriptions, Drawer, Empty, List, Space, Typography } from 'antd';
import {
  CompassOutlined,
  MailOutlined,
  PhoneOutlined,
  QuestionCircleOutlined,
  ReadOutlined,
} from '@ant-design/icons';
import { useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useHelpSettingsData } from '../../hooks/useHelpSettings.js';
import { useManualBookModule } from '../../hooks/useManualBook.js';
import { useHelpCenter } from '../../state/HelpCenterContext.jsx';
import { resolveHelpModule } from '../../utils/helpModules.js';

export default function HelpCenter() {
  const [open, setOpen] = useState(false);
  const location = useLocation();
  const navigate = useNavigate();
  const helpCenter = useHelpCenter();
  const { appVersion, support } = useHelpSettingsData();
  const currentModule = resolveHelpModule(location.pathname);
  const { sections: currentSections } = useManualBookModule(currentModule);
  const tour = helpCenter?.tourFor(currentModule) || helpCenter?.tourFor('general');

  function go(path) {
    setOpen(false);
    navigate(path);
  }

  return (
    <>
      <Button
        type="text"
        icon={<QuestionCircleOutlined />}
        onClick={() => setOpen(true)}
        aria-label="Pusat Bantuan"
        data-tour-id="help-center-button"
      />
      <Drawer title="Pusat Bantuan" open={open} onClose={() => setOpen(false)} width={380} destroyOnHidden>
        <Space direction="vertical" size="middle" style={{ width: '100%' }}>
          <List
            header={<Typography.Text strong>Panduan</Typography.Text>}
            bordered
            dataSource={[
              { key: 'manual-book', icon: <ReadOutlined />, label: 'Manual Book', onClick: () => go('/manual-book') },
              {
                key: 'current-page',
                icon: <ReadOutlined />,
                label: 'Panduan halaman ini',
                onClick: () => go(currentSections[0] ? `/manual-book/${currentModule}/${currentSections[0].slug}` : `/manual-book/${currentModule}`),
                disabled: currentSections.length === 0,
              },
              {
                key: 'tour',
                icon: <CompassOutlined />,
                label: 'Mulai tur halaman ini',
                onClick: () => { setOpen(false); helpCenter?.startTour(tour?.module); },
                disabled: !tour,
              },
              { key: 'faq', icon: <QuestionCircleOutlined />, label: 'FAQ', onClick: () => go('/manual-book/general/faq-kendala-umum') },
            ]}
            renderItem={(item) => (
              <List.Item>
                <Button type="text" block icon={item.icon} disabled={item.disabled} onClick={item.onClick} style={{ textAlign: 'left', justifyContent: 'flex-start' }}>
                  {item.label}
                </Button>
              </List.Item>
            )}
          />

          <div>
            <Typography.Text strong>Hubungi Administrator</Typography.Text>
            {support?.email || support?.phone ? (
              <Descriptions column={1} size="small" className="section-row">
                {support.email ? <Descriptions.Item label={<MailOutlined />}><a href={`mailto:${support.email}`}>{support.email}</a></Descriptions.Item> : null}
                {support.phone ? <Descriptions.Item label={<PhoneOutlined />}><a href={`tel:${support.phone}`}>{support.phone}</a></Descriptions.Item> : null}
              </Descriptions>
            ) : (
              <Empty
                className="section-row"
                image={Empty.PRESENTED_IMAGE_SIMPLE}
                description="Kontak admin belum diatur. Silakan hubungi admin estate Anda secara langsung untuk melaporkan kendala."
              />
            )}
          </div>

          <div>
            <Typography.Text strong>Informasi Aplikasi</Typography.Text>
            <Descriptions column={1} size="small" className="section-row">
              <Descriptions.Item label="Versi">{appVersion || '-'}</Descriptions.Item>
            </Descriptions>
          </div>
        </Space>
      </Drawer>
    </>
  );
}
