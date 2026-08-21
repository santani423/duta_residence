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

// Show a Blob (e.g. a PDF, for viewing/printing) in a tab opened via window.open.
// Open the tab synchronously in the click handler and pass its handle here once the
// blob resolves - opening it only after the await would get blocked as a popup in
// most browsers since it's no longer inside the synchronous user-gesture call stack.
export function openBlobInWindow(target, blob) {
  if (!target) return;
  target.location.href = URL.createObjectURL(blob);
}
