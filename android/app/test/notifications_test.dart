import 'dart:convert';

import 'package:app/src/api/api_client.dart';
import 'package:app/src/notifications/app_notification.dart';
import 'package:app/src/notifications/notification_detail_screen.dart';
import 'package:app/src/notifications/notification_link_handler.dart';
import 'package:app/src/notifications/notification_list_view.dart';
import 'package:app/src/notifications/notification_repository.dart';
import 'package:app/src/notifications/notification_router.dart';
import 'package:app/src/storage/token_store.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:intl/date_symbol_data_local.dart';

class _FakeTokenStore implements TokenStore {
  @override
  Future<String?> read() async => 'token';
  @override
  Future<void> save(String token) async {}
  @override
  Future<void> clear() async {}
}

/// In-memory stand-in for the notification API, so read/unread state persists across
/// requests exactly like the real backend (which is what makes web/mobile sync work).
class _FakeServer {
  _FakeServer(this.rows);

  final Map<String, Map<String, dynamic>> rows;
  final requests = <String>[];

  Map<String, dynamic> _present(Map<String, dynamic> row) => {
    ...row,
    'is_read': row['read_status'] == 'read',
  };

  Future<http.Response> handle(http.Request request) async {
    final path = request.url.path.replaceFirst('/api/v1/', '');
    requests.add('${request.method} $path');
    Map<String, dynamic> ok(Object? data, {Map<String, dynamic>? meta}) => {
      'success': true,
      'data': data,
      'meta': meta,
    };
    http.Response json(Object body, [int status = 200]) => http.Response(
      jsonEncode(body),
      status,
      headers: {'content-type': 'application/json'},
    );

    final parts = path.split('/');
    // resident/notifications[/id[/read|unread]] | notifications/...
    final base = parts.first == 'resident' ? 2 : 1;
    if (parts.length == base) {
      final unread = rows.values.where((r) => r['read_status'] == 'unread');
      return json(
        ok(
          rows.values.map(_present).toList(),
          meta: {'unread_count': unread.length},
        ),
      );
    }
    final tail = parts[base];
    if (tail == 'read-all') {
      for (final row in rows.values) {
        row['read_status'] = 'read';
      }
      return json(ok(null));
    }
    final row = rows[tail];
    if (row == null) {
      return json({'success': false, 'message': 'Not found'}, 404);
    }
    if (parts.length > base + 1) {
      row['read_status'] = parts[base + 1] == 'read' ? 'read' : 'unread';
    }
    return json(ok(_present(row)));
  }
}

Map<String, dynamic> _row(
  int id, {
  String status = 'unread',
  Map<String, dynamic>? reference,
}) => {
  'id': id,
  'title': 'Judul $id',
  'message': 'Isi pesan $id',
  'type': 'payment_verified',
  'category': 'payment',
  'category_label': 'Pembayaran',
  'read_status': status,
  'created_at': '2026-09-18T10:00:00Z',
  'sender': {'id': 7, 'name': 'Petugas Loket'},
  'reference': ?reference,
};

ApiClient _client(_FakeServer server) => ApiClient(
  tokenStore: _FakeTokenStore(),
  httpClient: MockClient(server.handle),
  baseUrl: 'https://example.test/api/v1',
);

void main() {
  setUpAll(() async => initializeDateFormatting('id_ID'));

  group('AppNotification / repository', () {
    test('parses payload and reads unread state from read_status or is_read', () {
      final n = AppNotification.fromJson({
        ..._row(
          1,
          reference: {
            'resource': 'payment',
            'resource_label': 'Pembayaran',
            'id': '12',
            'available': true,
          },
        ),
      });
      expect(n.isRead, isFalse);
      expect(n.title, 'Judul 1');
      expect(n.senderName, 'Petugas Loket');
      expect(n.reference?.resource, 'payment');
      expect(n.copyWith(isRead: true).isRead, isTrue);
      // Legacy rows without read_status fall back to read_at.
      expect(
        AppNotification.fromJson({'id': 2, 'read_at': '2026-01-01'}).isRead,
        isTrue,
      );
    });

    test('list de-duplicates repeated ids and reports the unread count', () async {
      final server = _FakeServer({'1': _row(1), '2': _row(2, status: 'read')});
      final repo = NotificationRepository(
        _client(server),
        NotificationSource.staff,
      );
      final page = await repo.list();
      expect(page.items.map((e) => e.id), ['1', '2']);
      expect(page.unreadCount, 1);
    });

    test('mark read / unread hit the source-specific endpoints', () async {
      final server = _FakeServer({'5': _row(5)});
      final repo = NotificationRepository(
        _client(server),
        NotificationSource.resident,
      );
      expect((await repo.markRead('5')).isRead, isTrue);
      expect((await repo.markUnread('5')).isRead, isFalse);
      expect(server.requests, [
        'POST resident/notifications/5/read',
        'POST resident/notifications/5/unread',
      ]);
    });

    test('source is chosen from the signed-in role', () {
      expect(NotificationSource.forRole('collector'), NotificationSource.staff);
      expect(
        NotificationSource.forRole('supervisor'),
        NotificationSource.supervisor,
      );
      expect(NotificationSource.forRole('customer'), NotificationSource.resident);
      expect(NotificationSource.forRole(null), NotificationSource.resident);
    });
  });

  group('routing', () {
    final available = {
      'resource': 'payment',
      'resource_label': 'Pembayaran',
      'id': '12',
      'available': true,
    };

    test('resident payment notification opens the payments screen', () {
      final target = resolveNotificationTarget(
        source: NotificationSource.resident,
        apiClient: _client(_FakeServer({})),
        notification: AppNotification.fromJson(_row(1, reference: available)),
      );
      expect(target?.label, 'Buka Pembayaran');
    });

    test('deleted or unknown references yield no target', () {
      final client = _client(_FakeServer({}));
      expect(
        resolveNotificationTarget(
          source: NotificationSource.resident,
          apiClient: client,
          notification: AppNotification.fromJson(
            _row(1, reference: {...available, 'available': false}),
          ),
        ),
        isNull,
      );
      expect(
        resolveNotificationTarget(
          source: NotificationSource.resident,
          apiClient: client,
          notification: AppNotification.fromJson(_row(1)),
        ),
        isNull,
      );
      expect(
        resolveNotificationTarget(
          source: NotificationSource.staff,
          apiClient: client,
          notification: AppNotification.fromJson(_row(1, reference: available)),
        ),
        isNull,
      );
    });
  });

  group('NotificationDetailScreen', () {
    Future<void> pumpDetail(
      WidgetTester tester,
      _FakeServer server, {
      String id = '1',
    }) async {
      await tester.pumpWidget(
        MaterialApp(
          home: NotificationDetailScreen(
            apiClient: _client(server),
            source: NotificationSource.resident,
            notificationId: id,
          ),
        ),
      );
      await tester.pumpAndSettle();
    }

    testWidgets('shows full detail and marks the notification read on open', (
      tester,
    ) async {
      final server = _FakeServer({'1': _row(1)});
      await pumpDetail(tester, server);

      expect(find.text('Judul 1'), findsOneWidget);
      expect(find.text('Isi pesan 1'), findsOneWidget);
      expect(find.text('Sudah dibaca'), findsOneWidget);
      expect(find.text('Petugas Loket'), findsOneWidget);
      expect(server.rows['1']!['read_status'], 'read');
    });

    testWidgets('can be flipped back to unread, and a refresh does not undo it', (
      tester,
    ) async {
      final server = _FakeServer({'1': _row(1)});
      await pumpDetail(tester, server);

      await tester.tap(find.text('Tandai belum dibaca'));
      await tester.pumpAndSettle();
      expect(server.rows['1']!['read_status'], 'unread');
      expect(find.text('Belum dibaca'), findsOneWidget);

      await tester.fling(find.byType(ListView), const Offset(0, 400), 1000);
      await tester.pumpAndSettle();
      expect(server.rows['1']!['read_status'], 'unread');
    });

    testWidgets('a deleted related resource still opens with an explanation', (
      tester,
    ) async {
      final server = _FakeServer({
        '1': _row(
          1,
          reference: {
            'resource': 'payment',
            'resource_label': 'Pembayaran',
            'id': '99',
            'available': false,
            'message': 'Pembayaran terkait sudah tidak tersedia.',
          },
        ),
      });
      await pumpDetail(tester, server);

      expect(find.text('Pembayaran terkait sudah tidak tersedia.'), findsOneWidget);
      expect(find.text('Buka Pembayaran'), findsNothing);
      expect(find.text('Isi pesan 1'), findsOneWidget);
    });

    testWidgets('an id that is missing or not the user\'s shows a not-found state', (
      tester,
    ) async {
      await pumpDetail(tester, _FakeServer({'1': _row(1)}), id: '404');
      expect(
        find.text('Notifikasi tidak ditemukan atau bukan milik akun Anda.'),
        findsOneWidget,
      );
      expect(find.text('Coba Lagi'), findsOneWidget);
    });
  });

  group('NotificationListView', () {
    Future<void> pumpList(WidgetTester tester, _FakeServer server) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: NotificationListView(
              apiClient: _client(server),
              source: NotificationSource.resident,
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
    }

    testWidgets('renders unread indicator, opens detail on tap and refreshes on return', (
      tester,
    ) async {
      final server = _FakeServer({'1': _row(1), '2': _row(2, status: 'read')});
      await pumpList(tester, server);

      expect(find.text('Pusat Notifikasi (1 baru)'), findsOneWidget);
      expect(
        find.bySemanticsLabel(RegExp(r'^Belum dibaca\. Judul 1')),
        findsOneWidget,
      );

      await tester.tap(find.text('Judul 1'));
      await tester.pumpAndSettle();
      expect(find.text('Detail Notifikasi'), findsOneWidget);
      expect(server.rows['1']!['read_status'], 'read');

      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(find.text('Pusat Notifikasi'), findsOneWidget);
    });

    testWidgets('shows the empty state', (tester) async {
      await pumpList(tester, _FakeServer({}));
      expect(find.text('Belum ada notifikasi.'), findsOneWidget);
    });

    testWidgets('shows an error state with retry when the API fails', (
      tester,
    ) async {
      final client = ApiClient(
        tokenStore: _FakeTokenStore(),
        httpClient: MockClient(
          (_) async => http.Response(
            jsonEncode({'message': 'Server error'}),
            500,
            headers: {'content-type': 'application/json'},
          ),
        ),
        baseUrl: 'https://example.test/api/v1',
      );
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: NotificationListView(
              apiClient: client,
              source: NotificationSource.resident,
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Server error'), findsOneWidget);
      expect(find.text('Coba Lagi'), findsOneWidget);
    });

    testWidgets('mark-all-read clears the unread state', (tester) async {
      final server = _FakeServer({'1': _row(1), '2': _row(2)});
      await pumpList(tester, server);
      await tester.tap(find.text('Tandai'));
      await tester.pumpAndSettle();
      expect(server.rows.values.every((r) => r['read_status'] == 'read'), isTrue);
      expect(find.text('Pusat Notifikasi'), findsOneWidget);
    });
  });

  group('NotificationLinkHandler (push / deep link)', () {
    late GlobalKey<NavigatorState> navigatorKey;
    late _FakeServer server;
    late NotificationLinkHandler handler;

    Future<void> pumpApp(WidgetTester tester) async {
      navigatorKey = GlobalKey<NavigatorState>();
      server = _FakeServer({'1': _row(1), '2': _row(2)});
      handler = NotificationLinkHandler(
        navigatorKey: navigatorKey,
        apiClient: _client(server),
      );
      await tester.pumpWidget(
        MaterialApp(
          navigatorKey: navigatorKey,
          home: const Scaffold(body: Text('Beranda')),
        ),
      );
    }

    testWidgets('app open: opens the detail straight away', (tester) async {
      await pumpApp(tester);
      handler.updateSession(ready: true, role: 'customer');

      expect(handler.handlePayload({'notification_id': '1'}), isTrue);
      await tester.pumpAndSettle();

      expect(find.text('Judul 1'), findsOneWidget);
      expect(server.requests, contains('GET resident/notifications/1'));
      expect(server.rows['1']!['read_status'], 'read');
    });

    testWidgets('app closed: payload is held until the session is ready', (
      tester,
    ) async {
      await pumpApp(tester);

      // Launch payload arrives during startup, before login/bootstrap has finished.
      handler.handlePayload({'notification_id': 2});
      await tester.pumpAndSettle();
      expect(find.text('Judul 2'), findsNothing);
      expect(server.requests, isEmpty);

      // Biometric lock / login completes.
      handler.updateSession(ready: true, role: 'customer');
      await tester.pumpAndSettle();
      expect(find.text('Judul 2'), findsOneWidget);
    });

    testWidgets('the same notification delivered twice opens once', (tester) async {
      await pumpApp(tester);
      handler.updateSession(ready: true, role: 'customer');

      handler.handlePayload({'notification_id': '1'});
      await tester.pumpAndSettle();
      handler.handlePayload({'notification_id': '1'});
      await tester.pumpAndSettle();

      expect(server.requests.where((r) => r == 'GET resident/notifications/1'), hasLength(1));
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(find.text('Beranda'), findsOneWidget);
    });

    testWidgets('an explicit source in the payload overrides the role default', (
      tester,
    ) async {
      await pumpApp(tester);
      handler.updateSession(ready: true, role: 'customer');

      handler.handlePayload({'notification_id': '1', 'source': 'staff'});
      await tester.pumpAndSettle();
      expect(server.requests, contains('GET notifications/1'));
    });

    test('payloads without a notification id are rejected', () {
      final handler = NotificationLinkHandler(
        navigatorKey: GlobalKey<NavigatorState>(),
        apiClient: _client(_FakeServer({})),
      );
      expect(handler.handlePayload({'foo': 'bar'}), isFalse);
      expect(handler.handlePayload({'notification_id': ''}), isFalse);
    });
  });
}
