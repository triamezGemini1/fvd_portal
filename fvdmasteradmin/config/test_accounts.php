<?php

declare(strict_types=1);

/**
 * Credenciales canónicas para entornos de prueba (local / staging).
 * Crear o actualizar filas con: php fvdmasteradmin/cli/seed_all_pruebas.php
 *
 * Contraseña única para todas las cuentas de prueba (cambiar en producción).
 */
const FVD_TEST_PASSWORD = 'PruebaFVD2026!';

/** @return list<array{key: string, label: string, login: string, rol: string, notas: string, destino_tras_login: string}> */
function fvd_test_accounts_catalogue(): array
{
    $base = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
    $loginUrl = $base . '/login.php';

    return [
        [
            'key' => 'fvd_admin',
            'label' => 'Administrador FVD (general)',
            'login' => 'prueba.fvd.admin@test.fvd',
            'rol' => 'fvd_admin',
            'notas' => 'Entrada obligatoria al Panel maestro (SPA); sin atajos viejos a /admin/modules/.',
            'destino_tras_login' => $base . '/fvdmasteradmin/master_panel.php',
        ],
        [
            'key' => 'aso_admin',
            'label' => 'Administrador de asociación (club)',
            'login' => 'prueba.aso.admin@test.fvd',
            'rol' => 'aso_admin',
            'notas' => 'Ámbito regional; asociación de prueba 9901.',
            'destino_tras_login' => $base . '/fvdmasteradmin/index.php',
        ],
        [
            'key' => 'delegado',
            'label' => 'Delegado de club',
            'login' => 'delegado.prueba@fvd.local',
            'rol' => 'delegado_asoc',
            'notas' => 'Cuenta en tabla delegados (no en fvd_usuarios).',
            'destino_tras_login' => $base . '/fvdmasteradmin/delegado_dashboard_new.php',
        ],
        [
            'key' => 'delegado_miranda',
            'label' => 'Delegado prueba — Miranda',
            'login' => 'delegado.prueba.miranda@test.fvd',
            'rol' => 'delegado_asoc',
            'notas' => 'Crear/actualizar con: php fvdmasteradmin/cli/provision_delegados_regiones_ve.php',
            'destino_tras_login' => $base . '/fvdmasteradmin/delegado_dashboard_new.php',
        ],
        [
            'key' => 'delegado_aragua',
            'label' => 'Delegado prueba — Aragua',
            'login' => 'delegado.prueba.aragua@test.fvd',
            'rol' => 'delegado_asoc',
            'notas' => 'Misma contraseña que FVD_TEST_PASSWORD.',
            'destino_tras_login' => $base . '/fvdmasteradmin/delegado_dashboard_new.php',
        ],
        [
            'key' => 'delegado_capital',
            'label' => 'Delegado prueba — Capital / DC',
            'login' => 'delegado.prueba.capital@test.fvd',
            'rol' => 'delegado_asoc',
            'notas' => 'Misma contraseña que FVD_TEST_PASSWORD.',
            'destino_tras_login' => $base . '/fvdmasteradmin/delegado_dashboard_new.php',
        ],
        [
            'key' => 'delegado_anzoategui',
            'label' => 'Delegado prueba — Anzoátegui',
            'login' => 'delegado.prueba.anzoategui@test.fvd',
            'rol' => 'delegado_asoc',
            'notas' => 'Misma contraseña que FVD_TEST_PASSWORD.',
            'destino_tras_login' => $base . '/fvdmasteradmin/delegado_dashboard_new.php',
        ],
        [
            'key' => 'delegado_bolivar',
            'label' => 'Delegado prueba — Bolívar',
            'login' => 'delegado.prueba.bolivar@test.fvd',
            'rol' => 'delegado_asoc',
            'notas' => 'Misma contraseña que FVD_TEST_PASSWORD.',
            'destino_tras_login' => $base . '/fvdmasteradmin/delegado_dashboard_new.php',
        ],
        [
            'key' => 'usuario',
            'label' => 'Usuario ligero (sin ficha atleta)',
            'login' => 'prueba.usuario@test.fvd',
            'rol' => 'usuario',
            'notas' => 'Rol usuario sin atleta_id vinculado.',
            'destino_tras_login' => $base . '/fvdmasteradmin/index.php',
        ],
        [
            'key' => 'atleta_portal',
            'label' => 'Portal del atleta',
            'login' => 'prueba.atleta@test.fvd',
            'rol' => 'usuario + atleta_id',
            'notas' => 'Redirige a Mi ficha.',
            'destino_tras_login' => $base . '/fvdmasteradmin/atleta/mi_ficha.php',
        ],
        [
            'key' => '_login',
            'label' => 'URL de acceso',
            'login' => $loginUrl,
            'rol' => '—',
            'notas' => 'Todos usan la misma contraseña: ' . FVD_TEST_PASSWORD,
            'destino_tras_login' => $loginUrl,
        ],
    ];
}
