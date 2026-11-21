import 'package:material_ui/material_ui.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../nucleo/api.dart';
import '../../nucleo/tema.dart';

/// Registro de asistencia en la puerta: la cámara lee el QR del carné, o se
/// escribe el código a mano. Sirve para alumnos y para personal.
class PantallaEscaner extends StatefulWidget {
  const PantallaEscaner({super.key, required this.api, this.conCamara = true});

  final Api api;

  /// Falso en las pruebas y donde no hay cámara: queda solo el campo de texto.
  final bool conCamara;

  @override
  State<PantallaEscaner> createState() => PantallaEscanerState();
}

class PantallaEscanerState extends State<PantallaEscaner> {
  final _codigo = TextEditingController();
  Map<String, dynamic>? _resultado;
  String? _error;
  bool _enVuelo = false;
  String _ultimoCodigo = '';
  DateTime _ultimoMomento = DateTime.fromMillisecondsSinceEpoch(0);

  @override
  void dispose() {
    _codigo.dispose();
    super.dispose();
  }

  /// La cámara lee el mismo QR muchas veces por segundo: se ignora el mismo
  /// código durante unos segundos y nunca hay dos envíos a la vez.
  Future<void> marcar(String codigo, {bool aMano = false}) async {
    final limpio = codigo.trim();
    final ahora = DateTime.now();
    final repetido = limpio == _ultimoCodigo && ahora.difference(_ultimoMomento) < const Duration(seconds: 4);
    if (limpio.isEmpty || _enVuelo || (repetido && !aMano)) return;

    _enVuelo = true;
    _ultimoCodigo = limpio;
    _ultimoMomento = ahora;

    try {
      final datos = await widget.api.post('asistencia/marcar', {'codigo': limpio}) as Map<String, dynamic>;
      if (mounted) {
        setState(() {
          _resultado = datos;
          _error = null;
        });
      }
    } on ErrorApi catch (error) {
      if (mounted) {
        setState(() {
          _resultado = null;
          _error = error.mensaje;
        });
      }
      if (error.estado == 0) _ultimoCodigo = ''; // sin red: que se pueda reintentar ya
    } finally {
      _enVuelo = false;
    }
  }

  void _enviarAMano() {
    marcar(_codigo.text, aMano: true);
    _codigo.clear();
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        _Resultado(resultado: _resultado, error: _error),
        const SizedBox(height: 16),
        if (widget.conCamara)
          ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: AspectRatio(
              aspectRatio: 1,
              child: MobileScanner(
                onDetect: (captura) {
                  final valor = captura.barcodes.isEmpty ? null : captura.barcodes.first.rawValue;
                  if (valor != null) marcar(valor);
                },
              ),
            ),
          ),
        const SizedBox(height: 16),
        Row(
          children: [
            Expanded(
              child: TextField(
                controller: _codigo,
                decoration: const InputDecoration(labelText: 'Código del carné'),
                textCapitalization: TextCapitalization.characters,
                autocorrect: false,
                onSubmitted: (_) => _enviarAMano(),
              ),
            ),
            const SizedBox(width: 10),
            SizedBox(
              width: 110,
              child: FilledButton(onPressed: _enviarAMano, child: const Text('Marcar')),
            ),
          ],
        ),
      ],
    );
  }
}

class _Resultado extends StatelessWidget {
  const _Resultado({required this.resultado, required this.error});

  final Map<String, dynamic>? resultado;
  final String? error;

  @override
  Widget build(BuildContext context) {
    final (Color color, IconData icono, String titulo, String detalle) = _contenido();

    return Semantics(
      liveRegion: true,
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(16)),
        child: Column(
          children: [
            Icon(icono, color: Colors.white, size: 40),
            const SizedBox(height: 8),
            Text(
              titulo,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800),
            ),
            if (detalle.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(
                detalle,
                textAlign: TextAlign.center,
                style: const TextStyle(color: Color(0xE6FFFFFF)),
              ),
            ],
          ],
        ),
      ),
    );
  }

  (Color, IconData, String, String) _contenido() {
    if (error != null) {
      return (Marca.primario, Icons.error_outline, error!, '');
    }
    final r = resultado;
    if (r == null) {
      return (const Color(0xFF6C757D), Icons.qr_code_scanner, 'Acerca el carné a la cámara', 'o escribe el código');
    }

    final persona = r['persona'] as Map<String, dynamic>? ?? const {};
    final evento = r['evento'] as String? ?? '';
    final hora = evento == 'salida' ? 'Salida ${r['salida']}' : 'Entrada ${r['entrada']}';
    final detalle = [persona['detalle'], hora].whereType<String>().join(' · ');

    final (color, icono) = switch (evento) {
      'entrada' => (Marca.exito, Icons.check_circle_outline),
      'tardanza' => (Marca.aviso, Icons.schedule),
      'salida' => (Marca.info, Icons.logout),
      _ => (const Color(0xFF6C757D), Icons.info_outline),
    };
    return (color, icono, r['mensaje'] as String? ?? '', detalle);
  }
}
