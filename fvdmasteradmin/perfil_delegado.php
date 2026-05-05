<?php

declare(strict_types=1);

/**
 * Perfil para cuentas de la tabla `delegados` (no fvd_usuarios).
 * Permite datos de contacto, número de cédula, foto carnet e imagen de cédula si existen columnas en BD.
 */

if (!function_exists('url')) {
    require_once dirname(__DIR__) . '/config/paths.php';
}
require_once __DIR__ . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();
if (!AuthService::isDelegadoAsociacion()) {
    header('Location: ' . AuthService::loginUrl());
    exit;
}
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/services/MediaService.php';

/** @return list<string> */
function fvd_delegado_table_columns(PDO $pdo, string $table): array
{
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if (!is_string($db) || $db === '') {
        return [];
    }
    $st = $pdo->prepare(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t ORDER BY ORDINAL_POSITION'
    );
    $st->execute([':db' => $db, ':t' => $table]);

    return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function fvd_delegado_delete_upload(string $projRoot, ?string $relative): void
{
    if ($relative === null || $relative === '' || strpos($relative, '..') !== false) {
        return;
    }
    $full = $projRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relative, '/'));
    if (is_file($full)) {
        @unlink($full);
    }
}

$projRoot = dirname(__DIR__);
$fvd_page_title = 'Mi perfil (delegado)';
$pdo = fvd_db();

$uid = AuthService::userId();
if ($uid === null) {
    header('Location: ' . AuthService::loginUrl());
    exit;
}

$cols = fvd_delegado_table_columns($pdo, 'delegados');
$hasDoc = in_array('documento_identidad', $cols, true);
$hasFotoCarnet = in_array('foto_carnet', $cols, true);
$hasFotoCedula = in_array('foto_cedula', $cols, true);

$uploadsFs = $projRoot . DIRECTORY_SEPARATOR . 'uploads';
$uploadsPublic = rtrim(url('uploads'), '/');
$media = new MediaService($uploadsFs, $uploadsPublic);

$stRow = $pdo->prepare(
    'SELECT d.*, a.nombre AS asociacion_nombre FROM delegados d
     LEFT JOIN asociaciones a ON a.id = d.asociacion_id WHERE d.id = :id LIMIT 1'
);
$stRow->execute([':id' => $uid]);
$row = $stRow->fetch(PDO::FETCH_ASSOC);
if ($row === false) {
    http_response_code(404);
    echo 'Delegado no encontrado.';
    exit;
}

$fvdDelegadoDashboardUrl = url('fvdmasteradmin/delegado_dashboard_new.php');
$notifActivas = 0;
try {
    $chkNf = $pdo->query("SHOW TABLES LIKE 'fvd_notificaciones'");
    if ($chkNf !== false && $chkNf->fetchColumn() !== false) {
        $stNf = $pdo->prepare(
            'SELECT COUNT(*) FROM fvd_notificaciones WHERE delegado_id = :d AND visto_en IS NULL'
        );
        $stNf->execute([':d' => $uid]);
        $notifActivas = (int) $stNf->fetchColumn();
    }
} catch (Throwable $e) {
    $notifActivas = 0;
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim((string) ($_POST['nombre_contacto'] ?? ''));
    $tel = trim((string) ($_POST['telefono'] ?? ''));
    $pass = (string) ($_POST['password_new'] ?? '');
    $pass2 = (string) ($_POST['password_new2'] ?? '');
    try {
        $st = $pdo->prepare('SELECT id FROM delegados WHERE id = :id LIMIT 1');
        $st->execute([':id' => $uid]);
        if ($st->fetchColumn() === false) {
            throw new RuntimeException('Delegado no encontrado.');
        }

        $data = [
            'nombre_contacto' => $nombre,
            'telefono'         => $tel !== '' ? $tel : null,
        ];

        if ($hasDoc) {
            $dv = trim((string) ($_POST['documento_identidad'] ?? ''));
            $data['documento_identidad'] = $dv !== '' ? $dv : null;
        }

        $fileCarnet = $_FILES['foto_carnet'] ?? null;
        if ($hasFotoCarnet && is_array($fileCarnet)
            && (int) ($fileCarnet['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $check = $media->validateUpload($fileCarnet);
            if (!$check['ok']) {
                throw new RuntimeException('Foto carnet: ' . ($check['error'] ?? 'imagen no válida.'));
            }
            $rel = $media->storeUploaded($fileCarnet, 'delegados');
            if ($rel === false) {
                throw new RuntimeException('No se pudo guardar la foto carnet.');
            }
            $old = isset($row['foto_carnet']) ? (string) $row['foto_carnet'] : '';
            if ($old !== '' && $old !== $rel) {
                fvd_delegado_delete_upload($projRoot, $old);
            }
            $data['foto_carnet'] = $rel;
        }

        $fileCedula = $_FILES['foto_cedula'] ?? null;
        if ($hasFotoCedula && is_array($fileCedula)
            && (int) ($fileCedula['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $check = $media->validateUpload($fileCedula);
            if (!$check['ok']) {
                throw new RuntimeException('Imagen cédula: ' . ($check['error'] ?? 'archivo no válido.'));
            }
            $rel = $media->storeUploaded($fileCedula, 'delegados');
            if ($rel === false) {
                throw new RuntimeException('No se pudo guardar la imagen de la cédula.');
            }
            $old = isset($row['foto_cedula']) ? (string) $row['foto_cedula'] : '';
            if ($old !== '' && $old !== $rel) {
                fvd_delegado_delete_upload($projRoot, $old);
            }
            $data['foto_cedula'] = $rel;
        }

        if ($pass !== '' || $pass2 !== '') {
            if (strlen($pass) < 8) {
                throw new RuntimeException('La contraseña debe tener al menos 8 caracteres.');
            }
            if ($pass !== $pass2) {
                throw new RuntimeException('Las contraseñas no coinciden.');
            }
            $data['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
        }

        $sets = [];
        $bind = [':id' => $uid];
        $i = 0;
        foreach ($data as $c => $v) {
            $ph = ':p' . $i;
            $sets[] = '`' . str_replace('`', '``', $c) . '` = ' . $ph;
            $bind[$ph] = $v;
            ++$i;
        }
        $sql = 'UPDATE delegados SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $up = $pdo->prepare($sql);
        $up->execute($bind);

        AuthService::reloadSessionFromDatabase();
        $stRow->execute([':id' => $uid]);
        $row = $stRow->fetch(PDO::FETCH_ASSOC) ?: $row;
        $msg = 'Datos actualizados.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$fotoCarnetUrl = ($hasFotoCarnet && !empty($row['foto_carnet']))
    ? $media->resolveDisplayUrl((string) $row['foto_carnet']) : '';
$fotoCedulaUrl = ($hasFotoCedula && !empty($row['foto_cedula']))
    ? $media->resolveDisplayUrl((string) $row['foto_cedula']) : '';

$fvd_defer_return_bar = true;
require __DIR__ . '/includes/layout_header.php';
?>
<style>
.fvd-delegado-perfil-grid {
    max-width: 72rem;
    margin: 0 auto 1.5rem;
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.25rem;
    align-items: start;
    box-sizing: border-box;
    padding: 0 0.5rem;
}
@media (min-width: 900px) {
    .fvd-delegado-perfil-grid {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.08fr);
        gap: 1.5rem 2rem;
        padding: 0;
    }
}
.fvd-delegado-perfil-col--info {
    color: #000;
    font-weight: 700;
    font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
}
.fvd-delegado-perfil-col--form .fvd-card { max-width: none; }
.fvd-delegado-perfil-ident { margin: 0 0 0.35rem; }
.fvd-delegado-perfil-nombre-badge {
    display: inline-flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 0.35rem 0.5rem;
    margin: 0 0 0.75rem;
    padding: 0.5rem 1rem;
    border-radius: 999px;
    background: linear-gradient(180deg, #2e3092 0%, #1a1c6e 100%);
    color: #fff;
    font-size: 0.95rem;
    font-weight: 700;
    line-height: 1.35;
    border: 2px solid #0f172a;
    box-shadow: 0 3px 0 rgba(0, 0, 0, 0.35);
    max-width: 100%;
    box-sizing: border-box;
}
.fvd-delegado-perfil-nombre-badge__lbl {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    opacity: 0.88;
}
.fvd-delegado-perfil-nombre-badge__val {
    color: #fde047;
    word-break: break-word;
}
.fvd-delegado-perfil-panel-btn {
    display: inline-block;
    margin: 0 0 0.85rem;
    padding: 0.55rem 1.2rem;
    font-size: 0.9rem;
    font-weight: 700;
    text-decoration: none;
    color: #0f172a;
    background: #fde047;
    border: 2px solid #0f172a;
    border-radius: 10px;
    box-shadow: 0 3px 0 #0f172a;
    line-height: 1.35;
    font-family: inherit;
    cursor: pointer;
    box-sizing: border-box;
}
.fvd-delegado-perfil-panel-btn:hover {
    filter: brightness(1.06);
    color: #0f172a;
}
.fvd-delegado-perfil-panel-btn:focus-visible {
    outline: 3px solid #2e3092;
    outline-offset: 2px;
}
</style>
<?php
$fvdPerfilPanelHref = (isset($fvd_return_nav_url) && is_string($fvd_return_nav_url) && $fvd_return_nav_url !== '')
    ? $fvd_return_nav_url
    : $fvdDelegadoDashboardUrl;
?>
<div class="fvd-delegado-perfil-grid">
    <div class="fvd-delegado-perfil-col fvd-delegado-perfil-col--info">
        <?php if (function_exists('fvd_delegado_inner_heading_visible') && fvd_delegado_inner_heading_visible()): ?>
        <h1 style="margin:0 0 .75rem;font-size:1.35rem;font-weight:700;color:#000;">Tu perfil</h1>
        <?php endif; ?>
        <div class="fvd-delegado-perfil-ident">
            <div class="fvd-delegado-perfil-nombre-badge" role="group" aria-label="Nombre del delegado">
                <span class="fvd-delegado-perfil-nombre-badge__lbl">Nombre</span>
                <span class="fvd-delegado-perfil-nombre-badge__val"><?= htmlspecialchars(trim((string) ($row['nombre_contacto'] ?? '')) !== '' ? (string) $row['nombre_contacto'] : '—', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <p style="margin:0;">
                <a class="fvd-delegado-perfil-panel-btn no-print" href="<?= htmlspecialchars($fvdPerfilPanelHref, ENT_QUOTES, 'UTF-8') ?>">Ir al panel delegado</a>
            </p>
        </div>
        <p style="margin:.35rem 0;line-height:1.45;font-size:1rem;color:#000;font-weight:700;">
            Correo: <?= htmlspecialchars((string) ($row['email_acceso'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <p style="margin:.35rem 0 0;line-height:1.45;font-size:1rem;color:#000;font-weight:700;">
            Asociación: <?= htmlspecialchars((string) ($row['asociacion_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <?php if ($notifActivas > 0): ?>
        <a href="<?= htmlspecialchars($fvdDelegadoDashboardUrl, ENT_QUOTES, 'UTF-8') ?>"
           class="fvd-delegado-notif-banner"
           style="display:block;margin:1rem 0 0;box-sizing:border-box;padding:1rem 1.25rem;background:#FFEB3E;color:#000;font-weight:700;font-size:1.05rem;text-align:center;text-decoration:none;border:3px solid #000;border-radius:10px;line-height:1.35;cursor:pointer;box-shadow:0 4px 0 #000;">
            TIENES <?= $notifActivas ?> NOTIFICACIONES ACTIVAS
        </a>
        <?php endif; ?>
        <?php if ($hasFotoCarnet || $hasFotoCedula): ?>
        <div style="display:flex;flex-wrap:wrap;gap:1rem;margin:1rem 0 0;">
            <?php if ($hasFotoCarnet): ?>
            <div>
                <p style="margin:0 0 0.35rem;color:var(--fvd-muted);font-size:0.75rem;">Foto carnet actual</p>
                <div style="width:100px;height:100px;border-radius:8px;overflow:hidden;border:1px solid var(--fvd-border);">
                    <img src="<?= htmlspecialchars($fotoCarnetUrl !== '' ? $fotoCarnetUrl : $media->defaultAvatar(), ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                </div>
            </div>
            <?php endif; ?>
            <?php if ($hasFotoCedula): ?>
            <div>
                <p style="margin:0 0 0.35rem;color:var(--fvd-muted);font-size:0.75rem;">Imagen cédula actual</p>
                <div style="width:100px;height:100px;border-radius:8px;overflow:hidden;border:1px solid var(--fvd-border);">
                    <img src="<?= htmlspecialchars($fotoCedulaUrl !== '' ? $fotoCedulaUrl : $media->defaultAvatar(), ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="fvd-delegado-perfil-col fvd-delegado-perfil-col--form">
<div class="fvd-card" style="max-width:none">
    <h2 style="margin:0 0 .35rem;color:#000;font-weight:700;font-size:1.2rem;">Editar datos y seguridad</h2>
    <p style="font-size:0.8125rem;color:var(--fvd-muted)">Asociación: <strong><?= htmlspecialchars((string) ($row['asociacion_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></p>
    <?php if ($msg !== ''): ?><p class="fvd-mod-msg" style="color:#86efac"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($err !== ''): ?><p class="fvd-mod-msg"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <?php if (!$hasDoc || !$hasFotoCarnet || !$hasFotoCedula): ?>
        <p style="color:var(--fvd-amarillo);font-size:0.8125rem;line-height:1.45;margin:0.75rem 0;">
            Para cédula (número) y fotos, ejecute en MySQL:
            <code style="background:rgba(0,0,0,.2);padding:2px 6px;border-radius:4px;word-break:break-all;">fvdmasteradmin/sql/alter_delegados_documento_fotos.sql</code>
        </p>
    <?php endif; ?>

    <form method="post" class="fvd-atleta-form" enctype="multipart/form-data" style="max-width:none">
        <p><label>Correo de acceso</label><br><input class="fvd-input" type="text" value="<?= htmlspecialchars((string) ($row['email_acceso'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" disabled></p>
        <p><label for="nombre_contacto">Nombre contacto</label><br><input class="fvd-input" id="nombre_contacto" name="nombre_contacto" value="<?= htmlspecialchars((string) ($row['nombre_contacto'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></p>
        <p><label for="telefono">Teléfono</label><br><input class="fvd-input" id="telefono" name="telefono" value="<?= htmlspecialchars((string) ($row['telefono'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></p>
        <?php if ($hasDoc): ?>
        <p><label for="documento_identidad">Cédula / documento (número)</label><br>
            <input class="fvd-input" id="documento_identidad" name="documento_identidad" value="<?= htmlspecialchars((string) ($row['documento_identidad'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" inputmode="numeric" autocomplete="off"></p>
        <?php endif; ?>
        <?php if ($hasFotoCarnet): ?>
        <p><label for="foto_carnet_input">Foto tipo carnet (JPG, PNG, GIF, WebP · máx. 5&nbsp;MB)</label><br>
            <input class="fvd-input" type="file" id="foto_carnet_input" name="foto_carnet" accept="image/jpeg,image/png,image/gif,image/webp"></p>
        <div id="foto_carnet_preview" style="min-height:80px;padding:0.5rem;border:1px dashed var(--fvd-border);border-radius:8px;font-size:0.75rem;color:var(--fvd-muted);margin-bottom:0.5rem;display:flex;align-items:center;justify-content:center;">
            <?php if ($fotoCarnetUrl !== ''): ?>
                <img src="<?= htmlspecialchars($fotoCarnetUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Foto carnet actual" style="max-width:200px;max-height:180px;width:auto;height:auto;object-fit:contain;border-radius:8px;display:block">
            <?php else: ?>
                Vista previa
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($hasFotoCedula): ?>
        <p><label for="foto_cedula_input">Imagen de la cédula</label><br>
            <input class="fvd-input" type="file" id="foto_cedula_input" name="foto_cedula" accept="image/jpeg,image/png,image/gif,image/webp"></p>
        <div id="foto_cedula_preview" style="min-height:80px;padding:0.5rem;border:1px dashed var(--fvd-border);border-radius:8px;font-size:0.75rem;color:var(--fvd-muted);margin-bottom:0.5rem;display:flex;align-items:center;justify-content:center;">
            <?php if ($fotoCedulaUrl !== ''): ?>
                <img src="<?= htmlspecialchars($fotoCedulaUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Cédula actual" style="max-width:100%;max-height:200px;width:auto;height:auto;object-fit:contain;border-radius:8px;display:block">
            <?php else: ?>
                Vista previa
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <p><label for="password_new">Nueva contraseña (opcional)</label><br><input class="fvd-input" id="password_new" name="password_new" type="password" autocomplete="new-password"></p>
        <p><label for="password_new2">Repetir contraseña</label><br><input class="fvd-input" id="password_new2" name="password_new2" type="password" autocomplete="new-password"></p>
        <p><button type="submit" class="fvd-btn-primary">Guardar</button></p>
    </form>
</div>
    </div>
</div>
<script src="<?= htmlspecialchars(url('assets/js/file-preview.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.filePreview === 'undefined') { return; }
    if (document.getElementById('foto_carnet_input')) {
        window.filePreview.init('foto_carnet_input', 'foto_carnet_preview', 'image', { previewSize: 180 });
    }
    if (document.getElementById('foto_cedula_input')) {
        window.filePreview.init('foto_cedula_input', 'foto_cedula_preview', 'image', { previewSize: 200 });
    }
});
</script>
<?php
require __DIR__ . '/includes/layout_footer.php';
