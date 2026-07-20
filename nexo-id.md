# Nexo ID — Cuenta única del ecosistema Nexo

> Documento de evaluación y diseño. Síntesis de la sesión del 19/07/2026.
> Estado: idea validada, pendiente de diseño fino y desarrollo.

## 1. Qué es y por qué

Servicio central de identidad (SSO) para todas las herramientas Nexo. Hoy cada tool (nexolinks, nexoagenda, y próximamente nexoevents y nexoshort) tiene su registro separado. Nexo ID unifica: **una sola cuenta, todas las tools**, manteniendo cada producto separado pero con conexión fácil — el ecosistema.

Beneficios concretos:

- El usuario se registra una vez y entra a todo (menos fricción = más adopción entre tools).
- NexoShort necesita registro obligatorio como barrera anti-abuso: con Nexo ID lo tiene gratis desde el día uno.
- Pieza de portafolio fuerte: "diseñé el SSO del ecosistema" vende como desarrollador.
- Refuerza la narrativa de suite/ecosistema de la marca.

## 2. La ventaja estructural actual

Todas las tools viven en subdominios de `alvarocdev.com`. Una cookie de sesión emitida para **`.alvarocdev.com`** (con el punto inicial) es visible desde todos los subdominios. Eso permite **SSO real sin OAuth ni infraestructura nueva**, en PHP + MySQL sobre el hosting compartido actual.

Consecuencia estratégica: esto es una razón más para quedarse en subdominios de alvarocdev.com por ahora. Si algún día todo migra a `nexotools.com`, el mismo mecanismo funciona entre sus subdominios; recién si hubiera tools en dominios *distintos* haría falta un flujo formal tipo OAuth.

## 3. Arquitectura

### Componentes

- **Servicio central** en un subdominio propio, p. ej. `id.alvarocdev.com` (o `cuenta.alvarocdev.com`): registro, login, logout, recuperación de contraseña, perfil básico. Es una tool más del ecosistema.
- **Tabla central de usuarios** en MySQL (una sola fuente de verdad).
- **Cada tool**: elimina su tabla de usuarios propia; guarda sus datos con `user_id` de Nexo ID como clave foránea. Los productos siguen separados en datos y lógica, conectados solo por identidad.

### Modelo de datos (mínimo)

```sql
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,          -- password_hash() de PHP (bcrypt/argon2id)
  display_name  VARCHAR(100) NULL,
  email_verified TINYINT(1) NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE sessions (
  token_hash  CHAR(64) PRIMARY KEY,             -- hash del token, nunca el token en claro
  user_id     INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL,
  expires_at  DATETIME NOT NULL,
  INDEX idx_user (user_id)
);
```

### Flujo de sesión

1. Login en `id.alvarocdev.com` → se crea sesión y se emite cookie para `.alvarocdev.com` (flags: `Secure`, `HttpOnly`, `SameSite=Lax`).
2. Cualquier tool lee la cookie y valida server-side contra la tabla `sessions` (misma DB o endpoint interno de verificación).
3. Usuario no logueado en una tool → redirect a `id.alvarocdev.com/login?next=<url>` y vuelta.
4. Logout central invalida la sesión para todo el ecosistema.

## 4. Migración desde los registros separados

El único trabajo con complejidad real. Hoy hay usuarios duplicados entre tools, vinculables por email:

1. **Inventario**: exportar usuarios de cada tool (email, hash, fecha de alta, tool de origen).
2. **Unificación por email**: mismo email en varias tools = una sola cuenta Nexo ID.
3. **Conflicto de contraseñas** (mismo email, contraseñas distintas): migrar las credenciales de la **cuenta más antigua**; en el primer login a la otra tool la cuenta ya queda vinculada por email. Si el hash antiguo no es compatible, forzar reset de contraseña por email en el primer ingreso ("mejoramos la seguridad de tu cuenta").
4. **Re-mapeo de FKs**: en cada tool, reemplazar el `user_id` local por el `user_id` de Nexo ID (tabla puente temporal `old_id → nexo_id` durante la transición).
5. **Transición**: mantener login viejo funcionando en paralelo un tiempo corto; al validar, migrar y redirigir al flujo nuevo.
6. Comunicar el cambio a los usuarios existentes (email simple).

## 5. Seguridad (mínimos no negociables)

- `password_hash()` / `password_verify()` de PHP (bcrypt o argon2id). Nunca MD5/SHA a mano.
- Tokens de sesión aleatorios (`random_bytes`), guardados hasheados.
- Rate limiting en login y recuperación (por cuenta y por IP).
- Cookies `Secure` + `HttpOnly` + `SameSite=Lax`; HTTPS en todo.
- Verificación de email al registrarse (evita cuentas basura — importante para NexoShort).
- Tokens de recuperación de un solo uso y con expiración corta.
- Considerar: aviso por email al cambiar contraseña.

## 6. Riesgos y consideraciones

- **SPOF**: si Nexo ID cae, cae el login de todo el ecosistema (no las sesiones ya activas si cada tool valida contra la DB directamente). Mitigación: es la misma infra compartida que ya corre todo, no agrega un punto de falla nuevo real.
- **Cookie en dominio padre**: cualquier subdominio de alvarocdev.com puede leer la cookie — aceptable porque todos los subdominios son propios; tenerlo presente si algún día se aloja algo de terceros bajo ese dominio.
- **Acoplamiento**: las tools quedan acopladas a Nexo ID para identidad. Es el trade-off buscado (ecosistema), pero conviene que cada tool degrade con gracia si el endpoint de verificación no responde.

## 7. Conexión con NexoShort y las demás tools

- **NexoShort nace como primer "cliente" de Nexo ID**: registro obligatorio para crear links (anti-abuso) sin construir auth propio. Es el orden más limpio si no hay apuro; la alternativa es que NexoShort arranque con login simple provisional y migre después.
- nexolinks / nexoagenda: migran según el plan de la sección 4.
- nexoevents: al no existir aún, nace directamente sobre Nexo ID.
- Futuro: una página de cuenta central que muestre "tus tools" — la cara visible del ecosistema.

## 8. Roadmap

1. Diseño fino: decidir subdominio (`id.` vs `cuenta.`), definir endpoint de verificación vs acceso directo a DB
2. Construir Nexo ID standalone (registro, login, recovery, verificación de email, sesiones)
3. Integrar NexoShort como primer cliente (si los tiempos calzan; si no, provisional y migra)
4. Migrar nexolinks (la tool con más usuarios primero o última, según tolerancia al riesgo — decidir)
5. Migrar nexoagenda
6. nexoevents nace integrado
7. Página de cuenta central / "mis tools"

## 9. Costos

**$0 nuevos.** Corre en el hosting compartido ya pago, mismo stack PHP + MySQL.
