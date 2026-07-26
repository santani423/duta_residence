import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path_provider/path_provider.dart';
import 'package:signature/signature.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../services/location_service.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';

const _statusOptions = [
  ('completed', 'Selesai'),
  ('no_answer', 'Tidak Ada Jawaban'),
  ('refused', 'Menolak'),
  ('rescheduled', 'Dijadwalkan Ulang'),
];

class VisitFormScreen extends StatefulWidget {
  const VisitFormScreen({
    required this.apiClient,
    required this.unit,
    super.key,
  });

  final ApiClient apiClient;
  final Map<String, dynamic> unit;

  @override
  State<VisitFormScreen> createState() => _VisitFormScreenState();
}

class _VisitFormScreenState extends State<VisitFormScreen> {
  final _purposeController = TextEditingController();
  final _resultController = TextEditingController();
  final _metWithController = TextEditingController();
  final _notesController = TextEditingController();
  String _status = 'completed';
  bool _saving = false;
  String? _createdVisitId;
  final _locationService = const LocationService();
  final _signatureController = SignatureController(
    penStrokeWidth: 3,
    penColor: Colors.black,
  );

  @override
  void dispose() {
    _purposeController.dispose();
    _resultController.dispose();
    _metWithController.dispose();
    _notesController.dispose();
    _signatureController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_purposeController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Tujuan kunjungan wajib diisi.')),
      );
      return;
    }
    setState(() => _saving = true);
    try {
      double? lat;
      double? lng;
      try {
        final fix = await _locationService.captureCurrentPosition();
        lat = fix.latitude;
        lng = fix.longitude;
      } catch (_) {
        // GPS check-in is best-effort - a visit can still be logged without it.
      }

      final result = await widget.apiClient.postJson(
        'units/${widget.unit['id']}/visits',
        {
          'visit_date': DateTime.now().toIso8601String(),
          'purpose': _purposeController.text.trim(),
          'result': _resultController.text.trim(),
          'met_with': _metWithController.text.trim(),
          'notes': _notesController.text.trim(),
          'status': _status,
          if (lat != null) 'checkin_latitude': lat,
          if (lng != null) 'checkin_longitude': lng,
        },
      );
      final visit = asMap(result.data);
      setState(() => _createdVisitId = visit['id']?.toString());

      if (lat != null && lng != null && _createdVisitId != null) {
        await widget.apiClient.postJson(
          'visits/$_createdVisitId/evidence',
          {'type': 'gps', 'latitude': lat, 'longitude': lng},
        );
      }

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Kunjungan berhasil dicatat.')),
        );
      }
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

  Future<void> _capturePhoto() async {
    if (_createdVisitId == null) return;
    final picker = ImagePicker();
    final file = await picker.pickImage(
      source: ImageSource.camera,
      imageQuality: 80,
    );
    if (file == null) return;
    try {
      await widget.apiClient.postMultipart(
        'visits/$_createdVisitId/evidence',
        fields: {'type': 'photo'},
        fileField: 'file',
        filePath: file.path,
        fileName: file.name,
      );
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Foto bukti diunggah.')));
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _uploadSignature() async {
    if (_createdVisitId == null || _signatureController.isEmpty) return;
    final bytes = await _signatureController.toPngBytes();
    if (bytes == null) return;
    final directory = await getTemporaryDirectory();
    final file = File(
      '${directory.path}/signature-$_createdVisitId.png',
    );
    await file.writeAsBytes(bytes, flush: true);
    try {
      await widget.apiClient.postMultipart(
        'visits/$_createdVisitId/evidence',
        fields: {'type': 'signature'},
        fileField: 'file',
        filePath: file.path,
        fileName: 'signature.png',
      );
      _signatureController.clear();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Tanda tangan diunggah.')),
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

  @override
  Widget build(BuildContext context) {
    final unit = widget.unit;
    return Scaffold(
      appBar: AppBar(title: const Text('Catat Kunjungan')),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          DutaCard(
            child: Text(
              '${compact(unit['id'])} — ${compact(asMap(unit['resident'])['name'])}',
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          TextField(
            controller: _purposeController,
            enabled: _createdVisitId == null,
            decoration: const InputDecoration(
              labelText: 'Tujuan Kunjungan',
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
            onChanged: _createdVisitId == null
                ? (value) => setState(() => _status = value ?? _status)
                : null,
            decoration: const InputDecoration(labelText: 'Status Kunjungan'),
          ),
          const SizedBox(height: AppSpacing.md),
          TextField(
            controller: _metWithController,
            enabled: _createdVisitId == null,
            decoration: const InputDecoration(
              labelText: 'Bertemu Dengan',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          TextField(
            controller: _resultController,
            enabled: _createdVisitId == null,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: 'Hasil Kunjungan',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          TextField(
            controller: _notesController,
            enabled: _createdVisitId == null,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: 'Catatan Tambahan',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          if (_createdVisitId == null)
            FilledButton.icon(
              onPressed: _saving ? null : _submit,
              icon: const Icon(Icons.save_outlined),
              label: const Text('Simpan Kunjungan'),
            )
          else ...[
            const Divider(height: AppSpacing.xxl),
            const SectionHeader(title: 'Bukti Kunjungan'),
            const SizedBox(height: AppSpacing.md),
            OutlinedButton.icon(
              onPressed: _capturePhoto,
              icon: const Icon(Icons.camera_alt_outlined),
              label: const Text('Ambil Foto Bukti'),
            ),
            const SizedBox(height: AppSpacing.lg),
            const Text('Tanda Tangan Penghuni'),
            const SizedBox(height: AppSpacing.sm),
            Container(
              decoration: BoxDecoration(
                border: Border.all(
                  color: Theme.of(context).colorScheme.outlineVariant,
                ),
                borderRadius: BorderRadius.circular(AppSpacing.radiusSm),
              ),
              child: Signature(
                controller: _signatureController,
                height: 180,
                backgroundColor: Colors.white,
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            Row(
              children: [
                TextButton(
                  onPressed: _signatureController.clear,
                  child: const Text('Hapus'),
                ),
                const Spacer(),
                FilledButton(
                  onPressed: _uploadSignature,
                  child: const Text('Simpan Tanda Tangan'),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.lg),
            FilledButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Selesai'),
            ),
          ],
        ],
      ),
    );
  }
}
