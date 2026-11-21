/// Qué es la cuenta dentro del colegio. Decide qué pantallas ve.
enum Rol {
  admin,
  guardia,
  direccion,
  personal,
  alumno,
  apoderado;

  static Rol desde(String valor) => Rol.values.firstWhere((r) => r.name == valor, orElse: () => Rol.apoderado);

  /// Quienes registran asistencia en la puerta.
  bool get esDePuerta => this == Rol.admin || this == Rol.guardia;

  String get etiqueta => switch (this) {
    Rol.admin => 'Administrador',
    Rol.guardia => 'Guardia',
    Rol.direccion => 'Dirección',
    Rol.personal => 'Personal',
    Rol.alumno => 'Alumno',
    Rol.apoderado => 'Apoderado',
  };
}

/// La cuenta que inició sesión.
class Perfil {
  const Perfil({
    required this.id,
    required this.nombre,
    required this.usuario,
    required this.rol,
    required this.debeCambiarClave,
    this.codigo,
    this.carne,
  });

  factory Perfil.desdeJson(Map<String, dynamic> json) => Perfil(
    id: json['id'] as int,
    nombre: json['nombre'] as String,
    usuario: json['usuario'] as String,
    rol: Rol.desde(json['rol'] as String),
    debeCambiarClave: json['debe_cambiar_clave'] as bool? ?? false,
    codigo: json['codigo'] as String?,
    carne: json['carne'] as Map<String, dynamic>?,
  );

  final int id;
  final String nombre;
  final String usuario;
  final Rol rol;
  final bool debeCambiarClave;

  /// Código del carné: lo tienen alumnos y personal.
  final String? codigo;

  /// Lo que va impreso en su carné (título, nombre, grado o cargo, DNI, foto).
  /// Lo arma el servidor; falta en las sesiones abiertas con una versión anterior.
  final Map<String, dynamic>? carne;

  Map<String, dynamic> aJson() => {
    'id': id,
    'nombre': nombre,
    'usuario': usuario,
    'rol': rol.name,
    'debe_cambiar_clave': debeCambiarClave,
    'codigo': codigo,
    'carne': carne,
  };

  Perfil conClaveCambiada() =>
      Perfil(id: id, nombre: nombre, usuario: usuario, rol: rol, debeCambiarClave: false, codigo: codigo, carne: carne);

  /// El primer nombre, para saludar.
  String get primerNombre => nombre.split(' ').first;
}
