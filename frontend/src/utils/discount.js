// Konversi input diskon (persentase atau nominal) ke nominal rupiah, sama seperti backend
// (DiscountService::resolveManualDiscountAmount). Yang disimpan dan dihitung tetap nominal.
export const DISCOUNT_TYPE_PERCENTAGE = 'percentage';
export const DISCOUNT_TYPE_NOMINAL = 'nominal';

export function toNominalDiscount(amount, value, type) {
  const base = Number(amount) || 0;
  const input = Number(value) || 0;
  return type === DISCOUNT_TYPE_PERCENTAGE ? Math.round((base * input) / 100 * 100) / 100 : input;
}

// Diskon nominal yang sudah tersimpan -> nilai awal untuk field input sesuai tipenya.
export function fromNominalDiscount(amount, nominal, type) {
  const base = Number(amount) || 0;
  if (type !== DISCOUNT_TYPE_PERCENTAGE) return Number(nominal) || 0;
  return base > 0 ? Math.round(((Number(nominal) || 0) / base) * 10000) / 100 : 0;
}

// Batas maksimum nominal = batas persen dari pokok, dibulatkan ke sen.
export function maxNominalFromPercent(amount, percent) {
  return Math.round((Number(amount) || 0) * percent) / 100;
}

export function finalPrice(amount, nominalDiscount) {
  return Math.max(0, (Number(amount) || 0) - nominalDiscount);
}
