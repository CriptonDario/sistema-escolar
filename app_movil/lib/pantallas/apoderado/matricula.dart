import 'package:material_ui/material_ui.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../componentes/carga.dart';
import '../../nucleo/api.dart';
import '../../nucleo/tema.dart';

/// Abre una dirección fuera de la app. Se puede reemplazar en las pruebas.
typedef AbrirEnlace = Future<bool> Function(Uri direccion);

Future<bool> _abrirEnElNavegador(Uri direccion) => launchUrl(direccion, mode: LaunchMode.externalApplication);

/// Matrícula en línea: cuando el colegio la abre, el apoderado pide la
/// matrícula de cada hijo y la paga. El alumno queda matriculado al
/// registrarse el pago (en línea o en secretaría).
class PantallaMatricula extends StatefulWidget {
  const PantallaMatricula({super.key, required this.api, this.abrirEnlace = _abrirEnElNavegador});

  final Api api;
  final AbrirEnlace abrirEnlace;

  @override
  State<PantallaMatricula> createState() => _PantallaMatriculaState();
}

class _PantallaMatriculaState extends State<PantallaMatricula> with WidgetsBindingObserver {
  final _carga = GlobalKey<CargaState<Map<String, dynamic>>>();

  /// El grado elegido para cada hijo, antes de solicitar.
  final Map<int, int> _gradoElegido = {};
  bool _enviando = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  /// Al volver de pagar en el navegador se actualiza sola.
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _carga.currentState?.recargar();
  }

  Future<void> _hacer(Future<void> Function() accion) async {
    if (_enviando) return;
    setState(() => _enviando = true);
    try {
      await accion();
    } on Object catch (error) {
      if (mounted) mostrarError(context, error);
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  Future<void> _solicitar(int alumnoId, int gradoId) => _hacer(() async {
    final respuesta = await widget.api.post('matricula/hijos/$alumnoId', {'grado_id': gradoId}) as Map<String, dynamic>;
    await _carga.currentState?.recargar();
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('${respuesta['message']}')));
  });

  Future<void> _pagar(int solicitudId) => _hacer(() async {
    final respuesta = await widget.api.post('matricula/solicitudes/$solicitudId/pagar') as Map<String, dynamic>;
    final abierto = await widget.abrirEnlace(Uri.parse(respuesta['url'] as String));
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          abierto
              ? 'Termina el pago en el navegador. Al volver aquí verás la matrícula confirmada.'
              : 'No se pudo abrir la página de pago. Inténtalo de nuevo.',
        ),
      ),
    );
  });

  @override
  Widget build(BuildContext context) {
    return Carga<Map<String, dynamic>>(
      key: _carga,
      pedir: () async => await widget.api.get('matricula') as Map<String, dynamic>,
      construir: (context, datos, _) {
        final abierta = datos['abierta'] == true;
        final anio = datos['anio'];
        final monto = (datos['monto'] as num?)?.toDouble() ?? 0;
        final enLinea = datos['pago_en_linea'] == true;
        final grados = (datos['grados'] as List).cast<Map<String, dynamic>>();
        final hijos = (datos['hijos'] as List).cast<Map<String, dynamic>>();

        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              color: abierta ? const Color(0xFFE7F1FF) : null,
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Matrícula $anio', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
                    const SizedBox(height: 4),
                    Text(
                      abierta
                          ? 'La matrícula en línea está abierta. Costo: S/ ${monto.toStringAsFixed(2)} por alumno. '
                                'Primero la pides y luego pagas: el alumno queda matriculado al registrarse el pago.'
                          : 'La matrícula en línea no está abierta en este momento. El colegio avisará cuando se abra.',
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
            if (hijos.isEmpty)
              const Aviso(icono: Icons.family_restroom, texto: 'Tu cuenta todavía no tiene alumnos vinculados. Avisa en secretaría.'),
            for (final hijo in hijos)
              Card(
                margin: const EdgeInsets.only(bottom: 12),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('${hijo['nombre']}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
                      Text('Aula actual: ${hijo['aula_actual'] ?? 'sin aula'}', style: const TextStyle(color: Marca.textoSuave)),
                      const SizedBox(height: 12),
                      ..._estadoDe(hijo, abierta: abierta, enLinea: enLinea, anio: anio, grados: grados),
                    ],
                  ),
                ),
              ),
          ],
        );
      },
    );
  }

  List<Widget> _estadoDe(
    Map<String, dynamic> hijo, {
    required bool abierta,
    required bool enLinea,
    required Object? anio,
    required List<Map<String, dynamic>> grados,
  }) {
    final id = hijo['id'] as int;

    switch (hijo['estado']) {
      case 'matriculado':
        return [
          Row(
            children: [
              const Icon(Icons.verified, color: Marca.exito),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Matriculado en ${hijo['aula']} ($anio)',
                  style: const TextStyle(color: Marca.exito, fontWeight: FontWeight.w700),
                ),
              ),
            ],
          ),
        ];

      case 'pendiente_pago':
        final monto = (hijo['monto'] as num?)?.toDouble() ?? 0;
        return [
          Text('Solicitud registrada para ${hijo['grado_solicitado']}', style: const TextStyle(fontWeight: FontWeight.w700)),
          Text('Falta el pago de S/ ${monto.toStringAsFixed(2)} para confirmar la matrícula.'),
          const SizedBox(height: 12),
          if (enLinea)
            FilledButton.icon(
              onPressed: _enviando ? null : () => _pagar(hijo['solicitud_id'] as int),
              icon: const Icon(Icons.credit_card),
              label: const Text('Pagar en línea ahora'),
            ),
          const SizedBox(height: 8),
          Text(
            enLinea
                ? 'También puedes pagar en secretaría: al registrarse el pago, la matrícula se confirma sola.'
                : 'Paga en secretaría del colegio: al registrarse el pago, la matrícula se confirma sola y te llega un mensaje.',
            style: const TextStyle(color: Marca.textoSuave, fontSize: 13),
          ),
        ];

      default:
        if (!abierta) {
          return [Text('Todavía sin matrícula para $anio.', style: const TextStyle(color: Marca.textoSuave))];
        }
        final sugerido = hijo['grado_sugerido_id'] as int?;
        final elegido = _gradoElegido[id] ?? sugerido;
        return [
          DropdownButtonFormField<int>(
            initialValue: elegido,
            isExpanded: true,
            decoration: InputDecoration(labelText: 'Grado para $anio', border: const OutlineInputBorder()),
            items: [for (final grado in grados) DropdownMenuItem(value: grado['id'] as int, child: Text('${grado['nombre']}'))],
            onChanged: (valor) => setState(() {
              if (valor != null) _gradoElegido[id] = valor;
            }),
          ),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: _enviando || elegido == null ? null : () => _solicitar(id, elegido),
            child: const Text('Solicitar matrícula'),
          ),
        ];
    }
  }
}
