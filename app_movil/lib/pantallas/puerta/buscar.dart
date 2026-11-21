import 'package:material_ui/material_ui.dart';

import '../../componentes/carga.dart';
import '../../nucleo/api.dart';
import '../../nucleo/tema.dart';

/// Para marcar a mano a quien no trae su carné: se busca por nombre o código
/// y se registra con un toque.
class PantallaBuscar extends StatefulWidget {
  const PantallaBuscar({super.key, required this.api});

  final Api api;

  @override
  State<PantallaBuscar> createState() => _PantallaBuscarState();
}

class _PantallaBuscarState extends State<PantallaBuscar> {
  final _texto = TextEditingController();
  List<Map<String, dynamic>> _personas = const [];
  bool _buscando = false;
  bool _yaBusco = false;

  @override
  void dispose() {
    _texto.dispose();
    super.dispose();
  }

  Future<void> _buscar() async {
    final q = _texto.text.trim();
    if (q.length < 2) {
      mostrarError(context, ErrorApi('Escribe al menos 2 letras.'));
      return;
    }
    setState(() => _buscando = true);
    try {
      final datos = await widget.api.get('asistencia/personas', consulta: {'q': q}) as Map<String, dynamic>;
      if (!mounted) return;
      setState(() {
        _personas = (datos['personas'] as List).cast<Map<String, dynamic>>();
        _yaBusco = true;
      });
    } on Object catch (error) {
      if (mounted) mostrarError(context, error);
    } finally {
      if (mounted) setState(() => _buscando = false);
    }
  }

  Future<void> _marcar(Map<String, dynamic> persona) async {
    try {
      final datos = await widget.api.post('asistencia/marcar', {'codigo': persona['codigo']}) as Map<String, dynamic>;
      if (!mounted) return;
      mostrarExito(context, datos['mensaje'] as String);
      await _buscar();
    } on Object catch (error) {
      if (mounted) mostrarError(context, error);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(16),
          child: TextField(
            controller: _texto,
            decoration: InputDecoration(
              labelText: 'Nombre o código',
              suffixIcon: IconButton(tooltip: 'Buscar', icon: const Icon(Icons.search), onPressed: _buscar),
            ),
            textInputAction: TextInputAction.search,
            onSubmitted: (_) => _buscar(),
          ),
        ),
        if (_buscando) const LinearProgressIndicator(),
        Expanded(
          child: _personas.isEmpty
              ? Center(
                  child: Text(
                    _yaBusco ? 'Nadie coincide con esa búsqueda.' : 'Busca a quien no trae su carné.',
                    style: const TextStyle(color: Marca.textoSuave),
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  itemCount: _personas.length,
                  itemBuilder: (context, i) {
                    final p = _personas[i];
                    final entrada = p['entrada'] as String?;
                    final salida = p['salida'] as String?;
                    final estado = entrada == null
                        ? 'Sin marcar hoy'
                        : salida == null
                        ? 'Entró $entrada'
                        : 'Entró $entrada · Salió $salida';

                    return Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: ListTile(
                        leading: Icon(p['tipo'] == 'alumno' ? Icons.school : Icons.badge, color: Marca.primario),
                        title: Text(p['nombre'] as String, style: const TextStyle(fontWeight: FontWeight.w700)),
                        subtitle: Text('${p['codigo']} · ${p['detalle'] ?? ''}\n$estado'),
                        isThreeLine: true,
                        trailing: salida != null
                            ? null
                            : FilledButton.tonal(onPressed: () => _marcar(p), child: Text(entrada == null ? 'Entrada' : 'Salida')),
                      ),
                    );
                  },
                ),
        ),
      ],
    );
  }
}
