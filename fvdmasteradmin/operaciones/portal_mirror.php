<?php

declare(strict_types=1);

/**
 * Portal asociación (administrador FVD): elige asociación y abre el mismo panel que ven los delegados.
 */

require_once dirname(__DIR__) . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();
AuthService::requireRoles([AuthService::ROLE_FVD_ADMIN]);

require_once dirname(__DIR__) . '/config/db.php';
$projRoot = dirname(__DIR__, 2);
if (!function_exists('url')) {
    require_once $projRoot . '/config/paths.php';
}
if (!function_exists('fvd_append_embed_to_url')) {
    require_once $projRoot . '/config/fvd_navigation_return.php';
}

try {
    $pdo = fvd_db();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Error de conexión a la base de datos.';
    exit;
}

/**
 * Torneos del contexto del panel maestro: el torneo indicado y, si aplica, todo el grupo (campeonato).
 *
 * @return list<int>
 */
function fvd_portal_mirror_torneo_context_ids(PDO $pdo, int $torneoId, int $campeonatoGrupoId): array
{
    $map = [];
    $grupo = $campeonatoGrupoId > 0 ? $campeonatoGrupoId : 0;
    if ($torneoId > 0) {
        $map[$torneoId] = $torneoId;
        try {
            $st = $pdo->prepare('SELECT COALESCE(NULLIF(grupo_evento_id, 0), 0) AS g FROM torneosact WHERE torneo = :t LIMIT 1');
            $st->execute([':t' => $torneoId]);
            $g = (int) $st->fetchColumn();
            if ($g > 0) {
                $grupo = $g;
            }
        } catch (Throwable $e) {
            /* sin columna grupo_evento_id */
        }
    }
    if ($grupo > 0) {
        try {
            $st = $pdo->prepare('SELECT torneo FROM torneosact WHERE COALESCE(NULLIF(grupo_evento_id, 0), 0) = :g');
            $st->execute([':g' => $grupo]);
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $t = (int) ($r['torneo'] ?? 0);
                if ($t > 0) {
                    $map[$t] = $t;
                }
            }
        } catch (Throwable $e) {
            /* */
        }
    }

    return array_values($map);
}

/**
 * Asociaciones con filas en deuda_asociaciones o relacion_pagos para alguno de los torneos dados.
 *
 * @param list<int> $torneoIds
 *
 * @return list<array{id:int,nombre:string}>
 */
function fvd_portal_mirror_asociaciones_con_finanzas_torneo(PDO $pdo, array $torneoIds): array
{
    $torneoIds = array_values(array_unique(array_filter(array_map('intval', $torneoIds), static fn (int $x): bool => $x > 0)));
    if ($torneoIds === []) {
        return [];
    }
    $out = [];
    $ph = implode(',', array_fill(0, count($torneoIds), '?'));
    $bind = $torneoIds;
    foreach ($torneoIds as $t) {
        $bind[] = $t;
    }
    try {
        $sql = 'SELECT DISTINCT a.id, a.nombre
            FROM asociaciones a
            WHERE a.id IN (
                SELECT DISTINCT d.asociacion_id FROM deuda_asociaciones d
                WHERE d.torneo_id IN (' . $ph . ')
                UNION
                SELECT DISTINCT r.asociacion_id FROM relacion_pagos r
                WHERE r.torneo_id IN (' . $ph . ')
            )
            ORDER BY a.nombre ASC';
        $st = $pdo->prepare($sql);
        $st->execute($bind);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'nombre' => trim((string) ($row['nombre'] ?? '')),
            ];
        }
    } catch (Throwable $e) {
        error_log('[portal_mirror finanzas] ' . $e->getMessage());
    }

    return $out;
}

$err = '';
if (isset($_GET['clear']) && (string) $_GET['clear'] === '1') {
    AuthService::clearAdminPortalDelegadoAsociacionId();
}

$reqTorneoCtx = (int) ($_REQUEST['torneo_id'] ?? 0);
$reqCampCtx = (int) ($_REQUEST['campeonato_id'] ?? 0);
$embedDesdeMaster = (isset($_GET['embedded']) && (string) $_GET['embedded'] === '1')
    || (isset($_GET['fvd_master_embed']) && (string) $_GET['fvd_master_embed'] === '1');
$torneoIdsContexto = fvd_portal_mirror_torneo_context_ids($pdo, $reqTorneoCtx, $reqCampCtx);
$tieneContextoTorneo = $torneoIdsContexto !== [];
$filtroFinanzasTorneo = $tieneContextoTorneo;
$portalMirrorEmbedSinTorneo = $embedDesdeMaster && !$tieneContextoTorneo;
$asociaciones = [];
if ($tieneContextoTorneo) {
    $asociaciones = fvd_portal_mirror_asociaciones_con_finanzas_torneo($pdo, $torneoIdsContexto);
} elseif (!$portalMirrorEmbedSinTorneo) {
    try {
        $st = $pdo->query('SELECT id, nombre FROM asociaciones ORDER BY nombre ASC');
        if ($st !== false) {
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $asociaciones[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'nombre' => trim((string) ($row['nombre'] ?? '')),
                ];
            }
        }
    } catch (Throwable $e) {
        error_log('[portal_mirror] ' . $e->getMessage());
    }
}

$allowedAsocIds = [];
foreach ($asociaciones as $rowA) {
    $ia = (int) ($rowA['id'] ?? 0);
    if ($ia > 0) {
        $allowedAsocIds[$ia] = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aid = (int) ($_POST['asociacion_id'] ?? 0);
    if ($aid <= 0) {
        $err = 'Seleccione una asociación.';
    } elseif ($tieneContextoTorneo && !isset($allowedAsocIds[$aid])) {
        $err = 'Esa asociación no tiene información financiera registrada en el torneo de contexto.';
    } else {
        $ok = false;
        try {
            $chk = $pdo->prepare('SELECT 1 FROM asociaciones WHERE id = :id LIMIT 1');
            $chk->execute([':id' => $aid]);
            $ok = (bool) $chk->fetchColumn();
        } catch (Throwable $e) {
            error_log('[portal_mirror validate] ' . $e->getMessage());
        }
        if (!$ok) {
            $err = 'Asociación no válida.';
        } else {
            AuthService::setAdminPortalDelegadoAsociacionId($aid);
            $dest = fvd_append_embed_to_url(url('fvdmasteradmin/delegado_dashboard_new.php'));
            header('Location: ' . $dest, true, 302);
            exit;
        }
    }
}

$currentAid = AuthService::adminPortalDelegadoAsociacionId();
header('Content-Type: text/html; charset=UTF-8');
$selfUrl = htmlspecialchars(url('fvdmasteradmin/operaciones/portal_mirror.php'), ENT_QUOTES, 'UTF-8');
$formQueryParams = [];
if ($reqTorneoCtx > 0) {
    $formQueryParams['torneo_id'] = $reqTorneoCtx;
}
if ($reqCampCtx > 0) {
    $formQueryParams['campeonato_id'] = $reqCampCtx;
}
foreach (['embedded', 'fvd_master_embed'] as $qk) {
    if (isset($_GET[$qk]) && (string) $_GET[$qk] !== '') {
        $formQueryParams[$qk] = (string) $_GET[$qk];
    }
}
$formActionUrl = htmlspecialchars(
    url('fvdmasteradmin/operaciones/portal_mirror.php') . ($formQueryParams !== [] ? '?' . http_build_query($formQueryParams) : ''),
    ENT_QUOTES,
    'UTF-8'
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal asociación — FVD</title>
    <style>
        body { margin: 0; font-family: system-ui, Segoe UI, Roboto, sans-serif; background: #f1f5f9; color: #0f172a; }
        .wrap { max-width: 32rem; margin: 2rem auto; padding: 0 1rem; }
        .card {
            background: #fff; border-radius: 12px; padding: 1.35rem 1.5rem;
            box-shadow: 0 8px 30px rgba(15, 23, 42, .08); border: 1px solid #e2e8f0;
        }
        h1 { font-size: 1.15rem; margin: 0 0 .35rem; color: #1e3a8a; }
        p.lead { margin: 0 0 1rem; font-size: .9rem; color: #475569; line-height: 1.45; }
        label { display: block; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin-bottom: .35rem; }
        select {
            width: 100%; padding: .55rem .65rem; font-size: 1rem; border-radius: 8px; border: 1px solid #cbd5e1;
            background: #fff;
        }
        .err { background: #fef2f2; color: #991b1b; padding: .65rem .75rem; border-radius: 8px; font-size: .875rem; margin-bottom: 1rem; border: 1px solid #fecaca; }
        .actions { margin-top: 1.1rem; display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        button[type="submit"] {
            background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #fff; border: none; padding: .65rem 1.1rem;
            font-size: .95rem; font-weight: 700; border-radius: 8px; cursor: pointer;
        }
        button[type="submit"]:hover { filter: brightness(1.05); }
        a.muted { font-size: .85rem; color: #475569; }
        .hint { margin: 0 0 1rem; padding: .65rem .75rem; border-radius: 8px; background: #eff6ff; border: 1px solid #bfdbfe; font-size: .8125rem; color: #1e3a8a; line-height: 1.45; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Portal asociación</h1>
        <p class="lead">Elija la asociación para abrir el <strong>panel de delegado</strong> con sus datos y accesos (misma vista que usan los delegados de asociación).</p>
        <?php if ($portalMirrorEmbedSinTorneo): ?>
            <p class="hint">Desde el panel maestro debe elegir un <strong>torneo de contexto</strong> en la barra superior (selector de evento) y volver a abrir «Panel delegado (asociaciones)». Solo se muestran asociaciones con datos financieros del torneo.</p>
        <?php elseif ($filtroFinanzasTorneo): ?>
            <p class="hint">Solo se listan clubes con registro en <strong>deuda_asociaciones</strong> o <strong>relacion_pagos</strong> para el torneo o campeonato seleccionado en el panel maestro (<?= count($torneoIdsContexto) ?> torneo(s) en contexto).</p>
        <?php endif; ?>
        <?php if ($err !== ''): ?>
            <div class="err"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($asociaciones === []): ?>
            <?php if ($portalMirrorEmbedSinTorneo): ?>
                <p>No se puede listar asociaciones hasta que exista un torneo de contexto.</p>
            <?php elseif ($filtroFinanzasTorneo): ?>
                <p>No hay asociaciones con información financiera para este evento. Genere o sincronice estados de cuenta (<code>deuda_asociaciones</code>) o registre pagos en <code>relacion_pagos</code> para alguno de los torneos del contexto.</p>
            <?php else: ?>
                <p>No hay asociaciones registradas en el sistema.</p>
            <?php endif; ?>
        <?php else: ?>
            <form method="post" action="<?= $formActionUrl ?>">
                <?php if ($reqTorneoCtx > 0): ?>
                    <input type="hidden" name="torneo_id" value="<?= (int) $reqTorneoCtx ?>">
                <?php endif; ?>
                <?php if ($reqCampCtx > 0): ?>
                    <input type="hidden" name="campeonato_id" value="<?= (int) $reqCampCtx ?>">
                <?php endif; ?>
                <label for="asociacion_id">Asociación</label>
                <select id="asociacion_id" name="asociacion_id" required>
                    <option value="" disabled<?= $currentAid === null ? ' selected' : '' ?>>— Seleccione —</option>
                    <?php foreach ($asociaciones as $a): ?>
                        <?php if ($a['id'] <= 0) {
                            continue;
                        } ?>
                        <option value="<?= (int) $a['id'] ?>"<?= $currentAid === $a['id'] ? ' selected' : '' ?>>
                            <?= htmlspecialchars($a['nombre'] !== '' ? $a['nombre'] : ('#' . $a['id']), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="actions">
                    <button type="submit">Abrir panel de delegado</button>
                    <?php if ($currentAid !== null): ?>
                        <a class="muted" href="<?= htmlspecialchars(fvd_append_embed_to_url(url('fvdmasteradmin/delegado_dashboard_new.php')), ENT_QUOTES, 'UTF-8') ?>">Volver al panel sin cambiar</a>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
