import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

/// Un error que el servidor explicó (o un fallo de red) listo para mostrar.
class ErrorApi implements Exception {
  ErrorApi(this.mensaje, {this.estado = 0, this.codigo, this.campos = const {}});

  /// Texto para la persona: "Usuario o contraseña incorrectos."
  final String mensaje;

  /// Código HTTP; 0 si ni siquiera hubo respuesta.
  final int estado;

  /// Código propio del servidor, por ejemplo "cambiar_clave".
  final String? codigo;

  /// Errores de validación por campo.
  final Map<String, List<String>> campos;

  bool get sinSesion => estado == 401;
  bool get debeCambiarClave => codigo == 'cambiar_clave';

  @override
  String toString() => mensaje;
}

/// Cliente de la API del colegio. Todas las pantallas hablan con el servidor
/// a través de esta clase.
class Api {
  Api({required this.baseUrl, http.Client? cliente}) : _cliente = cliente ?? http.Client();

  String baseUrl;
  String? token;

  /// Se llama cuando el servidor responde que la sesión ya no vale.
  void Function()? alPerderSesion;

  final http.Client _cliente;

  static const _espera = Duration(seconds: 20);

  Future<dynamic> get(String ruta, {Map<String, String?> consulta = const {}}) {
    return _enviar('GET', ruta, consulta: consulta);
  }

  Future<dynamic> post(String ruta, [Map<String, dynamic> cuerpo = const {}]) {
    return _enviar('POST', ruta, cuerpo: cuerpo);
  }

  Future<dynamic> _enviar(String metodo, String ruta, {Map<String, String?> consulta = const {}, Map<String, dynamic>? cuerpo}) async {
    final limpia = <String, String>{
      for (final e in consulta.entries)
        if (e.value != null && e.value!.isNotEmpty) e.key: e.value!,
    };
    final base = Uri.parse('$baseUrl/$ruta');
    final uri = limpia.isEmpty ? base : base.replace(queryParameters: limpia);

    final peticion = http.Request(metodo, uri)
      ..headers['Accept'] = 'application/json'
      ..headers['Content-Type'] = 'application/json; charset=utf-8';
    if (token != null) {
      peticion.headers['Authorization'] = 'Bearer $token';
    }
    if (cuerpo != null) {
      peticion.body = jsonEncode(cuerpo);
    }

    final http.Response respuesta;
    try {
      respuesta = await http.Response.fromStream(await _cliente.send(peticion).timeout(_espera));
    } on TimeoutException {
      throw ErrorApi('El servidor tardó demasiado en responder. Inténtalo de nuevo.');
    } on Exception {
      throw ErrorApi('Sin conexión con el colegio. Revisa tu internet.');
    }

    dynamic datos;
    try {
      datos = respuesta.body.isEmpty ? null : jsonDecode(utf8.decode(respuesta.bodyBytes));
    } on FormatException {
      datos = null;
    }

    if (respuesta.statusCode >= 200 && respuesta.statusCode < 300) {
      return datos;
    }

    final mapa = datos is Map<String, dynamic> ? datos : const <String, dynamic>{};
    final error = ErrorApi(
      (mapa['message'] as String?) ?? 'Ocurrió un error (${respuesta.statusCode}).',
      estado: respuesta.statusCode,
      codigo: mapa['codigo'] as String?,
      campos: {
        for (final e in ((mapa['errors'] as Map<String, dynamic>?) ?? const {}).entries)
          e.key: [for (final m in (e.value as List)) m.toString()],
      },
    );

    if (error.sinSesion && token != null) {
      alPerderSesion?.call();
    }
    throw error;
  }
}
