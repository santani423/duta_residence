// Decides where a notification's "open related item" button goes. The backend sends a
// client-agnostic `reference` ({ resource, id, available }); this maps it onto this web
// app's routes for the inbox it came from. Returning null means "nothing to open" and the
// detail page simply shows the notification on its own.
//
// Only a subset of resources have a dedicated web page - the rest fall back to the
// closest list page the user is allowed to see, never to a route that would 403.
const ROUTES = {
  staff: {
    payment: { to: () => '/payments', permission: 'payments.view' },
    invoice: { to: () => '/billings', permission: 'billings.view' },
    cluster_rate_schedule: { to: () => '/clusters', permission: 'clusters.view' },
    approval: { to: () => '/supervisor/approvals', permission: 'approvals.view' },
    payment_scheme: { to: (ref) => `/payment-schemes?openId=${ref.id}`, permission: 'payment-schemes.view' },
  },
  resident: {
    payment: { to: (ref) => `/resident/payments/${ref.id}` },
    invoice: { to: (ref) => `/resident/invoices/${ref.id}` },
    receipt: { to: () => '/resident/documents' },
    complaint: { to: () => '/resident/complaints' },
  },
  supervisor: {
    payment: { to: () => '/payments', permission: 'payments.view' },
    approval: { to: () => '/supervisor/approvals', permission: 'approvals.view' },
    collector: { to: (_ref, notification) => (notification.related_collector ? `/supervisor/collectors/${notification.related_collector.id}` : null), permission: 'collector-monitoring.view' },
  },
};

export function resolveNotificationTarget(notification, sourceKey, can) {
  const reference = notification?.reference;
  if (!reference?.available) return null;

  const route = ROUTES[sourceKey]?.[reference.resource];
  if (!route) return null;
  if (route.permission && !can(route.permission)) return null;

  const to = route.to(reference, notification);
  return to ? { to, label: `Buka ${reference.resource_label}` } : null;
}
