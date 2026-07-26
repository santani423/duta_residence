import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';

const _statusOptions = [
  ('pending', 'Menunggu'),
  ('fulfilled', 'Terpenuhi'),
  ('broken', 'Tidak Terpenuhi'),
  ('rescheduled', 'Dijadwalkan Ulang'),
];

class PtpFormScreen extends StatefulWidget {
  const PtpFormScreen({
    required this.apiClient,
    required this.unit,
    super.key,
  });

  final ApiClient apiClient;
  final Map<String, dynamic> unit;

  @override
  State<PtpFormScreen> createState() => _PtpFormScreenState();
}

class _PtpFormScreenState extends State<PtpFormScreen> {
  final _amountController = TextEditingController();
  final _methodController = TextEditingController();
  final _reasonController = TextEditingController();
  DateTime _promisedDate = DateTime.now().add(const Duration(days: 3));
  String _status = 'pending';
  bool _saving = false;

  @override
  void dispose() {
    _amountController.dispose();
    _methodController.dispose();
    _reasonController.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _promisedDate,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (picked != null) setState(() => _promisedDate = picked);
  }

  Future<void> _submit() async {
    final amount = num.tryParse(_amountController.text.trim());
    if (amount == null || amount <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Jumlah janji bayar tidak valid.')),
      );
      return;
    }
    setState(() => _saving = true);
    try {
      await widget.apiClient.postJson(
        'units/${widget.unit['id']}/payment-promises',
        {
          'promised_amount': amount,
          'promised_date':
              '${_promisedDate.year}-${_promisedDate.month.toString().padLeft(2, '0')}-${_promisedDate.day.toString().padLeft(2, '0')}',
          'payment_method': _methodController.text.trim(),
          'reason': _reasonController.text.trim(),
          'status': _status,
        },
      );
      if (mounted) Navigator.of(context).pop();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Catat Janji Pembayaran')),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          DutaCard(
            child: Text(
              '${compact(widget.unit['id'])} — ${compact(asMap(widget.unit['resident'])['name'])}',
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          TextField(
            controller: _amountController,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(
              labelText: 'Jumlah yang Dijanjikan (Rp)',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Tanggal Janji Bayar'),
            subtitle: Text(dateOnly(_promisedDate.toIso8601String())),
            trailing: const Icon(Icons.calendar_month_outlined),
            onTap: _pickDate,
          ),
          TextField(
            controller: _methodController,
            decoration: const InputDecoration(
              labelText: 'Metode Pembayaran (opsional)',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          DropdownButtonFormField<String>(
            initialValue: _status,
            items: [
              for (final (value, label) in _statusOptions)
                DropdownMenuItem(value: value, child: Text(label)),
            ],
            onChanged: (value) => setState(() => _status = value ?? _status),
            decoration: const InputDecoration(labelText: 'Status'),
          ),
          const SizedBox(height: AppSpacing.md),
          TextField(
            controller: _reasonController,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: 'Alasan / Catatan',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          FilledButton.icon(
            onPressed: _saving ? null : _submit,
            icon: const Icon(Icons.save_outlined),
            label: const Text('Simpan Janji Pembayaran'),
          ),
        ],
      ),
    );
  }
}
