import 'package:material_ui/material_ui.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../nucleo/perfil.dart';
import '../nucleo/sesion.dart';
import '../nucleo/tema.dart';

/// El carné digital de quien inició sesión (alumno o personal).
class PantallaCarne extends StatelessWidget {
  const PantallaCarne({super.key, required this.sesion, required this.codigo});

  final Sesion sesion;
  final String? codigo;

  @override
  Widget build(BuildContext context) {
    final perfil = sesion.perfil;
    final carne = perfil.carne;
    final token = sesion.api.token;

    return Carne(
      titulo: (carne?['titulo'] as String?) ?? (perfil.rol == Rol.alumno ? 'CARNÉ DE ALUMNO' : 'CARNÉ DE PERSONAL'),
      colegio: sesion.colegio,
      nombre: (carne?['nombre'] as String?) ?? perfil.nombre,
      iniciales: carne?['iniciales'] as String?,
      codigo: codigo,
      etiquetaDetalle: (carne?['detalle_etiqueta'] as String?) ?? 'Grado y sección',
      grado: carne?['detalle'] as String?,
      dni: carne?['dni'] as String?,
      foto: carne?['foto'] as String?,
      cabeceras: token == null ? null : {'Authorization': 'Bearer $token'},
      // El reverso con los apoderados es del carné del alumno.
      apoderados: perfil.rol == Rol.alumno ? (carne?['apoderados'] as String?) : null,
      telefono: carne?['telefono'] as String?,
      leyenda: 'Muestra este código en la puerta para registrar tu ingreso y tu salida.',
      sinCodigo: 'Tu cuenta todavía no tiene un código de carné. Avisa en secretaría.',
    );
  }
}

/// El carné con el modelo del colegio: tamaño DNI, anverso y reverso.
/// Lo usa cada quien con el suyo y el apoderado con el de su hijo.
class Carne extends StatelessWidget {
  const Carne({
    super.key,
    required this.colegio,
    required this.nombre,
    required this.codigo,
    required this.leyenda,
    required this.sinCodigo,
    this.titulo = 'CARNÉ DE ALUMNO',
    this.iniciales,
    this.etiquetaDetalle = 'Grado y sección',
    this.grado,
    this.dni,
    this.apoderados,
    this.telefono,
    this.foto,
    this.cabeceras,
  });

  final String titulo;
  final String colegio;

  /// Como va impreso: apellidos y nombres.
  final String nombre;
  final String? codigo;
  final String? iniciales;

  /// El dato bajo el nombre: el aula del alumno o el cargo del trabajador.
  final String etiquetaDetalle;
  final String? grado;
  final String? dni;

  /// Si viene, se dibuja también el reverso.
  final String? apoderados;
  final String? telefono;

  /// Dirección de la foto. Está protegida: se pide con [cabeceras] (el token).
  final String? foto;
  final Map<String, String>? cabeceras;
  final String leyenda;
  final String sinCodigo;

  static const _azul = Color(0xFF14213D);

  @override
  Widget build(BuildContext context) {
    if (codigo == null || codigo!.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Text(
            sinCodigo,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Marca.textoSuave),
          ),
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        _Tarjeta(titulo: titulo, subtitulo: '$colegio · Control de acceso', anio: DateTime.now().year, cuerpo: _anverso()),
        if (apoderados != null) ...[
          const SizedBox(height: 16),
          _Tarjeta(titulo: colegio, subtitulo: 'Información adicional', cuerpo: _reverso()),
        ],
        const SizedBox(height: 16),
        Text(
          leyenda,
          textAlign: TextAlign.center,
          style: const TextStyle(color: Marca.textoSuave),
        ),
      ],
    );
  }

  Widget _anverso() {
    // Sin foto, o si no carga, van las iniciales.
    final conIniciales = Container(
      width: 80,
      height: 102,
      alignment: Alignment.center,
      color: Marca.primario,
      child: Text(
        iniciales ?? _inicialesDe(nombre),
        style: const TextStyle(color: Colors.white, fontSize: 34, fontWeight: FontWeight.w600),
      ),
    );

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(6),
            child: foto == null
                ? conIniciales
                : Image.network(
                    foto!,
                    headers: cabeceras,
                    width: 80,
                    height: 102,
                    fit: BoxFit.cover,
                    semanticLabel: 'Foto de $nombre',
                    errorBuilder: (_, _, _) => conIniciales,
                  ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _Campo('Apellidos y nombres', nombre.toUpperCase(), lineas: 2),
                if (grado != null) _Campo(etiquetaDetalle, grado!),
                if (dni != null && dni!.isNotEmpty) _Campo('Documento de identidad', dni!),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Semantics(
                label: 'Código QR del carné $codigo',
                child: QrImageView(data: codigo!, size: 92, padding: EdgeInsets.zero, backgroundColor: Colors.white),
              ),
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                decoration: BoxDecoration(color: _azul, borderRadius: BorderRadius.circular(6)),
                child: Text(
                  codigo!,
                  style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700, letterSpacing: 1),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _reverso() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
            decoration: BoxDecoration(
              color: const Color(0xFFFFF8E1),
              border: Border.all(color: const Color(0xFFF0C040)),
              borderRadius: BorderRadius.circular(6),
            ),
            child: Text(
              'Este carné es personal e intransferible. Si lo encuentra, comuníquese con $colegio.',
              style: const TextStyle(color: Color(0xFF5C4A00), fontSize: 10.5, height: 1.25),
            ),
          ),
          const SizedBox(height: 8),
          _Campo('Apoderado', apoderados!.isEmpty ? '—' : apoderados!.toUpperCase(), lineas: 2),
          _Campo('Teléfono de contacto', (telefono == null || telefono!.isEmpty) ? '—' : telefono!),
          const Spacer(),
          const Divider(height: 8, color: Color(0xFFCFD4DE)),
          Center(
            child: Text('Firma autorizada — $colegio', style: const TextStyle(color: Marca.textoSuave, fontSize: 9)),
          ),
        ],
      ),
    );
  }

  static String _inicialesDe(String nombre) {
    final palabras = nombre.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
    if (palabras.isEmpty) return '';
    final primera = palabras.first.substring(0, 1);
    return (palabras.length == 1 ? primera : primera + palabras.last.substring(0, 1)).toUpperCase();
  }
}

/// Una cara del carné. Se dibuja a un tamaño fijo con la proporción de un DNI
/// (85,6 × 54 mm) y se escala al ancho de la pantalla.
class _Tarjeta extends StatelessWidget {
  const _Tarjeta({required this.titulo, required this.subtitulo, required this.cuerpo, this.anio});

  final String titulo;
  final String subtitulo;
  final Widget cuerpo;
  final int? anio;

  @override
  Widget build(BuildContext context) {
    return AspectRatio(
      aspectRatio: 856 / 540,
      child: FittedBox(
        child: Container(
          width: 428,
          height: 270,
          clipBehavior: Clip.antiAlias,
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFE8EBF2)),
          ),
          child: Column(
            children: [
              Container(
                height: 72,
                color: Carne._azul,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Row(
                  children: [
                    Container(
                      width: 46,
                      height: 46,
                      padding: const EdgeInsets.all(4),
                      decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                      child: Image.asset('assets/logo.png'),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            titulo,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800),
                          ),
                          Text(
                            subtitulo.toUpperCase(),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: Color(0xBFFFFFFF), fontSize: 9.5, letterSpacing: .6),
                          ),
                        ],
                      ),
                    ),
                    if (anio != null) ...[
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: const Color(0x1FFFFFFF),
                          border: Border.all(color: const Color(0x59FFFFFF)),
                          borderRadius: BorderRadius.circular(7),
                        ),
                        child: Column(
                          children: [
                            const Text(
                              'AÑO',
                              style: TextStyle(color: Color(0xCCFFFFFF), fontSize: 8, fontWeight: FontWeight.w600),
                            ),
                            Text(
                              '$anio',
                              style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w800),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              Expanded(child: cuerpo),
            ],
          ),
        ),
      ),
    );
  }
}

class _Campo extends StatelessWidget {
  const _Campo(this.etiqueta, this.valor, {this.lineas = 1});

  final String etiqueta;
  final String valor;
  final int lineas;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            etiqueta.toUpperCase(),
            style: const TextStyle(color: Marca.primario, fontSize: 9, fontWeight: FontWeight.w800, letterSpacing: .5),
          ),
          Text(
            valor,
            maxLines: lineas,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Carne._azul, fontSize: 14.5, fontWeight: FontWeight.w800, height: 1.15),
          ),
        ],
      ),
    );
  }
}
