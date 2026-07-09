import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../models/user_session.dart';
import '../storage/token_store.dart';

enum SessionStatus { booting, authenticated, unauthenticated }

const _allowedRole = 'customer';
const _roleMismatchMessage =
    'Akun ini bukan akun penghuni. Silakan gunakan portal staf untuk masuk.';

class SessionController extends ChangeNotifier {
  SessionController({required this._apiClient, required this._tokenStore});

  final ApiClient _apiClient;
  final TokenStore _tokenStore;
  SessionStatus _status = SessionStatus.booting;
  UserSession? _user;
  String? _message;

  SessionStatus get status => _status;
  UserSession? get user => _user;
  String? get message => _message;

  Future<void> bootstrap() async {
    final token = await _tokenStore.read();
    if (token == null || token.isEmpty) {
      _setUnauthenticated();
      return;
    }

    try {
      final result = await _apiClient.get('auth/me');
      final user = UserSession.fromJson(
        Map<String, dynamic>.from(result.data as Map),
      );
      if (user.role != _allowedRole) {
        await _forceLogout();
        _setUnauthenticated(_roleMismatchMessage);
        notifyListeners();
        return;
      }
      _user = user;
      _status = SessionStatus.authenticated;
      _message = null;
    } on ApiException catch (error) {
      await _tokenStore.clear();
      _setUnauthenticated(error.message);
    }
    notifyListeners();
  }

  Future<void> login(String username, String password) async {
    final result = await _apiClient.postJson('auth/login', {
      'username': username,
      'password': password,
    });
    final data = Map<String, dynamic>.from(result.data as Map);
    final user = UserSession.fromJson(
      Map<String, dynamic>.from(data['user'] as Map),
    );
    await _tokenStore.save(data['token'].toString());

    if (user.role != _allowedRole) {
      await _forceLogout();
      _setUnauthenticated(_roleMismatchMessage);
      notifyListeners();
      throw const ApiException(_roleMismatchMessage);
    }

    _user = user;
    _status = SessionStatus.authenticated;
    _message = null;
    notifyListeners();
  }

  Future<void> _forceLogout() async {
    try {
      await _apiClient.postJson('auth/logout', {});
    } on ApiException {
      // Local session must still be cleared if the remote token is already invalid.
    }
    await _tokenStore.clear();
  }

  Future<void> logout() async {
    await _forceLogout();
    _setUnauthenticated();
    notifyListeners();
  }

  Future<void> expireSession() async {
    await _tokenStore.clear();
    _setUnauthenticated('Sesi Anda berakhir. Silakan login kembali.');
    notifyListeners();
  }

  void _setUnauthenticated([String? message]) {
    _user = null;
    _status = SessionStatus.unauthenticated;
    _message = message;
  }
}
