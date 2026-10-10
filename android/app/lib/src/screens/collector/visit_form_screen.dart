import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../services/location_service.dart';
import '../../theme/app_status_colors.dart';
import '../../utils/formatters.dart';
import '../../utils/visit_labels.dart';
import '../../widgets/duta_card.dart';
import 'signature_capture_screen.dart';

class VisitFormScreen extends StatefulWidget {
  const VisitFormScreen({
    required this.apiClient,
    required this.unit,
    this.visit,
    super.key,
  });

  final ApiClient apiClient;
  final Map<String, dynamic> unit;

  /// An already saved visit (a row from `residents/{id}/visits`) to resume,
  /// typically one still waiting for the resident's signature. The input stage
  /// is skipped: the stored values are shown read-only and the evidence
  /// section is available straight away.
  final Map<String, dynamic>? visit;

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
  // The create request is on its way. Leaving now would still save a visit
  // the collector never saw confirmed, so back navigation waits for it.
  bool _sendingVisit = false;
  bool _completing = false;
  String? _createdVisitId;
  // Sent back unchanged by the fallback finish request, so the visit keeps the
  // moment it was recorded rather than the moment it was finished.
  String? _visitDate;
  bool _hasSignature = false;
  // Whether the server already counts the visit as finished (lifecycle
  // completed). Storing the resident's signature does that for a "Selesai"
  // visit, so finishing it afterwards needs no further request.
  bool _finishedOnServer = false;
  final _locationService = const LocationService();

  @override
  void initState() {
    super.initState();
    final visit = widget.visit;
    if (visit == null) return;
    _createdVisitId = visit['id']?.toString();
    _visitDate = _localVisitDate(visit['visit_date']);
    _purposeController.text = _text(visit['purpose']);
    _metWithController.text = _text(visit['met_with']);
    _resultController.text = _text(visit['result']);
    _notesController.text = _text(visit['notes']);
    for (final (value, _) in visitStatusOptions) {
      if (value == visit['status']) _status = value;
    }
    _hasSignature = visitHasSignature(visit);
    _finishedOnServer = visit['lifecycle'] == 'completed';
  }

  @override
  void dispose() {
    _purposeController.dispose();
    _resultController.dispose();
    _metWithController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  bool get _signatureRequired => _status == 'completed';

  /// Saved as "Selesai" but not signed yet: the visit stays "Menunggu tanda
  /// tangan" on the server and does not count as finished.
  bool get _awaitingSignature =>
      _createdVisitId != null && _signatureRequired && !_hasSignature;

  static String _text(Object? value) => value == null ? '' : value.toString();

  /// The API serialises `visit_date` in UTC ("...Z") while the server stores
  /// the wall-clock time it receives - local time, as sent by this form on
  /// creation. Echoing the UTC string back would shift the visit by the
  /// timezone offset, so a resumed visit sends its original moment as local
  /// time again. Only the fallback finish request uses it; the round trip is
  /// exact when the phone runs on the server's timezone (WIB).
  static String? _localVisitDate(Object? value) {
    final raw = value?.toString();
    if (raw == null || raw.isEmpty) return null;
    return DateTime.tryParse(raw)?.toLocal().toIso8601String() ?? raw;
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
      // Leaving the form while the GPS fix was pending cancels the save.
      if (!mounted) return;

      final visitDate = DateTime.now().toIso8601String();
      setState(() => _sendingVisit = true);
      final result = await widget.apiClient
          .postJson('units/${widget.unit['id']}/visits', {
            'visit_date': visitDate,
            'purpose': _purposeController.text.trim(),
            'result': _resultController.text.trim(),
            'met_with': _metWithController.text.trim(),
            'notes': _notesController.text.trim(),
            'status': _status,
            'checkin_latitude': ?lat,
            'checkin_longitude': ?lng,
          });
      final visit = asMap(result.data);
      if (!mounted) return;
      setState(() {
        _sendingVisit = false;
        _createdVisitId = visit['id']?.toString();
        _visitDate = visitDate;
        _hasSignature = visitHasSignature(visit);
        _finishedOnServer = visit['lifecycle'] == 'completed';
      });

      if (lat != null && lng != null && _createdVisitId != null) {
        try {
          await widget.apiClient.postJson('visits/$_createdVisitId/evidence', {
            'type': 'gps',
            'latitude': lat,
            'longitude': lng,
          });
        } on ApiException {
          // The visit (with its check-in coordinates) is already saved; a
          // failed GPS evidence row must not hide that from the collector.
        }
      }

      if (mounted) {
        // For "Selesai" the server answers that the visit still needs the
        // resident's signature before it counts as finished.
        final message = result.message.isNotEmpty
            ? result.message
            : _signatureRequired
            ? 'Kunjungan tersimpan. Minta tanda tangan penghuni untuk menyelesaikannya.'
            : 'Kunjungan berhasil dicatat.';
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(message)));
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) {
        setState(() {
          _saving = false;
          _sendingVisit = false;
        });
      }
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
      // Storing the signature is what completes a "Selesai" visit server-side.
      setState(() {
        _hasSignature = true;
        _finishedOnServer = true;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Tanda tangan berhasil disimpan. Kunjungan selesai.'),
        ),
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
    if (_finishedOnServer) {
      // The stored signature already finished the visit server-side. A PUT
      // would only re-send the stored values - and could fail offline, making
      // a finished visit look unfinished.
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Kunjungan berhasil diselesaikan.')),
      );
      Navigator.of(context).pop();
      return;
    }

    // Signed but still in progress on the server (a resumed visit that already
    // carried a signature): finalise it explicitly.
    setState(() => _completing = true);
    try {
      await widget.apiClient.putJson('visits/$_createdVisitId', {
        'visit_date': _visitDate,
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

  /// Back navigation while the resident has not signed yet. The visit is
  /// already saved, so leaving is allowed - it just has to be deliberate. The
  /// main action goes straight to the signature that finishes the visit.
  Future<void> _confirmLeave() async {
    final leave = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Kunjungan belum selesai'),
        content: const Text(
          'Penghuni belum menandatangani. Kunjungan tersimpan sebagai "Menunggu tanda tangan" dan dapat dilanjutkan dari detail unit.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Keluar'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Lanjutkan Tanda Tangan'),
          ),
        ],
      ),
    );
    if (!mounted) return;
    if (leave == true) {
      Navigator.of(context).pop();
    } else if (leave == false) {
      // A barrier tap (null) only closes the dialog.
      await _requestSignature();
    }
  }

  @override
  Widget build(BuildContext context) {
    final unit = widget.unit;
    final visit = widget.visit;
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    // Waiting for the signature is a pending state rather than an error.
    final awaitingColors =
        theme.extension<AppStatusColors>()?.warning ??
        StatusColorPair(
          container: colors.errorContainer,
          onContainer: colors.onErrorContainer,
        );
    return PopScope(
      // Leaving waits while the visit is being sent (it would be saved without
      // the collector seeing it) and needs confirmation while the saved visit
      // still waits for the signature.
      canPop: !_awaitingSignature && !_sendingVisit,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop && !_sendingVisit) _confirmLeave();
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(
            visit == null ? 'Catat Kunjungan' : 'Lanjutkan Kunjungan',
          ),
        ),
        body: ListView(
          padding: const EdgeInsets.all(AppSpacing.lg),
          children: [
            DutaCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${compact(unit['id'])} — ${compact(asMap(unit['resident'])['name'])}',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  if (visit != null) ...[
                    const SizedBox(height: AppSpacing.xs),
                    Text(
                      'Waktu kunjungan: ${dateTime(visit['visit_date'])}',
                      style: theme.textTheme.bodySmall,
                    ),
                  ],
                ],
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
                for (final (value, label) in visitStatusOptions)
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
                icon: _saving
                    ? const SizedBox(
                        height: 18,
                        width: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.save_outlined),
                label: const Text('Simpan Kunjungan'),
              )
            else ...[
              const Divider(height: AppSpacing.xxl),
              const SectionHeader(title: 'Bukti Kunjungan'),
              // The signature is what finishes a "Selesai" visit, so it leads
              // the evidence section.
              if (_signatureRequired) ...[
                const SizedBox(height: AppSpacing.md),
                DutaCard(
                  color: _hasSignature
                      ? colors.primaryContainer
                      : awaitingColors.container,
                  padding: const EdgeInsets.all(AppSpacing.md),
                  child: Row(
                    children: [
                      Icon(
                        _hasSignature
                            ? Icons.check_circle_outline
                            : Icons.hourglass_top_rounded,
                        color: _hasSignature
                            ? colors.onPrimaryContainer
                            : awaitingColors.onContainer,
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: Text(
                          _hasSignature
                              ? 'Tanda tangan penghuni sudah tersimpan. Kunjungan selesai.'
                              : 'Menunggu tanda tangan penghuni.',
                          style: TextStyle(
                            fontWeight: FontWeight.w700,
                            color: _hasSignature
                                ? colors.onPrimaryContainer
                                : awaitingColors.onContainer,
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
              const SizedBox(height: AppSpacing.md),
              OutlinedButton.icon(
                onPressed: _capturePhoto,
                icon: const Icon(Icons.camera_alt_outlined),
                label: const Text('Ambil Foto Bukti'),
              ),
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
      ),
    );
  }
}
