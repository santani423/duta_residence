import { Tooltip } from 'antd';
import { useHelpVisible } from '../../hooks/useHelpSettings.js';

/**
 * Membungkus elemen apa pun (label field, tombol, ikon status, dsb) dengan tooltip
 * penjelasan singkat. Beda dengan InfoIcon yang selalu merender ikon sendiri, komponen
 * ini dipakai saat elemen yang butuh tooltip sudah ada (mis. label atau badge status).
 */
export default function HelpTooltip({ scope = {}, text, children, placement = 'top' }) {
  const visible = useHelpVisible(scope);

  if (!visible || !text) return children;

  return <Tooltip title={text} placement={placement}>{children}</Tooltip>;
}
