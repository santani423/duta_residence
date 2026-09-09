import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:signature/signature.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../widgets/duta_card.dart';

/// Full-screen resident-signature capture flow for a collector visit.
///
/// Draw -> preview/confirm -> upload. Kept as its own screen (rather than an
/// inline widget) so the drawing gesture never fights the visit form's
/// scroll view and so accidental taps on other controls are impossible while
/// the resident is signing.
class SignatureCaptureScreen extends StatefulWidget {
  const SignatureCaptureScreen({
    required this.apiClient,
    required this.visitId,
    super.key,
  });

  final ApiClient apiClient;
  final String visitId;

  @override
  State<SignatureCaptureScreen> createState() => _SignatureCaptureScreenState();
}

class _SignatureCaptureScreenState extends State<SignatureCaptureScreen> {
  final _signatureController = SignatureController(
    penStrokeWidth: 3,
    penColor: Colors.black,
  );

  Uint8List? _previewBytes;
  bool _uploading = false;
  String? _error;

  @override
  void dispose() {
    _signatureController.dispose();
    super.dispose();
  }

  Future<void> _confirmDrawing() async {
    if (_signatureController.isEmpty) {
      setState(
        () => _error =
            'Silakan minta penghuni membubuhkan tanda tangan terlebih dahulu.',
      );
      return;
    }
    final bytes = await _signatureController.toPngBytes();
    if (bytes == null) return;
    setState(() {
      _previewBytes = bytes;
      _error = null;
    });
  }

  void _redo() {
    setState(() {
      _previewBytes = null;
      _error = null;
    });
    _signatureController.clear();
  }

  Future<void> _submit() async {
    final bytes = _previewBytes;
    if (bytes == null) return;
    setState(() {
      _uploading = true;
      _error = null;
    });
    try {
      final directory = await getTemporaryDirectory();
      final file = File(
        '${directory.path}/signature-${widget.visitId}-${DateTime.now().millisecondsSinceEpoch}.png',
      );
      await file.writeAsBytes(bytes, flush: true);
      await widget.apiClient.postMultipart(
        'visits/${widget.visitId}/evidence',
        fields: const {'type': 'signature'},
        fileField: 'file',
        filePath: file.path,
        fileName: 'signature.png',
      );
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (error) {
      setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isPreview = _previewBytes != null;
    return Scaffold(
      appBar: AppBar(title: const Text('Tanda Tangan Penghuni')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                isPreview
                    ? 'Apakah tanda tangan sudah benar?'
                    : 'Silakan minta penghuni untuk membubuhkan tanda tangan di bawah ini.',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: AppSpacing.lg),
              Expanded(
                child: Container(
                  decoration: BoxDecoration(
                    border: Border.all(
                      color: Theme.of(context).colorScheme.outlineVariant,
                    ),
                    borderRadius: BorderRadius.circular(AppSpacing.radiusSm),
                    color: Colors.white,
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: isPreview
                      ? Center(child: Image.memory(_previewBytes!))
                      : Signature(
                          controller: _signatureController,
                          backgroundColor: Colors.white,
                        ),
                ),
              ),
              if (_error != null) ...[
                const SizedBox(height: AppSpacing.sm),
                DutaCard(
                  color: Theme.of(context).colorScheme.errorContainer,
                  padding: const EdgeInsets.all(AppSpacing.md),
                  child: Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.onErrorContainer,
                    ),
                  ),
                ),
              ],
              const SizedBox(height: AppSpacing.lg),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: _uploading
                          ? null
                          : (isPreview ? _redo : _signatureController.clear),
                      child: Text(isPreview ? 'Ulangi' : 'Hapus'),
                    ),
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    flex: 2,
                    child: FilledButton(
                      onPressed: _uploading
                          ? null
                          : (isPreview ? _submit : _confirmDrawing),
                      child: _uploading
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : Text(
                              isPreview ? 'Gunakan Tanda Tangan' : 'Konfirmasi',
                            ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
