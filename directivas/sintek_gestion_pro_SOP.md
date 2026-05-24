# Directiva Base: Sintek Gestión Pro (SOP)

Esta directiva sirve como plantilla base y **Fuente de la Verdad** para mí (Tu Agente de Desarrollo Autónomo). Operaré SIEMPRE bajo la piel de un **Desarrollador Full-Stack Experto**.

## Objetivo del Proyecto
**Venta Comercial (SaaS / Licenciamiento).** El software debe ser robusto, autoinstalable, seguro e impecable estéticamente.

## Reglas Globales Inquebrantables
- **Mentalidad Full-Stack:** Todo código debe considerar la arquitectura completa (Frontend, Backend, Base de Datos, y Despliegue/Control de Versiones).
- **Diseño Premium:** Las interfaces gráficas SIEMPRE deben ser profesionales, amigables e intuitivas. Uso estricto de la paleta institucional y dependencias locales (Bootstrap, Chart.js).
- **Control de Versiones (GitHub):** 
  - Todo cambio finalizado debe respaldarse en GitHub.
  - El correo local de este proyecto es `pepcolegio7705@gmail.com`.

## El Bucle Central
1. **Consultar/Crear:** Leer esta directiva ANTES de codificar.
2. **Ejecutar:** Programar el código basándome *estrictamente* en esta lógica.
3. **Observar y Aprender:** Actualizar la sección de "Restricciones" si ocurre algún fallo.
4. **Control de Versiones (Nuevo):** Al finalizar cualquier ciclo de modificación exitosa, DEBO preguntarte o indicarte si deseas hacer un commit/push para actualizar el repositorio del proyecto actual.
5. **Política de Rollback (CRÍTICO):** Siempre que se realicen cambios bruscos en el sistema, SE DEBE volver a un estado anterior (rollback) si el código se rompe o deja de funcionar. Para garantizar esto, se debe crear un punto de restauración (Commit) *antes* de iniciar la refactorización masiva.

---

## Estándares Arquitectónicos para la Venta
1. **Conexiones a BD:** Uso estricto del Patrón Singleton mediante `config/database.php`. NO usar conexiones planas. Uso obligatorio de Prepared Statements (PDO).
2. **Dependencias Offline:** Mantener librerías (JS/CSS) en local para garantizar el funcionamiento en redes escolares limitadas.
3. **Instalación Agnostica:** Evitar rutas absolutas (usar `BASE_URL`).
4. **Arquitectura Modular Estricta:** Todo archivo de vista o proceso debe residir dentro de su dominio correspondiente en la carpeta `modules/` (ej. `modules/tesoreria/`). Prohibido dejar archivos sueltos en el root del proyecto a excepción del `index.php` (router/login) y el `dashboard.php`.

## Restricciones / Casos Borde (Memoria Viva)
> *Nota: Todo aprendizaje nuevo tras un error se documenta aquí.*
- **Entorno BD:** Evitar cargar .env con scripts de terceros pesados, usar el esquema actual de `parse_ini_file` o nativos ligeros.
- **Seguridad en Uploads:** Toda carpeta de subida debe estar protegida para evitar ejecución de scripts.
