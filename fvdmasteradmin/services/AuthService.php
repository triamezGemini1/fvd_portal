<?php
/**
 * Autenticación y contexto de sesión para FVD Master Admin.
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

    /** @var string Código interno del último fallo (solo para depuración con APP_DEBUG). */
    private static $lastLoginFailure = '';

    public static function getLastLoginFailure(): string
    {
        return self::$lastLoginFailure;
    }

    private static function setLoginFailure(string $code): void
    {
        self::$lastLoginFailure = $code;
    }

    /** @var list<string> */
    public const ROLES = [
        self::ROLE_FVD_ADMIN,
        self::ROLE_ASO_ADMIN,
        self::ROLE_DELEGADO_ASOC,
        self::ROLE_USUARIO,
    ];

    public static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
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
        unset($_SESSION[self::SESSION_DELEGADO_TORNEO_CTX]);
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
        unset($_SESSION[self::SESSION_DELEGADO_TORNEO_CTX]);
        $_SESSION[self::SESSION_KEY] = [
            'auth_source'    => 'delegado',
            'id'             => (int) $row['id'],
            'email'          => (string) $row['email_acceso'],
            'nombre'         => (string) ($row['nombre_contacto'] ?? ''),
            'rol'            => self::ROLE_DELEGADO_ASOC,
            'id_asociacion'  => (int) $row['asociacion_id'],
            'atleta_id'      => null,
        ];
    }

    public static function logout(): void
    {
        self::ensureSession();
        unset($_SESSION[self::SESSION_KEY]);
    }

    /**
     * Intenta autenticar: primero `fvd_usuarios`, luego tabla `delegados`.
     */
    public static function attemptLogin(string $email, string $password): bool
    {
        self::$lastLoginFailure = '';
        $email = strtolower(trim($email));
        if ($email === '' || $password === '') {
            self::setLoginFailure('empty_input');

            return false;
        }

        if (self::attemptFvdUsuarioLogin($email, $password)) {
            return true;
        }

        $fail = self::getLastLoginFailure();
        if ($fail === 'db_error' || $fail === 'bad_password' || $fail === 'user_inactive') {
            return false;
        }

        return self::attemptDelegadoLogin($email, $password);
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
        $base = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');

        return $base . '/fvdmasteradmin/login.php';
    }

    public static function homeUrl(): string
    {
        $base = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');

        return $base . '/fvdmasteradmin/index.php';
    }

    public static function logoutUrl(): string
    {
        $base = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');

        return $base . '/fvdmasteradmin/logout.php';
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
