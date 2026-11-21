# Sistema Escolar

Sistema para colegios de nivel inicial, primaria y secundaria, con secciones
configurables por grado, y su app móvil.

```
sistema-escolar/
├── backend/     API y lógica del sistema — Laravel 12, PHP 8.2+
└── app_movil/   App Flutter (Android e iOS)
```

Es una reescritura limpia del sistema anterior (`C:\laragon\www\sitio`), que
sigue funcionando sin cambios mientras este se completa.

---

## Estado

| Parte | Estado |
|---|---|
| Login del panel y de la app, roles, bloqueo por intentos fallidos | Hecho, con pruebas |
| Cambio obligatorio de la contraseña inicial | Hecho, con pruebas |
| Identidad del colegio (nombre, logo, color) configurable | Hecho, con pruebas |
| Grados por nivel y secciones A, B, C… configurables | Hecho, con pruebas |
| Alumnos, personal y apoderados (con sus hijos) | Hecho, con pruebas |
| Kiosco de asistencia para alumnos y personal | Hecho, con pruebas |
| Historial de asistencia con descarga para Excel | Hecho, con pruebas |
| Mensajes al apoderado (ingreso, tardanza, salida, permiso, tarea, conducta, comunicado) | Hecho, con pruebas |
| Permisos de alumnos | Hecho, con pruebas |
| Matrículas por año | Hecho, con pruebas |
| Materias y notas, con bimestres, trimestres o semestres y escala numérica o literal por nivel | Hecho, con pruebas |
| Horarios por aula | Hecho, con pruebas |
| Tareas y agenda | Hecho, con pruebas |
| Conducta y tutoría | Hecho, con pruebas |
| Comunicados a todo el colegio, un nivel o un aula | Hecho, con pruebas |
| Usuarios del sistema y configuración | Hecho, con pruebas |
| API para la app: asistencia, mensajes, notas, horario, tareas, conducta, comunicados | Hecho, con pruebas |
| Notificaciones push (Firebase) | Hecho, probado contra un Firebase simulado |
| WhatsApp (UltraMsg o Meta), Sofía y facturación electrónica SUNAT vía Nubefact, configurables | Hecho, probado contra servicios simulados; falta probar con las cuentas reales |
| Pagos con recibo y estado de cuenta, conceptos, gastos e inventario | Hecho, con pruebas |
| Visitantes y bitácora de incidencias | Hecho, con pruebas |
| Asignación de docentes y sus funciones en la API (aulas, notas, tareas, conducta, horario) | Hecho, con pruebas |
| Importar los datos del sistema anterior (`colegio:importar-anterior`) | Hecho, con pruebas; falta ejecutarlo contra la base real |
| Prueba sobre MySQL | Pendiente (desarrollado y probado sobre SQLite) |
| Portal web de padres (`/familia`): mensajes y, por cada hijo, carné tamaño DNI con QR, asistencia, notas, tareas, horario, conducta, pagos y comunicados. Solo entra quien ya creó su contraseña en la app | Hecho, con pruebas |
| Matrícula en línea: el administrador la abre, el apoderado la pide desde la app o el portal y se confirma con el pago (en secretaría o en línea con Mercado Pago) | Hecho, con pruebas; falta probar el pago en línea con una cuenta real de Mercado Pago |
| Carné tamaño DNI con foto para alumnos, docentes y trabajadores; impresión individual y en lote | Hecho, con pruebas |
| Pases genéricos de visitante (VIS-01, VIS-02…): se entregan al ingresar y al escanearlos se marca la salida | Hecho, con pruebas |
| App móvil Flutter (guardia y admin, apoderado, alumno, docente) | Hecho, con pruebas; falta probarla en un teléfono y el push |

---|---|
| Cuentas y roles (admin, guardia, dirección, personal, alumno, apoderado) | Hecho, con pruebas |
| Login único, sesión por token, bloqueo por intentos fallidos | Hecho, con pruebas |
| Cambio obligatorio de la contraseña inicial | Hecho, con pruebas |
| Grados por nivel y secciones A, B, C… configurables | Hecho, con pruebas |
| Alumnos, personal y apoderados (con sus hijos) | Hecho, con pruebas |
| Asistencia por código de carné, para alumnos y personal | Hecho, con pruebas |
| Mensajes al apoderado (ingreso, tardanza, salida, permiso) | Hecho, con pruebas |
| Permisos de alumnos | Hecho, con pruebas |
| Notificaciones push (Firebase) | Hecho, probado contra un Firebase simulado |
| WhatsApp (UltraMsg o Meta), Sofía y facturación electrónica SUNAT vía Nubefact, configurables | Hecho, probado contra servicios simulados; falta probar con las cuentas reales |
| Panel web de administración | Pendiente |
| Matrículas, notas, pagos, horarios, tareas, conducta, comunicados | Pendiente |
| Importar los datos del sistema anterior (`colegio:importar-anterior`) | Hecho, con pruebas; falta ejecutarlo contra la base real |
| App móvil Flutter (guardia y admin, apoderado, alumno, docente) | Hecho, con pruebas; falta probarla en un teléfono y el push |

---

## Backend

### Instalar en una computadora de desarrollo

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed          # crea las tablas y los grados habituales
php artisan colegio:crear-admin admin                  # pide la contraseña
php artisan colegio:crear-admin porteria --rol=guardia
php artisan serve
```

Por defecto usa SQLite (`database/database.sqlite`), sin instalar nada más.

### Pruebas

```bash
php artisan test        # 196 pruebas
./vendor/bin/pint       # estilo de código
```

### Control de calidad

```bash
composer calidad
```

Ejecuta, en orden: el estilo del código (Pint), el análisis estático (PHPStan
nivel 5, sin excepciones ni líneas ignoradas), las pruebas y la consulta de
vulnerabilidades conocidas en las librerías. Todo debe salir en limpio antes de
publicar una versión.

### Poner en producción

1. Subir `backend/` al hosting y apuntar el dominio a `backend/public`.
2. En `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…` y
   la base MySQL (`DB_CONNECTION=mysql`, `DB_HOST`, `DB_DATABASE`,
   `DB_USERNAME`, `DB_PASSWORD`).
3. `composer install --no-dev --optimize-autoloader`
4. `php artisan key:generate` (solo la primera vez)
5. `php artisan migrate --force --seed`
6. `php artisan colegio:crear-admin <usuario>`
7. `php artisan config:cache && php artisan route:cache`
8. Programar la cola, que es la que envía las notificaciones push. En el cron
   del hosting, cada minuto:
   `php /ruta/backend/artisan queue:work --stop-when-empty --max-time=50`

Para el push hay que crear un proyecto en Firebase, descargar la clave de la
cuenta de servicio (JSON), guardarla fuera de `public/` y poner su ruta en
`FCM_CREDENCIALES`. Sin eso el sistema funciona igual: los mensajes quedan en
la bandeja de la app y solo falta el aviso instantáneo.

### Traer los datos del sistema anterior

1. En `.env`, completa `ANTERIOR_DB_HOST`, `ANTERIOR_DB_DATABASE`,
   `ANTERIOR_DB_USERNAME` y `ANTERIOR_DB_PASSWORD` con la base del sistema anterior.
2. `php artisan colegio:importar-anterior`

Trae grados y secciones, alumnos, personal, apoderados (una cuenta por cada
teléfono de apoderado), el historial de asistencia, las materias, los conceptos
de pago y las notas. Si `ANTERIOR_RUTA_FOTOS` apunta a la carpeta `uploads` del
sistema anterior, trae también las fotos del carné. Solo lee de la base
anterior y se puede repetir: lo que ya existe se salta. No trae contraseñas:
las cuentas nacen con la inicial. Con `--sin-asistencia` omite el historial.

### Integraciones opcionales

Se configuran en el panel, menú **Integraciones**. Las tres vienen apagadas y
el sistema funciona completo sin ellas.

| Integración | Qué hace | Qué se necesita |
|---|---|---|
| WhatsApp | Cada mensaje al apoderado sale también por WhatsApp | Cuenta de UltraMsg (instancia y token) o de Meta WhatsApp Business (Phone Number ID y token) |
| Mercado Pago | Los apoderados pagan la matrícula en línea y quedan matriculados al instante | El Access Token de la cuenta de Mercado Pago del colegio. El sistema debe estar publicado con https |
| Sofía | Recibe cada ingreso y salida y le escribe al apoderado | La dirección y el token que entrega Sofía |
| SUNAT | Emite la boleta o factura electrónica desde el recibo de un pago | Cuenta de Nubefact: ruta y token, y las series registradas ahí |

Los tokens se guardan cifrados con la `APP_KEY`: si esa clave cambia, hay que
volver a escribirlos. Los envíos salen por la cola, igual que el push, así que
necesitan el cron de `queue:work` (ver "Poner en producción"); en desarrollo,
`php artisan queue:work` en otra terminal.

### Cómo está organizado

| Carpeta | Contenido |
|---|---|
| `app/Enums` | Valores fijos del dominio: roles, niveles, tipos de mensaje y de permiso |
| `app/Models` | Tablas y sus relaciones |
| `app/Services` | Reglas del negocio: registrar asistencia, enviar mensajes, push, crear cuentas |
| `app/Http/Controllers/Api/V1` | Entradas de la API; solo validan, llaman a un servicio y responden |
| `app/Http/Requests` | Validación de lo que llega |
| `app/Http/Resources` | Forma de lo que se responde |
| `app/Http/Middleware` | Control de rol y de cuenta habilitada |
| `tests/Feature` | Pruebas de cada flujo, de la petición a la base de datos |

### API

Base: `/api/v1`. Salvo el login, cada petición lleva
`Authorization: Bearer <token>`. `php artisan route:list --path=api` las lista todas.

| Quién | Rutas |
|---|---|
| Todos | `POST auth/login` · `POST auth/logout` · `POST auth/clave` · `GET perfil` · `POST dispositivos` |
| Admin y guardia | `POST asistencia/marcar` · `GET asistencia/hoy` · `GET asistencia/personas?q=` |
| Apoderado | `GET hijos` · `GET hijos/{id}/asistencia` · `GET mensajes` · `POST mensajes/{id}/leer` · `POST mensajes/leer-todos` |
| Alumno y personal | `GET mi/asistencia` |
| Admin | `admin/grados` · `admin/secciones` · `admin/alumnos` · `admin/personal` · `admin/apoderados` · `admin/permisos` |

### Reglas de asistencia

- El primer escaneo del día es la **entrada**. Es puntual hasta la hora
  configurada inclusive (con `07:30`, hasta las 07:30:59), más la tolerancia.
- El siguiente escaneo es la **salida**, salvo que haya pasado menos del mínimo
  configurado desde la entrada (60 minutos por defecto): así un doble escaneo
  en la puerta no le avisa al apoderado que su hijo "salió".
- Un tercer escaneo no cambia nada.
- Hora de entrada, tolerancia y mínimo para la salida se guardan en la tabla
  `ajustes` y mandan sobre los valores de `config/colegio.php`.

### Cuentas

Las cuentas de alumnos, personal y apoderados nacen con la contraseña de
`COLEGIO_CLAVE_INICIAL` y deben cambiarla en su primer ingreso. El apoderado
entra con su teléfono; alumnos y personal, con su correo.

---

## App móvil

Una sola app para todos los perfiles; al iniciar sesión muestra las pantallas del rol.

| Perfil | Pantallas |
|---|---|
| Guardia y admin | Escanear el carné (cámara o código a mano), buscar por nombre para marcar a mano, resumen del día |
| Apoderado | Mensajes (ingreso, salida, permisos, tareas, comunicados, pagos) y, por cada hijo: carné tamaño DNI con QR, asistencia, notas, tareas, horario, conducta, pagos y comunicados |
| Alumno | Carné con su QR, notas, tareas, asistencia, horario, conducta y comunicados |
| Docente | Sus aulas (registrar notas, publicar tareas, registrar conducta), su horario, su asistencia y su carné |

### Requisitos

- Flutter en `C:\srclutter` (agrega `C:\srclutterin` al PATH).
- Android Studio con el SDK de Android.
- Para compilar con complementos en Windows hay que activar el **Modo de desarrollador**
  (`start ms-settings:developers`).

### Probarla

```bash
# 1. En una terminal: el servidor, con datos de demostración
cd backend
php artisan db:seed --class=DemoSeeder      # solo en desarrollo
php artisan serve --host=0.0.0.0

# 2. En otra terminal: la app
cd app_movil
flutter run                                  # emulador de Android
flutter run --dart-define=API_URL=http://192.168.1.50:8000/api/v1   # teléfono real: la IP de tu PC
```

Cuentas de demostración: `porteria` / `Guardia2026` (guardia),
`987654321` / `123456` (apoderado), `luis.ramos@demo.edu.pe` / `123456` (docente),
`ana.perez@demo.edu.pe` / `123456` (alumna). Las tres últimas piden crear una contraseña propia.

### Pruebas

```bash
flutter analyze
flutter test                                 # 18 pruebas con un servidor simulado
flutter test test/contrato_test.dart --dart-define=API_REAL=http://127.0.0.1:8000/api/v1
```

La última es la prueba de contrato: usa un servidor Laravel de verdad (con los
datos de demostración) y comprueba que las respuestas traen lo que las
pantallas leen. Cambia contraseñas y crea registros, así que debe apuntar a
una base de desarrollo.

### Publicarla

```bash
flutter build apk --release --dart-define=API_URL=https://tu-colegio.edu.pe/api/v1
```

La versión publicada solo acepta `https`. El ícono ya es el escudo del colegio
(`assets/icono.png`; tras cambiarlo, `dart run flutter_launcher_icons`).

Para Play Store la app debe ir firmada con una llave propia del colegio. Se
crea una sola vez y hay que guardarla bien: sin ella no se pueden publicar
actualizaciones.

```bash
keytool -genkey -v -keystore colegio.jks -keyalg RSA -keysize 2048 -validity 10000 -alias colegio
```

Luego crea `app_movil/android/key.properties` (no se sube al repositorio):

```properties
storeFile=C:/ruta/segura/colegio.jks
storePassword=la-contraseña-del-almacén
keyAlias=colegio
keyPassword=la-contraseña-de-la-llave
```

Con ese archivo presente, `flutter build appbundle --release --dart-define=API_URL=…`
genera el paquete firmado que se sube a Play Store. Sin él, se firma con la
llave de desarrollo, que sirve para probar pero no para publicar.

## Seguridad

Lo que el sistema hace por su cuenta:

- **Contraseñas** cifradas (bcrypt); nadie puede leerlas, ni el administrador.
- **Permisos por rol** en cada pantalla y en cada dirección de la API. Un
  apoderado solo ve a sus hijos; las fotos solo las ve quien corresponde.
- **Freno de intentos**: 5 fallos bloquean esa cuenta 15 minutos, y 40 fallos
  desde una misma dirección la bloquean 10 minutos (frena a quien prueba la
  contraseña inicial contra muchas cuentas).
- **Tope de peticiones** en la API, por teléfono.
- **Cabeceras de seguridad** en todas las respuestas (no se puede incrustar en
  otro sitio, no carga programas ajenos, https obligatorio cuando lo hay).
- **Cierre de sesiones** al cambiar o restablecer una contraseña y al
  desactivar una cuenta: en la app y en la web.
- **Registro de seguridad** (panel → Sistema → Seguridad): ingresos, intentos
  fallidos, bloqueos, cambios de contraseña, cuentas e integraciones. No guarda
  contraseñas.
- **Secretos cifrados** en la base (tokens de WhatsApp, SUNAT, Mercado Pago).

Antes de publicar, y de vez en cuando después:

```bash
php artisan colegio:revisar-seguridad
```

Marca con "CORREGIR" lo que dejaría expuesto al colegio (modo de pruebas
encendido, sin https, cookies sin proteger).

Riesgo conocido: las cuentas nuevas nacen con la misma contraseña inicial
(`COLEGIO_CLAVE_INICIAL`). Hasta que cada persona entra y crea la suya, quien
conozca su usuario podría entrar antes. La pantalla de Seguridad muestra cuántas
cuentas siguen así.

## Lo que solo puede hacer el colegio

El sistema está completo, pero estas cuatro cosas dependen de cuentas del colegio:

1. **Publicar el backend con https** (hosting con PHP 8.2+ y MySQL). Pasos en
   "Poner en producción". Sin esto la app solo funciona conectada por cable a la
   computadora de desarrollo.
2. **Pagos en línea**: en el panel, Integraciones → Pagos en línea, pegar el
   Access Token de la cuenta de Mercado Pago del colegio y activarlo. Necesita
   el punto 1 para que los pagos se confirmen solos.
3. **Notificaciones push**: crear un proyecto en Firebase, descargar la clave de
   la cuenta de servicio y poner su ruta en `FCM_CREDENCIALES` (ver "Poner en
   producción"). Sin esto los mensajes llegan igual, pero el apoderado los ve al
   abrir la app.
4. **Play Store**: crear la llave de firma (arriba) y la cuenta de desarrollador
   de Google Play.

Pendiente técnico: el sistema se desarrolló y probó sobre SQLite; falta correr
las pruebas una vez sobre MySQL, que es lo que se usa en producción.
