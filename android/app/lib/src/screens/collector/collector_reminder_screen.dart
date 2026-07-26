import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';

String? _normalizeWhatsAppPhone(String? phone) {
  final digits = (phone ?? '').replaceAll(RegExp(r'\D'), '');
  if (digits.isEmpty) return null;
  if (digits.startsWith('62')) return digits;
  if (digits.startsWith('0')) return '62${digits.substring(1)}';
  return '62$digits';
}

class CollectorReminderScreen extends StatefulWidget {
  const CollectorReminderScreen({
    required this.apiClient,
    this.unit,
    super.key,
  });

  final ApiClient apiClient;
  final Map<String, dynamic>? unit;

  @override
  State<CollectorReminderScreen> createState() =>
      _CollectorReminderScreenState();
}

class _CollectorReminderScreenState extends State<CollectorReminderScreen> {
  final _phoneController = TextEditingController();
  final _messageController = TextEditingController();
  bool _sending = false;

  @override
  void initState() {
    super.initState();
    final unit = widget.unit;
    if (unit != null) {
      final resident = asMap(unit['resident']);
      _phoneController.text = compact(resident['phone']).replaceAll('-', '');
      _messageController.text =
          'Yth. ${compact(resident['name'])},\n\nKami informasikan bahwa unit ${compact(unit['id'])} memiliki tagihan yang belum diselesaikan. Mohon segera melakukan pembayaran. Terima kasih.';
    }
  }

  @override
  void dispose() {
    _phoneController.dispose();
    _messageController.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final unit = widget.unit;
    if (unit == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pilih unit terlebih dahulu dari daftar Unit Saya.')),
      );
      return;
    }
    final normalized = _normalizeWhatsAppPhone(_phoneController.text);
    if (normalized == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Nomor telepon tidak valid.')),
      );
      return;
    }
    final uri = Uri.parse(
      'https://wa.me/$normalized?text=${Uri.encodeComponent(_messageController.text)}',
    );
    setState(() => _sending = true);
    try {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
      await widget.apiClient.postJson('collector-reminders', {
        'unit_id': unit['id'],
        'message': _messageController.text,
        'phone': _phoneController.text,
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Pengingat dicatat.')),
        );
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Pengingat WhatsApp')),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          if (widget.unit != null)
            DutaCard(
              child: Text(
                '${compact(widget.unit!['id'])} — ${compact(asMap(widget.unit!['resident'])['name'])}',
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
          const SizedBox(height: AppSpacing.md),
          TextField(
            controller: _phoneController,
            keyboardType: TextInputType.phone,
            decoration: const InputDecoration(
              labelText: 'Nomor WhatsApp',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          TextField(
            controller: _messageController,
            maxLines: 6,
            decoration: const InputDecoration(
              labelText: 'Pesan',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          FilledButton.icon(
            onPressed: _sending ? null : _send,
            icon: const Icon(Icons.send_rounded),
            label: const Text('Buka WhatsApp & Catat'),
          ),
        ],
      ),
    );
  }
}
