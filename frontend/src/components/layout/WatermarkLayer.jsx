import { useEffect, useRef, useState } from 'react';

const POSITION_STYLES = {
  'top-left': { justifyContent: 'flex-start', alignItems: 'flex-start' },
  'top-center': { justifyContent: 'center', alignItems: 'flex-start' },
  'top-right': { justifyContent: 'flex-end', alignItems: 'flex-start' },
  center: { justifyContent: 'center', alignItems: 'center' },
  'bottom-left': { justifyContent: 'flex-start', alignItems: 'flex-end' },
  'bottom-center': { justifyContent: 'center', alignItems: 'flex-end' },
  'bottom-right': { justifyContent: 'flex-end', alignItems: 'flex-end' },
};

function WatermarkMark({ config }) {
  const opacity = (config.opacity ?? 30) / 100;

  if (config.type === 'image') {
    return (
      <img
        src={config.imageUrl}
        alt=""
        draggable={false}
        style={{ width: config.size, height: 'auto', maxWidth: 'none', opacity, objectFit: 'contain', userSelect: 'none' }}
      />
    );
  }

  return (
    <span
      style={{
        fontSize: Math.max(10, Math.round((config.size || 200) * 0.16)),
        lineHeight: 1.1,
        fontWeight: 600,
        color: '#000',
        opacity,
        whiteSpace: 'nowrap',
        userSelect: 'none',
      }}
    >
      {config.textContent || ''}
    </span>
  );
}

/**
 * Pure presentational watermark overlay - shared by the global app-wide
 * overlay (WatermarkOverlay.jsx) and the admin settings page's live preview,
 * so both render identically from the same config shape. Renders as an
 * absolutely-positioned, `pointer-events: none` layer filling its nearest
 * positioned ancestor: the caller decides whether that ancestor is the whole
 * viewport (fixed) or a bounded preview box (relative).
 */
export default function WatermarkLayer({ config, style }) {
  const containerRef = useRef(null);
  const [tileCount, setTileCount] = useState({ cols: 0, rows: 0 });
  const isMultiple = config?.mode === 'multiple';
  const size = config?.size || 200;
  const spacing = config?.spacing ?? 150;

  useEffect(() => {
    if (!config?.enabled || !isMultiple) return undefined;
    const el = containerRef.current;
    if (!el) return undefined;

    function recalc() {
      const rect = el.getBoundingClientRect();
      const step = Math.max(1, size + spacing);
      setTileCount({
        cols: Math.max(1, Math.ceil(rect.width / step) + 1),
        rows: Math.max(1, Math.ceil(rect.height / step) + 1),
      });
    }

    recalc();
    const observer = new ResizeObserver(recalc);
    observer.observe(el);
    window.addEventListener('resize', recalc);
    return () => {
      observer.disconnect();
      window.removeEventListener('resize', recalc);
    };
  }, [config?.enabled, isMultiple, size, spacing]);

  if (!config?.enabled) return null;
  if (config.type === 'text' && !config.textContent) return null;
  if (config.type === 'image' && !config.imageUrl) return null;

  return (
    <div
      ref={containerRef}
      aria-hidden="true"
      style={{
        position: 'absolute',
        inset: 0,
        overflow: 'hidden',
        pointerEvents: 'none',
        display: 'flex',
        ...(isMultiple
          ? { flexWrap: 'wrap', alignContent: 'flex-start', justifyContent: 'flex-start' }
          : POSITION_STYLES[config.position] || POSITION_STYLES['bottom-right']),
        ...style,
      }}
    >
      {isMultiple ? (
        Array.from({ length: tileCount.cols * tileCount.rows }).map((_, index) => (
          <div
            key={index}
            style={{
              width: size,
              margin: spacing / 2,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              flexShrink: 0,
            }}
          >
            <WatermarkMark config={config} />
          </div>
        ))
      ) : (
        <div style={{ padding: 24 }}>
          <WatermarkMark config={config} />
        </div>
      )}
    </div>
  );
}
