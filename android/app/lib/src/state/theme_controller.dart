import 'package:flutter/material.dart';

import '../storage/preferences_store.dart';

class ThemeController extends ChangeNotifier {
  ThemeController(this._preferencesStore);

  final PreferencesStore _preferencesStore;
  ThemeMode _mode = ThemeMode.system;

  ThemeMode get mode => _mode;

  Future<void> load() async {
    _mode = await _preferencesStore.readThemeMode();
    notifyListeners();
  }

  Future<void> setMode(ThemeMode mode) async {
    if (_mode == mode) return;
    _mode = mode;
    notifyListeners();
    await _preferencesStore.saveThemeMode(mode);
  }
}
