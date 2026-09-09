import { useMemo } from 'react';
import { useWatermarkSettings } from '../../hooks/useWatermarkSettings.js';
import { cmsImageUrl } from '../../utils/cmsMedia.js';
import WatermarkLayer from './WatermarkLayer.jsx';

// Mounted once in AppShell so every route rendered inside the main app
// layout picks up the global watermark automatically - pages never configure
// it individually. Sits below Ant Design's overlay layers (modal/dropdown/
// tooltip all start at z-index 1000+) and is fully `pointer-events: none`,
// so it never intercepts clicks, hovers, drag & drop, or scrolling.
export default function WatermarkOverlay() {
  const { settings } = useWatermarkSettings();

  const config = useMemo(() => {
    if (!settings) return null;
    return {
      enabled: settings.enabled,
      type: settings.type,
      textContent: settings.text_content,
      imageUrl: cmsImageUrl(settings.media),
      opacity: settings.opacity,
      mode: settings.mode,
      size: settings.size,
      position: settings.position,
      spacing: settings.spacing,
    };
  }, [settings]);

  if (!config) return null;

  return <WatermarkLayer config={config} style={{ position: 'fixed', zIndex: 40 }} />;
}
