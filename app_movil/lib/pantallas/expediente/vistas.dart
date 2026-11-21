import 'package:material_ui/material_ui.dart';

import '../../componentes/carga.dart';
import '../../componentes/tarjetas.dart';
import '../../nucleo/api.dart';
import '../../nucleo/fechas.dart';
import '../../nucleo/tema.dart';

/// Las vistas del expediente de un alumno. Sirven igual para el apoderado
/// (prefijo "hijos/7") y para el propio alumno (prefijo "mi").

/// Asistencia del mes, con flechas para cambiar de mes.
class VistaAsistencia extends StatefulWidget {
  const VistaAsistencia({super.key, required this.api, required this.prefijo});

  final Api api;
  final String prefijo;

  @override
  State<VistaAsistencia> createState() => _VistaAsistenciaState();
}

class _VistaAsistenciaState extends State<VistaAsistencia> {
  DateTime _mes = DateTime(DateTime.now().year, DateTime.now().month);

  void _mover(int meses) => setState(() => _mes = DateTime(_mes.year, _mes.month + meses));

  @override
  Widget build(BuildContext context) {
    final esMesActual = _mes.year == DateTime.now().year && _mes.month == DateTime.now().month;

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              IconButton(tooltip: 'Mes anterior', icon: const Icon(Icons.chevron_left), onPressed: () => _mover(-1)),
              Text(Fechas.nombreDeMes(_mes), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
              IconButton(tooltip: 'Mes siguiente', icon: const Icon(Icons.chevron_right), onPressed: esMesActual ? null : () => _mover(1)),
            ],
          ),
        ),
        Expanded(
          child: Carga<Map<String, dynamic>>(
            // La clave hace que al cambiar de mes se vuelva a pedir.
            key: ValueKey(Fechas.claveDeMes(_mes)),
            pedir: () async =>
                await widget.api.get('${widget.prefijo}/asistencia', consulta: {'mes': Fechas.claveDeMes(_mes)}) as Map<String, dynamic>,
            construir: (context, datos, _) {
              final dias = (datos['dias'] as List).cast<Map<String, dynamic>>();
              final permisos = ((datos['permisos'] as List?) ?? const []).cast<Map<String, dynamic>>();

              if (dias.isEmpty && permisos.isEmpty) {
                return const Aviso(icono: Icons.event_busy, texto: 'Sin registros este mes.');
              }
              return ListView(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                children: [
                  for (final p in permisos)
                    Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: ListTile(
                        leading: const Icon(Icons.assignment_turned_in, color: Marca.info),
                        title: Text(p['tipo_etiqueta'] as String, style: const TextStyle(fontWeight: FontWeight.w700)),
                        subtitle: Text(
                          '${Fechas.corta(p['fecha'] as String)}${p['hora'] != null ? ' · ${p['hora']}' : ''}\n${p['motivo']}',
                        ),
                        isThreeLine: true,
                      ),
                    ),
                  for (final d in dias)
                    Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: ListTile(
                        title: Text(Fechas.corta(d['fecha'] as String), style: const TextStyle(fontWeight: FontWeight.w700)),
                        subtitle: Text('Entrada ${d['entrada'] ?? '—'} · Salida ${d['salida'] ?? '—'}'),
                        trailing: Etiqueta(textoDeEstado(d['estado'] as String?), color: colorDeEstado(d['estado'] as String?)),
                      ),
                    ),
                ],
              );
            },
          ),
        ),
      ],
    );
  }
}

/// Notas por materia, con una columna por periodo.
class VistaNotas extends StatelessWidget {
  const VistaNotas({super.key, required this.api, required this.prefijo});

  final Api api;
  final String prefijo;

  @override
  Widget build(BuildContext context) {
    return Carga<Map<String, dynamic>>(
      pedir: () async => await api.get('$prefijo/notas') as Map<String, dynamic>,
      construir: (context, datos, _) {
        final materias = (datos['materias'] as List).cast<Map<String, dynamic>>();
        final periodos = (datos['periodos'] as Map<String, dynamic>);
        final letras = datos['letras'] as Map<String, dynamic>?;

        if (materias.isEmpty) {
          return const Aviso(icono: Icons.menu_book, texto: 'Todavía no hay notas registradas este año.');
        }
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            for (final m in materias)
              Card(
                margin: const EdgeInsets.only(bottom: 10),
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(m['materia'] as String, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
                          ),
                          if (m['promedio'] != null) Etiqueta('Promedio ${m['promedio']}', color: Marca.info),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          for (final p in periodos.entries)
                            Expanded(
                              child: Column(
                                children: [
                                  Text(
                                    '${(m['periodos'] as Map<String, dynamic>)[p.key] ?? '—'}',
                                    style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
                                  ),
                                  Text(p.value as String, style: const TextStyle(fontSize: 11, color: Marca.textoSuave)),
                                ],
                              ),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            if (letras != null)
              Padding(
                padding: const EdgeInsets.all(8),
                child: Text(
                  letras.entries.map((e) => '${e.key}: ${e.value}').join(' · '),
                  style: const TextStyle(color: Marca.textoSuave, fontSize: 12),
                ),
              ),
          ],
        );
      },
    );
  }
}

/// Tareas, exámenes y actividades del aula.
class VistaTareas extends StatelessWidget {
  const VistaTareas({super.key, required this.api, required this.prefijo});

  final Api api;
  final String prefijo;

  @override
  Widget build(BuildContext context) {
    return Carga<Map<String, dynamic>>(
      pedir: () async => await api.get('$prefijo/tareas') as Map<String, dynamic>,
      construir: (context, datos, _) {
        final tareas = (datos['tareas'] as List).cast<Map<String, dynamic>>();
        if (tareas.isEmpty) {
          return const Aviso(icono: Icons.task_alt, texto: 'No hay tareas ni exámenes próximos.');
        }
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            for (final t in tareas)
              Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: ListTile(
                  leading: Icon(switch (t['tipo']) {
                    'examen' => Icons.quiz,
                    'actividad' => Icons.celebration,
                    _ => Icons.edit_note,
                  }, color: t['vencida'] == true ? Marca.textoSuave : Marca.primario),
                  title: Text(t['titulo'] as String, style: const TextStyle(fontWeight: FontWeight.w700)),
                  subtitle: Text(
                    [
                      if (t['materia'] != null) t['materia'] as String,
                      if ((t['descripcion'] as String?)?.isNotEmpty ?? false) t['descripcion'] as String,
                    ].join('\n'),
                  ),
                  trailing: Text(Fechas.corta(t['fecha_entrega'] as String), style: const TextStyle(fontWeight: FontWeight.w700)),
                ),
              ),
          ],
        );
      },
    );
  }
}

/// Horario semanal.
class VistaHorario extends StatelessWidget {
  const VistaHorario({super.key, required this.api, required this.ruta});

  final Api api;

  /// "hijos/7/horario", "mi/horario" o "docente/horario".
  final String ruta;

  @override
  Widget build(BuildContext context) {
    return Carga<Map<String, dynamic>>(
      pedir: () async => await api.get(ruta) as Map<String, dynamic>,
      construir: (context, datos, _) {
        final dias = (datos['dias'] as List).cast<Map<String, dynamic>>();
        if (dias.isEmpty) {
          return const Aviso(icono: Icons.calendar_month, texto: 'El horario todavía no está cargado.');
        }
        return ListView(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          children: [
            for (final dia in dias) ...[
              TituloDeBloque(dia['nombre'] as String),
              for (final b in (dia['bloques'] as List).cast<Map<String, dynamic>>())
                Card(
                  margin: const EdgeInsets.only(bottom: 6),
                  child: ListTile(
                    dense: true,
                    leading: Text('${b['inicio']}\n${b['fin']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12)),
                    title: Text(b['nombre'] as String, style: const TextStyle(fontWeight: FontWeight.w700)),
                    subtitle: (b['docente'] ?? b['aula']) == null ? null : Text((b['docente'] ?? b['aula']) as String),
                  ),
                ),
            ],
            const SizedBox(height: 16),
          ],
        );
      },
    );
  }
}

/// Méritos, deméritos, observaciones y citaciones.
class VistaConducta extends StatelessWidget {
  const VistaConducta({super.key, required this.api, required this.prefijo});

  final Api api;
  final String prefijo;

  @override
  Widget build(BuildContext context) {
    return Carga<Map<String, dynamic>>(
      pedir: () async => await api.get('$prefijo/conducta') as Map<String, dynamic>,
      construir: (context, datos, _) {
        final registros = (datos['registros'] as List).cast<Map<String, dynamic>>();
        if (registros.isEmpty) {
          return const Aviso(icono: Icons.emoji_events_outlined, texto: 'Sin registros de conducta este año.');
        }
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            for (final r in registros)
              Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: ListTile(
                  title: Text(r['descripcion'] as String),
                  subtitle: Text(Fechas.corta(r['fecha'] as String)),
                  trailing: Etiqueta(
                    r['tipo_etiqueta'] as String,
                    color: switch (r['tipo']) {
                      'merito' => Marca.exito,
                      'demerito' => Marca.primario,
                      _ => Marca.textoSuave,
                    },
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}

/// Comunicados del colegio.
class VistaComunicados extends StatelessWidget {
  const VistaComunicados({super.key, required this.api, required this.prefijo});

  final Api api;
  final String prefijo;

  @override
  Widget build(BuildContext context) {
    return Carga<Map<String, dynamic>>(
      pedir: () async => await api.get('$prefijo/comunicados') as Map<String, dynamic>,
      construir: (context, datos, _) {
        final comunicados = (datos['comunicados'] as List).cast<Map<String, dynamic>>();
        if (comunicados.isEmpty) {
          return const Aviso(icono: Icons.campaign_outlined, texto: 'No hay comunicados.');
        }
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            for (final c in comunicados)
              Card(
                margin: const EdgeInsets.only(bottom: 10),
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(c['titulo'] as String, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
                      Text(Fechas.relativa(c['fecha'] as String), style: const TextStyle(color: Marca.textoSuave, fontSize: 12)),
                      const SizedBox(height: 8),
                      Text(c['cuerpo'] as String),
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

/// Estado de cuenta: pensiones del año y pagos. Solo para el apoderado.
class VistaPagos extends StatelessWidget {
  const VistaPagos({super.key, required this.api, required this.prefijo});

  final Api api;
  final String prefijo;

  @override
  Widget build(BuildContext context) {
    return Carga<Map<String, dynamic>>(
      pedir: () async => await api.get('$prefijo/pagos') as Map<String, dynamic>,
      construir: (context, datos, _) {
        final pensiones = (datos['pensiones'] as List).cast<Map<String, dynamic>>();
        final pagos = (datos['pagos'] as List).cast<Map<String, dynamic>>();
        final vencidas = datos['pensiones_vencidas'] as int;

        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    Icon(vencidas > 0 ? Icons.warning_amber : Icons.verified, color: vencidas > 0 ? Marca.primario : Marca.exito, size: 32),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        vencidas > 0 ? '$vencidas pensión(es) vencida(s)' : 'Pensiones al día',
                        style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            TituloDeBloque('Pensiones ${datos['anio']}'),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final p in pensiones)
                      Etiqueta(
                        p['nombre'] as String,
                        color: switch (p['estado']) {
                          'pagada' => Marca.exito,
                          'vencida' => Marca.primario,
                          _ => Marca.textoSuave,
                        },
                      ),
                  ],
                ),
              ),
            ),
            const Padding(
              padding: EdgeInsets.fromLTRB(4, 8, 4, 0),
              child: Text('Verde: pagada · Rojo: vencida · Gris: por vencer', style: TextStyle(color: Marca.textoSuave, fontSize: 12)),
            ),
            const TituloDeBloque('Pagos del año'),
            if (pagos.isEmpty)
              const Padding(
                padding: EdgeInsets.all(16),
                child: Text('Sin pagos registrados.', style: TextStyle(color: Marca.textoSuave)),
              ),
            for (final p in pagos)
              Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: ListTile(
                  title: Text(p['detalle'] as String, style: const TextStyle(fontWeight: FontWeight.w700)),
                  subtitle: Text('Recibo N.° ${p['recibo']} · ${Fechas.corta(p['fecha'] as String)}'),
                  trailing: Text('S/ ${(p['monto'] as num).toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w800)),
                ),
              ),
          ],
        );
      },
    );
  }
}
