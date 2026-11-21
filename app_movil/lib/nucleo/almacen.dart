import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Lo que la app recuerda entre un uso y otro: la sesión y el servidor.
abstract class Almacen {
  Future<Map<String, dynamic>?> leer(String clave);
  Future<void> guardar(String clave, Map<String, dynamic> valor);
  Future<void> borrar(String clave);
}

/// Guarda en las preferencias del teléfono.
class AlmacenDelTelefono implements Almacen {
  @override
  Future<Map<String, dynamic>?> leer(String clave) async {
    final texto = (await SharedPreferences.getInstance()).getString(clave);
    if (texto == null) return null;
    try {
      return jsonDecode(texto) as Map<String, dynamic>;
    } on FormatException {
      return null;
    }
  }

  @override
  Future<void> guardar(String clave, Map<String, dynamic> valor) async {
    await (await SharedPreferences.getInstance()).setString(clave, jsonEncode(valor));
  }

  @override
  Future<void> borrar(String clave) async {
    await (await SharedPreferences.getInstance()).remove(clave);
  }
}

/// Guarda solo en memoria: para las pruebas.
class AlmacenEnMemoria implements Almacen {
  final Map<String, Map<String, dynamic>> _datos = {};

  @override
  Future<Map<String, dynamic>?> leer(String clave) async => _datos[clave];

  @override
  Future<void> guardar(String clave, Map<String, dynamic> valor) async => _datos[clave] = valor;

  @override
  Future<void> borrar(String clave) async => _datos.remove(clave);
}
