export function getApiErrorMessage(error, fallback = 'Terjadi kesalahan. Silakan coba lagi.') {
  if (!error) return fallback;
  if (typeof error === 'string') return error;
  if (error.message) return error.message;
  if (error.errors) {
    const first = Object.values(error.errors).flat()[0];
    if (first) return first;
  }
  return fallback;
}

export function mapValidationErrors(error) {
  if (!error?.errors) return [];
  return Object.entries(error.errors).map(([name, messages]) => ({
    name,
    errors: Array.isArray(messages) ? messages : [String(messages)],
  }));
}
