<?php

declare(strict_types=1);

/**
 * Niveles de acceso unificados para el portal FVD.
 * Se mapean desde {@see AuthService} (roles en sesión / BD).
 */
final class FvdAuth
{
    public const ADMIN_GRAL = 'ADMIN_GRAL';

    public const ADMIN_ASOC = 'ADMIN_ASOC';

    public const DELEGADO = 'DELEGADO';

    /** Jerarquía numérica: mayor = más privilegios. */
    public const RANK = [
        self::DELEGADO => 1,
        self::ADMIN_ASOC => 2,
        self::ADMIN_GRAL => 3,
    ];

    /**
     * @param class-string|null $authServiceClass FQCN si se usa otro bootstrap
     */
    public static function rankFromAuthRole(string $role, ?string $authServiceClass = null): int
    {
        $map = self::authRoleToLevelConst($role, $authServiceClass);

        return $map === null ? 0 : (int) (self::RANK[$map] ?? 0);
    }

    /**
     * @param class-string|null $authServiceClass
     */
    public static function levelConstFromAuthRole(string $role, ?string $authServiceClass = null): ?string
    {
        return self::authRoleToLevelConst($role, $authServiceClass);
    }

    /**
     * @param class-string|null $authServiceClass
     */
    private static function authRoleToLevelConst(string $role, ?string $authServiceClass): ?string
    {
        $class = $authServiceClass ?? self::defaultAuthServiceClass();

        if ($class === null || !class_exists($class)) {
            return null;
        }

        $r = trim($role);
        if ($r === '') {
            return null;
        }

        $fvd = defined($class . '::ROLE_FVD_ADMIN') ? (string) constant($class . '::ROLE_FVD_ADMIN') : 'fvd_admin';
        $aso = defined($class . '::ROLE_ASO_ADMIN') ? (string) constant($class . '::ROLE_ASO_ADMIN') : 'aso_admin';
        $del = defined($class . '::ROLE_DELEGADO_ASOC') ? (string) constant($class . '::ROLE_DELEGADO_ASOC') : 'delegado_asoc';

        if ($r === $fvd) {
            return self::ADMIN_GRAL;
        }
        if ($r === $aso) {
            return self::ADMIN_ASOC;
        }
        if ($r === $del) {
            return self::DELEGADO;
        }

        return null;
    }

    /**
     * @return class-string|null
     */
    private static function defaultAuthServiceClass(): ?string
    {
        $candidates = [
            dirname(__DIR__) . '/fvdmasteradmin/services/AuthService.php',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                require_once $path;

                return 'AuthService';
            }
        }

        return null;
    }

    public static function rankFromLevelConst(string $level): int
    {
        return (int) (self::RANK[$level] ?? 0);
    }

    /**
     * Exige sesión iniciada y rol mapeable a uno de los tres niveles.
     * Opcionalmente exige un rango mínimo (p. ej. solo federación: ADMIN_GRAL).
     */
    public static function requireStaffSession(string $minimumLevel = self::DELEGADO): void
    {
        $authClass = self::resolveAuthServiceClass();
        if ($authClass === null) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Configuración incompleta: no se encontró AuthService.';
            exit;
        }

        $authClass::ensureSession();
        $authClass::requireLogin();

        $role = method_exists($authClass, 'role') ? (string) $authClass::role() : '';
        $rank = self::rankFromAuthRole($role, $authClass);
        if ($rank < 1) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Acceso denegado: se requiere un rol de gestión (delegado, admin. asociación o admin. general).';
            exit;
        }

        $need = self::rankFromLevelConst($minimumLevel);
        if ($rank < $need) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Acceso denegado: su nivel no alcanza el mínimo requerido para este recurso.';
            exit;
        }
    }

    /**
     * @return class-string|null
     */
    private static function resolveAuthServiceClass(): ?string
    {
        if (class_exists('AuthService', false)) {
            return 'AuthService';
        }

        $path = dirname(__DIR__) . '/fvdmasteradmin/services/AuthService.php';
        if (!is_file($path)) {
            return null;
        }
        require_once $path;

        return class_exists('AuthService', false) ? 'AuthService' : null;
    }
}
