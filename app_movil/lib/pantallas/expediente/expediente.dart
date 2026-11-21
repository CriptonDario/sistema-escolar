import 'package:material_ui/material_ui.dart';

import '../../nucleo/api.dart';
import 'vistas.dart';

/// El expediente de un hijo, en pestañas. Lo abre el apoderado al tocar a su hijo.
class PantallaExpediente extends StatelessWidget {
  const PantallaExpediente({super.key, required this.api, required this.alumnoId, required this.nombre, required this.carne});

  final Api api;
  final int alumnoId;
  final String nombre;

  /// El carné del hijo, ya armado con sus datos.
  final Widget carne;

  @override
  Widget build(BuildContext context) {
    final prefijo = 'hijos/$alumnoId';

    return DefaultTabController(
      length: 8,
      child: Scaffold(
        appBar: AppBar(
          title: Text(nombre),
          bottom: const TabBar(
            isScrollable: true,
            labelColor: Colors.white,
            unselectedLabelColor: Color(0xB3FFFFFF),
            indicatorColor: Colors.white,
            tabs: [
              Tab(text: 'Asistencia'),
              Tab(text: 'Carné'),
              Tab(text: 'Notas'),
              Tab(text: 'Tareas'),
              Tab(text: 'Horario'),
              Tab(text: 'Conducta'),
              Tab(text: 'Pagos'),
              Tab(text: 'Comunicados'),
            ],
          ),
        ),
        body: TabBarView(
          children: [
            VistaAsistencia(api: api, prefijo: prefijo),
            carne,
            VistaNotas(api: api, prefijo: prefijo),
            VistaTareas(api: api, prefijo: prefijo),
            VistaHorario(api: api, ruta: '$prefijo/horario'),
            VistaConducta(api: api, prefijo: prefijo),
            VistaPagos(api: api, prefijo: prefijo),
            VistaComunicados(api: api, prefijo: prefijo),
          ],
        ),
      ),
    );
  }
}

/// Pantalla con una sola vista y su título: para abrir desde un menú.
class PantallaSimple extends StatelessWidget {
  const PantallaSimple({super.key, required this.titulo, required this.contenido});

  final String titulo;
  final Widget contenido;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(titulo)),
      body: contenido,
    );
  }
}
