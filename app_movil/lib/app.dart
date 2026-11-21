import 'package:material_ui/material_ui.dart';

import 'nucleo/api.dart';
import 'nucleo/perfil.dart';
import 'nucleo/sesion.dart';
import 'nucleo/tema.dart';
import 'pantallas/alumno/mas.dart';
import 'pantallas/apoderado/hijos.dart';
import 'pantallas/apoderado/matricula.dart';
import 'pantallas/apoderado/mensajes.dart';
import 'pantallas/cambiar_clave.dart';
import 'pantallas/carne.dart';
import 'pantallas/docente/aulas.dart';
import 'pantallas/expediente/vistas.dart';
import 'pantallas/inicio.dart';
import 'pantallas/login.dart';
import 'pantallas/puerta/buscar.dart';
import 'pantallas/puerta/escaner.dart';
import 'pantallas/puerta/hoy.dart';

/// La app. Decide qué mostrar según la sesión: el login, el cambio
/// obligatorio de contraseña o las pantallas del rol.
class AppColegio extends StatelessWidget {
  const AppColegio({super.key, required this.sesion, this.conCamara = true});

  final Sesion sesion;

  /// Falso en las pruebas, donde no hay cámara.
  final bool conCamara;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Colegio',
      debugShowCheckedModeBanner: false,
      theme: temaDelColegio(),
      home: ListenableBuilder(
        listenable: sesion,
        builder: (context, _) {
          if (!sesion.lista) {
            return const Scaffold(body: Center(child: CircularProgressIndicator()));
          }
          if (!sesion.abierta) {
            return PantallaLogin(sesion: sesion);
          }
          if (sesion.perfil.debeCambiarClave) {
            return PantallaCambiarClave(sesion: sesion, obligatorio: true);
          }
          // La clave reinicia las pestañas si entra otra cuenta en el mismo teléfono.
          return PantallaInicio(
            key: ValueKey('${sesion.perfil.rol.name}-${sesion.perfil.id}'),
            sesion: sesion,
            secciones: seccionesPara(sesion, conCamara: conCamara),
          );
        },
      ),
    );
  }
}

/// Las secciones de la barra inferior para cada rol.
List<Seccion> seccionesPara(Sesion sesion, {bool conCamara = true}) {
  final Api api = sesion.api;
  final perfil = sesion.perfil;

  return switch (perfil.rol) {
    Rol.admin || Rol.guardia => [
      Seccion(
        titulo: 'Escanear',
        icono: Icons.qr_code_scanner,
        pantalla: PantallaEscaner(api: api, conCamara: conCamara),
        soloAlVerla: true,
      ),
      Seccion(
        titulo: 'Buscar',
        icono: Icons.person_search,
        pantalla: PantallaBuscar(api: api),
      ),
      Seccion(
        titulo: 'Hoy',
        icono: Icons.insights,
        pantalla: PantallaHoy(api: api),
      ),
    ],
    Rol.apoderado => [
      Seccion(
        titulo: 'Mensajes',
        icono: Icons.mark_chat_unread_outlined,
        pantalla: PantallaMensajes(api: api),
      ),
      Seccion(
        titulo: 'Mis hijos',
        icono: Icons.family_restroom,
        pantalla: PantallaHijos(api: api, colegio: sesion.colegio),
      ),
      Seccion(
        titulo: 'Matrícula',
        icono: Icons.how_to_reg,
        pantalla: PantallaMatricula(api: api),
      ),
    ],
    Rol.alumno => [
      Seccion(
        titulo: 'Mi carné',
        icono: Icons.qr_code_2,
        pantalla: PantallaCarne(sesion: sesion, codigo: perfil.codigo),
      ),
      Seccion(
        titulo: 'Notas',
        icono: Icons.grading,
        pantalla: VistaNotas(api: api, prefijo: 'mi'),
      ),
      Seccion(
        titulo: 'Tareas',
        icono: Icons.edit_note,
        pantalla: VistaTareas(api: api, prefijo: 'mi'),
      ),
      Seccion(
        titulo: 'Más',
        icono: Icons.menu,
        pantalla: PantallaMasDelAlumno(api: api),
      ),
    ],
    Rol.personal => [
      Seccion(
        titulo: 'Mis aulas',
        icono: Icons.class_outlined,
        pantalla: PantallaAulas(api: api),
      ),
      Seccion(
        titulo: 'Horario',
        icono: Icons.calendar_month,
        pantalla: VistaHorario(api: api, ruta: 'docente/horario'),
      ),
      Seccion(
        titulo: 'Asistencia',
        icono: Icons.event_available,
        pantalla: VistaAsistencia(api: api, prefijo: 'mi'),
      ),
      Seccion(
        titulo: 'Mi carné',
        icono: Icons.qr_code_2,
        pantalla: PantallaCarne(sesion: sesion, codigo: perfil.codigo),
      ),
    ],
    Rol.direccion => [
      Seccion(
        titulo: 'Inicio',
        icono: Icons.home_outlined,
        pantalla: PantallaSinFunciones(rol: perfil.rol),
      ),
    ],
  };
}
