import { api } from '../../services/estateApi.js';

// One config per notification inbox. The staff bell, the resident portal and the
// supervisor center are three different backend inboxes with identical payloads, so the
// list item / detail page take a `source` instead of hard-coding endpoints.
export const notificationSources = {
  staff: {
    key: 'staff',
    queryKey: 'notifications',
    listPath: '/notifications',
    detailPath: (id) => `/notifications/${id}`,
    api: {
      list: api.notifications.list,
      get: api.notifications.get,
      read: api.notifications.read,
      unread: api.notifications.unread,
      readAll: api.notifications.readAll,
    },
  },
  resident: {
    key: 'resident',
    queryKey: 'resident-notifications',
    listPath: '/resident/notifications',
    detailPath: (id) => `/resident/notifications/${id}`,
    api: {
      list: api.resident.notifications,
      get: api.resident.notification,
      read: api.resident.readNotification,
      unread: api.resident.unreadNotification,
      readAll: api.resident.readAllNotifications,
    },
  },
  supervisor: {
    key: 'supervisor',
    queryKey: 'supervisor-notifications',
    listPath: '/supervisor/notifications',
    detailPath: (id) => `/supervisor/notifications/${id}`,
    api: {
      list: api.supervisorNotifications.list,
      get: api.supervisorNotifications.get,
      read: api.supervisorNotifications.markRead,
      unread: api.supervisorNotifications.markUnread,
      readAll: api.supervisorNotifications.markAllRead,
    },
  },
};

/** The inbox behind the header bell for the signed-in account. */
export function bellSourceFor(hasRole) {
  return hasRole('customer') ? notificationSources.resident : notificationSources.staff;
}
