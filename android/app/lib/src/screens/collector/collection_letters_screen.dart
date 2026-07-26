import 'dart:io';

import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/state_views.dart';

const _letterTypeLabels = {
  'reminder': 'Pengingat',
  'warning': 'Peringatan',
  'final_notice': 'Peringatan Terakhir',
};

class CollectionLettersScreen extends StatefulWidget {
  const CollectionLettersScreen({
    required this.apiClient,
    this.unitId,
    super.key,
  });

  final ApiClient apiClient;
  final String? unitId;

  @override
  State<CollectionLettersScreen> createState() =>
      _CollectionLettersScreenState();
}

class _CollectionLettersScreenState extends State<CollectionLettersScreen> {
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final result = await widget.apiClient.get(
      'collection-letters',
      query: widget.unitId != null ? {'unit_id': widget.unitId} : const {},
    );
    return asList(result.data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _download(Map<String, dynamic> letter) async {
    try {
      final bytes = await widget.apiClient.downloadBytes(
        'collection-letters/${letter['id']}/download',
      );
      final directory = await getTemporaryDirectory();
      final file = File('${directory.path}/surat-penagihan-${letter['id']}.pdf');
      await file.writeAsBytes(bytes, flush: true);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Surat tersimpan: ${file.path}')),
        );
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _createLetter() async {
    if (widget.unitId == null) return;
    final result = await showDialog<Map<String, String>>(
      context: context,
      builder: (_) => _CreateLetterDialog(),
    );
    if (result == null) return;
    try {
      await widget.apiClient.postJson('collection-letters', {
        'unit_id': widget.unitId,
        'letter_type': result['letter_type'],
        'content': result['content'],
      });
      await _refresh();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Surat Penagihan'),
        actions: [
          if (widget.unitId != null)
            IconButton(
              icon: const Icon(Icons.add_rounded),
              onPressed: _createLetter,
            ),
        ],
      ),
      body: FutureBuilder<List<dynamic>>(
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
            return const EmptyView(message: 'Belum ada surat penagihan.');
          }
          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView.separated(
              padding: const EdgeInsets.all(AppSpacing.lg),
              itemCount: items.length,
              separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
              itemBuilder: (context, index) {
                final letter = asMap(items[index]);
                return DutaCard(
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _letterTypeLabels[letter['letter_type']] ??
                                  compact(letter['letter_type']),
                              style: const TextStyle(fontWeight: FontWeight.w800),
                            ),
                            const SizedBox(height: AppSpacing.xs),
                            Text(
                              compact(asMap(letter['resident'])['name']),
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                            Text(
                              dateTime(letter['generated_at']),
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ],
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.download_outlined),
                        onPressed: () => _download(letter),
                      ),
                    ],
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class _CreateLetterDialog extends StatefulWidget {
  @override
  State<_CreateLetterDialog> createState() => _CreateLetterDialogState();
}

class _CreateLetterDialogState extends State<_CreateLetterDialog> {
  String _type = 'reminder';
  final _contentController = TextEditingController();

  @override
  void dispose() {
    _contentController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Buat Surat Penagihan'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          DropdownButtonFormField<String>(
            initialValue: _type,
            items: _letterTypeLabels.entries
                .map(
                  (entry) => DropdownMenuItem(
                    value: entry.key,
                    child: Text(entry.value),
                  ),
                )
                .toList(),
            onChanged: (value) => setState(() => _type = value ?? _type),
            decoration: const InputDecoration(labelText: 'Jenis Surat'),
          ),
          const SizedBox(height: AppSpacing.md),
          TextField(
            controller: _contentController,
            maxLines: 5,
            decoration: const InputDecoration(
              labelText: 'Isi Surat',
              border: OutlineInputBorder(),
            ),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Batal'),
        ),
        FilledButton(
          onPressed: () => Navigator.of(context).pop({
            'letter_type': _type,
            'content': _contentController.text,
          }),
          child: const Text('Buat'),
        ),
      ],
    );
  }
}
