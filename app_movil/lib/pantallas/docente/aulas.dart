import 'package:material_ui/material_ui.dart';

import '../../componentes/carga.dart';
import '../../nucleo/api.dart';
import '../../nucleo/tema.dart';
import 'notas.dart';
import 'publicar.dart';

/// Las aulas y materias que el colegio le asignó al docente este año.
class PantallaAulas extends StatelessWidget {
  const PantallaAulas({super.key, required this.api});

  final Api api;

  @override
  Widget build(BuildContext context) {
    return Carga<Map<String, dynamic>>(
      pedir: () async => await api.get('docente/aulas') as Map<String, dynamic>,
      construir: (context, datos, _) {
        final aulas = (datos['aulas'] as List).cast<Map<String, dynamic>>();
        if (aulas.isEmpty) {
          return const Aviso(icono: Icons.class_outlined, texto: 'Todavía no tienes aulas asignadas este año. Consulta con la dirección.');
        }
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            for (final aula in aulas)
              Card(
                margin: const EdgeInsets.only(bottom: 12),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(aula['materia'] as String, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
                      Text(aula['aula'] as String, style: const TextStyle(color: Marca.textoSuave)),
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          FilledButton.tonalIcon(
                            onPressed: () => Navigator.of(context).push(
                              MaterialPageRoute<void>(
                                builder: (_) => PantallaNotasDelAula(api: api, aula: aula),
                              ),
                            ),
                            icon: const Icon(Icons.grading),
                            label: const Text('Notas'),
                          ),
                          FilledButton.tonalIcon(
                            onPressed: () => Navigator.of(context).push(
                              MaterialPageRoute<void>(
                                builder: (_) => PantallaPublicarTarea(api: api, aula: aula),
                              ),
                            ),
                            icon: const Icon(Icons.edit_note),
                            label: const Text('Tarea'),
                          ),
                          FilledButton.tonalIcon(
                            onPressed: () => Navigator.of(context).push(
                              MaterialPageRoute<void>(
                                builder: (_) => PantallaRegistrarConducta(api: api, aula: aula),
                              ),
                            ),
                            icon: const Icon(Icons.emoji_events_outlined),
                            label: const Text('Conducta'),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}
