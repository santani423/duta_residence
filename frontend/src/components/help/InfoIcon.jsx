import { Tooltip } from 'antd';
import { InfoCircleOutlined } from '@ant-design/icons';
import { useHelpVisible } from '../../hooks/useHelpSettings.js';
import HelpPopover from './HelpPopover.jsx';

/**
 * Ikon info reusable dipakai di judul halaman, label field, tombol aksi, tabel, dsb.
 * - Kalau hanya `tooltip` diberikan: tampil sebagai Tooltip ringkas (tanpa panggilan API tambahan).
 * - Kalau `module` diberikan: tampil sebagai HelpPopover dengan tautan ke Manual Book.
 * Otomatis tersembunyi kalau pengaturan "Tampilkan Ikon Informasi" nonaktif untuk scope ini.
 */
export default function InfoIcon({ scope = {}, tooltip, module, slug, size = 14, style }) {
  const visible = useHelpVisible(scope);

  if (!visible) return null;

  const icon = (
    <InfoCircleOutlined
      style={{ fontSize: size, cursor: 'pointer', color: 'var(--gd-help-icon-color, #8c8c8c)', ...style }}
      aria-label="Bantuan"
    />
  );

  if (!module) {
    return tooltip ? <Tooltip title={tooltip}>{icon}</Tooltip> : null;
  }

  return (
    <HelpPopover scope={scope} description={tooltip} module={module} slug={slug}>
      {icon}
    </HelpPopover>
  );
}
