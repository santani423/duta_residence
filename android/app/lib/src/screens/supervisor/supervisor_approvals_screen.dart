import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/state_views.dart';
import '../../widgets/status_badge.dart';
import 'supervisor_approval_detail_screen.dart';

const _typeLabels = {
  'reversal': 'Pembatalan',
  'penalty_waiver': 'Keringanan Denda',
  'installment_plan': 'Rencana Cicilan',
  'billing_adjustment': 'Penyesuaian Tagihan',
};

const _statusLabels = {
  'submitted': 'Diajukan',
  'under_review': 'Ditinjau',
  'approved': 'Disetujui',
  'rejected': 'Ditolak',
  'cancelled': 'Dibatalkan',
  'expired': 'Kedaluwarsa',
};

class SupervisorApprovalsScreen extends StatefulWidget {
  const SupervisorApprovalsScreen({required this.apiClient, super.key});

  final ApiClient apiClient;

  @override
  State<SupervisorApprovalsScreen> createState() =>
      _SupervisorApprovalsScreenState();
}

class _SupervisorApprovalsScreenState
    extends State<SupervisorApprovalsScreen> {
  late Future<List<dynamic>> _future;
  String? _type;
  String? _status;
  bool _pendingOnly = false;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final result = await widget.apiClient.get(
      'approval-requests',
      query: {
        'per_page': 50,
        if (_type != null) 'type': _type,
        if (_status != null) 'status': _status,
        if (_pendingOnly) 'pending_only': 1,
      },
    );
    return asList(result.data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  void _applyFilters() {
    setState(() => _future = _load());
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.lg,
            AppSpacing.lg,
            AppSpacing.lg,
            0,
          ),
          child: Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: [
              FilterChip(
                label: const Text('Tertunda'),
                selected: _pendingOnly,
                onSelected: (value) {
                  _pendingOnly = value;
                  _applyFilters();
                },
              ),
              for (final entry in _typeLabels.entries)
                ChoiceChip(
                  label: Text(entry.value),
                  selected: _type == entry.key,
                  onSelected: (selected) {
                    _type = selected ? entry.key : null;
                    _applyFilters();
                  },
                ),
              for (final entry in _statusLabels.entries)
                ChoiceChip(
                  label: Text(entry.value),
                  selected: _status == entry.key,
                  onSelected: (selected) {
                    _status = selected ? entry.key : null;
                    _applyFilters();
                  },
                ),
            ],
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
                return RefreshIndicator(
                  onRefresh: _refresh,
                  child: ListView(
                    children: const [
                      SizedBox(height: 120),
                      EmptyView(message: 'Belum ada pengajuan persetujuan.'),
                    ],
                  ),
                );
              }
              return RefreshIndicator(
                onRefresh: _refresh,
                child: ListView.separated(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  itemCount: items.length,
                  separatorBuilder: (_, _) =>
                      const SizedBox(height: AppSpacing.md),
                  itemBuilder: (context, index) {
                    final item = asMap(items[index]);
                    return DutaCard(
                      onTap: () async {
                        await Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => SupervisorApprovalDetailScreen(
                              apiClient: widget.apiClient,
                              approvalId: item['id'].toString(),
                            ),
                          ),
                        );
                        _refresh();
                      },
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  compact(item['request_number']),
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ),
                              if (item['status'] != null)
                                StatusBadge(item['status']),
                            ],
                          ),
                          const SizedBox(height: AppSpacing.xs),
                          Text(
                            _typeLabels[item['type']] ?? compact(item['type']),
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                          const SizedBox(height: AppSpacing.xs),
                          Text(
                            compact(item['reason']),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                          const SizedBox(height: AppSpacing.xs),
                          Row(
                            children: [
                              Text(
                                money(item['amount']),
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const Spacer(),
                              Text(
                                compact(asMap(item['requester'])['name']),
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                            ],
                          ),
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
