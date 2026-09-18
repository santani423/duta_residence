import { useCallback, useEffect, useState } from 'react';

const MAX_REMEMBERED = 300;

function supported() {
  return typeof window !== 'undefined' && 'Notification' in window;
}

function readSeen(key) {
  try {
    const raw = localStorage.getItem(key);
    return raw === null ? null : new Set(JSON.parse(raw));
  } catch {
    return null;
  }
}

function writeSeen(key, ids) {
  try {
    localStorage.setItem(key, JSON.stringify([...ids].slice(-MAX_REMEMBERED)));
  } catch {
    // Storage may be full/blocked; worst case is one repeated OS notification.
  }
}

/**
 * Shows an OS-level notification for each newly arrived unread item while the tab is not
 * in focus, and routes a click on it to the notification's detail page.
 *
 * Duplicates are avoided three ways: ids already shown are remembered in localStorage
 * (shared by every tab of this user, so only the first tab to poll a new item shows it),
 * the very first poll only records what already exists instead of announcing the backlog,
 * and the OS-level `tag` makes the browser replace rather than stack a repeated one.
 */
export function useBrowserNotifications({ items, userKey, sourceKey, onOpen }) {
  const [permission, setPermission] = useState(() => (supported() ? Notification.permission : 'unsupported'));
  const storageKey = `gd_notif_seen_${userKey}_${sourceKey}`;

  const request = useCallback(async () => {
    if (!supported()) return 'unsupported';
    const result = await Notification.requestPermission();
    setPermission(result);
    return result;
  }, []);

  useEffect(() => {
    if (!items || !userKey) return;

    const seen = readSeen(storageKey);
    if (seen === null) {
      writeSeen(storageKey, new Set(items.map((item) => item.id)));
      return;
    }

    const fresh = items.filter((item) => item.read_status === 'unread' && !seen.has(item.id));
    if (!fresh.length) return;

    fresh.forEach((item) => seen.add(item.id));
    writeSeen(storageKey, seen);

    if (!supported() || Notification.permission !== 'granted' || document.hasFocus()) return;

    // Oldest first so the newest ends up on top of the OS notification stack.
    [...fresh].reverse().forEach((item) => {
      const popup = new Notification(item.title || 'Notifikasi baru', {
        body: item.message,
        tag: `gd-notification-${sourceKey}-${item.id}`,
        icon: '/logo-app.png',
      });
      popup.onclick = () => {
        window.focus();
        popup.close();
        onOpen(item);
      };
    });
  }, [items, storageKey, userKey, sourceKey, onOpen]);

  return { permission, supported: permission !== 'unsupported', request };
}
