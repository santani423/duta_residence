import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/info_row.dart';
import '../../widgets/state_views.dart';
import '../../widgets/status_badge.dart';

const _typeLabels = {
  'reversal': 'Pembatalan',
  'penalty_waiver': 'Keringanan Denda',
  'installment_plan': 'Rencana Cicilan',
  'billing_adjustment': 'Penyesuaian Tagihan',
};

class SupervisorApprovalDetailScreen extends StatefulWidget {
  const SupervisorApprovalDetailScreen({
    required this.apiClient,
    required this.approvalId,
    super.key,
  });

  final ApiClient apiClient;
  final String approvalId;

  @override
  State<SupervisorApprovalDetailScreen> createState() =>
      _SupervisorApprovalDetailScreenState();
}

class _SupervisorApprovalDetailScreenState
    extends State<SupervisorApprovalDetailScreen> {
  late Future<Map<String, dynamic>> _future;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final result = await widget.apiClient.get(
      'approval-requests/${widget.approvalId}',
    );
    return asMap(result.data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _decide({required bool approve}) async {
    final notes = await showDialog<String>(
      context: context,
      builder: (_) => _DecisionDialog(approve: approve),
    );
    if (notes == null || !mounted) return;
    if (!approve && notes.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Catatan penolakan wajib diisi.')),
      );
      return;
    }
    setState(() => _submitting = true);
    try {
      await widget.apiClient.postJson(
        'approval-requests/${widget.approvalId}/${approve ? 'approve' : 'reject'}',
        {if (notes.trim().isNotEmpty) 'notes': notes.trim()},
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              approve ? 'Pengajuan berhasil disetujui.' : 'Pengajuan berhasil ditolak.',
            ),
          ),
        );
      }
      await _refresh();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Detail Persetujuan')),
      body: FutureBuilder<Map<String, dynamic>>(
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
          final documents = asList(data['documents']);
          final requestable = asMap(data['requestable']);
          final isDecidable =
              data['status'] == 'submitted' || data['status'] == 'under_review';

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
                          Expanded(
                            child: Text(
                              compact(data['request_number']),
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w800),
                            ),
                          ),
                          if (data['status'] != null)
                            StatusBadge(data['status']),
                        ],
                      ),
                      const SizedBox(height: AppSpacing.lg),
                      InfoRows(
                        items: {
                          'Jenis':
                              _typeLabels[data['type']] ?? compact(data['type']),
                          'Alasan': data['reason'],
                          'Jumlah': money(data['amount']),
                          'Pemohon': asMap(data['requester'])['name'],
                          'Kolektor Terkait': asMap(
                            data['related_collector'],
                          )['name'],
                          'Unit Terkait': asMap(data['related_unit'])['id'],
                          'Penghuni Terkait': asMap(
                            data['related_resident'],
                          )['name'],
                          'Diputuskan Oleh': asMap(data['decider'])['name'],
                          'Diputuskan Pada': data['decided_at'] == null
                              ? null
                              : dateTime(data['decided_at']),
                        },
                      ),
                    ],
                  ),
                ),
                if (requestable.isNotEmpty) ...[
                  const SizedBox(height: AppSpacing.md),
                  DutaCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const SectionHeader(title: 'Detail Pengajuan'),
                        const SizedBox(height: AppSpacing.lg),
                        InfoRows(
                          items: {
                            for (final entry in requestable.entries)
                              if (entry.value is! Map && entry.value is! List)
                                entry.key: entry.value,
                          },
                        ),
                      ],
                    ),
                  ),
                ],
                if (documents.isNotEmpty) ...[
                  const SizedBox(height: AppSpacing.md),
                  DutaCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const SectionHeader(title: 'Dokumen Pendukung'),
                        const SizedBox(height: AppSpacing.md),
                        for (final document in documents)
                          Padding(
                            padding: const EdgeInsets.symmetric(
                              vertical: AppSpacing.xs,
                            ),
                            child: Text(
                              compact(asMap(document)['original_filename']),
                            ),
                          ),
                      ],
                    ),
                  ),
                ],
                if (isDecidable) ...[
                  const SizedBox(height: AppSpacing.xl),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: _submitting
                              ? null
                              : () => _decide(approve: false),
                          icon: const Icon(Icons.close_rounded),
                          label: const Text('Tolak'),
                        ),
                      ),
                      const SizedBox(width: AppSpacing.md),
                      Expanded(
                        child: FilledButton.icon(
                          onPressed: _submitting
                              ? null
                              : () => _decide(approve: true),
                          icon: const Icon(Icons.check_rounded),
                          label: const Text('Setujui'),
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}

class _DecisionDialog extends StatefulWidget {
  const _DecisionDialog({required this.approve});

  final bool approve;

  @override
  State<_DecisionDialog> createState() => _DecisionDialogState();
}

class _DecisionDialogState extends State<_DecisionDialog> {
  final _notesController = TextEditingController();

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.approve ? 'Setujui Pengajuan' : 'Tolak Pengajuan'),
      content: TextField(
        controller: _notesController,
        maxLines: 3,
        autofocus: true,
        decoration: InputDecoration(
          labelText: widget.approve
              ? 'Catatan (opsional)'
              : 'Catatan Penolakan (wajib)',
          border: const OutlineInputBorder(),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Batal'),
        ),
        FilledButton(
          onPressed: () => Navigator.of(context).pop(_notesController.text),
          child: Text(widget.approve ? 'Setujui' : 'Tolak'),
        ),
      ],
    );
  }
}
