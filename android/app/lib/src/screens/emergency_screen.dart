import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart' as ll;

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../constants/app_spacing.dart';
import '../models/emergency_alert.dart';
import '../services/location_service.dart';
import '../theme/app_status_colors.dart';
import '../utils/formatters.dart';
import '../widgets/duta_card.dart';
import '../widgets/info_row.dart';

enum _Phase { idle, locating, sending, waiting, resolved, error }

const _cancelHoldSeconds = 5;
const _statusTexts = [
  'Sinyal Darurat Terkirim - Menunggu Konfirmasi Admin',
  'Mencari Admin Standby...',
];

class EmergencyScreen extends StatefulWidget {
  const EmergencyScreen({required this.apiClient, super.key});

  final ApiClient apiClient;

  @override
  State<EmergencyScreen> createState() => _EmergencyScreenState();
}

class _EmergencyScreenState extends State<EmergencyScreen>
    with TickerProviderStateMixin {
  final _noteController = TextEditingController();
  final _locationService = const LocationService();

  _Phase _phase = _Phase.idle;
  String? _errorMessage;
  LocationFix? _fix;
  EmergencyAlert? _alert;

  late final AnimationController _pulseController = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 2400),
  )..repeat();

  Timer? _pollTimer;
  Timer? _countdownTimer;
  Timer? _statusTextTimer;
  int _cancelCountdown = _cancelHoldSeconds;
  int _statusTextIndex = 0;
  bool _cancelling = false;

  @override
  void dispose() {
    _noteController.dispose();
    _pulseController.dispose();
    _pollTimer?.cancel();
    _countdownTimer?.cancel();
    _statusTextTimer?.cancel();
    super.dispose();
  }

  Future<void> _startEmergency() async {
    setState(() {
      _phase = _Phase.locating;
      _errorMessage = null;
    });

    LocationFix? fix;
    try {
      fix = await _locationService.captureCurrentPosition();
    } on LocationCaptureException catch (error) {
      if (!mounted) return;
      final proceed = await _confirmSendWithoutLocation(error.message);
      if (proceed != true) {
        if (mounted) setState(() => _phase = _Phase.idle);
        return;
      }
    }
    if (!mounted) return;
    _fix = fix;
    await _send();
  }

  Future<bool?> _confirmSendWithoutLocation(String reason) {
    return showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Lokasi tidak tersedia'),
        content: Text('$reason\n\nKirim sinyal darurat tanpa data lokasi?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(context).colorScheme.error,
            ),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Kirim Tanpa Lokasi'),
          ),
        ],
      ),
    );
  }

  Future<void> _send() async {
    setState(() => _phase = _Phase.sending);
    try {
      final note = _noteController.text.trim();
      final fix = _fix;
      final result = await widget.apiClient.postJson('resident/emergency', {
        if (note.isNotEmpty) 'note': note,
        if (fix != null) ...{
          'latitude': fix.latitude,
          'longitude': fix.longitude,
          'accuracy_meters': fix.accuracyMeters,
          'location_captured_at': fix.capturedAt.toIso8601String(),
        },
      });
      final alert = EmergencyAlert.fromJson(asMap(result.data));
      if (!mounted) return;
      setState(() {
        _alert = alert;
        _phase = _Phase.waiting;
      });
      _beginWaitingTimers();
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _phase = _Phase.error;
        _errorMessage = error.message;
      });
    }
  }

  void _beginWaitingTimers() {
    _cancelCountdown = _cancelHoldSeconds;
    _statusTextIndex = 0;
    _statusTextTimer = Timer.periodic(const Duration(milliseconds: 2600), (_) {
      if (!mounted) return;
      setState(() => _statusTextIndex = (_statusTextIndex + 1) % _statusTexts.length);
    });
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) return;
      if (_cancelCountdown <= 1) {
        timer.cancel();
        setState(() => _cancelCountdown = 0);
        return;
      }
      setState(() => _cancelCountdown -= 1);
    });
    // Polling menggantikan push WebSocket/Firebase: cukup cepat untuk terasa
    // langsung, tanpa perlu server realtime tambahan di backend.
    _pollTimer = Timer.periodic(const Duration(seconds: 3), (_) => _poll());
  }

  Future<void> _poll() async {
    final alert = _alert;
    if (alert == null || _phase != _Phase.waiting) return;
    try {
      final result = await widget.apiClient.get(
        'resident/emergency/${alert.id}',
      );
      final updated = EmergencyAlert.fromJson(asMap(result.data));
      if (!mounted) return;
      if (updated.isAcknowledged || updated.isCancelled) {
        _pollTimer?.cancel();
        _countdownTimer?.cancel();
        _statusTextTimer?.cancel();
        setState(() {
          _alert = updated;
          _phase = _Phase.resolved;
        });
      } else {
        setState(() => _alert = updated);
      }
    } on ApiException {
      // Koneksi bermasalah sesaat - polling berikutnya akan mencoba lagi,
      // tidak perlu mengganggu layar menunggu dengan error sesaat.
    }
  }

  Future<void> _cancelAlert() async {
    final alert = _alert;
    if (alert == null || _cancelCountdown > 0 || _cancelling) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Batalkan sinyal darurat?'),
        content: const Text(
          'Admin akan diberi tahu bahwa sinyal ini dibatalkan.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Tidak'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Ya, Batalkan'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _cancelling = true);
    try {
      final result = await widget.apiClient.postJson(
        'resident/emergency/${alert.id}/cancel',
        const {},
      );
      _pollTimer?.cancel();
      _countdownTimer?.cancel();
      _statusTextTimer?.cancel();
      if (!mounted) return;
      setState(() {
        _alert = EmergencyAlert.fromJson(asMap(result.data));
        _phase = _Phase.resolved;
        _cancelling = false;
      });
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _cancelling = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.message)));
    }
  }

  bool get _canDismiss =>
      _phase == _Phase.idle || _phase == _Phase.resolved || _phase == _Phase.error;

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: _canDismiss,
      child: Scaffold(
        body: SafeArea(
          child: switch (_phase) {
            _Phase.idle => _IdleView(
              noteController: _noteController,
              onTrigger: _startEmergency,
              onClose: () => Navigator.of(context).pop(),
            ),
            _Phase.locating => const _BusyView(
              message: 'Mengambil lokasi presisi...',
            ),
            _Phase.sending => const _BusyView(
              message: 'Mengirim sinyal darurat...',
            ),
            _Phase.waiting => _WaitingView(
              alert: _alert!,
              fix: _fix,
              pulseController: _pulseController,
              statusText: _statusTexts[_statusTextIndex],
              cancelCountdown: _cancelCountdown,
              cancelling: _cancelling,
              onCancel: _cancelAlert,
            ),
            _Phase.resolved => _ResolvedView(
              alert: _alert!,
              onClose: () => Navigator.of(context).pop(),
            ),
            _Phase.error => _ErrorPhaseView(
              message: _errorMessage ?? 'Gagal mengirim sinyal darurat.',
              onRetry: _send,
              onClose: () => setState(() => _phase = _Phase.idle),
            ),
          },
        ),
      ),
    );
  }
}

class _IdleView extends StatelessWidget {
  const _IdleView({
    required this.noteController,
    required this.onTrigger,
    required this.onClose,
  });

  final TextEditingController noteController;
  final VoidCallback onTrigger;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Column(
      children: [
        Align(
          alignment: Alignment.topLeft,
          child: IconButton(
            onPressed: onClose,
            icon: const Icon(Icons.close_rounded),
          ),
        ),
        Expanded(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.xl),
            child: Column(
              children: [
                const SizedBox(height: AppSpacing.lg),
                Icon(Icons.warning_rounded, size: 56, color: colors.error),
                const SizedBox(height: AppSpacing.lg),
                Text(
                  'Sinyal Darurat',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: AppSpacing.sm),
                Text(
                  'Tekan tombol di bawah untuk mengirim lokasi presisi Anda ke admin standby secara langsung.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: colors.onSurfaceVariant),
                ),
                const SizedBox(height: AppSpacing.xxl),
                GestureDetector(
                  onTap: onTrigger,
                  child: Container(
                    width: 176,
                    height: 176,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: colors.error,
                      boxShadow: [
                        BoxShadow(
                          color: colors.error.withValues(alpha: 0.4),
                          blurRadius: 32,
                          spreadRadius: 4,
                        ),
                      ],
                    ),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.sos_rounded, color: colors.onError, size: 48),
                        const SizedBox(height: 4),
                        Text(
                          'KIRIM SOS',
                          style: TextStyle(
                            color: colors.onError,
                            fontWeight: FontWeight.w900,
                            letterSpacing: 1,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: AppSpacing.xxl),
                TextField(
                  controller: noteController,
                  maxLines: 3,
                  decoration: const InputDecoration(
                    labelText: 'Catatan (opsional)',
                    hintText: 'Contoh: kebakaran di dapur, ada pencuri, dll.',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: AppSpacing.lg),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _BusyView extends StatelessWidget {
  const _BusyView({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(
            width: 40,
            height: 40,
            child: CircularProgressIndicator(strokeWidth: 3, color: colors.error),
          ),
          const SizedBox(height: AppSpacing.lg),
          Text(
            message,
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
          ),
        ],
      ),
    );
  }
}

class _WaitingView extends StatelessWidget {
  const _WaitingView({
    required this.alert,
    required this.fix,
    required this.pulseController,
    required this.statusText,
    required this.cancelCountdown,
    required this.cancelling,
    required this.onCancel,
  });

  final EmergencyAlert alert;
  final LocationFix? fix;
  final AnimationController pulseController;
  final String statusText;
  final int cancelCountdown;
  final bool cancelling;
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final counting = cancelCountdown > 0;
    return SingleChildScrollView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Column(
        children: [
          const SizedBox(height: AppSpacing.md),
          _PulseRing(controller: pulseController, color: colors.error),
          const SizedBox(height: AppSpacing.xl),
          Text(
            'Sinyal Darurat Aktif',
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: AppSpacing.sm),
          AnimatedSwitcher(
            duration: const Duration(milliseconds: 400),
            child: Text(
              statusText,
              key: ValueKey(statusText),
              textAlign: TextAlign.center,
              style: TextStyle(color: colors.error, fontWeight: FontWeight.w700),
            ),
          ),
          const SizedBox(height: AppSpacing.xl),
          if (fix != null) ...[
            const SectionHeader(title: 'Lokasi yang Dikirim'),
            const SizedBox(height: AppSpacing.md),
            _MiniLocationMap(fix: fix!),
            const SizedBox(height: AppSpacing.md),
            DutaCard(
              child: InfoRows(
                items: {
                  'Latitude': fix!.latitude.toStringAsFixed(6),
                  'Longitude': fix!.longitude.toStringAsFixed(6),
                  'Akurasi': '± ${fix!.accuracyMeters.toStringAsFixed(1)} m',
                  'Waktu Kirim': dateTime(fix!.capturedAt.toIso8601String()),
                },
              ),
            ),
            const SizedBox(height: AppSpacing.xl),
          ],
          if (alert.note != null && alert.note!.isNotEmpty) ...[
            DutaCard(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    Icons.notes_rounded,
                    color: colors.onSurfaceVariant,
                    size: 20,
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(child: Text(alert.note!)),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.xl),
          ],
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: counting || cancelling ? null : onCancel,
              icon: cancelling
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.close_rounded),
              label: Text(
                counting ? 'Batal ($cancelCountdown)' : 'Batalkan Sinyal Darurat',
              ),
              style: OutlinedButton.styleFrom(
                foregroundColor: colors.error,
                side: BorderSide(
                  color: colors.error.withValues(alpha: counting ? 0.3 : 0.7),
                ),
                padding: const EdgeInsets.symmetric(vertical: AppSpacing.md),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Sonar-style pulse: three staggered rings expand and fade around a fixed
/// SOS badge, driven by one repeating [AnimationController].
class _PulseRing extends StatelessWidget {
  const _PulseRing({required this.controller, required this.color});

  final AnimationController controller;
  final Color color;

  static const _delays = [0.0, 0.33, 0.66];

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 160,
      height: 160,
      child: AnimatedBuilder(
        animation: controller,
        builder: (context, child) {
          return Stack(
            alignment: Alignment.center,
            children: [
              for (final delay in _delays) _ring(controller.value, delay),
              Container(
                width: 88,
                height: 88,
                decoration: BoxDecoration(shape: BoxShape.circle, color: color),
                child: const Icon(Icons.sos_rounded, color: Colors.white, size: 40),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _ring(double value, double delay) {
    final t = (value + delay) % 1.0;
    final scale = 0.5 + t * 0.9;
    final opacity = (1 - t).clamp(0.0, 1.0);
    return Opacity(
      opacity: opacity * 0.5,
      child: Transform.scale(
        scale: scale,
        child: Container(
          width: 160,
          height: 160,
          decoration: BoxDecoration(shape: BoxShape.circle, color: color),
        ),
      ),
    );
  }
}

class _MiniLocationMap extends StatelessWidget {
  const _MiniLocationMap({required this.fix});

  final LocationFix fix;

  /// Zooms out as the GPS margin of error grows, so the accuracy circle
  /// stays readable inside the fixed-height map instead of overflowing it.
  static double _zoomForAccuracy(double accuracyMeters) {
    if (accuracyMeters <= 15) return 18;
    if (accuracyMeters <= 30) return 17;
    if (accuracyMeters <= 75) return 16;
    if (accuracyMeters <= 150) return 15;
    return 14;
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final point = ll.LatLng(fix.latitude, fix.longitude);
    return Container(
      height: 220,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
        border: Border.all(color: colors.outlineVariant.withValues(alpha: 0.5)),
        boxShadow: [
          BoxShadow(
            color: colors.shadow.withValues(alpha: 0.1),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Stack(
        children: [
          FlutterMap(
            options: MapOptions(
              initialCenter: point,
              initialZoom: _zoomForAccuracy(fix.accuracyMeters),
              interactionOptions: const InteractionOptions(
                flags: InteractiveFlag.pinchZoom | InteractiveFlag.drag,
              ),
            ),
            children: [
              TileLayer(
                // tile.openstreetmap.org explicitly blocks apps that fetch
                // tiles directly from it (see osm.wiki/Blocked - this isn't
                // just a User-Agent issue). CARTO's free basemap tiles are
                // meant for exactly this kind of app usage and need no key.
                urlTemplate:
                    'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
                subdomains: const ['a', 'b', 'c', 'd'],
                userAgentPackageName: 'com.dutaresidence.resident',
                retinaMode: MediaQuery.devicePixelRatioOf(context) > 1.0,
              ),
              CircleLayer(
                circles: [
                  CircleMarker(
                    point: point,
                    radius: fix.accuracyMeters,
                    useRadiusInMeter: true,
                    color: Colors.blue.withValues(alpha: 0.15),
                    borderColor: Colors.blue.withValues(alpha: 0.6),
                    borderStrokeWidth: 1.5,
                  ),
                ],
              ),
              MarkerLayer(
                markers: [
                  Marker(
                    point: point,
                    width: 40,
                    height: 40,
                    // location_on's visual tip sits at the bottom of its
                    // bounding box - topCenter anchors that tip exactly on
                    // the coordinate instead of floating the icon's center
                    // (and therefore the pin) above the real point.
                    alignment: Alignment.topCenter,
                    child: Icon(
                      Icons.location_on_rounded,
                      color: colors.error,
                      size: 40,
                      shadows: const [
                        Shadow(color: Colors.black38, blurRadius: 4),
                      ],
                    ),
                  ),
                ],
              ),
            ],
          ),
          Positioned(
            right: AppSpacing.sm,
            top: AppSpacing.sm,
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: colors.surface.withValues(alpha: 0.92),
                borderRadius: BorderRadius.circular(AppSpacing.pill),
                boxShadow: [
                  BoxShadow(
                    color: colors.shadow.withValues(alpha: 0.12),
                    blurRadius: 8,
                  ),
                ],
              ),
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: AppSpacing.sm,
                  vertical: 4,
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.gps_fixed_rounded, size: 12, color: colors.primary),
                    const SizedBox(width: 4),
                    Text(
                      '± ${fix.accuracyMeters.toStringAsFixed(0)} m',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        color: colors.onSurface,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ResolvedView extends StatelessWidget {
  const _ResolvedView({required this.alert, required this.onClose});

  final EmergencyAlert alert;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final statusColors = Theme.of(context).extension<AppStatusColors>();
    final acknowledged = alert.isAcknowledged;
    final name = alert.acknowledgerName;
    final title = acknowledged ? 'Bantuan Sedang Diproses' : 'Sinyal Darurat Dibatalkan';
    final message = acknowledged
        ? (name != null && name.isNotEmpty
              ? 'Admin $name telah menerima sinyal Anda dan sedang memproses bantuan.'
              : 'Admin telah menerima sinyal Anda dan sedang memproses bantuan.')
        : 'Sinyal darurat ini telah dibatalkan.';
    final iconColor = acknowledged
        ? (statusColors?.success.onContainer ?? Colors.green)
        : colors.onSurfaceVariant;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              acknowledged ? Icons.verified_rounded : Icons.cancel_rounded,
              size: 72,
              color: iconColor,
            ),
            const SizedBox(height: AppSpacing.lg),
            Text(
              title,
              textAlign: TextAlign.center,
              style: Theme.of(
                context,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              message,
              textAlign: TextAlign.center,
              style: TextStyle(color: colors.onSurfaceVariant),
            ),
            const SizedBox(height: AppSpacing.xl),
            SizedBox(
              width: double.infinity,
              child: FilledButton(onPressed: onClose, child: const Text('Tutup')),
            ),
          ],
        ),
      ),
    );
  }
}

class _ErrorPhaseView extends StatelessWidget {
  const _ErrorPhaseView({
    required this.message,
    required this.onRetry,
    required this.onClose,
  });

  final String message;
  final VoidCallback onRetry;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.error_outline_rounded, size: 64, color: colors.error),
            const SizedBox(height: AppSpacing.lg),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: AppSpacing.xl),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: onClose,
                    child: const Text('Batal'),
                  ),
                ),
                const SizedBox(width: AppSpacing.md),
                Expanded(
                  child: FilledButton(
                    style: FilledButton.styleFrom(backgroundColor: colors.error),
                    onPressed: onRetry,
                    child: const Text('Coba Lagi'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
