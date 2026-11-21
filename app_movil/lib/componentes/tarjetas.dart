import 'package:material_ui/material_ui.dart';

import '../nucleo/tema.dart';

/// Etiqueta de color pequeña: "Puntual", "Tarde", "Pagada"...
class Etiqueta extends StatelessWidget {
  const Etiqueta(this.texto, {super.key, required this.color});

  final String texto;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
      child: Text(
        texto,
        style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w700),
      ),
    );
  }
}

/// Título de un bloque dentro de una lista.
class TituloDeBloque extends StatelessWidget {
  const TituloDeBloque(this.texto, {super.key});

  final String texto;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 16, 4, 8),
      child: Text(
        texto.toUpperCase(),
        style: const TextStyle(color: Marca.textoSuave, fontSize: 12, fontWeight: FontWeight.w700, letterSpacing: 0.8),
      ),
    );
  }
}

/// Un número grande con su rótulo, para los resúmenes.
class Indicador extends StatelessWidget {
  const Indicador({super.key, required this.valor, required this.rotulo, this.color = Marca.texto});

  final String valor;
  final String rotulo;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Column(
        children: [
          Text(
            valor,
            style: TextStyle(fontSize: 26, fontWeight: FontWeight.w800, color: color),
          ),
          Text(
            rotulo,
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 12, color: Marca.textoSuave),
          ),
        ],
      ),
    );
  }
}

/// Colores y textos de los estados de asistencia.
Color colorDeEstado(String? estado) => switch (estado) {
  'tarde' => Marca.aviso,
  'falta' => Marca.primario,
  _ => Marca.exito,
};

String textoDeEstado(String? estado) => switch (estado) {
  'tarde' => 'Tarde',
  'falta' => 'Falta',
  'puntual' => 'Puntual',
  _ => 'Sin registro',
};
