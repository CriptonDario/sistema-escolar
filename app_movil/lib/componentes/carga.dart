import 'package:material_ui/material_ui.dart';

import '../nucleo/api.dart';
import '../nucleo/tema.dart';

/// Pide datos al servidor y muestra lo que corresponda: el indicador de
/// carga, el error con "Reintentar", o el contenido. Deslizar hacia abajo
/// vuelve a pedirlos.
///
/// Al recargar conserva en pantalla lo que ya había: la lista no parpadea ni
/// se vacía mientras llega la respuesta nueva, y si esa recarga falla (por
/// ejemplo, sin señal un momento) se sigue viendo lo último que se cargó.
class Carga<T> extends StatefulWidget {
  const Carga({super.key, required this.pedir, required this.construir});

  final Future<T> Function() pedir;
  final Widget Function(BuildContext context, T datos, Future<void> Function() recargar) construir;

  @override
  State<Carga<T>> createState() => CargaState<T>();
}

class CargaState<T> extends State<Carga<T>> {
  T? _datos;
  bool _hayDatos = false;
  Object? _error;

  @override
  void initState() {
    super.initState();
    recargar();
  }

  /// Vuelve a pedir los datos. Se puede llamar desde fuera con una GlobalKey.
  Future<void> recargar() async {
    try {
      final datos = await widget.pedir();
      if (!mounted) return;
      setState(() {
        _datos = datos;
        _hayDatos = true;
        _error = null;
      });
    } on Object catch (error) {
      if (!mounted) return;
      setState(() => _error = error);
    }
  }

  Future<void> _reintentar() async {
    setState(() => _error = null);
    await recargar();
  }

  @override
  Widget build(BuildContext context) {
    if (_hayDatos) {
      return RefreshIndicator(onRefresh: recargar, child: widget.construir(context, _datos as T, recargar));
    }
    if (_error != null) {
      final error = _error;
      return Aviso(
        icono: Icons.cloud_off,
        texto: error is ErrorApi ? error.mensaje : 'No se pudo cargar.',
        accion: FilledButton.tonal(onPressed: _reintentar, child: const Text('Reintentar')),
      );
    }
    return const Center(child: CircularProgressIndicator());
  }
}

/// Mensaje centrado para listas vacías y errores.
class Aviso extends StatelessWidget {
  const Aviso({super.key, required this.icono, required this.texto, this.accion});

  final IconData icono;
  final String texto;
  final Widget? accion;

  @override
  Widget build(BuildContext context) {
    // Dentro de una lista para que "deslizar para actualizar" funcione
    // también cuando no hay nada que mostrar.
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(32),
      children: [
        const SizedBox(height: 60),
        Icon(icono, size: 56, color: Marca.textoSuave),
        const SizedBox(height: 16),
        Text(
          texto,
          textAlign: TextAlign.center,
          style: const TextStyle(color: Marca.textoSuave, fontSize: 15),
        ),
        if (accion != null) ...[const SizedBox(height: 16), Center(child: accion)],
      ],
    );
  }
}

/// Muestra un error del servidor en la barra inferior.
void mostrarError(BuildContext context, Object error) {
  final texto = error is ErrorApi ? error.mensaje : 'Ocurrió un error inesperado.';
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(texto), backgroundColor: Marca.texto));
}

void mostrarExito(BuildContext context, String texto) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(texto), backgroundColor: Marca.exito));
}
