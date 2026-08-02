import 'package:flutter/widgets.dart';

import '../state/site_identity_controller.dart';

/// Exposes [SiteIdentityController] to the whole widget tree without
/// threading it through every screen constructor - mirrors how
/// `Theme.of(context)`/`MediaQuery.of(context)` work in Flutter itself.
class SiteIdentityScope extends InheritedNotifier<SiteIdentityController> {
  const SiteIdentityScope({
    required SiteIdentityController controller,
    required super.child,
    super.key,
  }) : super(notifier: controller);

  static SiteIdentityController of(BuildContext context) {
    final scope = context
        .dependOnInheritedWidgetOfExactType<SiteIdentityScope>();
    assert(scope != null, 'No SiteIdentityScope found in context');
    return scope!.notifier!;
  }
}
