import 'package:material_ui/material_ui.dart';

import '../nucleo/perfil.dart';
import '../nucleo/sesion.dart';
import '../nucleo/tema.dart';
import 'cambiar_clave.dart';

/// Una sección de la barra inferior.
class Seccion {
  const Seccion({required this.titulo, required this.icono, required this.pantalla, this.soloAlVerla = false});

  final String titulo;
  final IconData icono;
  final Widget pantalla;

  /// Si es verdadero, la pantalla solo existe mientras su pestaña está a la
  /// vista. Es para el escáner: fuera de su pestaña la cámara debe apagarse,
  /// por batería y porque nadie espera que siga grabando.
  final bool soloAlVerla;
}

/// El armazón común a todos los perfiles: barra superior con el nombre del
/// colegio y el menú de la cuenta, y barra inferior con las secciones del rol.
class PantallaInicio extends StatefulWidget {
  const PantallaInicio({super.key, required this.sesion, required this.secciones});

  final Sesion sesion;
  final List<Seccion> secciones;

  @override
  State<PantallaInicio> createState() => _PantallaInicioState();
}

class _PantallaInicioState extends State<PantallaInicio> {
  int _actual = 0;

  @override
  Widget build(BuildContext context) {
    final secciones = widget.secciones;
    final perfil = widget.sesion.perfil;

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(secciones[_actual].titulo, style: const TextStyle(fontWeight: FontWeight.w800)),
            Text(widget.sesion.colegio, style: const TextStyle(fontSize: 12, color: Color(0xD9FFFFFF))),
          ],
        ),
        actions: [
          PopupMenuButton<String>(
            tooltip: 'Mi cuenta',
            icon: const Icon(Icons.account_circle),
            onSelected: (opcion) {
              if (opcion == 'clave') {
                Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => PantallaCambiarClave(sesion: widget.sesion)));
              } else {
                widget.sesion.cerrar();
              }
            },
            itemBuilder: (_) => [
              PopupMenuItem<String>(
                enabled: false,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      perfil.nombre,
                      style: const TextStyle(color: Marca.texto, fontWeight: FontWeight.w700),
                    ),
                    Text(perfil.rol.etiqueta, style: const TextStyle(color: Marca.textoSuave, fontSize: 12)),
                  ],
                ),
              ),
              const PopupMenuDivider(),
              const PopupMenuItem<String>(value: 'clave', child: Text('Cambiar contraseña')),
              const PopupMenuItem<String>(value: 'salir', child: Text('Cerrar sesión')),
            ],
          ),
        ],
      ),
      // IndexedStack conserva cada sección como la dejaste al cambiar de pestaña.
      body: IndexedStack(
        index: _actual,
        children: [
          for (final (i, s) in secciones.indexed)
            if (s.soloAlVerla && i != _actual) const SizedBox.shrink() else s.pantalla,
        ],
      ),
      bottomNavigationBar: secciones.length < 2
          ? null
          : NavigationBar(
              selectedIndex: _actual,
              onDestinationSelected: (i) => setState(() => _actual = i),
              destinations: [for (final s in secciones) NavigationDestination(icon: Icon(s.icono), label: s.titulo)],
            ),
    );
  }
}

/// Pantalla para roles que aún no tienen funciones en la app.
class PantallaSinFunciones extends StatelessWidget {
  const PantallaSinFunciones({super.key, required this.rol});

  final Rol rol;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Text(
          'La cuenta de ${rol.etiqueta.toLowerCase()} se usa desde el panel web del colegio.',
          textAlign: TextAlign.center,
          style: const TextStyle(color: Marca.textoSuave, fontSize: 15),
        ),
      ),
    );
  }
}
