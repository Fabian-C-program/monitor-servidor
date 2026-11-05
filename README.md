## Preguntas de Análisis y Seguridad

### 1. ¿Por qué la temperatura suele aparecer como “No disponible” en Windows?
Por un tema de **permisos de seguridad** del propio sistema operativo. 

* **El bloqueo de Windows:** Para leer la temperatura, el código tiene que acceder a una zona profunda de la BIOS/UEFI (`root\wmi`). Windows bloquea este acceso a los usuarios comunes para que ningún programa rompa nada.
* **La prueba de Administrador:** Si abres la terminal como Administrador, el sistema te da los privilegios necesarios y el comando sí te devuelve el valor real. Además, dependes de que el fabricante de tu placa base sea compatible con las lecturas genéricas de Microsoft.

---

### 2. ¿Por qué el script de PowerShell nunca debe incluir datos que vengan del usuario?
Porque abrirías la puerta a un ataque gravísimo llamado **Inyección de Comandos**.

* Si dejas que un usuario escriba algo en un formulario y metes ese texto directamente en un comando `shell_exec()`, un atacante astuto podría usar comillas o puntos y comas (`;`) para cerrar tu comando original.
* Justo después, podría escribir código destructivo (como borrar carpetas o descargar malware), y el servidor lo ejecutaría con los mismos permisos que tiene tu aplicación de Laravel.

---

### 3. ¿Qué riesgo tiene dejar `/api/metrics` sin autenticación en un servidor real?
Básicamente, le estás regalando información a los atacantes y exponiendo el servidor a que lo tumben (**Ataques DoS**).

* **Información pública:** Cualquier persona en internet podría ver la versión exacta de tu sistema operativo y de PHP. Si tienes algo desactualizado, un hacker sabrá exactamente qué vulnerabilidad usar en tu contra.
* **Saturación del servidor:** Ejecutar comandos de consola como `top` o scripts de PowerShell consume recursos reales. Si un robot empieza a hacer miles de peticiones por segundo a tu API, tu procesador se va a saturar al 100% y la página web se caerá.

---

### 4. Con polling cada 5 segundos, ¿cuántos pedidos por minuto recibe el servidor si hay 50 personas mirando?
El servidor recibiría exactamente **600 peticiones por minuto**.

La cuenta es muy sencilla:
* Cada usuario hace 12 peticiones al minuto (60 segundos / 5 segundos de intervalo).
* Si multiplicas esas 12 peticiones por las 50 personas conectadas al mismo tiempo, te da un total de **600 visitas por minuto** saturando la API.

---

### 5. Si una app solo necesita la temperatura, ¿qué datos recibe de más al llamar a `/api/metrics`?
Recibe absolutamente todo lo demás. Estás haciendo lo que en desarrollo llamamos **Over-fetching** (traer datos de sobra).

A la app le llega una tonelada de información que no va a usar para nada:
* El nombre del equipo y la versión del sistema operativo.
* La versión de PHP.
* Todos los datos detallados del disco duro (total, libre, porcentaje).
* Todos los datos detallados de la memoria RAM (total, disponible, porcentaje).
* El uso actual de la CPU y los segundos que lleva el equipo encendido.
