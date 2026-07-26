// residents.phone is free-text with no format validation today, so normalize before
// building a wa.me link (which requires E.164-ish digits with country code, no leading 0).
export function normalizeWhatsAppPhone(phone) {
  const digits = String(phone || '').replace(/\D/g, '');
  if (!digits) return null;
  if (digits.startsWith('62')) return digits;
  if (digits.startsWith('0')) return `62${digits.slice(1)}`;
  return `62${digits}`;
}

export function buildWhatsAppLink(phone, message) {
  const normalized = normalizeWhatsAppPhone(phone);
  if (!normalized) return null;
  return `https://wa.me/${normalized}?text=${encodeURIComponent(message)}`;
}
