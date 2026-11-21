import 'package:material_ui/material_ui.dart';

import 'app.dart';
import 'nucleo/almacen.dart';
import 'nucleo/api.dart';
import 'nucleo/config.dart';
import 'nucleo/sesion.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();

  final sesion = Sesion(
    api: Api(baseUrl: apiUrlPorDefecto),
    almacen: AlmacenDelTelefono(),
  );
  sesion.restaurar();

  runApp(AppColegio(sesion: sesion));
}
