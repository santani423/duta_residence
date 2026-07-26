import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../services/location_service.dart';
import '../../widgets/state_views.dart';

enum _Phase { idle, locating, sending, sent, error }

class CollectorEmergencyScreen extends StatefulWidget {
  const CollectorEmergencyScreen({required this.apiClient, super.key});

  final ApiClient apiClient;

  @override
  State<CollectorEmergencyScreen> createState() =>
      _CollectorEmergencyScreenState();
}

class _CollectorEmergencyScreenState extends State<CollectorEmergencyScreen> {
  _Phase _phase = _Phase.idle;
  String? _errorMessage;
  final _locationService = const LocationService();

  Future<void> _send() async {
    setState(() => _phase = _Phase.locating);
    try {
      final fix = await _locationService.captureCurrentPosition();
      setState(() => _phase = _Phase.sending);
      await widget.apiClient.postJson('emergency-alerts', {
        'latitude': fix.latitude,
        'longitude': fix.longitude,
        'accuracy_meters': fix.accuracyMeters,
        'note': 'Sinyal darurat dikirim oleh kolektor dari lapangan.',
      });
      setState(() => _phase = _Phase.sent);
    } on ApiException catch (error) {
      setState(() {
        _phase = _Phase.error;
        _errorMessage = error.message;
      });
    } catch (error) {
      setState(() {
        _phase = _Phase.error;
        _errorMessage = error.toString();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(title: const Text('Sinyal Darurat')),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.xl),
          child: switch (_phase) {
            _Phase.idle => Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.warning_amber_rounded, size: 72, color: colors.error),
                const SizedBox(height: AppSpacing.lg),
                Text(
                  'Kirim sinyal darurat jika Anda memerlukan bantuan segera saat bertugas di lapangan.',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodyLarge,
                ),
                const SizedBox(height: AppSpacing.xl),
                FilledButton.icon(
                  style: FilledButton.styleFrom(
                    backgroundColor: colors.error,
                    foregroundColor: colors.onError,
                    minimumSize: const Size.fromHeight(52),
                  ),
                  onPressed: _send,
                  icon: const Icon(Icons.sos_rounded),
                  label: const Text('Kirim Sinyal Darurat'),
                ),
              ],
            ),
            _Phase.locating || _Phase.sending => const LoadingView(
              message: 'Mengirim sinyal darurat...',
            ),
            _Phase.sent => Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.check_circle_rounded, size: 72, color: colors.primary),
                const SizedBox(height: AppSpacing.lg),
                const Text('Sinyal darurat berhasil dikirim ke tim admin.'),
                const SizedBox(height: AppSpacing.xl),
                FilledButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('Tutup'),
                ),
              ],
            ),
            _Phase.error => ErrorView(
              message: _errorMessage ?? 'Gagal mengirim sinyal darurat.',
              onRetry: _send,
            ),
          },
        ),
      ),
    );
  }
}
