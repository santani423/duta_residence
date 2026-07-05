import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../constants/app_config.dart';
import '../constants/app_spacing.dart';
import '../utils/formatters.dart';
import '../widgets/duta_card.dart';
import '../widgets/info_row.dart';
import '../widgets/state_views.dart';
import '../widgets/status_badge.dart';

class PropertyScreen extends StatefulWidget {
  const PropertyScreen({required this.apiClient, super.key});

  final ApiClient apiClient;

  @override
  State<PropertyScreen> createState() => _PropertyScreenState();
}

class _PropertyScreenState extends State<PropertyScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final result = await widget.apiClient.get('customer/property');
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
          return ErrorView(message: message, onRetry: () => _refresh());
        }
        final property = snapshot.data ?? {};
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            children: [
              DutaCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        CircleAvatar(
                          radius: 28,
                          backgroundColor: Theme.of(
                            context,
                          ).colorScheme.primaryContainer,
                          child: Icon(
                            Icons.domain_rounded,
                            color: Theme.of(
                              context,
                            ).colorScheme.onPrimaryContainer,
                          ),
                        ),
                        const SizedBox(width: AppSpacing.lg),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                compact(property['unit_label']),
                                style: Theme.of(context).textTheme.headlineSmall
                                    ?.copyWith(fontWeight: FontWeight.w900),
                              ),
                              const SizedBox(height: AppSpacing.xs),
                              Text(
                                AppConfig.estateName,
                                style: TextStyle(
                                  color: Theme.of(
                                    context,
                                  ).colorScheme.onSurfaceVariant,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    StatusBadge(property['status']),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              DutaCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SectionHeader(title: 'Detail Properti'),
                    const SizedBox(height: AppSpacing.lg),
                    InfoRows(
                      items: {
                        'Cluster': property['cluster'],
                        'Blok': property['block'],
                        'Nomor Unit': property['lot_number'],
                        'Tipe Properti': property['property_type'],
                        'Status Hunian': property['occupancy'],
                        'Luas Bangunan': property['building_area'],
                        'Luas Tanah': property['land_area'],
                        'Serah Terima': dateOnly(property['handover_date']),
                      },
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              DutaCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SectionHeader(title: 'Pengelolaan Tagihan'),
                    const SizedBox(height: AppSpacing.md),
                    Text(
                      'Penanggung jawab tagihan mengikuti data kepemilikan dan konfigurasi backend.',
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
