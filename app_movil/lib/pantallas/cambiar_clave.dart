import 'package:material_ui/material_ui.dart';

import '../componentes/carga.dart';
import '../nucleo/sesion.dart';
import '../nucleo/tema.dart';

/// Regla de la contraseña nueva: la misma que aplica el servidor. Devuelve el
/// motivo del rechazo, o null si es válida.
String? validarClaveNueva(String? valor) {
  final v = valor ?? '';
  if (v.length < 8) return 'Mínimo 8 caracteres';
  if (!v.contains(RegExp('[A-Z]')) || !v.contains(RegExp('[a-z]'))) return 'Usa mayúsculas y minúsculas';
  if (!v.contains(RegExp('[0-9]'))) return 'Incluye al menos un número';
  return null;
}

/// Cambio de contraseña. Cuando [obligatorio] es verdadero es la pantalla que
/// ve quien entró con la contraseña inicial: no puede usar nada más hasta
/// crear la suya.
class PantallaCambiarClave extends StatefulWidget {
  const PantallaCambiarClave({super.key, required this.sesion, this.obligatorio = false});

  final Sesion sesion;
  final bool obligatorio;

  @override
  State<PantallaCambiarClave> createState() => _PantallaCambiarClaveState();
}

class _PantallaCambiarClaveState extends State<PantallaCambiarClave> {
  final _formulario = GlobalKey<FormState>();
  final _actual = TextEditingController();
  final _nueva = TextEditingController();
  final _repetida = TextEditingController();
  bool _enviando = false;

  @override
  void dispose() {
    _actual.dispose();
    _nueva.dispose();
    _repetida.dispose();
    super.dispose();
  }

  Future<void> _guardar() async {
    if (_enviando || !_formulario.currentState!.validate()) return;
    setState(() => _enviando = true);
    try {
      await widget.sesion.cambiarClave(_actual.text, _nueva.text);
      if (!mounted) return;
      mostrarExito(context, 'Contraseña actualizada.');
      if (!widget.obligatorio) Navigator.of(context).pop();
    } on Object catch (error) {
      if (mounted) mostrarError(context, error);
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.obligatorio ? 'Crea tu contraseña' : 'Cambiar contraseña'),
        automaticallyImplyLeading: !widget.obligatorio,
        actions: [
          if (widget.obligatorio)
            TextButton(
              onPressed: widget.sesion.cerrar,
              child: const Text('Salir', style: TextStyle(color: Colors.white)),
            ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formulario,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (widget.obligatorio) ...[
                const Icon(Icons.shield_outlined, size: 52, color: Marca.primario),
                const SizedBox(height: 12),
                const Text(
                  'Entraste con la contraseña inicial. Antes de continuar, elige una que solo tú conozcas.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Marca.textoSuave, fontSize: 15),
                ),
                const SizedBox(height: 20),
              ],
              TextFormField(
                controller: _actual,
                decoration: InputDecoration(labelText: widget.obligatorio ? 'Contraseña inicial' : 'Contraseña actual'),
                obscureText: true,
                textInputAction: TextInputAction.next,
                validator: (v) => (v == null || v.isEmpty) ? 'Escribe tu contraseña actual' : null,
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _nueva,
                decoration: const InputDecoration(
                  labelText: 'Nueva contraseña',
                  helperText: 'Mínimo 8 caracteres, con mayúsculas, minúsculas y números',
                  helperMaxLines: 2,
                ),
                obscureText: true,
                textInputAction: TextInputAction.next,
                validator: validarClaveNueva,
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _repetida,
                decoration: const InputDecoration(labelText: 'Repite la nueva contraseña'),
                obscureText: true,
                onFieldSubmitted: (_) => _guardar(),
                validator: (v) => v != _nueva.text ? 'Las contraseñas no coinciden' : null,
              ),
              const SizedBox(height: 22),
              FilledButton(onPressed: _enviando ? null : _guardar, child: Text(_enviando ? 'Guardando…' : 'Guardar contraseña')),
            ],
          ),
        ),
      ),
    );
  }
}
