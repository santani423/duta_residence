export const SHAPE_TYPES = ['rect', 'circle', 'triangle', 'trapezoid', 'polygon', 'line', 'text'];

export const POLYGON_LIKE_SHAPES = ['trapezoid', 'polygon', 'line'];

export const DEFAULT_UNIT_SIZE = { width: 60, height: 90 };

export const STATUS_COLORS = {
  dihuni: { fill: '#95de64', stroke: '#52c41a', label: 'Dihuni' },
  kosong: { fill: '#69b1ff', stroke: '#1677ff', label: 'Kosong / Tersedia' },
  tunggakan: { fill: '#ff7875', stroke: '#cf1322', label: 'Ada Tunggakan' },
  nonaktif: { fill: '#bfbfbf', stroke: '#595959', label: 'Belum Aktif' },
};

export function deriveUnitColor(unitDetail) {
  if (!unitDetail) return STATUS_COLORS.kosong;
  if (unitDetail.has_arrears) return STATUS_COLORS.tunggakan;
  if (String(unitDetail.occupancy_id) === '1') return STATUS_COLORS.dihuni;
  if (String(unitDetail.occupancy_id) === '2') return STATUS_COLORS.kosong;
  if (['RK', 'TA'].includes(unitDetail.status_id)) return STATUS_COLORS.nonaktif;
  return STATUS_COLORS.kosong;
}

export function objectColor(object) {
  if (object.object_category === 'unit' && object.is_color_auto !== false) {
    const derived = deriveUnitColor(object.unit_detail);
    return { fill: derived.fill, stroke: derived.stroke };
  }
  return { fill: object.fill_color || '#91caff', stroke: object.stroke_color || '#1677ff' };
}

export function createUuid() {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID();
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

export function defaultPointsFor(shapeType, width, height) {
  const w = width || 100;
  const h = height || 60;
  if (shapeType === 'trapezoid') {
    return [w * 0.25, 0, w * 0.75, 0, w, h, 0, h];
  }
  if (shapeType === 'line') {
    return [0, 0, w, 0];
  }
  // generic polygon default: simple rectangle-ish quad, editable afterwards
  return [0, 0, w, 0, w, h, 0, h];
}

export function unitSizeFromArea(landArea, scaleMetersPerPixel) {
  if (!landArea || !scaleMetersPerPixel) return DEFAULT_UNIT_SIZE;
  const sideMeters = Math.sqrt(Number(landArea));
  const sidePixels = sideMeters / scaleMetersPerPixel;
  return { width: Math.max(20, Math.round(sidePixels)), height: Math.max(20, Math.round(sidePixels * 1.3)) };
}

export function cleanObjectForSave(object) {
  const {
    id, object_category, unit_id, component_type_id, shape_type,
    x, y, width, height, rotation, points, fill_color, stroke_color,
    stroke_width, opacity, layer_order, is_locked, is_color_auto,
    label_text, group_id, metadata,
  } = object;

  return {
    id, object_category, unit_id, component_type_id, shape_type,
    x, y, width, height, rotation, points, fill_color, stroke_color,
    stroke_width, opacity, layer_order, is_locked, is_color_auto,
    label_text, group_id, metadata,
  };
}
