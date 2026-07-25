import '../utils/formatters.dart';

class EmergencyAlert {
  const EmergencyAlert({
    required this.id,
    required this.status,
    required this.note,
    required this.latitude,
    required this.longitude,
    required this.accuracyMeters,
    required this.locationCapturedAt,
    required this.createdAt,
    required this.acknowledgedAt,
    required this.acknowledgerName,
  });

  final int id;
  final String status;
  final String? note;
  final double? latitude;
  final double? longitude;
  final double? accuracyMeters;
  final DateTime? locationCapturedAt;
  final DateTime? createdAt;
  final DateTime? acknowledgedAt;
  final String? acknowledgerName;

  bool get hasLocation => latitude != null && longitude != null;
  bool get isActive => status == 'active';
  bool get isAcknowledged => status == 'acknowledged';
  bool get isCancelled => status == 'cancelled';

  factory EmergencyAlert.fromJson(Map<String, dynamic> json) {
    final acknowledger = asMap(json['acknowledger']);
    return EmergencyAlert(
      id: (json['id'] as num).toInt(),
      status: json['status']?.toString() ?? 'active',
      note: json['note']?.toString(),
      latitude: _toDouble(json['latitude']),
      longitude: _toDouble(json['longitude']),
      accuracyMeters: _toDouble(json['accuracy_meters']),
      locationCapturedAt: _toDate(json['location_captured_at']),
      createdAt: _toDate(json['created_at']),
      acknowledgedAt: _toDate(json['acknowledged_at']),
      acknowledgerName: acknowledger['name']?.toString(),
    );
  }

  static double? _toDouble(Object? value) {
    if (value == null) return null;
    if (value is num) return value.toDouble();
    return double.tryParse(value.toString());
  }

  static DateTime? _toDate(Object? value) {
    if (value == null || value.toString().isEmpty) return null;
    return DateTime.tryParse(value.toString());
  }
}
