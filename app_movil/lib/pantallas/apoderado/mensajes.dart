import 'dart:async';

import 'package:material_ui/material_ui.dart';

import '../../componentes/carga.dart';
import '../../nucleo/api.dart';
import '../../nucleo/fechas.dart';
import '../../nucleo/tema.dart';

/// La bandeja del apoderado: cada ingreso, salida, permiso, tarea o
/// comunicado llega como un mensaje.
class PantallaMensajes extends StatefulWidget {
  const PantallaMensajes({super.key, required this.api});

  final Api api;

  @override
  State<PantallaMensajes> createState() => _PantallaMensajesState();
}

class _PantallaMensajesState extends State<PantallaMensajes> with WidgetsBindingObserver {
  /// Cada cuánto se vuelve a consultar la bandeja mientras la app está abierta.
  /// Hasta que el colegio configure las notificaciones push, así llegan los
  /// mensajes nuevos sin que el apoderado tenga que deslizar para actualizar.
  static const _intervalo = Duration(seconds: 60);

  final _bandeja = GlobalKey<CargaState<Map<String, dynamic>>>();
  Timer? _reloj;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _reloj = Timer.periodic(_intervalo, (_) => _recargar());
  }

  @override
  void dispose() {
    _reloj?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  /// Al volver a la app desde otra (o desde la pantalla apagada) se actualiza
  /// de inmediato, sin esperar al siguiente minuto.
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _recargar();
  }

  /// Pide la bandeja otra vez sin vaciar la pantalla mientras tanto.
  void _recargar() => _bandeja.currentState?.recargar();

  Future<void> _leer(Map<String, dynamic> mensaje) async {
    if (mensaje['leido'] == true) return;
    try {
      await widget.api.post('mensajes/${mensaje['id']}/leer');
      _recargar();
    } on Object catch (error) {
      if (mounted) mostrarError(context, error);
    }
  }

  Future<void> _leerTodos() async {
    try {
      await widget.api.post('mensajes/leer-todos');
      _recargar();
    } on Object catch (error) {
      if (mounted) mostrarError(context, error);
    }
  }

  static (IconData, Color) _aspecto(String tipo) => switch (tipo) {
    'entrada' => (Icons.login, Marca.exito),
    'tardanza' => (Icons.schedule, Marca.aviso),
    'salida' => (Icons.logout, Marca.info),
    'permiso' => (Icons.assignment_turned_in, Marca.info),
    'tarea' => (Icons.edit_note, Marca.primario),
    'conducta' => (Icons.emoji_events, Marca.aviso),
    'pago' => (Icons.payments, Marca.exito),
    _ => (Icons.campaign, Marca.primario),
  };

  @override
  Widget build(BuildContext context) {
    return Carga<Map<String, dynamic>>(
      key: _bandeja,
      pedir: () async => await widget.api.get('mensajes', consulta: {'limite': '50'}) as Map<String, dynamic>,
      construir: (context, datos, _) {
        final mensajes = (datos['mensajes'] as List).cast<Map<String, dynamic>>();
        final noLeidos = datos['no_leidos'] as int;

        if (mensajes.isEmpty) {
          return const Aviso(
            icono: Icons.mark_email_unread_outlined,
            texto: 'Aquí verás cuando tu hijo ingrese o salga del colegio, sus permisos, tareas y comunicados.',
          );
        }
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (noLeidos > 0)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                // Wrap y no Row: en un teléfono angosto el botón baja de línea
                // en vez de salirse de la pantalla.
                child: Wrap(
                  alignment: WrapAlignment.spaceBetween,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    Text('$noLeidos sin leer', style: const TextStyle(fontWeight: FontWeight.w700)),
                    TextButton(onPressed: _leerTodos, child: const Text('Marcar todos como leídos')),
                  ],
                ),
              ),
            for (final m in mensajes) _Mensaje(mensaje: m, aspecto: _aspecto(m['tipo'] as String), alTocar: () => _leer(m)),
          ],
        );
      },
    );
  }
}

class _Mensaje extends StatelessWidget {
  const _Mensaje({required this.mensaje, required this.aspecto, required this.alTocar});

  final Map<String, dynamic> mensaje;
  final (IconData, Color) aspecto;
  final VoidCallback alTocar;

  @override
  Widget build(BuildContext context) {
    final leido = mensaje['leido'] == true;
    final (icono, color) = aspecto;

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      color: leido ? Colors.white : const Color(0xFFFFF5F5),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: alTocar,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                backgroundColor: color.withValues(alpha: 0.12),
                child: Icon(icono, color: color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            mensaje['titulo'] as String,
                            style: TextStyle(fontWeight: leido ? FontWeight.w600 : FontWeight.w800, fontSize: 15),
                          ),
                        ),
                        if (!leido) const Icon(Icons.circle, size: 10, color: Marca.primario),
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text(mensaje['cuerpo'] as String, style: const TextStyle(color: Marca.texto)),
                    const SizedBox(height: 6),
                    Text(Fechas.relativa(mensaje['fecha'] as String), style: const TextStyle(color: Marca.textoSuave, fontSize: 12)),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
