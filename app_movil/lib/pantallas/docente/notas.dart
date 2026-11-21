import 'package:flutter/services.dart';
import 'package:material_ui/material_ui.dart';

import '../../componentes/carga.dart';
import '../../nucleo/api.dart';
import '../../nucleo/tema.dart';

/// Registro de notas de un aula en una materia. La escala (números o letras)
/// la decide el colegio según el nivel.
class PantallaNotasDelAula extends StatefulWidget {
  const PantallaNotasDelAula({super.key, required this.api, required this.aula});

  final Api api;

  /// seccion_id, aula, materia_id, materia.
  final Map<String, dynamic> aula;

  @override
  State<PantallaNotasDelAula> createState() => _PantallaNotasDelAulaState();
}

class _PantallaNotasDelAulaState extends State<PantallaNotasDelAula> {
  int _periodo = 1;
  bool _guardando = false;

  /// alumno_id → lo que el docente escribió o eligió.
  final Map<int, String> _valores = {};

  String get _ruta => 'docente/aulas/${widget.aula['seccion_id']}/notas';

  Future<Map<String, dynamic>> _pedir() async {
    final datos = await widget.api.get(
      _ruta,
      consulta: {'materia_id': '${widget.aula['materia_id']}', 'periodo': '$_periodo'},
    ) as Map<String, dynamic>;

    _valores
      ..clear()
      ..addEntries([
        for (final a in (datos['alumnos'] as List).cast<Map<String, dynamic>>())
          MapEntry(a['id'] as int, a['nota'] == null ? '' : '${a['nota']}'),
      ]);
    return datos;
  }

  Future<void> _guardar() async {
    setState(() => _guardando = true);
    try {
      await widget.api.post(_ruta, {
        'materia_id': widget.aula['materia_id'],
        'periodo': _periodo,
        // Vacío borra la nota; el servidor valida la escala.
        'notas': {for (final e in _valores.entries) '${e.key}': e.value.isEmpty ? null : e.value},
      });
      if (mounted) mostrarExito(context, 'Notas guardadas.');
    } on Object catch (error) {
      if (mounted) mostrarError(context, error);
    } finally {
      if (mounted) setState(() => _guardando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(widget.aula['materia'] as String, style: const TextStyle(fontWeight: FontWeight.w800)),
            Text(widget.aula['aula'] as String, style: const TextStyle(fontSize: 12)),
          ],
        ),
      ),
      body: Carga<Map<String, dynamic>>(
        key: ValueKey(_periodo),
        pedir: _pedir,
        construir: (context, datos, _) {
          final alumnos = (datos['alumnos'] as List).cast<Map<String, dynamic>>();
          final periodos = datos['periodos'] as Map<String, dynamic>;
          final letras = datos['letras'] as Map<String, dynamic>?;

          return Column(
            children: [
              Padding(
                padding: const EdgeInsets.all(12),
                child: SegmentedButton<int>(
                  segments: [for (final p in periodos.entries) ButtonSegment(value: int.parse(p.key), label: Text(p.value as String))],
                  selected: {_periodo},
                  onSelectionChanged: (s) => setState(() => _periodo = s.first),
                ),
              ),
              Expanded(
                child: alumnos.isEmpty
                    ? const Center(
                        child: Text('Esta aula no tiene alumnos activos.', style: TextStyle(color: Marca.textoSuave)),
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        itemCount: alumnos.length,
                        separatorBuilder: (_, _) => const Divider(height: 1),
                        itemBuilder: (context, i) {
                          final alumno = alumnos[i];
                          final id = alumno['id'] as int;

                          return Padding(
                            padding: const EdgeInsets.symmetric(vertical: 6),
                            child: Row(
                              children: [
                                Expanded(child: Text('${i + 1}. ${alumno['nombre']}')),
                                SizedBox(
                                  width: 110,
                                  child: letras == null
                                      ? TextFormField(
                                          key: ValueKey('nota-$id-$_periodo'),
                                          initialValue: _valores[id],
                                          decoration: InputDecoration(hintText: '0–${datos['nota_maxima']}', isDense: true),
                                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                          inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.]'))],
                                          textAlign: TextAlign.center,
                                          onChanged: (v) => _valores[id] = v,
                                        )
                                      : DropdownButtonFormField<String>(
                                          key: ValueKey('letra-$id-$_periodo'),
                                          initialValue: _valores[id],
                                          isDense: true,
                                          decoration: const InputDecoration(isDense: true),
                                          items: [
                                            const DropdownMenuItem(value: '', child: Text('—')),
                                            for (final letra in letras.keys) DropdownMenuItem(value: letra, child: Text(letra)),
                                          ],
                                          onChanged: (v) => _valores[id] = v ?? '',
                                        ),
                                ),
                              ],
                            ),
                          );
                        },
                      ),
              ),
              SafeArea(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: FilledButton.icon(
                    onPressed: _guardando || alumnos.isEmpty ? null : _guardar,
                    icon: const Icon(Icons.save),
                    label: Text(_guardando ? 'Guardando…' : 'Guardar notas'),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
