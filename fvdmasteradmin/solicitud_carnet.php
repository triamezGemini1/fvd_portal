<?php

declare(strict_types=1);

require_once __DIR__ . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();

if (!AuthService::checkAccess([AuthService::ROLE_ASO_ADMIN, AuthService::ROLE_DELEGADO_ASOC])) {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso</title></head><body><p>No tiene permisos para esta sección.</p></body></html>';
    exit;
}

$myAs = AuthService::idAsociacion();
if ($myAs === null || (int) $myAs <= 0) {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso</title></head><body><p>Su usuario no tiene asociación asignada.</p></body></html>';
    exit;
}

require_once __DIR__ . '/includes/fvd_app_base_path.php';
$fvd_delegado_sol_redirect = $fvdAppBasePath . '/fvdmasteradmin/solicitud_carnet.php';
$fvd_delegado_sol_tipo_requerido = 'carnet';
require_once __DIR__ . '/includes/delegado_solicitud_process.php';

AuthService::ensureSession();
$solMsg = isset($_GET['msg']) && $_GET['msg'] === 'sol_ok';
$solErr = (string) ($_SESSION['fvd_delegado_sol_err'] ?? '');
unset($_SESSION['fvd_delegado_sol_err']);
$prefillAtletaId = isset($_GET['atleta_id']) ? max(0, (int) $_GET['atleta_id']) : 0;

$fvd_sidebar_active = 'sol_carnet';
$fvd_page_title = 'Solicitar carnet';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="fvd-dash" style="max-width:40rem">
    <h1>Solicitar carnet (revisión FVD)</h1>
    <p style="font-size:0.8125rem;color:var(--fvd-muted);margin:0 0 1rem">
        En <strong>Gestión fichas / carnets</strong> puede marcar <strong>Registrar carnet solicitado</strong> (<code>carnet=1</code>).
        Este formulario envía la solicitud al administrador general para revisión.
    </p>
    <?php if ($solMsg): ?><p class="fvd-mod-msg" style="color:#86efac">Solicitud registrada. El administrador general la revisará.</p><?php endif; ?>
    <?php if ($solErr !== ''): ?><p class="fvd-mod-msg"><?= htmlspecialchars($solErr, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <section class="fvd-card" style="padding:12px;margin-top:10px">
        <form method="post" action="">
            <input type="hidden" name="_action" value="delegado_solicitud">
            <input type="hidden" name="tipo" value="carnet">
            <label style="font-size:0.75rem;color:var(--fvd-muted);display:block">Atleta (ID)</label>
            <input class="fvd-input" name="atleta_id" type="number" required style="width:100%;max-width:22rem;margin-bottom:8px" value="<?= $prefillAtletaId > 0 ? (int) $prefillAtletaId : '' ?>">
            <label style="font-size:0.75rem;color:var(--fvd-muted);display:block">Nota</label>
            <input class="fvd-input" name="nota" style="width:100%;max-width:22rem;margin-bottom:10px">
            <button type="submit" class="fvd-btn-primary">Enviar solicitud</button>
        </form>
    </section>
</div>
<?php
require __DIR__ . '/includes/layout_footer.php';
