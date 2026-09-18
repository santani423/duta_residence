import '../utils/formatters.dart';

/// What a notification points at, as decided by the backend
/// (`NotificationPresenter::reference`). [resource] is a client-agnostic key such
/// as `payment` or `invoice`; [available] is false when the record was deleted.
class NotificationReference {
  const NotificationReference({
    required this.resource,
    required this.resourceLabel,
    required this.id,
    required this.available,
    this.message,
  });

  final String resource;
  final String resourceLabel;
  final String id;
  final bool available;
  final String? message;

  static NotificationReference? fromJson(Object? json) {
    final map = asMap(json);
    if (map.isEmpty) return null;
    return NotificationReference(
      resource: compact(map['resource']),
      resourceLabel: compact(map['resource_label']),
      id: compact(map['id']),
      available: map['available'] == true,
      message: map['message']?.toString(),
    );
  }
}

/// One notification from any of the three inboxes (resident, staff/collector,
/// supervisor). All of them share this payload shape.
class AppNotification {
  const AppNotification({
    required this.id,
    required this.title,
    required this.message,
    required this.type,
    required this.category,
    required this.categoryLabel,
    required this.isRead,
    required this.createdAt,
    required this.raw,
    this.senderName,
    this.unitId,
    this.reference,
  });

  final String id;
  final String title;
  final String message;
  final String type;
  final String category;
  final String categoryLabel;
  final bool isRead;
  final String? createdAt;
  final String? senderName;
  final String? unitId;
  final NotificationReference? reference;

  /// Untouched payload, for source-specific fields (supervisor priority, ...).
  final Map<String, dynamic> raw;

  factory AppNotification.fromJson(Object? json) {
    final map = asMap(json);
    final read =
        map['is_read'] == true ||
        map['read_status'] == 'read' ||
        (map['read_status'] == null && map['read_at'] != null);
    return AppNotification(
      id: compact(map['id']),
      title: compact(map['title'] ?? titleCaseStatus(map['type'])),
      message: compact(map['message'] ?? map['body'] ?? map['description']),
      type: compact(map['type']),
      category: compact(map['category']),
      categoryLabel: compact(map['category_label'] ?? map['category']),
      isRead: read,
      createdAt: map['created_at']?.toString(),
      senderName: asMap(map['sender'])['name']?.toString(),
      unitId: map['unit_id']?.toString(),
      reference: NotificationReference.fromJson(map['reference']),
      raw: map,
    );
  }

  AppNotification copyWith({bool? isRead}) => AppNotification(
    id: id,
    title: title,
    message: message,
    type: type,
    category: category,
    categoryLabel: categoryLabel,
    isRead: isRead ?? this.isRead,
    createdAt: createdAt,
    senderName: senderName,
    unitId: unitId,
    reference: reference,
    raw: raw,
  );
}
