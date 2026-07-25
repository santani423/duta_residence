import 'package:geolocator/geolocator.dart';

class LocationCaptureException implements Exception {
  const LocationCaptureException(this.message);

  final String message;

  @override
  String toString() => message;
}

/// One precise GPS fix: coordinates, margin of error, and the moment it was
/// captured - exactly what the emergency signal needs to attach to a report.
class LocationFix {
  const LocationFix({
    required this.latitude,
    required this.longitude,
    required this.accuracyMeters,
    required this.capturedAt,
  });

  final double latitude;
  final double longitude;
  final double accuracyMeters;
  final DateTime capturedAt;
}

class LocationService {
  const LocationService();

  /// Resolves the sender's current position through the platform's Fused
  /// Location Provider (geolocator's Android backend talks to
  /// FusedLocationProviderClient directly, combining GPS/Wi-Fi/cell for the
  /// fastest accurate fix) rather than raw GPS alone.
  Future<LocationFix> captureCurrentPosition() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw const LocationCaptureException(
        'Aktifkan layanan lokasi (GPS) di perangkat Anda untuk mengirim sinyal darurat.',
      );
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied) {
      throw const LocationCaptureException(
        'Izin lokasi ditolak. Sinyal darurat butuh lokasi Anda agar admin bisa membantu.',
      );
    }
    if (permission == LocationPermission.deniedForever) {
      throw const LocationCaptureException(
        'Izin lokasi diblokir permanen. Aktifkan lewat Pengaturan aplikasi.',
      );
    }

    final position = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.best,
        timeLimit: Duration(seconds: 20),
      ),
    );

    return LocationFix(
      latitude: position.latitude,
      longitude: position.longitude,
      accuracyMeters: position.accuracy,
      capturedAt: position.timestamp,
    );
  }
}
