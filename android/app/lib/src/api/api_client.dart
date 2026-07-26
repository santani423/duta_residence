import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:http/http.dart' as http;

import '../constants/app_config.dart';
import '../models/api_result.dart';
import '../storage/token_store.dart';
import 'api_exception.dart';

typedef UnauthorizedCallback = Future<void> Function(String? code);

class ApiClient {
  ApiClient({
    required this._tokenStore,
    http.Client? httpClient,
    String baseUrl = AppConfig.apiBaseUrl,
  }) : _http = httpClient ?? http.Client(),
       _baseUri = Uri.parse(baseUrl);

  final TokenStore _tokenStore;
  final http.Client _http;
  final Uri _baseUri;
  UnauthorizedCallback? onUnauthorized;

  Future<ApiResult> get(
    String path, {
    Map<String, Object?> query = const {},
  }) async {
    final headers = await _headers();
    return _send(() => _http.get(_uri(path, query), headers: headers));
  }

  Future<ApiResult> postJson(String path, Map<String, Object?> body) async {
    final headers = await _headers(jsonBody: true);
    return _send(
      () => _http.post(_uri(path), headers: headers, body: jsonEncode(body)),
    );
  }

  Future<ApiResult> putJson(String path, Map<String, Object?> body) async {
    final headers = await _headers(jsonBody: true);
    return _send(
      () => _http.put(_uri(path), headers: headers, body: jsonEncode(body)),
    );
  }

  Future<ApiResult> delete(String path) async {
    final headers = await _headers();
    return _send(() => _http.delete(_uri(path), headers: headers));
  }

  Future<ApiResult> postMultipart(
    String path, {
    Map<String, String> fields = const {},
    PlatformFile? file,
    String fileField = 'attachment',
    // Alternative to `file` for callers that already have a real on-disk
    // path (image_picker's XFile, or bytes written out via path_provider,
    // e.g. a signature capture) instead of file_picker's PlatformFile.
    String? filePath,
    String? fileName,
  }) async {
    return _send(() async {
      final request = http.MultipartRequest('POST', _uri(path));
      request.headers.addAll(await _headers());
      request.fields.addAll(fields);
      if (file?.path != null) {
        request.files.add(
          await http.MultipartFile.fromPath(
            fileField,
            file!.path!,
            filename: file.name,
          ),
        );
      } else if (filePath != null) {
        request.files.add(
          await http.MultipartFile.fromPath(
            fileField,
            filePath,
            filename: fileName,
          ),
        );
      }
      final streamed = await request.send();
      return http.Response.fromStream(streamed);
    });
  }

  Future<List<int>> downloadBytes(String path) async {
    final headers = await _headers();
    return _handleDownload(
      await _request(() => _http.get(_uri(path), headers: headers)),
    );
  }

  Future<List<int>> downloadDocument(Map<String, dynamic> document) async {
    final headers = await _headers();
    return _handleDownload(
      await _request(
        () => _http.get(downloadUriForDocument(document), headers: headers),
      ),
    );
  }

  List<int> _handleDownload(http.Response response) {
    if (response.statusCode >= 200 && response.statusCode < 300) {
      return response.bodyBytes;
    }
    throw ApiException(
      'Dokumen gagal diunduh.',
      statusCode: response.statusCode,
    );
  }

  Uri downloadUriForDocument(Map<String, dynamic> document) {
    final type = document['download_type']?.toString();
    final id = Uri.encodeComponent(document['download_id']?.toString() ?? '');
    return switch (type) {
      'payment_receipt' => _uri('resident/payments/$id/receipt/download'),
      'receipt' => _uri('resident/receipts/$id/download'),
      _ => _uri('resident/invoices/$id/download'),
    };
  }

  Future<ApiResult> _send(Future<http.Response> Function() request) async {
    return _handle(await _request(request));
  }

  static const _requestTimeout = Duration(seconds: 20);
  static const _connectionErrorMessage =
      'Koneksi API tidak tersedia. Periksa server atau jaringan Anda.';

  Future<http.Response> _request(
    Future<http.Response> Function() request,
  ) async {
    try {
      return await request().timeout(_requestTimeout);
    } catch (error) {
      if (!_isConnectivityError(error)) rethrow;
      try {
        return await request().timeout(_requestTimeout);
      } catch (error) {
        if (!_isConnectivityError(error)) rethrow;
        throw const ApiException(_connectionErrorMessage);
      }
    }
  }

  bool _isConnectivityError(Object error) =>
      error is SocketException ||
      error is HandshakeException ||
      error is TimeoutException ||
      error is http.ClientException;

  Future<ApiResult> _handle(http.Response response) async {
    final decoded = _decode(response.body);
    if (response.statusCode == 401) {
      final code = decoded is Map ? decoded['code']?.toString() : null;
      await onUnauthorized?.call(code);
    }

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException(
        decoded is Map && decoded['message'] != null
            ? decoded['message'].toString()
            : 'Request gagal diproses.',
        statusCode: response.statusCode,
        errors: decoded is Map ? decoded['errors'] : null,
      );
    }

    if (decoded is Map<String, dynamic>) {
      final success = decoded['success'];
      if (success == false) {
        throw ApiException(
          decoded['message']?.toString() ?? 'Request gagal diproses.',
        );
      }
      return ApiResult(
        data: decoded['data'],
        message: decoded['message']?.toString() ?? '',
        meta: decoded['meta'] is Map<String, dynamic>
            ? decoded['meta'] as Map<String, dynamic>
            : null,
      );
    }
    return ApiResult(data: decoded);
  }

  dynamic _decode(String body) {
    if (body.trim().isEmpty) return null;
    try {
      return jsonDecode(body);
    } on FormatException {
      return body;
    }
  }

  Future<Map<String, String>> _headers({bool jsonBody = false}) async {
    final headers = <String, String>{
      HttpHeaders.acceptHeader: 'application/json',
    };
    if (jsonBody) headers[HttpHeaders.contentTypeHeader] = 'application/json';
    final token = await _tokenStore.read();
    if (token != null && token.isNotEmpty) {
      headers[HttpHeaders.authorizationHeader] = 'Bearer $token';
    }
    return headers;
  }

  Uri _uri(String path, [Map<String, Object?> query = const {}]) {
    final cleanPath = path.startsWith('/') ? path.substring(1) : path;
    final basePath = _baseUri.path.endsWith('/')
        ? _baseUri.path
        : '${_baseUri.path}/';
    return _baseUri.replace(
      path: '$basePath$cleanPath',
      queryParameters: {
        ..._baseUri.queryParameters,
        for (final entry in query.entries)
          if (entry.value != null && entry.value.toString().isNotEmpty)
            entry.key: entry.value.toString(),
      },
    );
  }
}
