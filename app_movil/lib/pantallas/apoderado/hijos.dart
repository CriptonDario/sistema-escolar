import 'package:material_ui/material_ui.dart';

import '../../componentes/carga.dart';
import '../../componentes/tarjetas.dart';
import '../../nucleo/api.dart';
import '../../nucleo/tema.dart';
import '../carne.dart';
import '../expediente/expediente.dart';

/// Los hijos del apoderado, con cómo va su día. Tocar a uno abre su expediente.
class PantallaHijos extends StatelessWidget {
  const PantallaHijos({super.key, required this.api, required this.colegio});

  final Api api;
  final String colegio;

  Widget _carneDe(Map<String, dynamic> hijo) {
    final apoderados = ((hijo['apoderados'] as List?) ?? const []).cast<Map<String, dynamic>>();
    final telefonos = apoderados.map((a) => a['telefono'] as String?).where((t) => t != null && t.isNotEmpty);
    final nombres = '${hijo['nombres']}';
    final apellidos = '${hijo['apellidos']}';

    return Carne(
      colegio: colegio,
      nombre: '$apellidos $nombres',
      iniciales: '${nombres.characters.firstOrNull ?? ''}${apellidos.characters.firstOrNull ?? ''}'.toUpperCase(),
      codigo: hijo['codigo'] as String?,
      grado: hijo['aula'] as String?,
      dni: hijo['dni'] as String?,
      apoderados: apoderados.map((a) => a['nombres']).join(' - '),
      telefono: telefonos.firstOrNull,
      foto: hijo['foto'] as String?,
      cabeceras: api.token == null ? null : {'Authorization': 'Bearer ${api.token}'},
      leyenda: 'Este es el carné de tu hijo. Con este código se registra su ingreso y su salida en la puerta.',
      sinCodigo: 'Este alumno todavía no tiene código de carné. Avisa en secretaría.',
    );
  }

  @override
  Widget build(BuildContext context) {
    return Carga<Map<String, dynamic>>(
      pedir: () async => await api.get('hijos') as Map<String, dynamic>,
      construir: (context, datos, _) {
        final hijos = (datos['data'] as List).cast<Map<String, dynamic>>();
        if (hijos.isEmpty) {
          return const Aviso(icono: Icons.family_restroom, texto: 'Tu cuenta todavía no tiene alumnos vinculados. Avisa en secretaría.');
        }
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            for (final hijo in hijos)
              Card(
                margin: const EdgeInsets.only(bottom: 12),
                child: InkWell(
                  borderRadius: BorderRadius.circular(16),
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => PantallaExpediente(
                        api: api,
                        alumnoId: hijo['id'] as int,
                        nombre: '${hijo['nombres']} ${hijo['apellidos']}',
                        carne: _carneDe(hijo),
                      ),
                    ),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const CircleAvatar(
                              radius: 24,
                              backgroundColor: Color(0x1FFD0D0D),
                              child: Icon(Icons.school, color: Marca.primario),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    '${hijo['nombres']} ${hijo['apellidos']}',
                                    style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17),
                                  ),
                                  Text((hijo['aula'] as String?) ?? 'Sin aula', style: const TextStyle(color: Marca.textoSuave)),
                                ],
                              ),
                            ),
                            const Icon(Icons.chevron_right, color: Marca.textoSuave),
                          ],
                        ),
                        const Divider(height: 24),
                        _Hoy(hoy: hijo['hoy'] as Map<String, dynamic>?),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}

class _Hoy extends StatelessWidget {
  const _Hoy({required this.hoy});

  final Map<String, dynamic>? hoy;

  @override
  Widget build(BuildContext context) {
    if (hoy == null) {
      return const Row(
        children: [
          Icon(Icons.hourglass_empty, size: 18, color: Marca.textoSuave),
          SizedBox(width: 8),
          Text('Hoy todavía no registra ingreso', style: TextStyle(color: Marca.textoSuave)),
        ],
      );
    }
    final salida = hoy!['salida'] as String?;
    return Row(
      children: [
        Expanded(
          child: Text(
            salida == null ? 'Ingresó a las ${hoy!['entrada']}' : 'Ingresó ${hoy!['entrada']} · Salió $salida',
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        ),
        Etiqueta(textoDeEstado(hoy!['estado'] as String?), color: colorDeEstado(hoy!['estado'] as String?)),
      ],
    );
  }
}
