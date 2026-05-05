<?php
/**
 * Autenticación y contexto de sesión para FVD Master Admin.
 *
 * Modelo previsto para gestión deportiva/administrativa:
 * - Rol federación: {@see ROLE_FVD_ADMIN} — administrador general (cuenta en `fvd_usuarios`).
 * - Rol club: {@see ROLE_DELEGADO_ASOC} — delegado (cuenta en tabla `delegados`, un vínculo por asociación).
 *
 * Otros valores en `fvd_usuarios.rol` se mantienen por compatibilidad con datos y código existente:
 * - {@see ROLE_ASO_ADMIN} — administrador de club vía `fvd_usuarios`; si la política es solo delegado,
 *   conviene migrar esos usuarios a filas en `delegados` y dejar de usar este rol.
 * - {@see ROLE_USUARIO} — acceso ligero (p. ej. portal atleta con `atleta_id`); no es gestión de club.
 */

declare(strict_types=1);

if (!function_exists('env')) {
    $envBootstrap = dirname(__DIR__, 2) . '/config/env.php';
    if (is_file($envBootstrap)) {
        require_once $envBootstrap;
    }
}

require_once dirname(__DIR__) . '/config/db.php';

class AuthService
{
    /** Super administrador FVD (mismo valor que {@see ROLE_FVD_ADMIN}). */
    public const ROLE_SUPER_ADMIN = 'fvd_admin';

    public const ROLE_FVD_ADMIN = 'fvd_admin';

    public const ROLE_ASO_ADMIN = 'aso_admin';

    /** Delegado con cuenta en tabla `delegados` (un registro por asociación). */
    public const ROLE_DELEGADO_ASOC = 'delegado_asoc';

    public const ROLE_USUARIO = 'usuario';

    private const SESSION_KEY = 'fvd_master_user';

    /** Contexto opcional: delegado gestiona solo este torneo (panel evento). */
    private const SESSION_DELEGADO_TORNEO_CTX = 'fvd_delegado_torneo_context_id';

    /**
     * Prefijo web del proyecto (p. ej. /fvd_portal), alineado con {@see BASE_URL} en config/paths.php.
     * No usar env('APP_BASE_PATH','') con default vacío: rompe instalaciones en subcarpeta (login → URL mal formada, página en blanco).
     */
    private static function appWebBase(): string
    {
        $root = dirname(__DIR__, 2);
        if (!defined('BASE_URL')) {
            require_once $root . '/config/paths.php';
        }

        return rtrim((string) BASE_URL, '/');
    }

    /** Grupo de evento (campeonato vinculado: M/F/juvenil) para inscripciones y reportes del delegado. */
    private const SESSION_DELEGADO_CAMPEONATO_GRUPO = 'fvd_delegado_campeonato_grupo_id';

    /** Asociación elegida en el portal asociación (admin FVD en vista delegado). */
    private const SESSION_ADMIN_PORTAL_DELEGADO_ASOC = 'fvd_admin_portal_delegado_asoc_id';

    /** Ruta interna canónica del panel delegado (volver sin encadenar `ret` en cada enlace). */
    private const SESSION_DELEGADO_PANEL_HOME = 'fvd_delegado_panel_home_url';

    /** @var string Código interno del último fallo (solo para depuración con APP_DEBUG). */
    private static $lastLoginFailure = '';

    /** @var string Mensaje de PDO si el fallo fue db_error (para APP_DEBUG / logs). */
    private static $lastPdoErrorDetail = '';

    public static function getLastLoginFailure(): string
    {
        return self::$lastLoginFailure;
    }

    public static function getLastPdoErrorDetail(): string
    {
        return self::$lastPdoErrorDetail;
    }

    private static function setLoginFailure(string $code): void
    {
        self::$lastLoginFailure = $code;
    }

    /**
     * Roles que pueden aparecer en sesión o en BD (compatibilidad).
     *
     * @var list<string>
     */
    public const ROLES = [
        self::ROLE_FVD_ADMIN,
        self::ROLE_ASO_ADMIN,
        self::ROLE_DELEGADO_ASOC,
        self::ROLE_USUARIO,
    ];

    /**
     * Modelo reducido: solo administración general FVD + delegado de club (objetivo de despliegue).
     *
     * @var list<string>
     */
    public const ROLES_MODELO_PRINCIPAL = [
        self::ROLE_FVD_ADMIN,
        self::ROLE_DELEGADO_ASOC,
    ];

    public static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $base = self::appWebBase();
            $path = $base !== '' ? ($base . '/') : '/';

            /* Cookie Secure solo con HTTPS; en http://localhost WAMP la sesión no persiste si Secure queda activo (p. ej. php.ini / .htaccess). */
            $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            if (function_exists('env')) {
                $es = env('SESSION_SECURE', null);
                if ($es !== null && $es !== '') {
                    $secure = filter_var((string) $es, FILTER_VALIDATE_BOOLEAN);
                }
            }
            if (!headers_sent()) {
                if (!$secure) {
                    ini_set('session.cookie_secure', '0');
                }

                if (PHP_VERSION_ID >= 70300) {
                    session_set_cookie_params([
                        'lifetime' => 0,
                        'path'     => $path,
                        'secure'   => $secure,
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                } else {
                    session_set_cookie_params(0, $path, '', $secure, true);
                }
            }
            session_start();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        self::ensureSession();
        $u = $_SESSION[self::SESSION_KEY] ?? null;

        return is_array($u) ? $u : null;
    }

    public static function isAuthenticated(): bool
    {
        return self::user() !== null;
    }

    public static function userId(): ?int
    {
        $u = self::user();
        if ($u === null || !isset($u['id'])) {
            return null;
        }

        return (int) $u['id'];
    }

    public static function role(): ?string
    {
        $u = self::user();

        return isset($u['rol']) ? (string) $u['rol'] : null;
    }

    /**
     * Origen de la sesión: usuario maestro o fila `delegados`.
     */
    public static function authSource(): string
    {
        $u = self::user();
        if ($u === null) {
            return '';
        }

        return isset($u['auth_source']) ? (string) $u['auth_source'] : 'fvd_usuario';
    }

    public static function isSuperAdmin(): bool
    {
        return self::role() === self::ROLE_FVD_ADMIN;
    }

    /** Administrador general de la federación (sinónimo claro de {@see isSuperAdmin}). */
    public static function isAdministradorGeneral(): bool
    {
        return self::isSuperAdmin();
    }

    /** Alias breve de {@see isAdministradorGeneral()} (panel maestro / rutas). */
    public static function isAdminGral(): bool
    {
        return self::isAdministradorGeneral();
    }

    /**
     * Quita variables de sesión propias del flujo delegado (p. ej. tras login como admin general).
     */
    public static function clearDelegadoPanelSessionKeys(): void
    {
        self::ensureSession();
        unset($_SESSION['fvd_master_delegado_notif_token'], $_SESSION[self::SESSION_DELEGADO_CAMPEONATO_GRUPO]);
        unset($_SESSION[self::SESSION_DELEGADO_TORNEO_CTX], $_SESSION[self::SESSION_DELEGADO_PANEL_HOME]);
    }

    /**
     * Fija la URL de retorno al panel delegado (ruta interna, p. ej. /fvd_portal/fvdmasteradmin/delegado_dashboard_new.php).
     * Solo aplica a delegado o admin general en modo portal-asociación.
     */
    public static function setDelegadoPanelHomeUrl(string $urlOrPath): void
    {
        self::ensureSession();
        $portalAsoc = self::adminPortalDelegadoAsociacionId();
        $pOk = $portalAsoc !== null && (int) $portalAsoc > 0;
        if (!self::isDelegadoAsociacion() && !(self::isSuperAdmin() && $pOk)) {
            return;
        }
        if (!function_exists('fvd_return_sanitize')) {
            $nav = dirname(__DIR__, 2) . '/config/fvd_navigation_return.php';
            if (is_file($nav)) {
                require_once $nav;
            }
        }
        $trim = trim($urlOrPath);
        if ($trim === '') {
            unset($_SESSION[self::SESSION_DELEGADO_PANEL_HOME]);

            return;
        }
        if (preg_match('#^https?://#i', $trim) === 1) {
            $p = parse_url($trim);
            if (is_array($p) && isset($p['path'])) {
                $trim = (string) $p['path']
                    . (isset($p['query']) && (string) $p['query'] !== '' ? '?' . $p['query'] : '');
            }
        }
        if (!function_exists('fvd_return_sanitize')) {
            return;
        }
        $san = fvd_return_sanitize($trim);
        if ($san !== null && $san !== '') {
            $_SESSION[self::SESSION_DELEGADO_PANEL_HOME] = $san;
        }
    }

    /**
     * Ruta del panel delegado guardada en sesión (para barra «Volver» sin parámetro ret).
     */
    public static function delegadoPanelHomeUrl(): ?string
    {
        self::ensureSession();
        if (!isset($_SESSION[self::SESSION_DELEGADO_PANEL_HOME])) {
            return null;
        }
        $v = $_SESSION[self::SESSION_DELEGADO_PANEL_HOME];
        if (!is_string($v) || $v === '') {
            return null;
        }
        if (!function_exists('fvd_return_sanitize')) {
            $nav = dirname(__DIR__, 2) . '/config/fvd_navigation_return.php';
            if (is_file($nav)) {
                require_once $nav;
            }
        }

        return function_exists('fvd_return_sanitize') ? fvd_return_sanitize($v) : $v;
    }

    public static function isDelegadoAsociacion(): bool
    {
        return self::role() === self::ROLE_DELEGADO_ASOC;
    }

    /**
     * Puede actuar en nombre de una asociación (delegado, aso_admin o super admin).
     */
    public static function canManageAsociacion(int $asociacionId): bool
    {
        if ($asociacionId <= 0) {
            return false;
        }
        if (self::isSuperAdmin()) {
            return true;
        }
        $mine = self::idAsociacion();

        return $mine !== null && (int) $mine === $asociacionId;
    }

    /**
     * id_asociacion en sesión (puede ser null para fvd_admin).
     */
    public static function idAsociacion(): ?int
    {
        $u = self::user();
        if ($u === null || !array_key_exists('id_asociacion', $u)) {
            return null;
        }
        if ($u['id_asociacion'] === null || $u['id_asociacion'] === '') {
            return null;
        }

        return (int) $u['id_asociacion'];
    }

    /**
     * Asociación en la que el administrador FVD está «actuando como delegado» (portal asociación).
     */
    public static function adminPortalDelegadoAsociacionId(): ?int
    {
        self::ensureSession();
        if (!self::isSuperAdmin()) {
            return null;
        }
        if (!isset($_SESSION[self::SESSION_ADMIN_PORTAL_DELEGADO_ASOC])) {
            return null;
        }
        $v = (int) $_SESSION[self::SESSION_ADMIN_PORTAL_DELEGADO_ASOC];

        return $v > 0 ? $v : null;
    }

    public static function setAdminPortalDelegadoAsociacionId(int $asociacionId): void
    {
        self::ensureSession();
        if (!self::isSuperAdmin() || $asociacionId <= 0) {
            return;
        }
        $_SESSION[self::SESSION_ADMIN_PORTAL_DELEGADO_ASOC] = $asociacionId;
    }

    public static function clearAdminPortalDelegadoAsociacionId(): void
    {
        self::ensureSession();
        unset($_SESSION[self::SESSION_ADMIN_PORTAL_DELEGADO_ASOC]);
    }

    /**
     * Deja de arrastrar el modo «portal asociación» (admin FVD actuando como club) y el «home» delegado en sesión.
     * No toca fvd_master_delegado_notif_token (otro flujo de pruebas en master_panel).
     * Para un admin en sesión, limpia también claves de contexto de delegado que no deberían aplicarle aunque existan por datos viejos.
     */
    public static function clearAdminPortalDelegadoContext(): void
    {
        self::ensureSession();
        if (!self::isSuperAdmin()) {
            return;
        }
        self::clearAdminPortalDelegadoAsociacionId();
        unset($_SESSION[self::SESSION_DELEGADO_PANEL_HOME]);
        unset(
            $_SESSION[self::SESSION_DELEGADO_TORNEO_CTX],
            $_SESSION[self::SESSION_DELEGADO_CAMPEONATO_GRUPO]
        );
    }

    /**
     * Torneo activo en modo administración restringida (solo rol delegado).
     */
    public static function delegadoTorneoContextId(): ?int
    {
        self::ensureSession();
        if (!self::isDelegadoAsociacion()) {
            return null;
        }
        if (!isset($_SESSION[self::SESSION_DELEGADO_TORNEO_CTX])) {
            return null;
        }
        $v = $_SESSION[self::SESSION_DELEGADO_TORNEO_CTX];
        if ($v === null || $v === '') {
            return null;
        }
        $n = (int) $v;

        return $n > 0 ? $n : null;
    }

    public static function setDelegadoTorneoContext(?int $torneoId): void
    {
        self::ensureSession();
        if (!self::isDelegadoAsociacion()) {
            return;
        }
        if ($torneoId === null || $torneoId <= 0) {
            unset($_SESSION[self::SESSION_DELEGADO_TORNEO_CTX]);

            return;
        }
        $_SESSION[self::SESSION_DELEGADO_TORNEO_CTX] = $torneoId;
    }

    public static function clearDelegadoTorneoContext(): void
    {
        self::ensureSession();
        unset($_SESSION[self::SESSION_DELEGADO_TORNEO_CTX]);
    }

    /**
     * ID de grupo de campeonato (grupo_evento_id) en flujos de delegado (inscripciones / reportes).
     */
    public static function delegadoCampeonatoGrupoId(): ?int
    {
        self::ensureSession();
        if (!self::isDelegadoAsociacion()) {
            return null;
        }
        if (!isset($_SESSION[self::SESSION_DELEGADO_CAMPEONATO_GRUPO])) {
            return null;
        }
        $v = $_SESSION[self::SESSION_DELEGADO_CAMPEONATO_GRUPO];
        if ($v === null || $v === '') {
            return null;
        }
        $n = (int) $v;

        return $n > 0 ? $n : null;
    }

    public static function setDelegadoCampeonatoGrupo(?int $grupoEventoId): void
    {
        self::ensureSession();
        if (!self::isDelegadoAsociacion()) {
            return;
        }
        if ($grupoEventoId === null || $grupoEventoId <= 0) {
            unset($_SESSION[self::SESSION_DELEGADO_CAMPEONATO_GRUPO]);

            return;
        }
        $_SESSION[self::SESSION_DELEGADO_CAMPEONATO_GRUPO] = $grupoEventoId;
    }

    public static function clearDelegadoCampeonatoGrupo(): void
    {
        self::ensureSession();
        unset($_SESSION[self::SESSION_DELEGADO_CAMPEONATO_GRUPO]);
    }

    /**
     * atletas.id cuando la cuenta es portal de atleta (columna atleta_id en fvd_usuarios).
     */
    public static function atletaId(): ?int
    {
        $u = self::user();
        if ($u === null || !array_key_exists('atleta_id', $u)) {
            return null;
        }
        if ($u['atleta_id'] === null || $u['atleta_id'] === '') {
            return null;
        }

        return (int) $u['atleta_id'];
    }

    /**
     * Usuario con rol «usuario» vinculado a ficha de atleta (panel reducido + mi ficha).
     */
    public static function isAthletePortalUser(): bool
    {
        if (self::role() !== self::ROLE_USUARIO) {
            return false;
        }
        $aid = self::atletaId();

        return $aid !== null && $aid > 0;
    }

    /**
     * Comprueba si el rol actual está entre los permitidos.
     *
     * @param list<string> $allowedRoles ej: ['fvd_admin', 'aso_admin']
     */
    public static function checkAccess(array $allowedRoles): bool
    {
        $r = self::role();
        if ($r === null) {
            return false;
        }

        return in_array($r, $allowedRoles, true);
    }

    /**
     * Redirige al login si no hay sesión.
     */
    public static function requireLogin(): void
    {
        self::ensureSession();
        if (!self::isAuthenticated()) {
            self::redirectToLogin();
        }
    }

    /**
     * Si no tiene ninguno de los roles, responde 403 y termina.
     *
     * @param list<string> $allowedRoles
     */
    public static function requireRoles(array $allowedRoles): void
    {
        self::requireLogin();
        if (!self::checkAccess($allowedRoles)) {
            http_response_code(403);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso denegado</title></head><body><p>No tiene permisos para ver esta sección.</p></body></html>';
            exit;
        }
    }

    /**
     * @param array<string, mixed> $row Fila de fvd_usuarios
     */
    public static function loginFromRow(array $row): void
    {
        self::ensureSession();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        unset($_SESSION[self::SESSION_DELEGADO_TORNEO_CTX], $_SESSION[self::SESSION_ADMIN_PORTAL_DELEGADO_ASOC]);
        $_SESSION[self::SESSION_KEY] = [
            'auth_source'    => 'fvd_usuario',
            'id'             => (int) $row['id'],
            'email'          => (string) $row['email'],
            'nombre'         => isset($row['nombre']) ? (string) $row['nombre'] : '',
            'rol'            => (string) $row['rol'],
            'id_asociacion'  => isset($row['id_asociacion']) && $row['id_asociacion'] !== null && $row['id_asociacion'] !== ''
                ? (int) $row['id_asociacion']
                : null,
            'atleta_id'      => isset($row['atleta_id']) && $row['atleta_id'] !== null && $row['atleta_id'] !== ''
                ? (int) $row['atleta_id']
                : null,
        ];
    }

    /**
     * @param array<string, mixed> $row Fila de `delegados`
     */
    public static function loginFromDelegado(array $row): void
    {
        self::ensureSession();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        unset($_SESSION[self::SESSION_DELEGADO_TORNEO_CTX], $_SESSION[self::SESSION_ADMIN_PORTAL_DELEGADO_ASOC]);
        $_SESSION[self::SESSION_KEY] = [
            'auth_source'    => 'delegado',
            'id'             => (int) $row['id'],
            'email'          => (string) $row['email_acceso'],
            'nombre'         => (string) ($row['nombre_contacto'] ?? ''),
            'rol'            => self::ROLE_DELEGADO_ASOC,
            'id_asociacion'  => (int) $row['asociacion_id'],
            'atleta_id'      => null,
        ];
        $root = dirname(__DIR__, 2);
        if (!function_exists('url')) {
            require_once $root . '/config/paths.php';
        }
        self::setDelegadoPanelHomeUrl(url('fvdmasteradmin/delegado_dashboard_new.php'));
    }

    public static function logout(): void
    {
        self::ensureSession();
        unset(
            $_SESSION[self::SESSION_KEY],
            $_SESSION[self::SESSION_ADMIN_PORTAL_DELEGADO_ASOC],
            $_SESSION[self::SESSION_DELEGADO_PANEL_HOME]
        );
    }

    /**
     * Intenta autenticar: primero `fvd_usuarios`, luego tabla `delegados`.
     *
     * Si el correo existe en `fvd_usuarios` pero la contraseña no coincide, aun así se intenta
     * `delegados` (mismo email). Así un delegado no queda bloqueado por una fila residual o de
     * prueba en `fvd_usuarios` con otra clave.
     */
    public static function attemptLogin(string $email, string $password): bool
    {
        self::$lastLoginFailure = '';
        self::$lastPdoErrorDetail = '';
        $email = strtolower(trim($email));
        if ($email === '' || $password === '') {
            self::setLoginFailure('empty_input');

            return false;
        }

        if (self::attemptFvdUsuarioLogin($email, $password)) {
            return true;
        }

        $failFvd = self::getLastLoginFailure();
        if ($failFvd === 'db_error' || $failFvd === 'user_inactive') {
            return false;
        }

        if (self::attemptDelegadoLogin($email, $password)) {
            return true;
        }

        $failDel = self::getLastLoginFailure();
        if ($failFvd === 'bad_password' && $failDel === 'user_not_found') {
            self::setLoginFailure('bad_password');
        }

        return false;
    }

    private static function attemptFvdUsuarioLogin(string $emailNorm, string $password): bool
    {
        try {
            $pdo = fvd_db();
            $stmt = $pdo->prepare(
                'SELECT id, email, password_hash, nombre, rol, id_asociacion, atleta_id, activo
                 FROM fvd_usuarios WHERE LOWER(TRIM(email)) = :e LIMIT 1'
            );
            $stmt->execute([':e' => $emailNorm]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[fvdmasteradmin/AuthService] ' . $e->getMessage());
            self::$lastPdoErrorDetail = $e->getMessage();
            self::setLoginFailure('db_error');

            return false;
        }

        if (!$row) {
            self::setLoginFailure('user_not_found');

            return false;
        }

        if (!(int) ($row['activo'] ?? 0)) {
            self::setLoginFailure('user_inactive');

            return false;
        }

        $hash = trim((string) ($row['password_hash'] ?? ''));
        if ($hash === '' || !password_verify($password, $hash)) {
            self::setLoginFailure('bad_password');

            return false;
        }

        self::loginFromRow($row);

        return true;
    }

    private static function attemptDelegadoLogin(string $emailNorm, string $password): bool
    {
        try {
            $pdo = fvd_db();
            $chk = $pdo->query("SHOW TABLES LIKE 'delegados'");
            if ($chk === false || $chk->fetchColumn() === false) {
                self::setLoginFailure('user_not_found');

                return false;
            }
            $stmt = $pdo->prepare(
                'SELECT id, asociacion_id, email_acceso, password_hash, nombre_contacto, activo
                 FROM delegados WHERE LOWER(TRIM(email_acceso)) = :e LIMIT 1'
            );
            $stmt->execute([':e' => $emailNorm]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[fvdmasteradmin/AuthService delegado] ' . $e->getMessage());
            self::$lastPdoErrorDetail = $e->getMessage();
            self::setLoginFailure('db_error');

            return false;
        }

        if (!$row) {
            self::setLoginFailure('user_not_found');

            return false;
        }

        if (!(int) ($row['activo'] ?? 0)) {
            self::setLoginFailure('user_inactive');

            return false;
        }

        $hash = trim((string) ($row['password_hash'] ?? ''));
        if ($hash === '' || !password_verify($password, $hash)) {
            self::setLoginFailure('bad_password');

            return false;
        }

        try {
            $pdo = fvd_db();
            $up = $pdo->prepare('UPDATE delegados SET ultimo_acceso = NOW() WHERE id = :id');
            $up->execute([':id' => (int) $row['id']]);
        } catch (PDOException $e) {
            error_log('[fvdmasteradmin/AuthService delegado ultimo_acceso] ' . $e->getMessage());
        }

        self::loginFromDelegado($row);

        return true;
    }

    /**
     * Actualiza la sesión desde la BD (tras editar perfil u otros cambios).
     */
    public static function reloadSessionFromDatabase(): void
    {
        $id = self::userId();
        if ($id === null) {
            return;
        }
        try {
            $pdo = fvd_db();
            if (self::authSource() === 'delegado') {
                $ctxTorneo = self::delegadoTorneoContextId();
                $stmt = $pdo->prepare(
                    'SELECT id, asociacion_id, email_acceso, password_hash, nombre_contacto, activo
                     FROM delegados WHERE id = :id LIMIT 1'
                );
                $stmt->execute([':id' => $id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row || !(int) ($row['activo'] ?? 0)) {
                    self::logout();
                    self::redirectToLogin();

                    return;
                }
                self::loginFromDelegado($row);
                if ($ctxTorneo !== null && $ctxTorneo > 0) {
                    self::setDelegadoTorneoContext($ctxTorneo);
                }

                return;
            }

            $stmt = $pdo->prepare(
                'SELECT id, email, password_hash, nombre, rol, id_asociacion, atleta_id, activo
                 FROM fvd_usuarios WHERE id = :id LIMIT 1'
            );
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[fvdmasteradmin/AuthService::reloadSessionFromDatabase] ' . $e->getMessage());

            return;
        }
        if (!$row || !(int) ($row['activo'] ?? 0)) {
            self::logout();
            self::redirectToLogin();

            return;
        }
        self::loginFromRow($row);
    }

    public static function loginUrl(): string
    {
        $root = dirname(__DIR__, 2);
        if (!function_exists('url')) {
            require_once $root . '/config/paths.php';
        }

        return url('login.php');
    }

    /**
     * Tras login exitoso: admin general siempre al Panel Maestro; no se respeta return_to hacia /admin/modules/.
     *
     * @param string|null $fromSession Valor previo de $_SESSION['fvd_redirect_after_login']
     */
    public static function safeRedirectAfterLogin(?string $fromSession): string
    {
        $base = self::appWebBase();
        $master = $base . '/fvdmasteradmin/master_panel.php';
        if (self::role() === self::ROLE_FVD_ADMIN) {
            return $master;
        }
        $s = is_string($fromSession) ? trim($fromSession) : '';
        if ($s !== '' && isset($s[0]) && $s[0] === '/') {
            if (stripos($s, '/admin/modules/') !== false) {
                return self::homeUrl();
            }

            return $s;
        }

        return self::homeUrl();
    }

    public static function homeUrl(): string
    {
        $base = self::appWebBase();
        if (self::isDelegadoAsociacion()) {
            return $base . '/fvdmasteradmin/perfil_delegado.php';
        }
        if (self::role() === self::ROLE_FVD_ADMIN) {
            return $base . '/fvdmasteradmin/master_panel.php';
        }

        return $base . '/fvdmasteradmin/index.php';
    }

    public static function logoutUrl(): string
    {
        $base = self::appWebBase();

        return $base . '/fvdmasteradmin/logout.php';
    }

    /**
     * Perfil de cuenta (contraseña, datos); delegados se redirigen internamente a perfil_delegado.php.
     */
    public static function perfilUrl(): string
    {
        $base = self::appWebBase();

        return $base . '/fvdmasteradmin/perfil.php';
    }

    private static function redirectToLogin(): void
    {
        self::ensureSession();
        $q = $_SERVER['REQUEST_URI'] ?? '';
        if (is_string($q) && $q !== '') {
            $_SESSION['fvd_redirect_after_login'] = $q;
        }
        header('Location: ' . self::loginUrl());
        exit;
    }
}
