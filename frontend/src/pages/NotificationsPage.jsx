import NotificationInbox from '../components/notifications/NotificationInbox.jsx';
import { notificationSources } from '../components/notifications/notificationSources.js';

export default function NotificationsPage() {
  return (
    <NotificationInbox
      source={notificationSources.staff}
      title="Notifikasi"
      subtitle="Daftar notifikasi operasional dan sistem."
      breadcrumbs={[{ label: 'Notifikasi' }]}
    />
  );
}
