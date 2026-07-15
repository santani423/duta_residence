import { Popover, Space, Typography } from 'antd';
import { useHelpVisible } from '../../hooks/useHelpSettings.js';
import ManualBookLink from './ManualBookLink.jsx';

/**
 * Popover bantuan yang lebih lengkap dari Tooltip: judul, deskripsi, tautan ke Manual Book,
 * dan slot opsional untuk tombol "Mulai Tur". Dipakai langsung atau lewat InfoIcon/PageHelpButton.
 */
export default function HelpPopover({ scope = {}, title = 'Bantuan', description, module, slug, tourButton, children, trigger = 'click' }) {
  const visible = useHelpVisible(scope);

  if (!visible) return children || null;

  return (
    <Popover
      trigger={trigger}
      title={title}
      content={(
        <Space direction="vertical" size="small" style={{ maxWidth: 280 }}>
          {description ? <Typography.Text>{description}</Typography.Text> : null}
          {module ? <ManualBookLink module={module} slug={slug} /> : null}
          {tourButton || null}
        </Space>
      )}
    >
      {children}
    </Popover>
  );
}
