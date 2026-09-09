import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../services/location_service.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';
import 'signature_capture_screen.dart';

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
  bool _completing = false;
  String? _createdVisitId;
  bool _hasSignature = false;
  final _locationService = const LocationService();

  @override
  void dispose() {
    _purposeController.dispose();
    _resultController.dispose();
    _metWithController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  bool get _signatureRequired => _status == 'completed';

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

      final result = await widget.apiClient
          .postJson('units/${widget.unit['id']}/visits', {
            'visit_date': DateTime.now().toIso8601String(),
            'purpose': _purposeController.text.trim(),
            'result': _resultController.text.trim(),
            'met_with': _metWithController.text.trim(),
            'notes': _notesController.text.trim(),
            'status': _status,
            if (lat != null) 'checkin_latitude': lat,
            if (lng != null) 'checkin_longitude': lng,
          });
      final visit = asMap(result.data);
      setState(() => _createdVisitId = visit['id']?.toString());

      if (lat != null && lng != null && _createdVisitId != null) {
        await widget.apiClient.postJson('visits/$_createdVisitId/evidence', {
          'type': 'gps',
          'latitude': lat,
          'longitude': lng,
        });
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

  Future<void> _requestSignature() async {
    if (_createdVisitId == null) return;
    final signed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => SignatureCaptureScreen(
          apiClient: widget.apiClient,
          visitId: _createdVisitId!,
        ),
      ),
    );
    if (signed == true && mounted) {
      setState(() => _hasSignature = true);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Tanda tangan berhasil disimpan.')),
      );
    }
  }

  Future<void> _finish() async {
    if (_signatureRequired && !_hasSignature) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Tanda tangan penghuni diperlukan. Silakan minta penghuni untuk melakukan tanda tangan terlebih dahulu.',
          ),
        ),
      );
      return;
    }
    if (!_signatureRequired) {
      Navigator.of(context).pop();
      return;
    }

    setState(() => _completing = true);
    try {
      await widget.apiClient.putJson('visits/$_createdVisitId', {
        'visit_date': DateTime.now().toIso8601String(),
        'purpose': _purposeController.text.trim(),
        'result': _resultController.text.trim(),
        'met_with': _metWithController.text.trim(),
        'notes': _notesController.text.trim(),
        'status': _status,
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Kunjungan berhasil diselesaikan.')),
        );
        Navigator.of(context).pop();
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _completing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final unit = widget.unit;
    final colors = Theme.of(context).colorScheme;
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
            if (_signatureRequired) ...[
              const SizedBox(height: AppSpacing.lg),
              DutaCard(
                color: _hasSignature
                    ? colors.primaryContainer
                    : colors.errorContainer,
                padding: const EdgeInsets.all(AppSpacing.md),
                child: Row(
                  children: [
                    Icon(
                      _hasSignature
                          ? Icons.check_circle_outline
                          : Icons.warning_amber_outlined,
                      color: _hasSignature
                          ? colors.onPrimaryContainer
                          : colors.onErrorContainer,
                    ),
                    const SizedBox(width: AppSpacing.sm),
                    Expanded(
                      child: Text(
                        _hasSignature
                            ? 'Tanda tangan penghuni sudah tersimpan.'
                            : 'Tanda tangan penghuni belum diberikan.',
                        style: TextStyle(
                          fontWeight: FontWeight.w700,
                          color: _hasSignature
                              ? colors.onPrimaryContainer
                              : colors.onErrorContainer,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.sm),
              OutlinedButton.icon(
                onPressed: _requestSignature,
                icon: const Icon(Icons.draw_outlined),
                label: Text(
                  _hasSignature
                      ? 'Tanda Tangan Ulang'
                      : 'Minta Tanda Tangan Penghuni',
                ),
              ),
            ],
            const SizedBox(height: AppSpacing.lg),
            FilledButton(
              onPressed: _completing ? null : _finish,
              child: _completing
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Text(
                      _signatureRequired ? 'Selesaikan Kunjungan' : 'Selesai',
                    ),
            ),
          ],
        ],
      ),
    );
  }
}
