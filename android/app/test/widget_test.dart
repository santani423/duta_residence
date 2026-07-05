import 'package:app/src/widgets/status_badge.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('Status badge renders normalized status text', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(body: StatusBadge('waiting_verification')),
      ),
    );

    expect(find.text('Waiting Verification'), findsOneWidget);
  });
}
