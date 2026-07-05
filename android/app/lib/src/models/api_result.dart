class ApiResult {
  const ApiResult({required this.data, this.message = '', this.meta});

  final dynamic data;
  final String message;
  final Map<String, dynamic>? meta;
}
