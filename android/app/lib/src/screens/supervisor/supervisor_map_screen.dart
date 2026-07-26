import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart' as ll;

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/state_views.dart';

/// Read-only map of collector positions and active emergencies, scoped to
/// this supervisor's own collectors/clusters via `GET supervisor/map`.
/// Deliberately does not request device location permission or start any
/// location service - a Supervisor only ever displays positions received
/// from the API, it never reports its own.
class SupervisorMapScreen extends StatefulWidget {
  const SupervisorMapScreen({required this.apiClient, super.key});

  final ApiClient apiClient;

  @override
  State<SupervisorMapScreen> createState() => _SupervisorMapScreenState();
}

class _SupervisorMapScreenState extends State<SupervisorMapScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final result = await widget.apiClient.get('supervisor/map');
    return asMap(result.data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const LoadingView();
        }
        if (snapshot.hasError) {
          final message = snapshot.error is ApiException
              ? (snapshot.error as ApiException).message
              : snapshot.error.toString();
          return ErrorView(message: message, onRetry: _refresh);
        }
        final data = snapshot.data ?? {};
        final locations = asList(data['collector_locations']).map(asMap).toList();
        final emergencies = asList(data['active_emergencies']).map(asMap).toList();

        final locationPoints = locations
            .where((l) => l['latitude'] != null && l['longitude'] != null)
            .toList();
        final emergencyPoints = emergencies
            .where((e) => e['latitude'] != null && e['longitude'] != null)
            .toList();

        if (locationPoints.isEmpty && emergencyPoints.isEmpty) {
          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              children: const [
                SizedBox(height: 120),
                EmptyView(
                  message: 'Belum ada data lokasi kolektor atau darurat.',
                ),
              ],
            ),
          );
        }

        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            children: [
              _SupervisorMap(
                collectorLocations: locationPoints,
                emergencies: emergencyPoints,
              ),
              const SizedBox(height: AppSpacing.lg),
              if (emergencies.isNotEmpty) ...[
                Text(
                  'Darurat Aktif',
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                for (final emergency in emergencies)
                  Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.md),
                    child: DutaCard(
                      color: Theme.of(context).colorScheme.errorContainer,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            compact(asMap(emergency['unit'])['id']),
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                          Text(compact(asMap(emergency['resident'])['name'])),
                          Text(
                            dateTime(emergency['location_captured_at']),
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ],
                      ),
                    ),
                  ),
                const SizedBox(height: AppSpacing.md),
              ],
              Text(
                'Lokasi Kolektor',
                style: Theme.of(
                  context,
                ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: AppSpacing.md),
              if (locations.isEmpty)
                const Text('Belum ada kolektor yang mengirim lokasi.')
              else
                for (final location in locations)
                  Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.md),
                    child: DutaCard(
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  compact(asMap(location['collector'])['name']),
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                Text(
                                  dateTime(location['recorded_at']),
                                  style: Theme.of(context).textTheme.bodySmall,
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
            ],
          ),
        );
      },
    );
  }
}

class _SupervisorMap extends StatelessWidget {
  const _SupervisorMap({
    required this.collectorLocations,
    required this.emergencies,
  });

  final List<Map<String, dynamic>> collectorLocations;
  final List<Map<String, dynamic>> emergencies;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final collectorPoints = collectorLocations
        .map(
          (location) => ll.LatLng(
            double.parse(location['latitude'].toString()),
            double.parse(location['longitude'].toString()),
          ),
        )
        .toList();
    final emergencyPoints = emergencies
        .map(
          (emergency) => ll.LatLng(
            double.parse(emergency['latitude'].toString()),
            double.parse(emergency['longitude'].toString()),
          ),
        )
        .toList();
    final center = emergencyPoints.isNotEmpty
        ? emergencyPoints.first
        : collectorPoints.first;

    return Container(
      height: 320,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
        border: Border.all(color: colors.outlineVariant.withValues(alpha: 0.5)),
      ),
      child: FlutterMap(
        options: MapOptions(
          initialCenter: center,
          initialZoom: 13,
          interactionOptions: const InteractionOptions(
            flags: InteractiveFlag.pinchZoom | InteractiveFlag.drag,
          ),
        ),
        children: [
          TileLayer(
            urlTemplate:
                'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
            subdomains: const ['a', 'b', 'c', 'd'],
            userAgentPackageName: 'com.dutaresidence.resident',
            retinaMode: MediaQuery.devicePixelRatioOf(context) > 1.0,
          ),
          MarkerLayer(
            markers: [
              for (var i = 0; i < collectorPoints.length; i++)
                Marker(
                  point: collectorPoints[i],
                  width: 34,
                  height: 34,
                  alignment: Alignment.topCenter,
                  child: Icon(
                    Icons.person_pin_circle_rounded,
                    color: colors.primary,
                    size: 34,
                    shadows: const [Shadow(color: Colors.black38, blurRadius: 4)],
                  ),
                ),
              for (var i = 0; i < emergencyPoints.length; i++)
                Marker(
                  point: emergencyPoints[i],
                  width: 34,
                  height: 34,
                  alignment: Alignment.topCenter,
                  child: Icon(
                    Icons.warning_rounded,
                    color: colors.error,
                    size: 34,
                    shadows: const [Shadow(color: Colors.black38, blurRadius: 4)],
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}
