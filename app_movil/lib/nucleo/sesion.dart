import 'package:flutter/foundation.dart';

import 'almacen.dart';
import 'api.dart';
import 'perfil.dart';

/// La sesión abierta en este teléfono. Las pantallas la escuchan: cuando
/// cambia (entrar, salir, cambiar la clave) la app se redibuja sola.
class Sesion extends ChangeNotifier {
  Sesion({required this.api, required this.almacen}) {
    api.alPerderSesion = _olvidar;
  }

  static const _clave = 'sesion';

  final Api api;
  final Almacen almacen;

  Perfil? _perfil;
  String _colegio = 'Mi colegio';
  bool _lista = false;

  /// Ya se revisó si había una sesión guardada.
  bool get lista => _lista;
  bool get abierta => _perfil != null;
  Perfil get perfil => _perfil!;
  String get colegio => _colegio;

  /// Recupera la sesión guardada, si la hay. Se llama una vez al abrir la app.
  Future<void> restaurar() async {
    final guardada = await almacen.leer(_clave);
    if (guardada != null && guardada['token'] is String && guardada['perfil'] is Map) {
      api.token = guardada['token'] as String;
      _perfil = Perfil.desdeJson(Map<String, dynamic>.from(guardada['perfil'] as Map));
      _colegio = guardada['colegio'] as String? ?? _colegio;
    }
    _lista = true;
    notifyListeners();
  }

  Future<void> iniciar(String usuario, String clave) async {
    final datos =
        await api.post('auth/login', {'usuario': usuario.trim(), 'clave': clave, 'dispositivo': 'App del colegio'}) as Map<String, dynamic>;

    api.token = datos['token'] as String;
    _perfil = Perfil.desdeJson(datos['perfil'] as Map<String, dynamic>);
    _colegio = (datos['colegio'] as Map<String, dynamic>?)?['nombre'] as String? ?? _colegio;
    await _persistir();
    notifyListeners();
  }

  Future<void> cambiarClave(String actual, String nueva) async {
    await api.post('auth/clave', {'clave_actual': actual, 'clave_nueva': nueva});
    _perfil = perfil.conClaveCambiada();
    await _persistir();
    notifyListeners();
  }

  Future<void> cerrar() async {
    try {
      await api.post('auth/logout');
    } on ErrorApi {
      // Sin red igual se cierra en este teléfono.
    }
    await _olvidar();
  }

  Future<void> _persistir() => almacen.guardar(_clave, {'token': api.token, 'perfil': perfil.aJson(), 'colegio': _colegio});

  Future<void> _olvidar() async {
    api.token = null;
    _perfil = null;
    await almacen.borrar(_clave);
    notifyListeners();
  }
}
