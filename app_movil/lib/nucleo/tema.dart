import 'package:material_ui/material_ui.dart';

/// Identidad visual: la misma del panel web (rojo institucional, tarjetas
/// blancas redondeadas).
abstract final class Marca {
  static const primario = Color(0xFFFD0D0D);
  static const primarioOscuro = Color(0xFFCF0B0B);
  static const fondo = Color(0xFFF3F5FA);
  static const texto = Color(0xFF1C2430);
  static const textoSuave = Color(0xFF7A8394);

  static const exito = Color(0xFF16A34A);
  static const aviso = Color(0xFFE8A013);
  static const info = Color(0xFF0D6EFD);
}

ThemeData temaDelColegio() {
  final esquema = ColorScheme.fromSeed(seedColor: Marca.primario, primary: Marca.primario, surface: Colors.white);

  return ThemeData(
    colorScheme: esquema,
    scaffoldBackgroundColor: Marca.fondo,
    appBarTheme: const AppBarTheme(backgroundColor: Marca.primario, foregroundColor: Colors.white, centerTitle: false, elevation: 0),
    cardTheme: CardThemeData(
      color: Colors.white,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: Color(0xFFE8EBF2)),
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: Marca.primario,
        foregroundColor: Colors.white,
        minimumSize: const Size.fromHeight(52),
        textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0xFFD9DEE8)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Marca.primario, width: 2),
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: Colors.white,
      indicatorColor: Marca.primario.withValues(alpha: 0.12),
      labelTextStyle: WidgetStateProperty.all(const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
    ),
  );
}
