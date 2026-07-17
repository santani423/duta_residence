// Trigger a browser download from a Blob fetched via axios (with the auth header already
// attached by the http interceptor) - a plain <a href> to the API would 401 since the
// backend requires a Bearer token that only axios attaches, not a normal navigation.
export function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  URL.revokeObjectURL(url);
}
