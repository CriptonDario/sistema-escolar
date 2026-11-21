/// Dónde está el servidor del colegio.
///
/// Se fija al compilar:
///   flutter run --dart-define=API_URL=https://micolegio.edu.pe/api/v1
///
/// El valor por defecto apunta a la computadora de desarrollo vista desde el
/// emulador de Android (10.0.2.2 es "el localhost de la PC").
const String apiUrlPorDefecto = String.fromEnvironment('API_URL', defaultValue: 'http://10.0.2.2:8000/api/v1');
