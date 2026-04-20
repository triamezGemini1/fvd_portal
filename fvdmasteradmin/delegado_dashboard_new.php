<?php
declare(strict_types=1);

require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/config/db.php';
require_once dirname(__DIR__) . '/config/paths.php';
require_once __DIR__ . '/includes/vite_assets.php';
require_once __DIR__ . '/includes/fvd_brand.php';
require_once dirname(__DIR__) . '/src/Views/Delegado/Dashboard.php';

AuthService::ensureSession();
AuthService::requireLogin();

if (!AuthService::isDelegadoAsociacion()) {
    header('Location: ' . url('login.php'), true, 302);
    exit;
}

try {
    $pdo = fvd_db();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Error de conexión a la base de datos.';
    exit;
}

$stats = [
    'atletas_afiliados' => 0,
    'afiliaciones' => 0,
    'carnets' => 0,
    'anualidades' => 0,
    'traspasos' => 0,
    'inscritos' => 0,
];
$torneoStats = [];
$asociacionId = (int) (AuthService::idAsociacion() ?? 0);

if ($asociacionId > 0) {
    try {
        $stmt = $pdo->prepare(
            'SELECT
                COUNT(*) AS atletas_afiliados,
                SUM(CASE WHEN COALESCE(afiliacion, 0) = 1 THEN 1 ELSE 0 END) AS afiliaciones,
                SUM(CASE WHEN COALESCE(carnet, 0) = 1 THEN 1 ELSE 0 END) AS carnets,
                SUM(CASE WHEN COALESCE(anualidad, 0) = 1 THEN 1 ELSE 0 END) AS anualidades,
                SUM(CASE WHEN COALESCE(traspaso, 0) = 1 THEN 1 ELSE 0 END) AS traspasos,
                SUM(CASE WHEN COALESCE(inscripcion, 0) = 1 THEN 1 ELSE 0 END) AS inscritos
             FROM atletas
             WHERE asociacion = :asoc'
        );
        $stmt->execute([':asoc' => $asociacionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            foreach (array_keys($stats) as $k) {
                $stats[$k] = (int) ($row[$k] ?? 0);
            }
        }

        $stTor = $pdo->prepare(
            'SELECT
                a.torneo_id,
                COALESCE(NULLIF(TRIM(t.nombre), \'\'), CONCAT(\'Torneo #\', a.torneo_id)) AS torneo_nombre,
                COUNT(*) AS atletas_afiliados,
                SUM(CASE WHEN COALESCE(a.afiliacion, 0) = 1 THEN 1 ELSE 0 END) AS afiliaciones,
                SUM(CASE WHEN COALESCE(a.carnet, 0) = 1 THEN 1 ELSE 0 END) AS carnets,
                SUM(CASE WHEN COALESCE(a.anualidad, 0) = 1 THEN 1 ELSE 0 END) AS anualidades,
                SUM(CASE WHEN COALESCE(a.traspaso, 0) = 1 THEN 1 ELSE 0 END) AS traspasos,
                SUM(CASE WHEN COALESCE(a.inscripcion, 0) = 1 THEN 1 ELSE 0 END) AS inscritos
             FROM atletas a
             LEFT JOIN torneosact t ON t.torneo = a.torneo_id
             WHERE a.asociacion = :asoc AND COALESCE(a.torneo_id, 0) > 0
             GROUP BY a.torneo_id, t.nombre
             ORDER BY a.torneo_id DESC'
        );
        $stTor->execute([':asoc' => $asociacionId]);
        $torneoRows = $stTor->fetchAll(PDO::FETCH_ASSOC);
        foreach ($torneoRows as $tr) {
            $torneoStats[] = [
                'torneo_id' => (int) ($tr['torneo_id'] ?? 0),
                'torneo_nombre' => (string) ($tr['torneo_nombre'] ?? ''),
                'atletas_afiliados' => (int) ($tr['atletas_afiliados'] ?? 0),
                'afiliaciones' => (int) ($tr['afiliaciones'] ?? 0),
                'carnets' => (int) ($tr['carnets'] ?? 0),
                'anualidades' => (int) ($tr['anualidades'] ?? 0),
                'traspasos' => (int) ($tr['traspasos'] ?? 0),
                'inscritos' => (int) ($tr['inscritos'] ?? 0),
            ];
        }
    } catch (Throwable $e) {
        /* Fallback de compatibilidad si el esquema usa "persona". */
        try {
            $stmtAlt = $pdo->prepare('SELECT COUNT(*) AS atletas_afiliados FROM persona WHERE asociacion_id = :asoc');
            $stmtAlt->execute([':asoc' => $asociacionId]);
            $stats['atletas_afiliados'] = (int) $stmtAlt->fetchColumn();
        } catch (Throwable $e2) {
            $stats['atletas_afiliados'] = 0;
        }
    }
}

$asociacionLabel = 'Miranda 4';
if ($asociacionId > 0) {
    try {
        $stAsoc = $pdo->prepare('SELECT nombre FROM asociaciones WHERE id = :id LIMIT 1');
        $stAsoc->execute([':id' => $asociacionId]);
        $asocNombre = trim((string) ($stAsoc->fetchColumn() ?: ''));
        if ($asocNombre !== '') {
            $asociacionLabel = $asocNombre;
        }
    } catch (Throwable $e) {
        /* Mantener fallback estable si falla esta consulta auxiliar. */
    }
}
$viteTags = '';

/* delegados-app no siempre declara CSS en manifest; inyectamos app.css para asegurar Tailwind. */
$manifestPath = dirname(__DIR__) . '/public/build/.vite/manifest.json';
if (is_readable($manifestPath)) {
    $decoded = json_decode((string) file_get_contents($manifestPath), true);
    if (is_array($decoded)) {
        $baseBuild = rtrim(url('public/build'), '/');
        $v = (string) (@filemtime($manifestPath) ?: time());
        foreach (fvd_vite_manifest_css_for_entry($decoded, 'resources/js/app.js') as $css) {
            $href = $baseBuild . '/' . ltrim((string) $css, '/') . '?v=' . rawurlencode($v);
            $viteTags .= '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
    }
}
$viteTags .= fvd_vite_tags('resources/js/delegado-app.js');

$user = AuthService::user() ?? [];
$userDisplayName = trim((string) ($user['nombre'] ?? 'Usuario activo'));
if ($userDisplayName === '') {
    $userDisplayName = trim((string) ($user['email'] ?? 'Usuario activo'));
}
if ($userDisplayName === '') {
    $userDisplayName = 'Usuario activo';
}

$brandLogoUrl = fvd_brand_logo_public_url();
$perfilUrl = AuthService::perfilUrl();
$logoutUrl = AuthService::logoutUrl();
$panelUrl = AuthService::homeUrl();
$torneoCtx = (int) (AuthService::delegadoTorneoContextId() ?? 0);
$campeonatoCtx = (int) (AuthService::delegadoCampeonatoGrupoId() ?? 0);
$qTorneo = [];
if ($torneoCtx > 0) {
    $qTorneo['torneo_id'] = $torneoCtx;
} elseif (isset($torneoStats[0]['torneo_id']) && (int) $torneoStats[0]['torneo_id'] > 0) {
    $qTorneo['torneo_id'] = (int) $torneoStats[0]['torneo_id'];
}
if ($campeonatoCtx > 0) {
    $qTorneo['campeonato_id'] = $campeonatoCtx;
}
$qTorneoStr = $qTorneo !== [] ? ('?' . http_build_query($qTorneo)) : '';
$actionUrls = [
    'afiliaciones' => url('fvdmasteradmin/solicitud_afiliacion.php'),
    'carnets' => url('fvdmasteradmin/solicitud_carnet.php'),
    'transferencias' => url('fvdmasteradmin/solicitud_traspaso.php'),
    'inscribir_torneo' => fvd_master_module_url('torneo_inscripcion/index.php' . $qTorneoStr),
    'administrar_inscripciones' => fvd_master_module_url('inscripcion_torneo/index.php' . $qTorneoStr),
];

\FvdPortal\Views\Delegado\Dashboard::render(
    $stats,
    $torneoStats,
    $asociacionLabel,
    $userDisplayName,
    $perfilUrl,
    $logoutUrl,
    $panelUrl,
    $brandLogoUrl,
    $actionUrls,
    $viteTags
);
