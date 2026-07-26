import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart' as ll;
import 'package:url_launcher/url_launcher.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/state_views.dart';

class RouteMapScreen extends StatefulWidget {
  const RouteMapScreen({required this.apiClient, super.key});

  final ApiClient apiClient;

  @override
  State<RouteMapScreen> createState() => _RouteMapScreenState();
}

class _RouteMapScreenState extends State<RouteMapScreen> {
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final result = await widget.apiClient.get(
      'units',
      query: {'per_page': 200},
    );
    return asList(result.data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _openInMaps(Map<String, dynamic> unit) async {
    final lat = unit['latitude'];
    final lng = unit['longitude'];
    final uri = (lat != null && lng != null)
        ? Uri.parse(
            'https://www.google.com/maps/search/?api=1&query=$lat,$lng',
          )
        : Uri.parse(
            'https://www.google.com/maps/search/?api=1&query=${Uri.encodeComponent('${asMap(unit['cluster'])['name'] ?? ''} Blok ${unit['block']} No ${unit['lot_number']}')}',
          );
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<dynamic>>(
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
        final items = (snapshot.data ?? const []).map(asMap).toList();
        final withCoords = items
            .where((unit) => unit['latitude'] != null && unit['longitude'] != null)
            .toList();
        final withoutCoords = items
            .where((unit) => unit['latitude'] == null || unit['longitude'] == null)
            .toList();

        if (items.isEmpty) {
          return const EmptyView(message: 'Belum ada unit yang ditugaskan kepada Anda.');
        }

        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            children: [
              if (withCoords.isNotEmpty)
                _UnitsMap(units: withCoords)
              else
                const DutaCard(
                  child: Text('Belum ada unit dengan titik koordinat tersimpan.'),
                ),
              const SizedBox(height: AppSpacing.lg),
              for (final unit in items)
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
                                unit['id']?.toString() ?? '-',
                                style: const TextStyle(fontWeight: FontWeight.w800),
                              ),
                              Text(
                                'Blok ${compact(unit['block'])} No ${compact(unit['lot_number'])} — ${compact(asMap(unit['resident'])['name'])}',
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                            ],
                          ),
                        ),
                        IconButton(
                          icon: const Icon(Icons.directions_outlined),
                          onPressed: () => _openInMaps(unit),
                        ),
                      ],
                    ),
                  ),
                ),
              if (withoutCoords.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: AppSpacing.sm),
                  child: Text(
                    '${withoutCoords.length} unit belum memiliki titik koordinat. Ketuk ikon arah untuk membuka alamatnya di peta.',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ),
            ],
          ),
        );
      },
    );
  }
}

class _UnitsMap extends StatelessWidget {
  const _UnitsMap({required this.units});

  final List<Map<String, dynamic>> units;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final points = units
        .map(
          (unit) => ll.LatLng(
            double.parse(unit['latitude'].toString()),
            double.parse(unit['longitude'].toString()),
          ),
        )
        .toList();
    final center = points.first;

    return Container(
      height: 260,
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
              for (var i = 0; i < points.length; i++)
                Marker(
                  point: points[i],
                  width: 32,
                  height: 32,
                  alignment: Alignment.topCenter,
                  child: Icon(
                    Icons.location_on_rounded,
                    color: colors.error,
                    size: 32,
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
