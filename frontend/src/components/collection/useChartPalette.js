import { theme } from 'antd';
import { useMemo } from 'react';
import { useThemeMode } from '../../state/ThemeContext.jsx';

// Urutan hue kategorikal (tetap, jangan diputar ulang). Diambil dari preset palette token antd
// sehingga ikut di-generate ulang oleh darkAlgorithm. Langkah warna dipilih terpisah untuk
// light/dark dan sudah divalidasi (lightness band, chroma, separasi CVD & normal, kontras >= 3:1).
const SERIES_STEPS = {
  light: [['cyan', 7], ['geekblue', 6], ['orange', 7], ['purple', 6], ['magenta', 7], ['green', 7]],
  dark: [['cyan', 6], ['geekblue', 7], ['orange', 6], ['purple', 7], ['magenta', 7], ['green', 6]],
};

// Ramp sekuensial satu hue (volcano) untuk bucket umur tunggakan: makin tua makin tegas.
// Pada darkAlgorithm indeks lebih tinggi = lebih terang, jadi indeks yang sama tetap "makin tegas".
const AGING_STEPS = [3, 4, 5, 6, 7, 8];
const AGING_BUCKETS = ['current', '1_30', '31_60', '61_90', '91_180', '180_plus'];

/**
 * Palet chart yang aman untuk dark mode, seluruhnya dari token antd (tanpa hex hardcode di halaman).
 *
 * @returns {{
 *   isDark: boolean,
 *   series: string[],
 *   seriesColor: (index: number) => string,
 *   status: { good: string, warning: string, serious: string, critical: string, neutral: string, info: string },
 *   priority: { critical: string, high: string, medium: string, normal: string },
 *   aging: Record<string, string>,
 *   agingRamp: string[],
 *   grid: string, axis: string, text: string, textSecondary: string, surface: string, cursor: string,
 *   axisTick: { fill: string, fontSize: number },
 *   tooltip: { contentStyle: object, labelStyle: object, itemStyle: object, cursor: object },
 * }}
 */
export function useChartPalette() {
  const { token } = theme.useToken();
  const isDark = useThemeMode()?.effectiveMode === 'dark';

  return useMemo(() => {
    const pick = (hue, step, fallback) => token[`${hue}${step}`] || token[`${hue}-${step}`] || fallback;
    const series = SERIES_STEPS[isDark ? 'dark' : 'light'].map(([hue, step]) => pick(hue, step, token.colorPrimary));
    const agingRamp = AGING_STEPS.map((step) => pick('volcano', step, token.colorError));
    const status = {
      good: token.colorSuccess,
      warning: token.colorWarning,
      serious: pick('volcano', 6, token.colorError),
      critical: token.colorError,
      info: token.colorInfo,
      neutral: token.colorTextQuaternary,
    };

    return {
      isDark,
      series,
      seriesColor: (index) => series[((index % series.length) + series.length) % series.length],
      status,
      priority: {
        critical: status.critical,
        high: status.serious,
        medium: status.warning,
        normal: status.neutral,
      },
      aging: Object.fromEntries(AGING_BUCKETS.map((bucket, index) => [bucket, agingRamp[index]])),
      agingRamp,
      grid: token.colorBorderSecondary,
      axis: token.colorTextSecondary,
      text: token.colorText,
      textSecondary: token.colorTextSecondary,
      surface: token.colorBgContainer,
      cursor: token.colorFillTertiary,
      axisTick: { fill: token.colorTextSecondary, fontSize: 12 },
      tooltip: {
        contentStyle: {
          background: token.colorBgElevated,
          border: `1px solid ${token.colorBorder}`,
          borderRadius: token.borderRadius,
          color: token.colorText,
          boxShadow: token.boxShadowSecondary,
        },
        labelStyle: { color: token.colorText, fontWeight: 600 },
        itemStyle: { color: token.colorText },
        cursor: { fill: token.colorFillTertiary },
      },
    };
  }, [token, isDark]);
}

export default useChartPalette;
