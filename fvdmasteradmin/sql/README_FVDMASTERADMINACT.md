# Volcado `fvdmasteradminact` y esquema FVD

## Archivo de referencia

Puede colocar el export phpMyAdmin en **`fvdmasteradmin/sql/reference_fvdmasteradminact.sql`** (mismo contenido que `Descargas/fvdmasteradminact.sql`). Ese archivo grande (~6 MB) está en **`.gitignore`** para no versionar el volcado completo; en el repositorio solo quedan los scripts pequeños anteriores.

Úselo localmente para restaurar datos o comparar estructuras.

## Problema detectado en el volcado

Entre las tablas `roles_permissions` y `club_photos`, el comentario `-- Estructura de tabla para la tabla torneosact` aparece **sin el `CREATE TABLE`**: la definición de `torneosact` falta o está truncada. El código de la aplicación depende de `torneosact` con clave primaria **`torneo`**.

**Solución en este repo:** ejecutar primero `install_torneosact.sql` en bases nuevas o vacías.

## Scripts añadidos (lo que faltaba respecto al código PHP)

| Script | Propósito |
|--------|-----------|
| `install_torneosact.sql` | Crea `torneosact` alineada con `TorneosController` y módulos relacionados. |
| `install_inscripcion_torneo.sql` | Crea `inscripcion_torneo` como en el volcado (listado en Master Admin). |
| `alter_asociaciones_extend.sql` | Añade `providencia`, `indica`, `fechreg`, `fechprovi`, `ultelECC` si no existen. |

## Tablas del volcado no integradas en PHP (referencia solamente)

Incluyen entre otras: `access_levels`, `histcatlib` (ranking histórico), `permissions`/`roles` (MyISAM), `tournaments` (modelo alternativo), `usuarios` (login legado distinto de `fvd_usuarios`), `clubes`, `equipos`, `solicitudes_afiliacion`, etc. Pueden convivir en la misma BD; la aplicación actual no las usa de forma unificada.

## Orden sugerido en instalación limpia

1. Tablas núcleo del volcado o datos de referencia que necesite.
2. `install_torneosact.sql`
3. `install_fvd_usuarios.sql` + alters de perfil / `atleta_id`
4. `install_inscripcion_torneo.sql`
5. `alter_asociaciones_extend.sql` (si la tabla `asociaciones` ya existe)
