import 'package:material_ui/material_ui.dart';

import '../../componentes/carga.dart';
import '../../componentes/tarjetas.dart';
import '../../nucleo/api.dart';
import '../../nucleo/tema.dart';

/// Cómo va la asistencia del día: resumen de alumnos y personal, y las
/// últimas marcas.
class PantallaHoy extends StatelessWidget {
  const PantallaHoy({super.key, required this.api});

  final Api api;

  @override
  Widget build(BuildContext context) {
    return Carga<Map<String, dynamic>>(
      pedir: () async => await api.get('asistencia/hoy') as Map<String, dynamic>,
      construir: (context, datos, _) {
        final ultimas = (datos['ultimas'] as List).cast<Map<String, dynamic>>();

        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _Resumen(titulo: 'Alumnos', datos: datos['alumnos'] as Map<String, dynamic>),
            const SizedBox(height: 12),
            _Resumen(titulo: 'Personal', datos: datos['personal'] as Map<String, dynamic>),
            const TituloDeBloque('Últimas marcas'),
            if (ultimas.isEmpty)
              const Padding(
                padding: EdgeInsets.all(24),
                child: Text(
                  'Todavía no hay marcas hoy.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Marca.textoSuave),
                ),
              ),
            for (final marca in ultimas)
              Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: ListTile(
                  title: Text((marca['persona'] as Map)['nombre'] as String, style: const TextStyle(fontWeight: FontWeight.w700)),
                  subtitle: Text(
                    '${(marca['persona'] as Map)['detalle'] ?? ''}\nEntrada ${marca['entrada']}${marca['salida'] != null ? ' · Salida ${marca['salida']}' : ''}',
                  ),
                  isThreeLine: true,
                  trailing: Etiqueta(textoDeEstado(marca['estado'] as String?), color: colorDeEstado(marca['estado'] as String?)),
                ),
              ),
          ],
        );
      },
    );
  }
}

class _Resumen extends StatelessWidget {
  const _Resumen({required this.titulo, required this.datos});

  final String titulo;
  final Map<String, dynamic> datos;

  @override
  Widget build(BuildContext context) {
    final total = datos['total'] as int;
    final presentes = datos['presentes'] as int;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '$titulo · $total activos',
              style: const TextStyle(fontWeight: FontWeight.w700, color: Marca.textoSuave),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Indicador(valor: '$presentes', rotulo: 'Presentes'),
                Indicador(valor: '${datos['tardanzas']}', rotulo: 'Tardanzas', color: Marca.aviso),
                Indicador(valor: '${datos['salieron']}', rotulo: 'Salieron'),
                Indicador(valor: '${total - presentes < 0 ? 0 : total - presentes}', rotulo: 'Sin marcar', color: Marca.primario),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
