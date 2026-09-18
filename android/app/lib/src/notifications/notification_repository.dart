import '../api/api_client.dart';
import '../utils/formatters.dart';
import 'app_notification.dart';

/// The three backend inboxes. They are separate tables/scopes server-side but expose
/// the same verbs, so one repository serves all of them.
enum NotificationSource {
  resident('resident/notifications'),
  staff('notifications'),
  supervisor('supervisor-notifications');

  const NotificationSource(this.basePath);

  final String basePath;

  /// The inbox a signed-in account reads from, keyed by its role.
  static NotificationSource forRole(String? role) => switch (role) {
    'collector' => NotificationSource.staff,
    'supervisor' => NotificationSource.supervisor,
    _ => NotificationSource.resident,
  };
}

class NotificationPage {
  const NotificationPage({required this.items, required this.unreadCount});

  final List<AppNotification> items;
  final int unreadCount;
}

class NotificationRepository {
  const NotificationRepository(this.apiClient, this.source);

  final ApiClient apiClient;
  final NotificationSource source;

  Future<NotificationPage> list({
    int perPage = 30,
    Map<String, Object?> filters = const {},
  }) async {
    final result = await apiClient.get(
      source.basePath,
      query: {'per_page': perPage, ...filters},
    );
    // The same row can appear twice if the list shifts between paged fetches;
    // keep the first occurrence so a notification never renders (or notifies) twice.
    final seen = <String>{};
    final items = [
      for (final json in asList(result.data))
        if (seen.add(compact(asMap(json)['id'])))
          AppNotification.fromJson(json),
    ];
    final unread = result.meta?['unread_count'];
    return NotificationPage(
      items: items,
      unreadCount: unread is num
          ? unread.toInt()
          : items.where((item) => !item.isRead).length,
    );
  }

  Future<AppNotification> get(String id) async {
    final result = await apiClient.get('${source.basePath}/$id');
    return AppNotification.fromJson(result.data);
  }

  Future<AppNotification> markRead(String id) async {
    final result = await apiClient.postJson('${source.basePath}/$id/read', {});
    return AppNotification.fromJson(result.data);
  }

  Future<AppNotification> markUnread(String id) async {
    final result = await apiClient.postJson(
      '${source.basePath}/$id/unread',
      {},
    );
    return AppNotification.fromJson(result.data);
  }

  Future<void> markAllRead() async {
    await apiClient.postJson('${source.basePath}/read-all', {});
  }
}
