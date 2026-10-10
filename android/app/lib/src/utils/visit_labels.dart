import 'formatters.dart';

/// Collector visit outcomes (the `status` column) with their UI labels, in the
/// order the visit form offers them.
const visitStatusOptions = [
  ('completed', 'Selesai'),
  ('no_answer', 'Tidak Ada Jawaban'),
  ('refused', 'Menolak'),
  ('rescheduled', 'Dijadwalkan Ulang'),
];

/// A visit saved as "Selesai" that the resident has not signed yet.
const visitAwaitingSignatureLabel = 'Menunggu tanda tangan';

/// A visit that carries the resident's signature.
const visitSignedLabel = 'Ditandatangani';

/// Indonesian label for a visit outcome; unknown values fall back to a
/// title-cased version of the raw status.
String visitStatusLabel(Object? status) {
  for (final (value, label) in visitStatusOptions) {
    if (value == status) return label;
  }
  return titleCaseStatus(status);
}

/// Whether a visit was saved as "Selesai" but still waits for the resident's
/// signature, so it must not be treated as a finished visit yet.
///
/// Prefers the API's `awaiting_signature` flag and falls back to the raw
/// columns (status completed + lifecycle in_progress) for payloads without it.
/// Legacy visits keep lifecycle completed, so they are never awaiting even
/// when they have no signature.
bool visitAwaitingSignature(Map<String, dynamic> visit) {
  final flag = visit['awaiting_signature'];
  if (flag != null) return _isTrue(flag);
  return visit['status'] == 'completed' && visit['lifecycle'] == 'in_progress';
}

/// Whether the visit carries a (non-deleted) resident signature.
bool visitHasSignature(Map<String, dynamic> visit) =>
    _isTrue(visit['has_signature']);

bool _isTrue(Object? value) =>
    value == true || value == 1 || value == '1' || value == 'true';
