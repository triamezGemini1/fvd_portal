# FVD Portal

Aplicación **independiente** de la carpeta `crudmysql`: landing pública, FVD Master Admin y recursos asociados bajo `C:\wamp64\www\fvd_portal`.

## Requisitos

- PHP 8.x, Apache con `mod_rewrite` (WAMP).
- MySQL/MariaDB con la base usada por la FVD (p. ej. `fvdmasteradmin`).

## Configuración

1. **Virtual host o alias** apuntando a esta carpeta, o acceso directo `http://localhost/fvd_portal/`.
2. Archivo **`.env`** en la raíz (ya creado). Clave obligatoria: **`APP_BASE_PATH=/fvd_portal`** (o la ruta URL real si usa subcarpeta distinta).
3. Asegure permisos de escritura en **`uploads/`** y **`crud_atletas/uploads/`** si usa fotos de atletas y archivos de torneos.

## Estructura relevante

| Ruta | Uso |
|------|-----|
| `index.php` | Landing (regla cromática 80/15/5, estilo MisTorneos) |
| `fvdmasteradmin/` | Panel y login (`fvd_usuarios`) |
| `assets/css/fvd-ui-mistorneos.css` | Estilos compartidos |
| `config/paths.php`, `config/env.php` | Rutas y variables de entorno |
| `models/` | Modelos PHP copiados del proyecto origen (referencia / futuros usos) |

## Estética

No se modificaron las clases ni variables CSS de la landing ni del dashboard: se conservan **`fvd-ui-mistorneos.css`**, **`ui_settings.php`** y el layout público (`includes/public_header.php`).

## Migración desde `crudmysql`

Este árbol se generó copiando `fvdmasteradmin`, `config`, `includes`, `assets`, `models`, `uploads`, `crud_atletas` y las páginas públicas enlazadas desde la landing. Las rutas internas usan **`FVD_PROJECT_ROOT`** = directorio padre de `fvdmasteradmin` (esta raíz).
