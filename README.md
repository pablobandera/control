# AppJuan (control) — gestión de flota de taxis/remises

App real (no prototipo) para gestionar una flota: turnos, viajes, gastos, cuenta
corriente, service y reportes. Dos roles: **dueño** y **chofer**.

- **Frontend**: HTML + CSS + JavaScript puro, sin frameworks ni build step. Por
  ahora solo la versión móvil (`frontend/`).
- **Backend**: PHP puro (8.1+), sin frameworks, arquitectura modular
  (Config / Core / Models / Services / Controllers) con un router propio y una
  API JSON bajo `/...` (ver `backend/src/routes.php`).
- **Base de datos**: MySQL (InnoDB, utf8mb4).

La carpeta `Demo/` con los prototipos HTML originales se conserva como
referencia visual, pero ya no se usa: la app real vive en `frontend/` y `backend/`.

## Estructura

```
backend/
  public/     <- document root del backend (index.php, .htaccess, uploads/, install.php)
  src/        <- código PHP (fuera del document root en un deploy ideal)
  .env.example
database/
  schema.sql  <- crea todas las tablas + catálogos (Taxi/Uber, Efectivo/Transferencia)
frontend/
  index.html, css/, js/   <- la app móvil, estática
```

## 1. Configurar la base de datos

1. Creá una base de datos MySQL vacía (collation `utf8mb4_unicode_ci`).
2. Copiá `backend/.env.example` a `backend/.env` y completá `DB_HOST`,
   `DB_NAME`, `DB_USER`, `DB_PASS`.
3. Con el backend ya desplegado (ver paso 2), visitá una sola vez
   `https://tu-dominio/.../install.php` desde el navegador (o corré
   `php backend/public/install.php` por consola). Esto:
   - crea todas las tablas a partir de `database/schema.sql`,
   - crea el usuario administrador (dueño): **usuario `usuario`, contraseña `123456`** (con hash bcrypt real, generado ahí mismo con PHP).
4. **Importante**: después de instalar, entrá a la app y cambiá esa
   contraseña, y **borrá `install.php` del servidor** (si alguien más lo
   visita antes de que lo borres, no puede hacer nada porque el instalador se
   niega a correr una segunda vez, pero igual es buena práctica sacarlo).

Si preferís importar el esquema a mano por phpMyAdmin en vez de usar
`install.php`, podés importar `database/schema.sql` directamente — en ese
caso vas a necesitar igual correr `install.php` (o insertar el usuario dueño
vos mismo con un hash de `password_hash()`) para poder loguearte.

## 2. Desplegar el backend (PHP)

El backend es una API JSON pura. El **document root** de PHP tiene que
apuntar a `backend/public` (no a `backend/` ni a la raíz del proyecto).

- **Opción recomendada (subdominio propio)**: creá `api.tudominio.com` con
  document root en `backend/public`. Subí toda la carpeta `backend/` (con
  `src/` y `public/`) a tu hosting, donde sea, y apuntá el subdominio a
  `backend/public`.
- **Opción sin subdominio (un solo hosting)**: subí `backend/` completo a tu
  cuenta (por ejemplo, un nivel arriba de `public_html`), y desde
  `public_html` hacé que `/api` apunte a `backend/public` (symlink, alias de
  Apache, o copiando el contenido de `backend/public/` a `public_html/api/`
  y `backend/src/` a algún lado accesible por PHP pero no por HTTP). Los
  archivos `.htaccess` de `backend/src/` y `database/` ya bloquean el acceso
  HTTP directo como defensa extra.

El único requisito real del servidor es **PHP 8.1+ con las extensiones PDO,
pdo_mysql y fileinfo** (todas estándar en cualquier hosting PHP moderno).

## 3. Desplegar el frontend

Es HTML/CSS/JS estático: subí el contenido de `frontend/` a donde sirvas tu
sitio (por ejemplo `public_html/`).

Después editá **`frontend/js/config.js`**:

```js
const CONFIG = {
  API_BASE: '/api', // o 'https://api.tudominio.com' si usás un subdominio
};
```

Si el frontend y la API quedan en el **mismo dominio** (recomendado, evita
lidiar con CORS y cookies cross-site), dejá `API_BASE: '/api'` y asegurate de
que `/api` resuelva al `backend/public` del paso anterior.

Si quedan en **dominios distintos**, además completá `FRONTEND_ORIGIN` en
`backend/.env` con el origen exacto del frontend (por ejemplo
`https://www.tudominio.com`), para que el backend permita las cookies de
sesión entre dominios (CORS + `credentials: include`).

## 4. Probar en local (sin hosting)

```bash
# Terminal 1: backend
php -S localhost:8000 -t backend/public

# Terminal 2: frontend (cualquier servidor estático sirve)
php -S localhost:5500 -t frontend
```

Con `backend/.env` apuntando a tu MySQL local, corré el instalador una vez:

```bash
php backend/public/install.php
```

Y en `frontend/js/config.js` usá `API_BASE: 'http://localhost:8000'` mientras
probás en local (junto con `FRONTEND_ORIGIN=http://localhost:5500` en
`backend/.env`). Después entrá a `http://localhost:5500` y logueate con
`usuario` / `123456`.

## Modelo de datos y reglas de negocio

- Dos roles: **dueño** (ve toda la flota, reportes, da de alta/baja
  choferes) y **chofer** (carga su turno: viajes, gastos, cuenta corriente,
  service).
- Un chofer solo puede tener **un turno activo a la vez**.
- La liquidación de cada turno se calcula siempre por agregación SQL directa
  (nunca por acumuladores en el cliente), así nunca queda desincronizada:

  ```
  ingreso_efvo   = suma de (monto - descuento) de viajes en efectivo
  ingreso_transf = suma de (monto - descuento) de viajes por transferencia
  cc_total       = suma de (monto - descuento) de cuenta corriente (entra en la
                   base de la comisión aunque todavía no esté cobrada)
  total_bruto    = ingreso_efvo + ingreso_transf + cc_total
  comisión       = total_bruto × (% de comisión del chofer, o el de la flota si no tiene uno propio)
  a_rendir       = total_bruto − comisión − gastos
  ```
- El % de comisión es configurable por flota (Perfil → Editar datos de la
  flota) y puede sobreescribirse por chofer individual.
- Los gastos con categoría "Combustible" alimentan el KPI de combustible en
  Reportes (a diferencia del prototipo original, acá es un dato real, no
  aleatorio).
- Las contraseñas se guardan siempre con `password_hash()` (bcrypt), nunca en
  texto plano.
