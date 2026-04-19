<?php

declare(strict_types=1);

/**
 * Delegado / aso: buscar afiliados por cédula o Nº FVD y marcar atletas.carnet = 1 (solicitado).
 * Acceso desde el panel del delegado (no menú lateral).
 */

require_once dirname(__DIR__) . '/admin/_init.php';
require_once FVD_PROJECT_ROOT . '/src/Services/CarnetService.php';
require_once FVD_PROJECT_ROOT . '/src/Services/DelegadoTorneoVentanasService.php';

use FvdPortal\Services\CarnetService;
use FvdPortal\Services\DelegadoTorneoVentanasService;

fvd_admin_require_roles([AuthService::ROLE_ASO_ADMIN, AuthService::ROLE_DELEGADO_ASOC]);

$myAs = AuthService::idAsociacion();
if ($myAs === null || (int) $myAs <= 0) {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso</title></head><body><p>Su usuario no tiene asociación asignada.</p></body></html>';
    exit;
}

$pdo = fvd_db();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['_action'] ?? '') === 'marcar_carnet') {
    $tok = (string) ($_POST['_token'] ?? '');
    $sessTok = (string) ($_SESSION['fvd_delegado_carnet_token'] ?? '');
    if ($sessTok === '' || !hash_equals($sessTok, $tok)) {
        $_SESSION['fvd_delegado_carnet_flash'] = ['tipo' => 'err', 'msg' => 'Sesión expirada o solicitud no válida. Vuelva a intentar.'];
        header('Location: ' . url('fvdmasteradmin/delegado_carnet_afiliados.php'));
        exit;
    }

    $aid = max(0, (int) ($_POST['atleta_id'] ?? 0));
    if ($aid <= 0) {
        $_SESSION['fvd_delegado_carnet_flash'] = ['tipo' => 'err', 'msg' => 'Identificador de atleta no válido.'];
        header('Location: ' . url('fvdmasteradmin/delegado_carnet_afiliados.php'));
        exit;
    }

    $st = $pdo->prepare(
        'SELECT id, asociacion, torneo_id, carnet FROM atletas WHERE id = :id LIMIT 1'
    );
    $st->execute([':id' => $aid]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        $_SESSION['fvd_delegado_carnet_flash'] = ['tipo' => 'err', 'msg' => 'Atleta no encontrado.'];
        header('Location: ' . url('fvdmasteradmin/delegado_carnet_afiliados.php'));
        exit;
    }
    if ((int) ($row['asociacion'] ?? 0) !== (int) $myAs) {
        $_SESSION['fvd_delegado_carnet_flash'] = ['tipo' => 'err', 'msg' => 'No tiene permiso para modificar ese atleta.'];
        header('Location: ' . url('fvdmasteradmin/delegado_carnet_afiliados.php'));
        exit;
    }
    if ((int) ($row['carnet'] ?? 0) === 1) {
        $_SESSION['fvd_delegado_carnet_flash'] = ['tipo' => 'info', 'msg' => 'Ese atleta ya tenía el carnet marcado como solicitado.'];
        header('Location: ' . url('fvdmasteradmin/delegado_carnet_afiliados.php'));
        exit;
    }

    $tid = (int) ($row['torneo_id'] ?? 0);
    if (AuthService::isDelegadoAsociacion()) {
        if ($tid <= 0) {
            $ctx = AuthService::delegadoTorneoContextId();
            $tid = $ctx !== null && $ctx > 0 ? $ctx : 0;
        }
        if ($tid <= 0) {
            $_SESSION['fvd_delegado_carnet_flash'] = [
                'tipo' => 'err',
                'msg' => 'No se pudo validar el torneo del atleta. Acceda desde el panel con un evento activo o asigne torneo en la ficha.',
            ];
            header('Location: ' . url('fvdmasteradmin/delegado_carnet_afiliados.php'));
            exit;
        }
        try {
            DelegadoTorneoVentanasService::assertPuedeFase1Administrativa($pdo, $tid);
        } catch (Throwable $e) {
            $_SESSION['fvd_delegado_carnet_flash'] = ['tipo' => 'err', 'msg' => $e->getMessage()];
            header('Location: ' . url('fvdmasteradmin/delegado_carnet_afiliados.php'));
            exit;
        }
    }

    try {
        $n = CarnetService::emitirCarnet($pdo, [$aid]);
        $_SESSION['fvd_delegado_carnet_flash'] = [
            'tipo' => 'ok',
            'msg' => $n > 0 ? 'Carnet marcado como solicitado (carnet = 1).' : 'No se aplicó ningún cambio.',
        ];
    } catch (Throwable $e) {
        $_SESSION['fvd_delegado_carnet_flash'] = ['tipo' => 'err', 'msg' => $e->getMessage()];
    }

    header('Location: ' . url('fvdmasteradmin/delegado_carnet_afiliados.php'));
    exit;
}

$flash = null;
if (isset($_SESSION['fvd_delegado_carnet_flash']) && is_array($_SESSION['fvd_delegado_carnet_flash'])) {
    $flash = $_SESSION['fvd_delegado_carnet_flash'];
    unset($_SESSION['fvd_delegado_carnet_flash']);
}

$token = bin2hex(random_bytes(16));
$_SESSION['fvd_delegado_carnet_token'] = $token;

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$rows = [];
if ($q !== '') {
    $qNorm = preg_replace('/\s+/', '', $q);
    $digitsOnly = preg_replace('/\D/', '', $qNorm);
    $params = [':aid' => (int) $myAs];
    $conds = ['a.asociacion = :aid'];

    if ($digitsOnly !== '' && ctype_digit($digitsOnly)) {
        $params[':n'] = (int) $digitsOnly;
        $conds[] = '(a.numfvd = :n OR REPLACE(REPLACE(REPLACE(COALESCE(a.cedula,\'\'),\'-\',\'\'),\'.\',\'\'),\' \',\'\') LIKE :clike)';
        $params[':clike'] = '%' . $digitsOnly . '%';
    } else {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $qNorm) . '%';
        $params[':like1'] = $like;
        $conds[] = 'a.cedula LIKE :like1';
    }

    $sql = 'SELECT a.id, a.cedula, a.nombre, a.numfvd, a.carnet, a.torneo_id
            FROM atletas a
            WHERE ' . implode(' AND ', $conds) . '
            ORDER BY a.nombre ASC
            LIMIT 100';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
}

$fvd_sidebar_active = 'panel';
$fvd_page_title = 'Marcar carnet — afiliados';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="fvd-dash" style="max-width:56rem">
    <h1>Marcar carnet (afiliados)</h1>
    <p style="font-size:0.8125rem;color:var(--fvd-muted);margin:0 0 1rem">
        Busque por <strong>cédula</strong> o <strong>número FVD</strong>. Al confirmar, se actualiza <code>atletas.carnet = 1</code> (carnet solicitado).
        <?php if (AuthService::isDelegadoAsociacion()): ?>
            Solo en <strong>fase 1</strong> del calendario del torneo (misma regla que altas y traspasos).
        <?php endif; ?>
    </p>
    <?php if (is_array($flash)): ?>
        <?php
        $cls = ($flash['tipo'] ?? '') === 'ok' ? ' style="color:#86efac"' : '';
        ?>
        <p class="fvd-mod-msg"<?= $cls ?>><?= htmlspecialchars((string) ($flash['msg'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="get" action="" class="fvd-card" style="padding:12px;margin-bottom:1rem;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
        <div style="flex:1;min-width:12rem">
            <label style="font-size:0.75rem;color:var(--fvd-muted);display:block">Cédula o Nº FVD</label>
            <input class="fvd-input" name="q" type="search" style="width:100%;max-width:24rem" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej. 12345678 o número FVD" autocomplete="off">
        </div>
        <button type="submit" class="fvd-btn-primary">Buscar</button>
    </form>

    <?php if ($q === ''): ?>
        <p style="font-size:0.875rem;color:var(--fvd-muted)">Escriba un criterio y pulse Buscar.</p>
    <?php elseif ($rows === []): ?>
        <p style="font-size:0.875rem">Sin resultados para ese criterio.</p>
    <?php else: ?>
        <div class="fvd-mod-table-wrap">
            <table class="fvd-mod-table">
                <thead>
                <tr>
                    <th>Cédula</th>
                    <th>Nombre</th>
                    <th>Nº FVD</th>
                    <th>Carnet</th>
                    <th class="no-print"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $cid = (int) ($r['id'] ?? 0);
                    $cVal = (int) ($r['carnet'] ?? 0);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($r['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int) ($r['numfvd'] ?? 0) ?></td>
                        <td><?= $cVal ?></td>
                        <td class="no-print">
                            <?php if ($cVal === 0): ?>
                                <form method="post" action="" style="display:inline" onsubmit="return confirm('¿Marcar carnet como solicitado (carnet = 1) para este atleta?');">
                                    <input type="hidden" name="_action" value="marcar_carnet">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="atleta_id" value="<?= $cid ?>">
                                    <button type="submit" class="fvd-input" style="width:auto;padding:6px 12px;cursor:pointer">Marcar carnet solicitado</button>
                                </form>
                            <?php else: ?>
                                <span style="font-size:0.8125rem;color:var(--fvd-muted)">Ya solicitado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p style="margin-top:1.25rem;font-size:0.8125rem">
        <a href="<?= htmlspecialchars(url('fvdmasteradmin/delegado_dashboard.php'), ENT_QUOTES, 'UTF-8') ?>">← Panel del delegado</a>
        · <a href="<?= htmlspecialchars(url('modules/atletas/reporte_carnets.php'), ENT_QUOTES, 'UTF-8') ?>">Reporte carnets</a>
    </p>
</div>
<?php
require __DIR__ . '/includes/layout_footer.php';
