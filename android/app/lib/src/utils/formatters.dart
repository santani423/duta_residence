import 'package:intl/intl.dart';

final _currency = NumberFormat.currency(
  locale: 'id_ID',
  symbol: 'Rp ',
  decimalDigits: 0,
);
final _date = DateFormat('dd MMM yyyy', 'id_ID');
final _dateTime = DateFormat('dd MMM yyyy, HH:mm', 'id_ID');

String compact(Object? value) {
  if (value == null) return '-';
  final text = value.toString();
  return text.trim().isEmpty || text == 'null' ? '-' : text;
}

String money(Object? value) {
  if (value is num) return _currency.format(value);
  return _currency.format(num.tryParse(value?.toString() ?? '') ?? 0);
}

String dateOnly(Object? value) => _formatDate(value, _date);

String dateTime(Object? value) => _formatDate(value, _dateTime);

String _formatDate(Object? value, DateFormat format) {
  if (value == null || value.toString().isEmpty) return '-';
  final parsed = DateTime.tryParse(value.toString());
  if (parsed == null) return value.toString();
  return format.format(parsed.toLocal());
}

List<dynamic> asList(Object? value) => value is List ? value : const [];

Map<String, dynamic> asMap(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

String titleCaseStatus(Object? status) {
  return compact(status)
      .replaceAll('_', ' ')
      .split(' ')
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');
}
