import 'dart:async';
import 'dart:convert';

import 'package:app/src/api/api_client.dart';
import 'package:app/src/screens/collector/unit_detail_screen.dart';
import 'package:app/src/screens/collector/visit_form_screen.dart';
import 'package:app/src/storage/token_store.dart';
import 'package:app/src/utils/visit_labels.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geolocator/geolocator.dart';
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

/// Stands in for the device GPS: no fix by default, a fixed [position], or a
/// fix that only arrives once [pending] completes.
class _FakeGeolocator extends GeolocatorPlatform {
  _FakeGeolocator({this.position, this.pending});

  final Position? position;
  final Completer<Position>? pending;

  @override
  Future<bool> isLocationServiceEnabled() async =>
      position != null || pending != null;

  @override
  Future<LocationPermission> checkPermission() async =>
      LocationPermission.whileInUse;

  @override
  Future<Position> getCurrentPosition({LocationSettings? locationSettings}) =>
      pending?.future ?? Future.value(position!);
}

typedef _Route =
    (int, Map<String, dynamic>) Function(Map<String, dynamic> body);

/// Minimal stand-in for the collector visit API: answers the configured routes
/// ('METHOD path') and records every request together with its JSON body.
class _FakeApi {
  _FakeApi(this.routes);

  final Map<String, _Route> routes;
  final calls = <String>[];
  final bodies = <String, Map<String, dynamic>>{};

  /// Requests ('METHOD path') answered only once their completer completes,
  /// so the screen can be observed while the request is in flight.
  final holds = <String, Completer<void>>{};

  late final client = ApiClient(
    tokenStore: _FakeTokenStore(),
    httpClient: MockClient(_handle),
    baseUrl: 'https://example.test/api/v1',
  );

  Future<http.Response> _handle(http.Request request) async {
    final key =
        '${request.method} ${request.url.path.replaceFirst('/api/v1/', '')}';
    calls.add(key);
    final body = request.body.isEmpty
        ? <String, dynamic>{}
        : Map<String, dynamic>.from(jsonDecode(request.body) as Map);
    bodies[key] = body;
    await holds[key]?.future;
    final route = routes[key];
    final (status, payload) = route == null
        ? (404, <String, dynamic>{'success': false, 'message': 'Not found'})
        : route(body);
    return http.Response(
      jsonEncode(payload),
      status,
      headers: {'content-type': 'application/json; charset=utf-8'},
    );
  }
}

Map<String, dynamic> _ok(
  Object? data, {
  String message = 'Data berhasil ditemukan.',
}) => {'success': true, 'message': message, 'data': data, 'meta': null};

const _storedVisitDate = '2026-10-08T03:15:00.000000Z';

/// A row as returned by `GET residents/{resident}/visits`.
Map<String, dynamic> _visit({
  int id = 41,
  String purpose = 'Penagihan IPL Oktober',
  String status = 'completed',
  String lifecycle = 'in_progress',
  bool hasSignature = false,
}) => {
  'id': id,
  'unit_id': 'A-01',
  'purpose': purpose,
  'status': status,
  'lifecycle': lifecycle,
  'met_with': 'Ibu Sari',
  'result': 'Janji bayar minggu depan',
  'notes': 'Rumah sedang direnovasi',
  'visit_date': _storedVisitDate,
  'has_signature': hasSignature,
  'awaiting_signature': status == 'completed' && lifecycle == 'in_progress',
  'evidence_count': hasSignature ? 1 : 0,
};

final _unit = <String, dynamic>{
  'id': 'A-01',
  'resident_id': 5,
  'block': 'A',
  'lot_number': '01',
  'cluster': {'name': 'Cluster Melati'},
  'resident': {'name': 'Budi Santoso', 'phone': '081234567890'},
  'billings': <dynamic>[],
};

final _gpsFix = Position(
  latitude: -6.2,
  longitude: 106.8,
  timestamp: DateTime(2026, 10, 9, 10),
  accuracy: 5,
  altitude: 0,
  altitudeAccuracy: 0,
  heading: 0,
  headingAccuracy: 0,
  speed: 0,
  speedAccuracy: 0,
);

/// A tall phone-sized surface so the whole form fits without scrolling.
void _usePhoneSurface(WidgetTester tester) {
  tester.view.physicalSize = const Size(1080, 2400);
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);
}

/// Opens the visit form on top of a launcher page, so leaving it is observable.
Future<void> _pumpForm(
  WidgetTester tester,
  _FakeApi api, {
  Map<String, dynamic>? visit,
}) async {
  _usePhoneSurface(tester);
  await tester.pumpWidget(
    MaterialApp(
      home: Builder(
        builder: (context) => Scaffold(
          body: Center(
            child: FilledButton(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => VisitFormScreen(
                    apiClient: api.client,
                    unit: _unit,
                    visit: visit,
                  ),
                ),
              ),
              child: const Text('Buka Form'),
            ),
          ),
        ),
      ),
    ),
  );
  await tester.tap(find.text('Buka Form'));
  await tester.pumpAndSettle();
}

TextField _field(WidgetTester tester, String text) =>
    tester.widget<TextField>(find.widgetWithText(TextField, text));

const _savedAwaitingMessage =
    'Kunjungan tersimpan. Minta tanda tangan penghuni untuk menyelesaikannya.';
const _leaveDialogContent =
    'Penghuni belum menandatangani. Kunjungan tersimpan sebagai "Menunggu tanda tangan" dan dapat dilanjutkan dari detail unit.';

void main() {
  setUpAll(() async => initializeDateFormatting('id_ID'));
  setUp(() {
    GeolocatorPlatform.instance = _FakeGeolocator();
  });

  group('visit labels', () {
    test('outcome statuses use the Indonesian UI copy', () {
      expect(visitStatusLabel('completed'), 'Selesai');
      expect(visitStatusLabel('no_answer'), 'Tidak Ada Jawaban');
      expect(visitStatusLabel('refused'), 'Menolak');
      expect(visitStatusLabel('rescheduled'), 'Dijadwalkan Ulang');
      expect(visitStatusLabel('some_new_status'), 'Some New Status');
      expect(visitStatusLabel(null), '-');
      expect(visitAwaitingSignatureLabel, 'Menunggu tanda tangan');
      expect(visitSignedLabel, 'Ditandatangani');
    });

    test('awaiting follows the API flag, then status + lifecycle', () {
      expect(visitAwaitingSignature({'awaiting_signature': true}), isTrue);
      expect(visitAwaitingSignature({'awaiting_signature': 1}), isTrue);
      expect(
        visitAwaitingSignature({
          'awaiting_signature': false,
          'status': 'completed',
          'lifecycle': 'in_progress',
        }),
        isFalse,
      );
      // Payloads without the flag fall back to the raw columns.
      expect(
        visitAwaitingSignature({
          'status': 'completed',
          'lifecycle': 'in_progress',
        }),
        isTrue,
      );
      // Legacy visits stay lifecycle completed: never awaiting, even unsigned.
      expect(
        visitAwaitingSignature({
          'status': 'completed',
          'lifecycle': 'completed',
          'has_signature': false,
        }),
        isFalse,
      );
      expect(
        visitAwaitingSignature({
          'status': 'no_answer',
          'lifecycle': 'completed',
        }),
        isFalse,
      );
    });

    test('has_signature accepts JSON booleans and 1/0', () {
      expect(visitHasSignature({'has_signature': true}), isTrue);
      expect(visitHasSignature({'has_signature': 1}), isTrue);
      expect(visitHasSignature({'has_signature': false}), isFalse);
      expect(visitHasSignature({}), isFalse);
    });
  });

  group('VisitFormScreen', () {
    testWidgets(
      'saving "Selesai" waits for the signature, which finishes the visit',
      (tester) async {
        final api = _FakeApi({
          'POST units/A-01/visits': (body) => (
            201,
            _ok({
              ..._visit(id: 77),
              'purpose': body['purpose'],
              'visit_date': '2026-10-09T03:00:00.000000Z',
            }, message: _savedAwaitingMessage),
          ),
        });
        await _pumpForm(tester, api);
        expect(find.text('Catat Kunjungan'), findsOneWidget);

        await tester.enterText(
          find.widgetWithText(TextField, 'Tujuan Kunjungan'),
          'Penagihan IPL Oktober',
        );
        await tester.tap(find.text('Simpan Kunjungan'));
        await tester.pumpAndSettle();

        final created = api.bodies['POST units/A-01/visits']!;
        expect(created['status'], 'completed');
        expect(created.containsKey('checkin_latitude'), isFalse);
        expect(find.text(_savedAwaitingMessage), findsOneWidget);
        expect(find.text('Menunggu tanda tangan penghuni.'), findsOneWidget);
        expect(_field(tester, 'Penagihan IPL Oktober').enabled, isFalse);

        // Unsigned: leaving needs confirmation, whose main action goes
        // straight to the signature.
        await tester.pageBack();
        await tester.pumpAndSettle();
        expect(find.text('Kunjungan belum selesai'), findsOneWidget);
        await tester.tap(find.text('Lanjutkan Tanda Tangan'));
        await tester.pumpAndSettle();
        expect(find.text('Kunjungan belum selesai'), findsNothing);
        expect(find.text('Tanda Tangan Penghuni'), findsOneWidget);

        // The signature screen pops `true` once the upload succeeded; by then
        // the server has already completed the visit.
        tester.state<NavigatorState>(find.byType(Navigator)).pop(true);
        await tester.pumpAndSettle();
        expect(
          find.text(
            'Tanda tangan penghuni sudah tersimpan. Kunjungan selesai.',
          ),
          findsOneWidget,
        );
        expect(find.text('Tanda Tangan Ulang'), findsOneWidget);
        // Let the save message time out so the queued one becomes visible.
        await tester.pump(const Duration(seconds: 5));
        await tester.pumpAndSettle();
        expect(
          find.text('Tanda tangan berhasil disimpan. Kunjungan selesai.'),
          findsOneWidget,
        );

        await tester.tap(find.text('Selesaikan Kunjungan'));
        await tester.pumpAndSettle();
        // The stored signature already finished the visit server-side, so
        // nothing else is sent - no request left to fail once it is finished.
        expect(api.calls, ['POST units/A-01/visits']);
        expect(find.text('Buka Form'), findsOneWidget);
        await tester.pump(const Duration(seconds: 5));
        await tester.pumpAndSettle();
        expect(find.text('Kunjungan berhasil diselesaikan.'), findsOneWidget);
      },
    );

    testWidgets('leaving while the GPS fix is pending cancels the save', (
      tester,
    ) async {
      final fix = Completer<Position>();
      GeolocatorPlatform.instance = _FakeGeolocator(pending: fix);
      final api = _FakeApi({});
      await _pumpForm(tester, api);

      await tester.enterText(
        find.widgetWithText(TextField, 'Tujuan Kunjungan'),
        'Penagihan IPL Oktober',
      );
      await tester.tap(find.text('Simpan Kunjungan'));
      await tester.pump();
      expect(find.byType(CircularProgressIndicator), findsOneWidget);

      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(find.byType(VisitFormScreen), findsNothing);
      expect(find.text('Kunjungan belum selesai'), findsNothing);

      fix.complete(_gpsFix);
      await tester.pumpAndSettle();
      expect(api.calls, isEmpty);
    });

    testWidgets('back navigation waits while the visit is being sent', (
      tester,
    ) async {
      final api = _FakeApi({
        'POST units/A-01/visits': (body) =>
            (201, _ok(_visit(id: 79), message: _savedAwaitingMessage)),
      });
      final response = api.holds['POST units/A-01/visits'] = Completer<void>();
      await _pumpForm(tester, api);

      await tester.enterText(
        find.widgetWithText(TextField, 'Tujuan Kunjungan'),
        'Penagihan IPL Oktober',
      );
      await tester.tap(find.text('Simpan Kunjungan'));
      await tester.pump();
      expect(api.calls, ['POST units/A-01/visits']);

      // Nothing is confirmed yet: no leaving, and no "saved" leave dialog.
      await tester.pageBack();
      await tester.pump(const Duration(milliseconds: 500));
      expect(find.byType(VisitFormScreen), findsOneWidget);
      expect(find.text('Kunjungan belum selesai'), findsNothing);

      response.complete();
      await tester.pumpAndSettle();
      expect(find.text(_savedAwaitingMessage), findsOneWidget);
      expect(find.text('Menunggu tanda tangan penghuni.'), findsOneWidget);

      // Once saved, leaving the unsigned visit asks for confirmation.
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(find.text('Kunjungan belum selesai'), findsOneWidget);
    });

    testWidgets('a failed GPS evidence upload does not hide the saved visit', (
      tester,
    ) async {
      GeolocatorPlatform.instance = _FakeGeolocator(position: _gpsFix);
      final api = _FakeApi({
        'POST units/A-01/visits': (body) => (
          201,
          _ok(
            _visit(id: 78, status: 'no_answer', lifecycle: 'completed'),
            message: 'Kunjungan berhasil dicatat.',
          ),
        ),
        'POST visits/78/evidence': (body) =>
            (500, {'success': false, 'message': 'Server error'}),
      });
      await _pumpForm(tester, api);

      await tester.enterText(
        find.widgetWithText(TextField, 'Tujuan Kunjungan'),
        'Kunjungan rutin',
      );
      await tester.tap(find.byType(DropdownButtonFormField<String>));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Tidak Ada Jawaban').last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Simpan Kunjungan'));
      await tester.pumpAndSettle();

      final created = api.bodies['POST units/A-01/visits']!;
      expect(created['status'], 'no_answer');
      expect(created['checkin_latitude'], -6.2);
      expect(created['checkin_longitude'], 106.8);
      expect(api.calls, contains('POST visits/78/evidence'));
      expect(find.text('Kunjungan berhasil dicatat.'), findsOneWidget);
      expect(find.text('Server error'), findsNothing);

      // Other outcomes are final right away: no signature step, no guard.
      expect(find.text('Minta Tanda Tangan Penghuni'), findsNothing);
      await tester.tap(find.widgetWithText(FilledButton, 'Selesai'));
      await tester.pumpAndSettle();
      expect(find.text('Buka Form'), findsOneWidget);
      expect(api.calls.where((call) => call.startsWith('PUT')), isEmpty);
    });
  });

  group('VisitFormScreen resume mode', () {
    testWidgets('skips the input stage and shows the stored visit read-only', (
      tester,
    ) async {
      final api = _FakeApi({});
      await _pumpForm(tester, api, visit: _visit());

      expect(find.text('Lanjutkan Kunjungan'), findsOneWidget);
      expect(find.textContaining('Waktu kunjungan:'), findsOneWidget);
      expect(find.text('Simpan Kunjungan'), findsNothing);
      for (final value in [
        'Penagihan IPL Oktober',
        'Ibu Sari',
        'Janji bayar minggu depan',
        'Rumah sedang direnovasi',
      ]) {
        expect(_field(tester, value).enabled, isFalse, reason: value);
      }
      final status = tester.widget<DropdownButtonFormField<String>>(
        find.byType(DropdownButtonFormField<String>),
      );
      expect(status.initialValue, 'completed');
      expect(status.onChanged, isNull);

      expect(find.text('Menunggu tanda tangan penghuni.'), findsOneWidget);
      expect(find.text('Minta Tanda Tangan Penghuni'), findsOneWidget);
      expect(find.text('Ambil Foto Bukti'), findsOneWidget);
      expect(find.text('Selesaikan Kunjungan'), findsOneWidget);
      expect(api.calls, isEmpty);
    });

    testWidgets('finishing without the signature is blocked client-side', (
      tester,
    ) async {
      final api = _FakeApi({});
      await _pumpForm(tester, api, visit: _visit());

      await tester.tap(find.text('Selesaikan Kunjungan'));
      await tester.pump();

      expect(
        find.text(
          'Tanda tangan penghuni diperlukan. Silakan minta penghuni untuk melakukan tanda tangan terlebih dahulu.',
        ),
        findsOneWidget,
      );
      expect(api.calls, isEmpty);
    });

    testWidgets('leaving an unsigned visit asks for confirmation', (
      tester,
    ) async {
      await _pumpForm(tester, _FakeApi({}), visit: _visit());

      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(find.text('Kunjungan belum selesai'), findsOneWidget);
      expect(find.text(_leaveDialogContent), findsOneWidget);

      // Tapping outside the dialog only closes it.
      await tester.tapAt(const Offset(8, 8));
      await tester.pumpAndSettle();
      expect(find.text('Kunjungan belum selesai'), findsNothing);
      expect(find.text('Tanda Tangan Penghuni'), findsNothing);
      expect(find.text('Lanjutkan Kunjungan'), findsOneWidget);

      // The main action opens the signature screen; coming back unsigned
      // leaves the visit waiting.
      await tester.pageBack();
      await tester.pumpAndSettle();
      await tester.tap(find.text('Lanjutkan Tanda Tangan'));
      await tester.pumpAndSettle();
      expect(find.text('Kunjungan belum selesai'), findsNothing);
      expect(find.text('Tanda Tangan Penghuni'), findsOneWidget);
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(find.text('Lanjutkan Kunjungan'), findsOneWidget);
      expect(find.text('Menunggu tanda tangan penghuni.'), findsOneWidget);

      await tester.pageBack();
      await tester.pumpAndSettle();
      await tester.tap(find.text('Keluar'));
      await tester.pumpAndSettle();
      expect(find.byType(VisitFormScreen), findsNothing);
      expect(find.text('Buka Form'), findsOneWidget);
    });

    testWidgets('the signature alone finishes a resumed visit', (tester) async {
      final api = _FakeApi({});
      await _pumpForm(tester, api, visit: _visit());

      await tester.tap(find.text('Minta Tanda Tangan Penghuni'));
      await tester.pumpAndSettle();
      tester.state<NavigatorState>(find.byType(Navigator)).pop(true);
      await tester.pumpAndSettle();
      expect(
        find.text('Tanda tangan penghuni sudah tersimpan. Kunjungan selesai.'),
        findsOneWidget,
      );

      await tester.tap(find.text('Selesaikan Kunjungan'));
      await tester.pumpAndSettle();
      // No PUT: it would only re-send the stored values (its visit_date
      // round-tripped through the phone's timezone) after the visit finished.
      expect(api.calls, isEmpty);
      expect(find.text('Buka Form'), findsOneWidget);
    });

    testWidgets('an already finished visit closes without another request', (
      tester,
    ) async {
      final api = _FakeApi({});
      await _pumpForm(
        tester,
        api,
        visit: _visit(lifecycle: 'completed', hasSignature: true),
      );

      await tester.tap(find.text('Selesaikan Kunjungan'));
      await tester.pumpAndSettle();
      expect(api.calls, isEmpty);
      expect(find.text('Kunjungan berhasil diselesaikan.'), findsOneWidget);
      expect(find.text('Buka Form'), findsOneWidget);
    });

    testWidgets('a signed visit still in progress is finalised with its time', (
      tester,
    ) async {
      // Has a signature yet the server still holds it in progress: the
      // fallback PUT finalises it.
      final api = _FakeApi({
        'PUT visits/41': (body) => (
          200,
          _ok(
            _visit(lifecycle: 'completed', hasSignature: true),
            message: 'Kunjungan berhasil diselesaikan.',
          ),
        ),
      });
      await _pumpForm(tester, api, visit: _visit(hasSignature: true));
      expect(
        find.text('Tanda tangan penghuni sudah tersimpan. Kunjungan selesai.'),
        findsOneWidget,
      );
      expect(find.text('Tanda Tangan Ulang'), findsOneWidget);

      await tester.tap(find.text('Selesaikan Kunjungan'));
      await tester.pumpAndSettle();

      final sent = api.bodies['PUT visits/41']!;
      expect(sent['status'], 'completed');
      expect(sent['purpose'], 'Penagihan IPL Oktober');
      // Same moment as stored, sent as local wall-clock time like on creation:
      // echoing the UTC "...Z" string would shift it on the server.
      final visitDate = sent['visit_date'] as String;
      expect(
        DateTime.parse(
          visitDate,
        ).isAtSameMomentAs(DateTime.parse(_storedVisitDate)),
        isTrue,
      );
      expect(visitDate.endsWith('Z'), isFalse);
      expect(find.text('Kunjungan berhasil diselesaikan.'), findsOneWidget);
      expect(find.text('Buka Form'), findsOneWidget);
    });

    testWidgets('a signed visit can be left without confirmation', (
      tester,
    ) async {
      await _pumpForm(
        tester,
        _FakeApi({}),
        visit: _visit(lifecycle: 'completed', hasSignature: true),
      );

      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(find.text('Kunjungan belum selesai'), findsNothing);
      expect(find.text('Buka Form'), findsOneWidget);
    });
  });

  group('UnitDetailScreen', () {
    testWidgets(
      'labels visits in Indonesian and resumes the one awaiting a signature',
      (tester) async {
        _usePhoneSurface(tester);
        final api = _FakeApi({
          'GET units/A-01': (_) => (200, _ok(_unit)),
          'GET residents/5/visits': (_) => (
            200,
            _ok([
              _visit(id: 41, purpose: 'Penagihan IPL Oktober'),
              _visit(
                id: 40,
                purpose: 'Kunjungan rutin',
                lifecycle: 'completed',
                hasSignature: true,
              ),
              // Legacy: completed before signatures were required.
              _visit(id: 39, purpose: 'Kunjungan lama', lifecycle: 'completed'),
              _visit(
                id: 38,
                purpose: 'Tidak bertemu',
                status: 'no_answer',
                lifecycle: 'completed',
              ),
            ]),
          ),
          'GET residents/5/payment-promises': (_) => (200, _ok(<dynamic>[])),
        });
        int visitLoads() =>
            api.calls.where((call) => call == 'GET residents/5/visits').length;

        await tester.pumpWidget(
          MaterialApp(
            home: UnitDetailScreen(apiClient: api.client, unitId: 'A-01'),
          ),
        );
        await tester.pumpAndSettle();

        expect(find.textContaining('— Selesai'), findsNWidgets(3));
        expect(find.textContaining('— Tidak Ada Jawaban'), findsOneWidget);
        expect(find.textContaining('completed'), findsNothing);
        expect(find.text('Menunggu tanda tangan'), findsOneWidget);
        expect(find.byTooltip('Ditandatangani'), findsOneWidget);
        // The unsigned legacy visit counts as finished, so it carries no mark.
        expect(find.byTooltip('Belum ditandatangani'), findsNothing);
        expect(
          find.descendant(
            of: find
                .ancestor(
                  of: find.text('Kunjungan lama'),
                  matching: find.byType(Row),
                )
                .first,
            matching: find.byType(Icon),
          ),
          findsNothing,
        );
        expect(visitLoads(), 1);

        // Finished visits are not reopened, signed or not.
        for (final purpose in ['Kunjungan rutin', 'Kunjungan lama']) {
          await tester.tap(find.text(purpose));
          await tester.pumpAndSettle();
          expect(find.byType(VisitFormScreen), findsNothing, reason: purpose);
        }

        await tester.tap(find.text('Penagihan IPL Oktober'));
        await tester.pumpAndSettle();
        expect(find.text('Lanjutkan Kunjungan'), findsOneWidget);
        expect(find.text('Menunggu tanda tangan penghuni.'), findsOneWidget);

        await tester.pageBack();
        await tester.pumpAndSettle();
        await tester.tap(find.text('Keluar'));
        await tester.pumpAndSettle();
        expect(find.byType(VisitFormScreen), findsNothing);
        expect(visitLoads(), 2);
      },
    );

    testWidgets('keeps an older visit awaiting a signature reachable', (
      tester,
    ) async {
      _usePhoneSurface(tester);
      final api = _FakeApi({
        'GET units/A-01': (_) => (200, _ok(_unit)),
        'GET residents/5/visits': (_) => (
          200,
          _ok([
            for (var i = 1; i <= 6; i++)
              _visit(
                id: 60 - i,
                purpose: 'Kunjungan $i',
                status: 'no_answer',
                lifecycle: 'completed',
              ),
            _visit(id: 41, purpose: 'Penagihan IPL Agustus'),
          ]),
        ),
        'GET residents/5/payment-promises': (_) => (200, _ok(<dynamic>[])),
      });

      await tester.pumpWidget(
        MaterialApp(
          home: UnitDetailScreen(apiClient: api.client, unitId: 'A-01'),
        ),
      );
      await tester.pumpAndSettle();

      // The five newest visits, plus the older one still awaiting a signature.
      expect(find.textContaining('— Tidak Ada Jawaban'), findsNWidgets(5));
      expect(find.text('Kunjungan 6'), findsNothing);
      expect(find.text('Menunggu tanda tangan'), findsOneWidget);

      await tester.tap(find.text('Penagihan IPL Agustus'));
      await tester.pumpAndSettle();
      expect(find.text('Lanjutkan Kunjungan'), findsOneWidget);
    });
  });
}
