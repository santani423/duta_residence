import 'dart:async';

import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/state_views.dart';
import '../../widgets/status_badge.dart';
import 'supervisor_collector_detail_screen.dart';

class SupervisorCollectorsScreen extends StatefulWidget {
  const SupervisorCollectorsScreen({required this.apiClient, super.key});

  final ApiClient apiClient;

  @override
  State<SupervisorCollectorsScreen> createState() =>
      _SupervisorCollectorsScreenState();
}

class _SupervisorCollectorsScreenState
    extends State<SupervisorCollectorsScreen> {
  late Future<List<dynamic>> _future;
  final _searchController = TextEditingController();
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  Future<List<dynamic>> _load([String? search]) async {
    final result = await widget.apiClient.get(
      'supervisor/collectors',
      query: {
        'per_page': 50,
        if (search?.isNotEmpty ?? false) 'search': search,
      },
    );
    return asList(result.data);
  }

  void _onSearchChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () {
      setState(() => _future = _load(value));
    });
  }

  Future<void> _refresh() async {
    setState(() => _future = _load(_searchController.text));
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: TextField(
            controller: _searchController,
            onChanged: _onSearchChanged,
            decoration: const InputDecoration(
              hintText: 'Cari nama kolektor',
              prefixIcon: Icon(Icons.search_rounded),
              border: OutlineInputBorder(),
              isDense: true,
            ),
          ),
        ),
        Expanded(
          child: FutureBuilder<List<dynamic>>(
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
              final items = snapshot.data ?? const [];
              if (items.isEmpty) {
                return const EmptyView(
                  message: 'Belum ada kolektor dalam pengawasan Anda.',
                );
              }
              return RefreshIndicator(
                onRefresh: _refresh,
                child: ListView.separated(
                  padding: const EdgeInsets.fromLTRB(
                    AppSpacing.lg,
                    0,
                    AppSpacing.lg,
                    AppSpacing.lg,
                  ),
                  itemCount: items.length,
                  separatorBuilder: (_, _) =>
                      const SizedBox(height: AppSpacing.md),
                  itemBuilder: (context, index) {
                    final collector = asMap(items[index]);
                    final profile = asMap(collector['collector_profile']);
                    final location = asMap(collector['latest_location']);
                    return DutaCard(
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => SupervisorCollectorDetailScreen(
                            apiClient: widget.apiClient,
                            collectorId: collector['id'].toString(),
                          ),
                        ),
                      ),
                      child: Row(
                        children: [
                          const IconBadge(icon: Icons.person_outline_rounded),
                          const SizedBox(width: AppSpacing.md),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  compact(collector['name']),
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                Text(
                                  '${compact(profile['collector_code'])} — ${location.isEmpty ? 'Belum ada lokasi' : dateTime(location['recorded_at'])}',
                                  style: Theme.of(context).textTheme.bodySmall,
                                ),
                                const SizedBox(height: AppSpacing.xs),
                                Row(
                                  children: [
                                    Text(
                                      'Kunjungan: ${compact(collector['today_visit_count'])}',
                                      style: Theme.of(
                                        context,
                                      ).textTheme.bodySmall,
                                    ),
                                    const SizedBox(width: AppSpacing.md),
                                    Text(
                                      'Janji Aktif: ${compact(collector['active_ptp_count'])}',
                                      style: Theme.of(
                                        context,
                                      ).textTheme.bodySmall,
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                          if (profile['account_status'] != null)
                            StatusBadge(profile['account_status']),
                        ],
                      ),
                    );
                  },
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}
