import 'package:material_ui/material_ui.dart';

import '../componentes/carga.dart';
import '../nucleo/sesion.dart';
import '../nucleo/tema.dart';

/// Inicio de sesión. Un solo formulario para todos: el servidor reconoce si
/// la cuenta es de un apoderado, un alumno, un docente, el guardia o el admin.
class PantallaLogin extends StatefulWidget {
  const PantallaLogin({super.key, required this.sesion});

  final Sesion sesion;

  @override
  State<PantallaLogin> createState() => _PantallaLoginState();
}

class _PantallaLoginState extends State<PantallaLogin> {
  final _formulario = GlobalKey<FormState>();
  final _usuario = TextEditingController();
  final _clave = TextEditingController();
  bool _enviando = false;
  bool _verClave = false;

  @override
  void dispose() {
    _usuario.dispose();
    _clave.dispose();
    super.dispose();
  }

  Future<void> _entrar() async {
    if (_enviando || !_formulario.currentState!.validate()) return;
    setState(() => _enviando = true);
    try {
      await widget.sesion.iniciar(_usuario.text, _clave.text);
    } on Object catch (error) {
      if (mounted) mostrarError(context, error);
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Column(
        children: [
          // Panel de marca: el mismo rojo y escudo del login web.
          Container(
            width: double.infinity,
            padding: EdgeInsets.fromLTRB(24, MediaQuery.paddingOf(context).top + 36, 24, 36),
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [Marca.primario, Marca.primarioOscuro],
              ),
            ),
            child: Column(
              children: [
                Container(
                  width: 112,
                  height: 112,
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(26),
                    boxShadow: const [BoxShadow(color: Color(0x4D000000), blurRadius: 24, offset: Offset(0, 10))],
                  ),
                  child: Image.asset('assets/logo.png', fit: BoxFit.contain),
                ),
                const SizedBox(height: 16),
                Text(
                  widget.sesion.colegio,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Asistencia y control de acceso, en tiempo real.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Color(0xC7FFFFFF), fontSize: 14),
                ),
              ],
            ),
          ),
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: Form(
                key: _formulario,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const SizedBox(height: 4),
                    const Text(
                      'Iniciar sesión',
                      textAlign: TextAlign.center,
                      style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: Marca.texto),
                    ),
                    const SizedBox(height: 20),
                    TextFormField(
                      controller: _usuario,
                      decoration: const InputDecoration(labelText: 'Usuario, correo o teléfono', prefixIcon: Icon(Icons.person_outline)),
                      keyboardType: TextInputType.emailAddress,
                      autocorrect: false,
                      textInputAction: TextInputAction.next,
                      validator: (v) => (v == null || v.trim().isEmpty) ? 'Escribe tu usuario' : null,
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _clave,
                      decoration: InputDecoration(
                        labelText: 'Contraseña',
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(
                          tooltip: _verClave ? 'Ocultar contraseña' : 'Mostrar contraseña',
                          icon: Icon(_verClave ? Icons.visibility_off : Icons.visibility),
                          onPressed: () => setState(() => _verClave = !_verClave),
                        ),
                      ),
                      obscureText: !_verClave,
                      onFieldSubmitted: (_) => _entrar(),
                      validator: (v) => (v == null || v.isEmpty) ? 'Escribe tu contraseña' : null,
                    ),
                    const SizedBox(height: 20),
                    FilledButton.icon(
                      onPressed: _enviando ? null : _entrar,
                      icon: _enviando
                          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                          : const Icon(Icons.login),
                      label: const Text('Ingresar'),
                    ),
                    const SizedBox(height: 16),
                    const Text(
                      'Padres y apoderados entran con su teléfono.\n¿Problemas para entrar? Consulta en secretaría del colegio.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Marca.textoSuave, fontSize: 13),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
