/// Fechas en el formato que se usa en el colegio, sin depender de paquetes.
abstract final class Fechas {
  static const _meses = [
    'enero',
    'febrero',
    'marzo',
    'abril',
    'mayo',
    'junio',
    'julio',
    'agosto',
    'septiembre',
    'octubre',
    'noviembre',
    'diciembre',
  ];
  static const _dias = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];

  /// "2026-03-10" → "10/03/2026"
  static String corta(String iso) {
    final f = DateTime.tryParse(iso);
    if (f == null) return iso;
    return '${_dos(f.day)}/${_dos(f.month)}/${f.year}';
  }

  /// "martes 10 de marzo"
  static String larga(DateTime f) => '${_dias[f.weekday - 1]} ${f.day} de ${_meses[f.month - 1]}';

  /// Para la bandeja: "Hoy 07:25", "Ayer 13:05" o "10/03 07:25".
  static String relativa(String iso, {DateTime? ahora}) {
    final f = DateTime.tryParse(iso)?.toLocal();
    if (f == null) return iso;
    final hoy = ahora ?? DateTime.now();
    final dias = DateTime(hoy.year, hoy.month, hoy.day).difference(DateTime(f.year, f.month, f.day)).inDays;
    final hora = '${_dos(f.hour)}:${_dos(f.minute)}';
    if (dias == 0) return 'Hoy $hora';
    if (dias == 1) return 'Ayer $hora';
    return '${_dos(f.day)}/${_dos(f.month)} $hora';
  }

  /// "2026-03" del mes de una fecha.
  static String claveDeMes(DateTime f) => '${f.year}-${_dos(f.month)}';

  /// "marzo 2026"
  static String nombreDeMes(DateTime f) => '${_meses[f.month - 1]} ${f.year}';

  static String _dos(int n) => n.toString().padLeft(2, '0');
}
