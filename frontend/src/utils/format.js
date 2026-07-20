import dayjs from 'dayjs';

export function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(value || 0));
}

export function formatDate(value, fallback = '-') {
  if (!value) return fallback;
  const parsed = dayjs(value);
  return parsed.isValid() ? parsed.format('DD MMM YYYY') : fallback;
}

export function formatDateTime(value, fallback = '-') {
  if (!value) return fallback;
  const parsed = dayjs(value);
  return parsed.isValid() ? parsed.format('DD MMM YYYY HH:mm') : fallback;
}

export function formatPeriod(year, month) {
  if (!year || !month) return '-';
  return dayjs(`${year}-${String(month).padStart(2, '0')}-01`).format('MMMM YYYY');
}

export function compactText(value, fallback = '-') {
  if (value === null || value === undefined || value === '') return fallback;
  return value;
}

const NOTIFICATION_TYPE_LABELS = {
  emergency_alert: 'Emergency Alert',
};

export function formatNotificationType(value, fallback = '-') {
  if (!value) return fallback;
  if (NOTIFICATION_TYPE_LABELS[value]) return NOTIFICATION_TYPE_LABELS[value];
  return String(value).replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}
