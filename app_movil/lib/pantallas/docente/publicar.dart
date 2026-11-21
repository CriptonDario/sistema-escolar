import 'package:material_ui/material_ui.dart';

import '../../componentes/carga.dart';
import '../../nucleo/api.dart';
import '../../nucleo/fechas.dart';
import '../../nucleo/tema.dart';

/// Publicar una tarea, un examen o una actividad para un aula.
class PantallaPublicarTarea extends StatefulWidget {
  const PantallaPublicarTarea({super.key, required this.api, required this.aula});

  final Api api;
  final Map<String, dynamic> aula;

  @override
  State<PantallaPublicarTarea> createState() => _PantallaPublicarTareaState();
}

class _PantallaPublicarTareaState extends State<PantallaPublicarTarea> {
  final _formulario = GlobalKey<FormState>();
  final _titulo = TextEditingController();
  final _descripcion = TextEditingController();
  String _tipo = 'tarea';
  DateTime _fecha = DateTime.now().add(const Duration(days: 1));
  bool _enviando = false;

  @override
  void dispose() {
    _titulo.dispose();
    _descripcion.dispose();
    super.dispose();
  }

  Future<void> _elegirFecha() async {
    final hoy = DateTime.now();
    final elegida = await showDatePicker(
      context: context,
      initialDate: _fecha,
      firstDate: DateTime(hoy.year, hoy.month, hoy.day),
      lastDate: hoy.add(const Duration(days: 365)),
    );
    if (elegida != null) setState(() => _fecha = elegida);
  }

  Future<void> _publicar() async {
    if (_enviando || !_formulario.currentState!.validate()) return;
    setState(() => _enviando = true);
    try {
      await widget.api.post('docente/aulas/${widget.aula['seccion_id']}/tareas', {
        'materia_id': widget.aula['materia_id'],
        'tipo': _tipo,
        'titulo': _titulo.text.trim(),
        'descripcion': _descripcion.text.trim().isEmpty ? null : _descripcion.text.trim(),
        'fecha_entrega': _fecha.toIso8601String().substring(0, 10),
      });
      if (!mounted) return;
      mostrarExito(context, 'Publicado. Se avisó a los apoderados.');
      Navigator.of(context).pop();
    } on Object catch (error) {
      if (mounted) mostrarError(context, error);
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Publicar')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formulario,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('${widget.aula['materia']} · ${widget.aula['aula']}', style: const TextStyle(color: Marca.textoSuave)),
              const SizedBox(height: 14),
              SegmentedButton<String>(
                segments: const [
                  ButtonSegment(value: 'tarea', label: Text('Tarea')),
                  ButtonSegment(value: 'examen', label: Text('Examen')),
                  ButtonSegment(value: 'actividad', label: Text('Actividad')),
                ],
                selected: {_tipo},
                onSelectionChanged: (s) => setState(() => _tipo = s.first),
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _titulo,
                decoration: const InputDecoration(labelText: 'Título'),
                maxLength: 120,
                validator: (v) => (v == null || v.trim().isEmpty) ? 'Escribe el título' : null,
              ),
              TextFormField(
                controller: _descripcion,
                decoration: const InputDecoration(labelText: 'Detalle (opcional)'),
                maxLines: 4,
                maxLength: 2000,
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: _elegirFecha,
                icon: const Icon(Icons.event),
                label: Text('Fecha: ${Fechas.corta(_fecha.toIso8601String())}'),
              ),
              const SizedBox(height: 20),
              FilledButton(onPressed: _enviando ? null : _publicar, child: Text(_enviando ? 'Publicando…' : 'Publicar y avisar')),
            ],
          ),
        ),
      ),
    );
  }
}

/// Registrar un mérito, demérito, observación o citación de un alumno del aula.
class PantallaRegistrarConducta extends StatefulWidget {
  const PantallaRegistrarConducta({super.key, required this.api, required this.aula});

  final Api api;
  final Map<String, dynamic> aula;

  @override
  State<PantallaRegistrarConducta> createState() => _PantallaRegistrarConductaState();
}

class _PantallaRegistrarConductaState extends State<PantallaRegistrarConducta> {
  final _formulario = GlobalKey<FormState>();
  final _descripcion = TextEditingController();
  int? _alumnoId;
  String _tipo = 'merito';
  bool _enviando = false;

  @override
  void dispose() {
    _descripcion.dispose();
    super.dispose();
  }

  /// La lista del aula sale de la misma ruta de notas.
  Future<List<Map<String, dynamic>>> _alumnos() async {
    final datos = await widget.api.get(
      'docente/aulas/${widget.aula['seccion_id']}/notas',
      consulta: {'materia_id': '${widget.aula['materia_id']}'},
    ) as Map<String, dynamic>;
    return (datos['alumnos'] as List).cast<Map<String, dynamic>>();
  }

  Future<void> _registrar() async {
    if (_enviando || !_formulario.currentState!.validate()) return;
    setState(() => _enviando = true);
    try {
      await widget.api.post('docente/conducta', {'alumno_id': _alumnoId, 'tipo': _tipo, 'descripcion': _descripcion.text.trim()});
      if (!mounted) return;
      mostrarExito(context, 'Registrado. Se avisó al apoderado.');
      Navigator.of(context).pop();
    } on Object catch (error) {
      if (mounted) mostrarError(context, error);
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Conducta')),
      body: Carga<List<Map<String, dynamic>>>(
        pedir: _alumnos,
        construir: (context, alumnos, _) => SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(20),
          child: Form(
            key: _formulario,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(widget.aula['aula'] as String, style: const TextStyle(color: Marca.textoSuave)),
                const SizedBox(height: 14),
                DropdownButtonFormField<int>(
                  initialValue: _alumnoId,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Alumno'),
                  items: [for (final a in alumnos) DropdownMenuItem(value: a['id'] as int, child: Text(a['nombre'] as String))],
                  onChanged: (v) => setState(() => _alumnoId = v),
                  validator: (v) => v == null ? 'Elige al alumno' : null,
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue: _tipo,
                  decoration: const InputDecoration(labelText: 'Tipo'),
                  items: const [
                    DropdownMenuItem(value: 'merito', child: Text('Mérito')),
                    DropdownMenuItem(value: 'demerito', child: Text('Demérito')),
                    DropdownMenuItem(value: 'observacion', child: Text('Observación')),
                    DropdownMenuItem(value: 'citacion', child: Text('Citación')),
                  ],
                  onChanged: (v) => setState(() => _tipo = v ?? 'merito'),
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _descripcion,
                  decoration: const InputDecoration(labelText: 'Descripción'),
                  maxLines: 4,
                  maxLength: 500,
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Describe lo ocurrido' : null,
                ),
                const SizedBox(height: 12),
                FilledButton(onPressed: _enviando ? null : _registrar, child: Text(_enviando ? 'Registrando…' : 'Registrar y avisar')),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
