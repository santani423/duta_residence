export const SHAPE_TYPES = ['rect', 'circle', 'triangle', 'trapezoid', 'polygon', 'line', 'text'];

export const POLYGON_LIKE_SHAPES = ['trapezoid', 'polygon', 'line'];

export const DEFAULT_UNIT_SIZE = { width: 60, height: 90 };

export const STATUS_COLORS = {
  occupied: { fill: '#95de64', stroke: '#52c41a', label: 'Occupied' },
  ready_stock: { fill: '#69b1ff', stroke: '#1677ff', label: 'Ready Stock' },
  tanah_kosong: { fill: '#ffd666', stroke: '#d48806', label: 'Tanah Kosong' },
  tunggakan: { fill: '#ff7875', stroke: '#cf1322', label: 'Ada Tunggakan' },
};

// unitDetail.occupancy_status dihitung backend (Unit::getOccupancyStatusAttribute) dari
// tipe unit + ada/tidaknya penghuni aktif - jangan hitung ulang dari occupancy_id/status_id
// di sini supaya warna peta selalu konsisten dengan badge Status Unit di halaman lain.
export function deriveUnitColor(unitDetail) {
  if (!unitDetail) return STATUS_COLORS.ready_stock;
  if (unitDetail.has_arrears) return STATUS_COLORS.tunggakan;
  return STATUS_COLORS[unitDetail.occupancy_status] || STATUS_COLORS.ready_stock;
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
