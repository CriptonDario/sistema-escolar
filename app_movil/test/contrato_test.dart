// Prueba de contrato: la app habla con un servidor Laravel DE VERDAD y
// comprueba que las respuestas traen lo que las pantallas leen.
//
// Las demás pruebas usan un servidor simulado escrito a mano; esta es la que
// detecta si la app y el servidor dejaron de entenderse.
//
// Necesita un servidor levantado con los datos de demostración
// (php artisan db:seed --class=DemoSeeder) y se ejecuta así:
//
//   flutter test test/contrato_test.dart --dart-define=API_REAL=http://127.0.0.1:8802/api/v1
//
// Sin esa variable se omite sola.
import 'dart:io';

import 'package:app_colegio/nucleo/almacen.dart';
import 'package:app_colegio/nucleo/api.dart';
import 'package:app_colegio/nucleo/perfil.dart';
import 'package:app_colegio/nucleo/sesion.dart';
import 'package:flutter_test/flutter_test.dart';

const _url = String.fromEnvironment('API_REAL');

Sesion _sesionNueva() => Sesion(
  api: Api(baseUrl: _url),
  almacen: AlmacenEnMemoria(),
);

void main() {
  // Las pruebas de Flutter bloquean la red real por defecto.
  setUpAll(() => HttpOverrides.global = null);

  group('Contrato con el servidor', skip: _url.isEmpty ? 'Define API_REAL para ejecutarla.' : null, () {
    test('guardia: marca, resumen del día y búsqueda', () async {
      final sesion = _sesionNueva();
      await sesion.iniciar('porteria', 'Guardia2026');
      expect(sesion.perfil.rol, Rol.guardia);
      expect(sesion.colegio, isNotEmpty);

      final marca = await sesion.api.post('asistencia/marcar', {'codigo': 'DEMO-ALU1'}) as Map<String, dynamic>;
      expect(marca['evento'], isIn(['entrada', 'tardanza', 'entrada_ya_registrada', 'salida', 'salida_ya_registrada']));
      expect(marca['mensaje'], isA<String>());
      expect(marca['entrada'], matches(RegExp(r'^\d\d:\d\d$')));
      expect((marca['persona'] as Map)['detalle'], '2do Secundaria A');

      final hoy = await sesion.api.get('asistencia/hoy') as Map<String, dynamic>;
      for (final clave in ['total', 'presentes', 'tardanzas', 'salieron']) {
        expect((hoy['alumnos'] as Map)[clave], isA<int>(), reason: clave);
        expect((hoy['personal'] as Map)[clave], isA<int>(), reason: clave);
      }
      final ultima = (hoy['ultimas'] as List).first as Map<String, dynamic>;
      expect((ultima['persona'] as Map)['nombre'], isA<String>());
      expect(ultima['estado'], isA<String>());

      final busqueda = await sesion.api.get('asistencia/personas', consulta: {'q': 'demo'}) as Map<String, dynamic>;
      final persona = (busqueda['personas'] as List).first as Map<String, dynamic>;
      expect(persona.keys, containsAll(['tipo', 'codigo', 'nombre', 'detalle', 'entrada', 'salida']));

      await expectLater(
        sesion.api.post('asistencia/marcar', {'codigo': 'NO-EXISTE'}),
        throwsA(isA<ErrorApi>().having((e) => e.estado, 'estado', 422).having((e) => e.mensaje, 'mensaje', contains('no reconocido'))),
      );

      await sesion.cerrar();
      expect(sesion.abierta, isFalse);
    });

    test('apoderado: clave inicial, mensajes y expediente completo del hijo', () async {
      final sesion = _sesionNueva();

      // La cuenta de demostración nace con la contraseña inicial. Si una
      // corrida anterior ya la cambió, se entra con la nueva.
      try {
        await sesion.iniciar('987 654 321', '123456');
        expect(sesion.perfil.debeCambiarClave, isTrue);

        await expectLater(sesion.api.get('hijos'), throwsA(isA<ErrorApi>().having((e) => e.debeCambiarClave, 'debeCambiarClave', isTrue)));
        await sesion.cambiarClave('123456', 'Familia2026');
      } on ErrorApi {
        await sesion.iniciar('987654321', 'Familia2026');
      }
      expect(sesion.perfil.rol, Rol.apoderado);
      expect(sesion.perfil.debeCambiarClave, isFalse);

      final mensajes = await sesion.api.get('mensajes', consulta: {'limite': '50'}) as Map<String, dynamic>;
      expect(mensajes['no_leidos'], isA<int>());
      final mensaje = (mensajes['mensajes'] as List).first as Map<String, dynamic>;
      expect(mensaje.keys, containsAll(['id', 'tipo', 'titulo', 'cuerpo', 'fecha', 'leido']));
      expect(DateTime.tryParse(mensaje['fecha'] as String), isNotNull);
      await sesion.api.post('mensajes/${mensaje['id']}/leer');
      await sesion.api.post('mensajes/leer-todos');

      final hijos = (await sesion.api.get('hijos') as Map<String, dynamic>)['data'] as List;
      final hijo = hijos.first as Map<String, dynamic>;
      expect(hijo.keys, containsAll(['id', 'nombres', 'apellidos', 'aula', 'hoy']));
      expect((hijo['hoy'] as Map).keys, containsAll(['entrada', 'salida', 'estado']));
      final prefijo = 'hijos/${hijo['id']}';

      final asistencia = await sesion.api.get('$prefijo/asistencia') as Map<String, dynamic>;
      expect(asistencia.keys, containsAll(['mes', 'dias', 'permisos']));
      expect(((asistencia['dias'] as List).first as Map).keys, containsAll(['fecha', 'entrada', 'salida', 'estado']));

      final notas = await sesion.api.get('$prefijo/notas') as Map<String, dynamic>;
      expect(notas['escala'], 'numerica');
      expect((notas['periodos'] as Map).keys, contains('1'));
      final materia = (notas['materias'] as List).first as Map<String, dynamic>;
      expect(materia['materia'], 'Matemática');
      expect((materia['periodos'] as Map)['1'], 16);
      expect(materia['promedio'], 16);

      final horario = await sesion.api.get('$prefijo/horario') as Map<String, dynamic>;
      final dia = (horario['dias'] as List).first as Map<String, dynamic>;
      expect(dia['nombre'], 'Lunes');
      expect(((dia['bloques'] as List).first as Map).keys, containsAll(['inicio', 'fin', 'nombre', 'docente']));

      expect((await sesion.api.get('$prefijo/tareas') as Map)['tareas'], isA<List>());
      expect((await sesion.api.get('$prefijo/conducta') as Map)['registros'], isA<List>());
      expect((await sesion.api.get('$prefijo/comunicados') as Map)['comunicados'], isA<List>());

      final pagos = await sesion.api.get('$prefijo/pagos') as Map<String, dynamic>;
      expect(pagos.keys, containsAll(['anio', 'pensiones', 'pensiones_vencidas', 'pagos']));
      expect(((pagos['pensiones'] as List).first as Map).keys, containsAll(['nombre', 'estado']));

      // El expediente de un alumno ajeno está prohibido.
      await expectLater(sesion.api.get('hijos/999999/notas'), throwsA(isA<ErrorApi>().having((e) => e.estado, 'estado', anyOf(403, 404))));
    });

    test('docente: aulas, lista de notas, guardar y publicar', () async {
      final sesion = _sesionNueva();
      try {
        await sesion.iniciar('luis.ramos@demo.edu.pe', '123456');
        await sesion.cambiarClave('123456', 'Docente2026');
      } on ErrorApi {
        await sesion.iniciar('luis.ramos@demo.edu.pe', 'Docente2026');
      }
      expect(sesion.perfil.rol, Rol.personal);
      expect(sesion.perfil.codigo, 'DEMO-DOC1');

      final aulas = (await sesion.api.get('docente/aulas') as Map<String, dynamic>)['aulas'] as List;
      final aula = aulas.first as Map<String, dynamic>;
      expect(aula.keys, containsAll(['seccion_id', 'aula', 'materia_id', 'materia']));

      final ruta = 'docente/aulas/${aula['seccion_id']}/notas';
      final lista = await sesion.api.get(ruta, consulta: {'materia_id': '${aula['materia_id']}', 'periodo': '2'}) as Map<String, dynamic>;
      expect(lista.keys, containsAll(['periodos', 'escala', 'letras', 'nota_maxima', 'alumnos']));
      final alumno = (lista['alumnos'] as List).first as Map<String, dynamic>;
      expect(alumno.keys, containsAll(['id', 'nombre', 'nota']));

      // El mismo cuerpo que arma la pantalla de notas: valores como texto.
      final guardado = await sesion.api.post(ruta, {
        'materia_id': aula['materia_id'],
        'periodo': 2,
        'notas': {'${alumno['id']}': '18.5'},
      }) as Map<String, dynamic>;
      expect(guardado['guardadas'], 1);

      final releida = await sesion.api.get(ruta, consulta: {'materia_id': '${aula['materia_id']}', 'periodo': '2'}) as Map<String, dynamic>;
      expect(((releida['alumnos'] as List).first as Map)['nota'], 18.5);

      final manana = DateTime.now().add(const Duration(days: 1)).toIso8601String().substring(0, 10);
      await sesion.api.post('docente/aulas/${aula['seccion_id']}/tareas', {
        'materia_id': aula['materia_id'],
        'tipo': 'tarea',
        'titulo': 'Ejercicios de contrato',
        'descripcion': null,
        'fecha_entrega': manana,
      });
      await sesion.api.post('docente/conducta', {'alumno_id': alumno['id'], 'tipo': 'merito', 'descripcion': 'Prueba de contrato'});

      final horario = await sesion.api.get('docente/horario') as Map<String, dynamic>;
      expect((((horario['dias'] as List).first as Map)['bloques'] as List).first, containsPair('aula', '2do Secundaria A'));
      expect((await sesion.api.get('mi/asistencia') as Map).keys, containsAll(['mes', 'dias']));
    });

    test('alumno: su carné y su expediente', () async {
      final sesion = _sesionNueva();
      try {
        await sesion.iniciar('ana.perez@demo.edu.pe', '123456');
        await sesion.cambiarClave('123456', 'Alumna2026');
      } on ErrorApi {
        await sesion.iniciar('ana.perez@demo.edu.pe', 'Alumna2026');
      }
      expect(sesion.perfil.rol, Rol.alumno);
      expect(sesion.perfil.codigo, 'DEMO-ALU1');

      expect(((await sesion.api.get('mi/notas') as Map)['materias'] as List), isNotEmpty);
      expect((await sesion.api.get('mi/horario') as Map)['dias'], isNotEmpty);
      // La tarea y el mérito que publicó el docente en la prueba anterior.
      expect(
        ((await sesion.api.get('mi/tareas') as Map)['tareas'] as List).map((t) => (t as Map)['titulo']),
        contains('Ejercicios de contrato'),
      );
      expect(((await sesion.api.get('mi/conducta') as Map)['registros'] as List).first, containsPair('tipo_etiqueta', 'Mérito'));
      expect((await sesion.api.get('mi/comunicados') as Map)['comunicados'], isA<List>());
      expect((await sesion.api.get('mi/asistencia') as Map)['dias'], isNotEmpty);
    });
  });
}
