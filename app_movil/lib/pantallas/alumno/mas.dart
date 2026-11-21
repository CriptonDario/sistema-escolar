import 'package:material_ui/material_ui.dart';

import '../../nucleo/api.dart';
import '../../nucleo/tema.dart';
import '../expediente/expediente.dart';
import '../expediente/vistas.dart';

/// El resto del expediente del alumno, como un menú.
class PantallaMasDelAlumno extends StatelessWidget {
  const PantallaMasDelAlumno({super.key, required this.api});

  final Api api;

  @override
  Widget build(BuildContext context) {
    final opciones = <(String, IconData, Widget)>[
      ('Mi asistencia', Icons.event_available, VistaAsistencia(api: api, prefijo: 'mi')),
      ('Mi horario', Icons.calendar_month, VistaHorario(api: api, ruta: 'mi/horario')),
      ('Conducta', Icons.emoji_events_outlined, VistaConducta(api: api, prefijo: 'mi')),
      ('Comunicados', Icons.campaign_outlined, VistaComunicados(api: api, prefijo: 'mi')),
    ];

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        for (final (titulo, icono, vista) in opciones)
          Card(
            margin: const EdgeInsets.only(bottom: 8),
            child: ListTile(
              leading: Icon(icono, color: Marca.primario),
              title: Text(titulo, style: const TextStyle(fontWeight: FontWeight.w700)),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => PantallaSimple(titulo: titulo, contenido: vista),
                ),
              ),
            ),
          ),
      ],
    );
  }
}
