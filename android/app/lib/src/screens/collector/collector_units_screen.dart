import 'dart:async';

import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/state_views.dart';
import 'unit_detail_screen.dart';

class CollectorUnitsScreen extends StatefulWidget {
  const CollectorUnitsScreen({required this.apiClient, super.key});

  final ApiClient apiClient;

  @override
  State<CollectorUnitsScreen> createState() => _CollectorUnitsScreenState();
}

class _CollectorUnitsScreenState extends State<CollectorUnitsScreen> {
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
      'units',
      query: {'per_page': 50, if (search?.isNotEmpty ?? false) 'search': search},
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
              hintText: 'Cari unit atau nama penghuni',
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
                  message: 'Belum ada unit yang ditugaskan kepada Anda.',
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
                    final unit = asMap(items[index]);
                    final resident = asMap(unit['resident']);
                    return DutaCard(
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => UnitDetailScreen(
                            apiClient: widget.apiClient,
                            unitId: unit['id'].toString(),
                          ),
                        ),
                      ),
                      child: Row(
                        children: [
                          const IconBadge(icon: Icons.home_work_outlined),
                          const SizedBox(width: AppSpacing.md),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '${compact(unit['id'])} — ${compact(asMap(unit['cluster'])['name'])}',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                Text(
                                  compact(resident['name']),
                                  style: Theme.of(context).textTheme.bodySmall,
                                ),
                              ],
                            ),
                          ),
                          const Icon(Icons.chevron_right_rounded),
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
