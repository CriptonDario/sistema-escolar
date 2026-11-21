import 'dart:convert';

import 'package:app_colegio/pantallas/apoderado/matricula.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:app_colegio/app.dart';
import 'package:app_colegio/nucleo/almacen.dart';
import 'package:app_colegio/nucleo/api.dart';
import 'package:app_colegio/nucleo/fechas.dart';
import 'package:app_colegio/nucleo/sesion.dart';
import 'package:app_colegio/pantallas/cambiar_clave.dart';
import 'package:app_colegio/pantallas/puerta/escaner.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:material_ui/material_ui.dart';

/// Un servidor del colegio de mentira: responde lo que cada prueba le indica
/// y recuerda qué se le pidió.
class ServidorFalso {
  final Map<String, Object Function(http.Request)> rutas = {};
  final List<http.Request> pedidos = [];

  http.Client get cliente => MockClient((peticion) async {
    pedidos.add(peticion);
    final clave = '${peticion.method} ${peticion.url.path.replaceFirst('/api/v1/', '')}';
    final manejador = rutas[clave];
    if (manejador == null) {
      return _json({'message': 'Ruta no simulada: $clave'}, 404);
    }
    final resultado = manejador(peticion);
    return resultado is http.Response ? resultado : _json(resultado, 200);
  });

  /// El cuerpo JSON del último pedido a esa ruta.
  Map<String, dynamic> cuerpoDe(String clave) {
    final pedido = pedidos.lastWhere((p) => '${p.method} ${p.url.path.replaceFirst('/api/v1/', '')}' == clave);
    return jsonDecode(pedido.body) as Map<String, dynamic>;
  }

  int vecesPedido(String clave) => pedidos.where((p) => '${p.method} ${p.url.path.replaceFirst('/api/v1/', '')}' == clave).length;
}

http.Response _json(Object cuerpo, int estado) =>
    http.Response.bytes(utf8.encode(jsonEncode(cuerpo)), estado, headers: {'content-type': 'application/json; charset=utf-8'});

Map<String, dynamic> _login(String rol, {bool claveInicial = false, String? codigo}) => {
  'token': 'token-de-prueba',
  'perfil': {'id': 1, 'nombre': 'María Pérez', 'usuario': '987654321', 'rol': rol, 'debe_cambiar_clave': claveInicial, 'codigo': codigo},
  'colegio': {'nombre': 'I.E.P. Rabí Olam'},
};

Future<Sesion> _abrirApp(WidgetTester tester, ServidorFalso servidor) async {
  // Un teléfono alto, para que las listas quepan sin desplazarse.
  tester.view.physicalSize = const Size(1080, 2400);
  tester.view.devicePixelRatio = 2.5;
  addTearDown(tester.view.reset);

  final sesion = Sesion(
    api: Api(baseUrl: 'https://colegio.test/api/v1', cliente: servidor.cliente),
    almacen: AlmacenEnMemoria(),
  );
  await sesion.restaurar();
  await tester.pumpWidget(AppColegio(sesion: sesion, conCamara: false));
  return sesion;
}

Future<void> _iniciarSesion(WidgetTester tester, {String usuario = '987654321', String clave = 'Familia2026'}) async {
  await tester.enterText(find.widgetWithText(TextFormField, 'Usuario, correo o teléfono'), usuario);
  await tester.enterText(find.widgetWithText(TextFormField, 'Contraseña'), clave);
  await tester.tap(find.text('Ingresar'));
  await tester.pumpAndSettle();
}

void main() {
  group('Inicio de sesión', () {
    testWidgets('muestra el error del servidor y no entra', (tester) async {
      final servidor = ServidorFalso()..rutas['POST auth/login'] = (_) => _json({'message': 'Usuario o contraseña incorrectos.'}, 401);

      final sesion = await _abrirApp(tester, servidor);
      await _iniciarSesion(tester, clave: 'mala');

      expect(find.text('Usuario o contraseña incorrectos.'), findsOneWidget);
      expect(sesion.abierta, isFalse);
    });

    testWidgets('no envía el formulario vacío', (tester) async {
      final servidor = ServidorFalso();

      await _abrirApp(tester, servidor);
      await tester.tap(find.text('Ingresar'));
      await tester.pump();

      expect(find.text('Escribe tu usuario'), findsOneWidget);
      expect(servidor.pedidos, isEmpty);
    });

    testWidgets('con la contraseña inicial obliga a crear una propia', (tester) async {
      final servidor = ServidorFalso()
        ..rutas['POST auth/login'] = ((_) => _login('apoderado', claveInicial: true))
        ..rutas['POST auth/clave'] = ((_) => {'message': 'Contraseña actualizada.'})
        ..rutas['GET mensajes'] = ((_) => {'no_leidos': 0, 'mensajes': <Object>[]});

      await _abrirApp(tester, servidor);
      await _iniciarSesion(tester, clave: '123456');

      expect(find.text('Crea tu contraseña'), findsOneWidget);
      expect(find.text('Mensajes'), findsNothing);

      // Una contraseña débil no sale del teléfono.
      await tester.enterText(find.widgetWithText(TextFormField, 'Contraseña inicial'), '123456');
      await tester.enterText(find.widgetWithText(TextFormField, 'Nueva contraseña'), 'abc');
      await tester.enterText(find.widgetWithText(TextFormField, 'Repite la nueva contraseña'), 'abc');
      await tester.tap(find.text('Guardar contraseña'));
      await tester.pump();
      expect(find.text('Mínimo 8 caracteres'), findsOneWidget);
      expect(servidor.vecesPedido('POST auth/clave'), 0);

      await tester.enterText(find.widgetWithText(TextFormField, 'Nueva contraseña'), 'Familia2026');
      await tester.enterText(find.widgetWithText(TextFormField, 'Repite la nueva contraseña'), 'Familia2026');
      await tester.tap(find.text('Guardar contraseña'));
      await tester.pumpAndSettle();

      expect(servidor.cuerpoDe('POST auth/clave'), {'clave_actual': '123456', 'clave_nueva': 'Familia2026'});
      expect(find.text('Mensajes'), findsWidgets);
    });

    testWidgets('recuerda la sesión y vuelve al login si el servidor la rechaza', (tester) async {
      final servidor = ServidorFalso()
        ..rutas['POST auth/login'] = ((_) => _login('apoderado'))
        ..rutas['GET mensajes'] = ((_) => _json({'message': 'Inicia sesión para continuar.'}, 401));

      final sesion = await _abrirApp(tester, servidor);
      await _iniciarSesion(tester);

      // El servidor respondió 401 al pedir los mensajes: la sesión se cierra sola.
      expect(sesion.abierta, isFalse);
      expect(find.text('Ingresar'), findsOneWidget);
      expect(await sesion.almacen.leer('sesion'), isNull);
    });
  });

  group('Apoderado', () {
    ServidorFalso servidorDeApoderado() {
      var leido = false;
      return ServidorFalso()
        ..rutas['POST auth/login'] = ((_) => _login('apoderado'))
        ..rutas['GET mensajes'] = ((_) => {
          'no_leidos': leido ? 0 : 1,
          'mensajes': [
            {
              'id': 7,
              'tipo': 'entrada',
              'titulo': 'Ingresó al colegio',
              'cuerpo': 'Ana Pérez ingresó al colegio a las 07:25 (10/03/2026).',
              'fecha': '2026-03-10T07:25:00-05:00',
              'leido': leido,
              'alumno_id': 3,
              'alumno': 'Ana Pérez',
            },
          ],
        })
        ..rutas['POST mensajes/7/leer'] = ((_) {
          leido = true;
          return {'message': 'ok'};
        })
        ..rutas['GET hijos'] = ((_) => {
          'data': [
            {
              'id': 3,
              'codigo': '2SB07',
              'dni': '91749105',
              'apoderados': [
                {'nombres': 'María Pérez', 'telefono': '987654321'},
                {'nombres': 'Henry Pérez', 'telefono': null},
              ],
              'nombres': 'Ana',
              'apellidos': 'Pérez',
              'aula': '2do Secundaria B',
              'hoy': {'fecha': '2026-03-10', 'entrada': '07:25', 'salida': null, 'estado': 'puntual'},
            },
          ],
        })
        ..rutas['GET hijos/3/asistencia'] = ((_) => {'mes': '2026-03', 'dias': <Object>[], 'permisos': <Object>[]})
        ..rutas['GET hijos/3/notas'] = ((_) => {
          'anio': 2026,
          'escala': 'numerica',
          'letras': null,
          'periodos': {'1': 'Trimestre 1', '2': 'Trimestre 2', '3': 'Trimestre 3'},
          'materias': [
            {
              'materia': 'Matemática',
              'periodos': {'1': 14, '2': 17, '3': null},
              'promedio': 15.5,
            },
          ],
        });
    }

    testWidgets('ve sus mensajes y los marca como leídos al tocarlos', (tester) async {
      final servidor = servidorDeApoderado();

      await _abrirApp(tester, servidor);
      await _iniciarSesion(tester);

      expect(find.text('Ingresó al colegio'), findsOneWidget);
      expect(find.text('Ana Pérez ingresó al colegio a las 07:25 (10/03/2026).'), findsOneWidget);
      expect(find.text('1 sin leer'), findsOneWidget);

      await tester.tap(find.text('Ingresó al colegio'));
      await tester.pumpAndSettle();

      expect(servidor.vecesPedido('POST mensajes/7/leer'), 1);
      expect(find.text('1 sin leer'), findsNothing);
    });

    testWidgets('la bandeja se actualiza sola cada minuto, sin vaciar la pantalla', (tester) async {
      final servidor = servidorDeApoderado();

      await _abrirApp(tester, servidor);
      await _iniciarSesion(tester);
      expect(servidor.vecesPedido('GET mensajes'), 1);

      await tester.pump(const Duration(seconds: 61));
      // Mientras llega la respuesta nueva, el mensaje anterior sigue a la vista.
      expect(find.text('Ingresó al colegio'), findsOneWidget);
      expect(find.byType(CircularProgressIndicator), findsNothing);
      await tester.pumpAndSettle();

      expect(servidor.vecesPedido('GET mensajes'), 2);
      expect(find.text('Ingresó al colegio'), findsOneWidget);
    });

    testWidgets('cada pedido lleva el token de la sesión', (tester) async {
      final servidor = servidorDeApoderado();

      await _abrirApp(tester, servidor);
      await _iniciarSesion(tester);

      final pedido = servidor.pedidos.lastWhere((p) => p.url.path.endsWith('/mensajes'));
      expect(pedido.headers['Authorization'], 'Bearer token-de-prueba');
      expect(pedido.url.toString(), startsWith('https://colegio.test/api/v1/mensajes'));
    });

    testWidgets('ve a sus hijos y abre las notas de uno', (tester) async {
      final servidor = servidorDeApoderado();

      await _abrirApp(tester, servidor);
      await _iniciarSesion(tester);

      await tester.tap(find.text('Mis hijos').last);
      await tester.pumpAndSettle();
      expect(find.text('Ana Pérez'), findsOneWidget);
      expect(find.text('2do Secundaria B'), findsOneWidget);
      expect(find.text('Ingresó a las 07:25'), findsOneWidget);

      await tester.tap(find.text('Ana Pérez'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Notas'));
      await tester.pumpAndSettle();

      expect(find.text('Matemática'), findsOneWidget);
      expect(find.text('Promedio 15.5'), findsOneWidget);
      expect(find.text('Trimestre 3'), findsOneWidget);
    });

    testWidgets('pide la matrícula de su hijo y abre el pago en línea', (tester) async {
      var estado = 'sin_solicitar';
      Uri? abierta;
      Map<String, dynamic> resumen() => {
        'abierta': true,
        'anio': 2027,
        'monto': 300,
        'fecha_limite': null,
        'pago_en_linea': true,
        'grados': [
          {'id': 1, 'nombre': '1ro Secundaria'},
          {'id': 2, 'nombre': '2do Secundaria'},
        ],
        'hijos': [
          {
            'id': 3,
            'nombre': 'Ana Pérez',
            'aula_actual': '1ro Secundaria A',
            'estado': estado,
            'aula': estado == 'matriculado' ? '2do Secundaria A' : null,
            'solicitud_id': estado == 'pendiente_pago' ? 8 : null,
            'grado_solicitado': estado == 'sin_solicitar' ? null : '2do Secundaria',
            'monto': estado == 'sin_solicitar' ? null : 300,
            'grado_sugerido_id': 2,
          },
        ],
      };
      Map<String, dynamic>? pedido;
      final api = Api(
        baseUrl: 'https://colegio.test/api/v1',
        cliente:
            (ServidorFalso()
                  ..rutas['GET matricula'] = ((_) => resumen())
                  ..rutas['POST matricula/hijos/3'] = ((peticion) {
                    pedido = jsonDecode(peticion.body) as Map<String, dynamic>;
                    estado = 'pendiente_pago';
                    return {'message': 'Solicitud registrada. La matrícula se confirma con el pago.', ...resumen()};
                  })
                  ..rutas['POST matricula/solicitudes/8/pagar'] = ((_) => {'url': 'https://www.mercadopago.com.pe/pagar/pref-9'}))
                .cliente,
      );

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: PantallaMatricula(
              api: api,
              abrirEnlace: (direccion) async {
                abierta = direccion;
                // Paga en el navegador; al volver ya está matriculado.
                estado = 'matriculado';
                return true;
              },
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.textContaining('La matrícula en línea está abierta. Costo: S/ 300.00'), findsOneWidget);
      expect(find.text('Ana Pérez'), findsOneWidget);
      // Propone el grado siguiente.
      expect(find.text('2do Secundaria'), findsOneWidget);

      await tester.tap(find.text('Solicitar matrícula'));
      await tester.pumpAndSettle();
      expect(pedido?['grado_id'], 2);
      expect(find.text('Solicitud registrada para 2do Secundaria'), findsOneWidget);
      expect(find.text('Falta el pago de S/ 300.00 para confirmar la matrícula.'), findsOneWidget);

      await tester.tap(find.text('Pagar en línea ahora'));
      await tester.pumpAndSettle();
      expect(abierta.toString(), 'https://www.mercadopago.com.pe/pagar/pref-9');

      // Vuelve a la app.
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      await tester.pumpAndSettle();
      expect(find.text('Matriculado en 2do Secundaria A (2027)'), findsOneWidget);
    });

    testWidgets('ve el carné de su hijo con su código QR', (tester) async {
      final servidor = servidorDeApoderado();

      await _abrirApp(tester, servidor);
      await _iniciarSesion(tester);

      await tester.tap(find.text('Mis hijos').last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Ana Pérez'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Carné'));
      await tester.pumpAndSettle();

      expect(find.byType(QrImageView), findsOneWidget);
      expect(find.text('2SB07'), findsOneWidget);
      expect(find.text('2do Secundaria B'), findsOneWidget);
      // Anverso y reverso, con el modelo del colegio.
      expect(find.text('CARNÉ DE ALUMNO'), findsOneWidget);
      expect(find.text('PÉREZ ANA'), findsOneWidget);
      expect(find.text('91749105'), findsOneWidget);
      expect(find.text('MARÍA PÉREZ - HENRY PÉREZ'), findsOneWidget);
      expect(find.text('987654321'), findsOneWidget);
    });
  });

  group('Guardia', () {
    testWidgets('marca la asistencia escribiendo el código', (tester) async {
      final servidor = ServidorFalso()
        ..rutas['POST auth/login'] = ((_) => _login('guardia'))
        ..rutas['POST asistencia/marcar'] = ((peticion) {
          final codigo = (jsonDecode(peticion.body) as Map<String, dynamic>)['codigo'];
          if (codigo == 'NADA') {
            return _json({'message': 'Código no reconocido. No corresponde a un alumno ni a personal activo.'}, 422);
          }
          return {
            'evento': 'entrada',
            'registrado': true,
            'mensaje': 'Ana Pérez. Entrada: 07:20',
            'entrada': '07:20',
            'salida': null,
            'persona': {'tipo': 'alumno', 'codigo': '2SB01', 'nombre': 'Ana Pérez', 'detalle': '2do Secundaria B', 'foto': null},
          };
        });

      await _abrirApp(tester, servidor);
      await _iniciarSesion(tester, usuario: 'porteria', clave: 'Guardia2026');

      expect(find.text('Acerca el carné a la cámara'), findsOneWidget);

      await tester.enterText(find.widgetWithText(TextField, 'Código del carné'), '2sb01 ');
      await tester.tap(find.text('Marcar'));
      await tester.pumpAndSettle();

      expect(servidor.cuerpoDe('POST asistencia/marcar'), {'codigo': '2sb01'});
      expect(find.text('Ana Pérez. Entrada: 07:20'), findsOneWidget);
      expect(find.text('2do Secundaria B · Entrada 07:20'), findsOneWidget);

      await tester.enterText(find.widgetWithText(TextField, 'Código del carné'), 'NADA');
      await tester.tap(find.text('Marcar'));
      await tester.pumpAndSettle();
      expect(find.textContaining('Código no reconocido'), findsOneWidget);
    });

    testWidgets('el escáner solo existe mientras su pestaña está a la vista', (tester) async {
      final servidor = ServidorFalso()
        ..rutas['POST auth/login'] = ((_) => _login('guardia'))
        ..rutas['GET asistencia/hoy'] = ((_) => {
          'fecha': '2026-03-10',
          'alumnos': {'total': 1, 'presentes': 0, 'tardanzas': 0, 'salieron': 0},
          'personal': {'total': 2, 'presentes': 0, 'tardanzas': 0, 'salieron': 0},
          'ultimas': <Object>[],
        });

      await _abrirApp(tester, servidor);
      await _iniciarSesion(tester, usuario: 'porteria', clave: 'Guardia2026');
      expect(find.byType(PantallaEscaner), findsOneWidget);

      // Al pasar a otra pestaña el escáner se desmonta: la cámara queda libre.
      await tester.tap(find.text('Hoy'));
      await tester.pumpAndSettle();
      expect(find.byType(PantallaEscaner), findsNothing);
      expect(find.text('Todavía no hay marcas hoy.'), findsOneWidget);

      await tester.tap(find.text('Escanear'));
      await tester.pumpAndSettle();
      expect(find.byType(PantallaEscaner), findsOneWidget);
    });

    testWidgets('el guardia no ve pantallas de apoderado', (tester) async {
      final servidor = ServidorFalso()..rutas['POST auth/login'] = ((_) => _login('guardia'));

      await _abrirApp(tester, servidor);
      await _iniciarSesion(tester, usuario: 'porteria', clave: 'Guardia2026');

      expect(find.text('Buscar'), findsOneWidget);
      expect(find.text('Hoy'), findsOneWidget);
      expect(find.text('Mis hijos'), findsNothing);
      expect(servidor.vecesPedido('GET mensajes'), 0);
    });
  });

  group('Docente', () {
    testWidgets('registra las notas de su aula', (tester) async {
      final servidor = ServidorFalso()
        ..rutas['POST auth/login'] = ((_) => _login('personal', codigo: 'DOC01'))
        ..rutas['GET docente/aulas'] = ((_) => {
          'anio': 2026,
          'aulas': [
            {'seccion_id': 4, 'aula': '2do Secundaria B', 'nivel': 'secundaria', 'materia_id': 9, 'materia': 'Matemática'},
          ],
        })
        ..rutas['GET docente/horario'] = ((_) => {'dias': <Object>[]})
        ..rutas['GET mi/asistencia'] = ((_) => {'mes': '2026-03', 'dias': <Object>[], 'permisos': <Object>[]})
        ..rutas['GET docente/aulas/4/notas'] = ((_) => {
          'aula': '2do Secundaria B',
          'periodo': 1,
          'periodos': {'1': 'Trimestre 1', '2': 'Trimestre 2', '3': 'Trimestre 3'},
          'escala': 'numerica',
          'letras': null,
          'nota_maxima': 20,
          'alumnos': [
            {'id': 3, 'codigo': '2SB01', 'nombre': 'Pérez, Ana', 'nota': null},
            {'id': 5, 'codigo': '2SB02', 'nombre': 'Rojas, Luis', 'nota': 12},
          ],
        })
        ..rutas['POST docente/aulas/4/notas'] = ((_) => {'message': 'Notas guardadas.', 'guardadas': 2});

      await _abrirApp(tester, servidor);
      await _iniciarSesion(tester, usuario: 'luis@colegio.edu.pe', clave: 'Docente2026');

      expect(find.text('Matemática'), findsOneWidget);
      await tester.tap(find.text('Notas'));
      await tester.pumpAndSettle();

      expect(find.text('1. Pérez, Ana'), findsOneWidget);
      await tester.enterText(find.byKey(const ValueKey('nota-3-1')), '17.5');
      await tester.tap(find.text('Guardar notas'));
      await tester.pumpAndSettle();

      expect(servidor.cuerpoDe('POST docente/aulas/4/notas'), {
        'materia_id': 9,
        'periodo': 1,
        'notas': {'3': '17.5', '5': '12'},
      });
      expect(find.text('Notas guardadas.'), findsOneWidget);
    });
  });

  group('Reglas sueltas', () {
    test('la contraseña nueva exige largo, mayúsculas, minúsculas y números', () {
      expect(validarClaveNueva('abc'), isNotNull);
      expect(validarClaveNueva('abcdefgh1'), isNotNull);
      expect(validarClaveNueva('Abcdefgh'), isNotNull);
      expect(validarClaveNueva('Familia2026'), isNull);
    });

    test('las fechas se muestran como se usan en el colegio', () {
      final ahora = DateTime(2026, 3, 10, 12);

      expect(Fechas.corta('2026-03-10'), '10/03/2026');
      expect(Fechas.relativa(DateTime(2026, 3, 10, 7, 25).toIso8601String(), ahora: ahora), 'Hoy 07:25');
      expect(Fechas.relativa(DateTime(2026, 3, 9, 13, 5).toIso8601String(), ahora: ahora), 'Ayer 13:05');
      expect(Fechas.relativa(DateTime(2026, 3, 2, 8, 0).toIso8601String(), ahora: ahora), '02/03 08:00');
      expect(Fechas.claveDeMes(DateTime(2026, 3, 10)), '2026-03');
      expect(Fechas.nombreDeMes(DateTime(2026, 3, 10)), 'marzo 2026');
    });

    test('un fallo de red se explica en vez de reventar', () async {
      final api = Api(baseUrl: 'https://colegio.test/api/v1', cliente: MockClient((_) async => throw http.ClientException('sin red')));

      await expectLater(api.get('hijos'), throwsA(isA<ErrorApi>().having((e) => e.mensaje, 'mensaje', contains('Sin conexión'))));
    });

    test('los errores de validación llegan por campo', () async {
      final api = Api(
        baseUrl: 'https://colegio.test/api/v1',
        cliente: MockClient(
          (_) async => _json({
            'message': 'La clave es débil.',
            'errors': {
              'clave_nueva': ['La clave es débil.'],
            },
          }, 422),
        ),
      );

      await expectLater(
        api.post('auth/clave', {'clave_nueva': 'x'}),
        throwsA(
          isA<ErrorApi>().having((e) => e.estado, 'estado', 422).having((e) => e.campos['clave_nueva'], 'campos', ['La clave es débil.']),
        ),
      );
    });
  });
}
