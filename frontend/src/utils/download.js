// Trigger a browser download from a Blob fetched via axios (with the auth header already
// attached by the http interceptor) - a plain <a href> to the API would 401 since the
// backend requires a Bearer token that only axios attaches, not a normal navigation.
export function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = filename;
  // Firefox only honours click() on anchors that are attached to the document.
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  // Revoking straight away can cancel the download in Safari/Firefox.
  setTimeout(() => URL.revokeObjectURL(url), 60000);
}

// Show a Blob (e.g. a PDF, for viewing/printing) in a tab opened via window.open.
// Open the tab synchronously in the click handler and pass its handle here once the
// blob resolves - opening it only after the await would get blocked as a popup in
// most browsers since it's no longer inside the synchronous user-gesture call stack.
// If the browser blocked the tab anyway (target is null) the file is downloaded instead,
// so the click never ends in silence.
export function openBlobInWindow(target, blob, fallbackFilename = 'dokumen.pdf') {
  if (!target) {
    downloadBlob(blob, fallbackFilename);
    return;
  }
  const url = URL.createObjectURL(blob);
  target.location.href = url;
  setTimeout(() => URL.revokeObjectURL(url), 60000);
}

// Fetch a PDF through axios (so the Bearer token is attached) and open it in a new tab.
// `request` must resolve to a Blob (the http interceptor already unwraps response.data).
// Errors are re-thrown after closing the blank tab, so callers only need a try/catch.
export async function printPdf(request, fallbackFilename) {
  const printWindow = window.open('', '_blank');
  try {
    openBlobInWindow(printWindow, await request(), fallbackFilename);
  } catch (error) {
    printWindow?.close();
    throw error;
  }
}
