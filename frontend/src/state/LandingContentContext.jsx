import { createContext, useContext } from 'react';

const LandingContentContext = createContext(null);

export function LandingContentProvider({ value, children }) {
  return <LandingContentContext.Provider value={value || null}>{children}</LandingContentContext.Provider>;
}

// Returns the cached CMS aggregate payload (or null while it's still
// loading / if the request failed) - every landing section reads its own
// slice from this and falls back to its local constant when empty.
export function useLandingContent() {
  return useContext(LandingContentContext);
}
