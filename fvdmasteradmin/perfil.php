<?php

declare(strict_types=1);

require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/config/db.php';

$projRoot = dirname(__DIR__);
if (!function_exists('url')) {
    require_once $projRoot . '/config/paths.php';
}
require_once __DIR__ . '/services/MediaService.php';

AuthService::ensureSession();
AuthService::requireLogin();

if (AuthService::isDelegadoAsociacion()) {
    require __DIR__ . '/perfil_delegado.php';
    exit;
}

$fvd_page_title = 'Mi perfil';
$pdo = fvd_db();
$uid = AuthService::userId();
if ($uid === null) {
    header('Location: ' . AuthService::loginUrl());
    exit;
}

/** @return list<string> */
function fvd_table_columns(PDO $pdo, string $table): array
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

function fvd_delete_stored_upload(string $projRoot, ?string $relative): void
{
    if ($relative === null || $relative === '' || strpos($relative, '..') !== false) {
        return;
    }
    $full = $projRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relative, '/'));
    if (is_file($full)) {
        @unlink($full);
    }
}

$cols = fvd_table_columns($pdo, 'fvd_usuarios');
$hasExtended = in_array('telefono', $cols, true);
$hasFoto = in_array('foto', $cols, true);
$hasSexo = in_array('sexo', $cols, true);
$hasFotoCarnet = in_array('foto_carnet', $cols, true);
$hasFotoCedula = in_array('foto_cedula', $cols, true);

$allowedUpdate = array_flip(array_intersect(
    ['nombre', 'apellidos', 'telefono', 'documento_identidad', 'direccion', 'ciudad', 'fecha_nacimiento', 'email', 'sexo', 'foto', 'foto_carnet', 'foto_cedula'],
    $cols
));

$uploadsFs = $projRoot . DIRECTORY_SEPARATOR . 'uploads';
$uploadsPublic = rtrim(url('uploads'), '/');
$media = new MediaService($uploadsFs, $uploadsPublic);

$msg = '';
$err = '';

$st = $pdo->prepare(
    'SELECT u.*, a.nombre AS asociacion_nombre
     FROM fvd_usuarios u
     LEFT JOIN asociaciones a ON u.id_asociacion = a.id
     WHERE u.id = :id LIMIT 1'
);
$st->execute([':id' => $uid]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    echo 'Usuario no encontrado.';
    exit;
}

$fotoUrl = '';
if ($hasFoto && !empty($row['foto'])) {
    $fotoUrl = $media->resolveDisplayUrl((string) $row['foto']);
}
$fotoCarnetUrl = '';
if ($hasFotoCarnet && !empty($row['foto_carnet'])) {
    $fotoCarnetUrl = $media->resolveDisplayUrl((string) $row['foto_carnet']);
}
$fotoCedulaUrl = '';
if ($hasFotoCedula && !empty($row['foto_cedula'])) {
    $fotoCedulaUrl = $media->resolveDisplayUrl((string) $row['foto_cedula']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['_action'] ?? 'datos');
    try {
        if ($action === 'password') {
            $cur = (string) ($_POST['password_actual'] ?? '');
            $n1 = (string) ($_POST['password_nueva'] ?? '');
            $n2 = (string) ($_POST['password_nueva2'] ?? '');
            if ($n1 === '' || $n1 !== $n2) {
                throw new RuntimeException('La nueva contraseña no coincide o está vacía.');
            }
            if (strlen($n1) < 8) {
                throw new RuntimeException('La nueva contraseña debe tener al menos 8 caracteres.');
            }
            $hash = (string) ($row['password_hash'] ?? '');
            if ($hash === '' || !password_verify($cur, $hash)) {
                throw new RuntimeException('La contraseña actual no es correcta.');
            }
            $newHash = password_hash($n1, PASSWORD_DEFAULT);
            $up = $pdo->prepare('UPDATE fvd_usuarios SET password_hash = :h WHERE id = :id');
            $up->execute([':h' => $newHash, ':id' => $uid]);
            $msg = 'Contraseña actualizada.';
            $st->execute([':id' => $uid]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: $row;
            if ($hasFoto && !empty($row['foto'])) {
                $fotoUrl = $media->resolveDisplayUrl((string) $row['foto']);
            }
            if ($hasFotoCarnet && !empty($row['foto_carnet'])) {
                $fotoCarnetUrl = $media->resolveDisplayUrl((string) $row['foto_carnet']);
            } else {
                $fotoCarnetUrl = '';
            }
            if ($hasFotoCedula && !empty($row['foto_cedula'])) {
                $fotoCedulaUrl = $media->resolveDisplayUrl((string) $row['foto_cedula']);
            } else {
                $fotoCedulaUrl = '';
            }
        } else {
            $data = [];
            if (isset($allowedUpdate['nombre'])) {
                $data['nombre'] = trim((string) ($_POST['nombre'] ?? ''));
            }
            if (isset($allowedUpdate['apellidos'])) {
                $data['apellidos'] = trim((string) ($_POST['apellidos'] ?? ''));
            }
            if (isset($allowedUpdate['telefono'])) {
                $data['telefono'] = trim((string) ($_POST['telefono'] ?? ''));
            }
            if (isset($allowedUpdate['documento_identidad'])) {
                $data['documento_identidad'] = trim((string) ($_POST['documento_identidad'] ?? ''));
            }
            if (isset($allowedUpdate['direccion'])) {
                $data['direccion'] = trim((string) ($_POST['direccion'] ?? ''));
            }
            if (isset($allowedUpdate['ciudad'])) {
                $data['ciudad'] = trim((string) ($_POST['ciudad'] ?? ''));
            }
            if (isset($allowedUpdate['fecha_nacimiento'])) {
                $fn = trim((string) ($_POST['fecha_nacimiento'] ?? ''));
                $data['fecha_nacimiento'] = $fn === '' ? null : $fn;
            }
            if (isset($allowedUpdate['sexo'])) {
                $sx = strtoupper(trim((string) ($_POST['sexo'] ?? '')));
                $data['sexo'] = ($sx === 'M' || $sx === 'F') ? $sx : null;
            }
            if (isset($allowedUpdate['email'])) {
                $em = strtolower(trim((string) ($_POST['email'] ?? '')));
                if ($em === '' || !filter_var($em, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Correo no válido.');
                }
                if ($em !== strtolower((string) ($row['email'] ?? ''))) {
                    $chk = $pdo->prepare('SELECT id FROM fvd_usuarios WHERE LOWER(TRIM(email)) = :e AND id <> :id LIMIT 1');
                    $chk->execute([':e' => $em, ':id' => $uid]);
                    if ($chk->fetch()) {
                        throw new RuntimeException('Ese correo ya está registrado.');
                    }
                }
                $data['email'] = $em;
            }

            $file = $_FILES['foto'] ?? null;
            if ($hasFoto && isset($allowedUpdate['foto']) && is_array($file)
                && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $check = $media->validateUpload($file);
                if (!$check['ok']) {
                    throw new RuntimeException($check['error'] ?? 'Imagen no válida.');
                }
                $rel = $media->storeUploaded($file, 'fvd_usuarios');
                if ($rel === false) {
                    throw new RuntimeException('No se pudo guardar la imagen.');
                }
                $oldFoto = isset($row['foto']) ? (string) $row['foto'] : '';
                if ($oldFoto !== '' && $oldFoto !== $rel) {
                    fvd_delete_stored_upload($projRoot, $oldFoto);
                }
                $data['foto'] = $rel;
            }

            $fileCarnet = $_FILES['foto_carnet'] ?? null;
            if ($hasFotoCarnet && isset($allowedUpdate['foto_carnet']) && is_array($fileCarnet)
                && (int) ($fileCarnet['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $check = $media->validateUpload($fileCarnet);
                if (!$check['ok']) {
                    throw new RuntimeException('Foto carnet: ' . ($check['error'] ?? 'imagen no válida.'));
                }
                $rel = $media->storeUploaded($fileCarnet, 'fvd_usuarios');
                if ($rel === false) {
                    throw new RuntimeException('No se pudo guardar la foto carnet.');
                }
                $old = isset($row['foto_carnet']) ? (string) $row['foto_carnet'] : '';
                if ($old !== '' && $old !== $rel) {
                    fvd_delete_stored_upload($projRoot, $old);
                }
                $data['foto_carnet'] = $rel;
            }

            $fileCedula = $_FILES['foto_cedula'] ?? null;
            if ($hasFotoCedula && isset($allowedUpdate['foto_cedula']) && is_array($fileCedula)
                && (int) ($fileCedula['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $check = $media->validateUpload($fileCedula);
                if (!$check['ok']) {
                    throw new RuntimeException('Imagen cédula: ' . ($check['error'] ?? 'archivo no válido.'));
                }
                $rel = $media->storeUploaded($fileCedula, 'fvd_usuarios');
                if ($rel === false) {
                    throw new RuntimeException('No se pudo guardar la imagen de la cédula.');
                }
                $old = isset($row['foto_cedula']) ? (string) $row['foto_cedula'] : '';
                if ($old !== '' && $old !== $rel) {
                    fvd_delete_stored_upload($projRoot, $old);
                }
                $data['foto_cedula'] = $rel;
            }

            if ($data === []) {
                throw new RuntimeException('No hay cambios que guardar. Seleccione una foto o modifique algún campo.');
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
            $sql = 'UPDATE fvd_usuarios SET ' . implode(', ', $sets) . ' WHERE id = :id';
            $up = $pdo->prepare($sql);
            $up->execute($bind);
            $msg = 'Datos guardados correctamente.';
            AuthService::reloadSessionFromDatabase();
            $st->execute([':id' => $uid]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: $row;
            if ($hasFoto && !empty($row['foto'])) {
                $fotoUrl = $media->resolveDisplayUrl((string) $row['foto']);
            } else {
                $fotoUrl = '';
            }
            if ($hasFotoCarnet && !empty($row['foto_carnet'])) {
                $fotoCarnetUrl = $media->resolveDisplayUrl((string) $row['foto_carnet']);
            } else {
                $fotoCarnetUrl = '';
            }
            if ($hasFotoCedula && !empty($row['foto_cedula'])) {
                $fotoCedulaUrl = $media->resolveDisplayUrl((string) $row['foto_cedula']);
            } else {
                $fotoCedulaUrl = '';
            }
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$createdAt = !empty($row['created_at']) ? date('d/m/Y H:i', strtotime((string) $row['created_at'])) : '—';
$updatedAt = !empty($row['updated_at']) ? date('d/m/Y H:i', strtotime((string) $row['updated_at'])) : '—';
$activoTxt = !empty($row['activo']) ? 'Activo' : 'Inactivo';
$sexoVal = isset($row['sexo']) && $row['sexo'] !== null && $row['sexo'] !== '' ? (string) $row['sexo'] : '';

require __DIR__ . '/includes/layout_header.php';
?>

<div class="fvd-card">
    <h1>Mi perfil</h1>
    <p class="fvd-muted" style="color:var(--fvd-muted);margin-top:0.25rem;">
        Actualice sus datos, la foto de perfil, la foto tipo carnet y una imagen legible de su cédula. El correo es su usuario de acceso.
    </p>

    <?php if ($msg !== ''): ?>
        <p class="fvd-flash fvd-flash--ok" style="margin:1rem 0;padding:0.6rem 0.75rem;border-radius:6px;background:rgba(34,197,94,0.15);border:1px solid rgba(34,197,94,0.4);"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($err !== ''): ?>
        <p class="fvd-flash fvd-flash--err" style="margin:1rem 0;padding:0.6rem 0.75rem;border-radius:6px;background:rgba(190,18,60,0.15);border:1px solid rgba(190,18,60,0.45);"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if (!$hasExtended || !$hasFoto || !$hasSexo || !$hasFotoCarnet || !$hasFotoCedula): ?>
        <p style="color:var(--fvd-amarillo);font-size:var(--fvd-font-body);margin:1rem 0;line-height:1.45;">
            Si faltan campos, ejecute en MySQL (en orden según su esquema):
            <code style="background:rgba(0,0,0,.25);padding:2px 6px;border-radius:4px;word-break:break-all;">alter_fvd_usuarios_perfil.sql</code>,
            <code style="background:rgba(0,0,0,.25);padding:2px 6px;border-radius:4px;">alter_fvd_usuarios_foto_sexo.sql</code>,
            <code style="background:rgba(0,0,0,.25);padding:2px 6px;border-radius:4px;word-break:break-all;">alter_fvd_usuarios_foto_carnet_cedula.sql</code>
            (rutas bajo <code style="background:rgba(0,0,0,.25);padding:2px 6px;border-radius:4px;">fvdmasteradmin/sql/</code>).
        </p>
    <?php endif; ?>

    <h2 style="margin-top:1.25rem;">Datos del registro</h2>
    <div class="fvd-perfil-registro" style="display:flex;flex-wrap:wrap;gap:1.25rem 1.75rem;align-items:flex-start;margin:0.75rem 0 1.5rem;">
        <div class="fvd-perfil-registro__foto" style="flex-shrink:0;display:flex;flex-wrap:wrap;gap:1rem;">
            <div>
                <p style="margin:0 0 0.5rem;color:var(--fvd-muted);font-size:0.9em;">Foto de perfil</p>
                <div id="fvd-perfil-avatar-wrap" style="width:120px;height:120px;border-radius:12px;overflow:hidden;border:2px solid var(--fvd-border);background:rgba(0,0,0,.2);display:flex;align-items:center;justify-content:center;">
                    <img id="fvd-perfil-avatar-current" src="<?= htmlspecialchars($fotoUrl !== '' ? $fotoUrl : $media->defaultAvatar(), ENT_QUOTES, 'UTF-8') ?>" alt="" width="120" height="120" style="width:100%;height:100%;object-fit:cover;">
                </div>
            </div>
            <?php if ($hasFotoCarnet): ?>
            <div>
                <p style="margin:0 0 0.5rem;color:var(--fvd-muted);font-size:0.9em;">Foto carnet</p>
                <div style="width:120px;height:120px;border-radius:12px;overflow:hidden;border:2px solid var(--fvd-border);background:rgba(0,0,0,.2);display:flex;align-items:center;justify-content:center;">
                    <img src="<?= htmlspecialchars($fotoCarnetUrl !== '' ? $fotoCarnetUrl : $media->defaultAvatar(), ENT_QUOTES, 'UTF-8') ?>" alt="" width="120" height="120" style="width:100%;height:100%;object-fit:cover;">
                </div>
            </div>
            <?php endif; ?>
            <?php if ($hasFotoCedula): ?>
            <div>
                <p style="margin:0 0 0.5rem;color:var(--fvd-muted);font-size:0.9em;">Imagen cédula</p>
                <div style="width:120px;height:120px;border-radius:12px;overflow:hidden;border:2px solid var(--fvd-border);background:rgba(0,0,0,.2);display:flex;align-items:center;justify-content:center;">
                    <img src="<?= htmlspecialchars($fotoCedulaUrl !== '' ? $fotoCedulaUrl : $media->defaultAvatar(), ENT_QUOTES, 'UTF-8') ?>" alt="" width="120" height="120" style="width:100%;height:100%;object-fit:cover;">
                </div>
            </div>
            <?php endif; ?>
        </div>
        <dl class="fvd-perfil-dl" style="display:grid;grid-template-columns:auto 1fr;gap:0.35rem 1rem;flex:1;min-width:min(100%,16rem);max-width:36rem;font-size:var(--fvd-font-body);margin:0;align-self:center;">
            <dt style="color:var(--fvd-muted);">ID usuario</dt><dd style="margin:0;"><?= (int) ($row['id'] ?? 0) ?></dd>
            <dt style="color:var(--fvd-muted);">Rol</dt><dd style="margin:0;"><?= htmlspecialchars((string) ($row['rol'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
            <dt style="color:var(--fvd-muted);">Asociación</dt>
            <dd style="margin:0;"><?= !empty($row['asociacion_nombre']) ? htmlspecialchars((string) $row['asociacion_nombre'], ENT_QUOTES, 'UTF-8') : (!empty($row['id_asociacion']) ? 'ID ' . (int) $row['id_asociacion'] : 'Ámbito nacional') ?></dd>
            <dt style="color:var(--fvd-muted);">Alta</dt><dd style="margin:0;"><?= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') ?></dd>
            <dt style="color:var(--fvd-muted);">Última actualización</dt><dd style="margin:0;"><?= htmlspecialchars($updatedAt, ENT_QUOTES, 'UTF-8') ?></dd>
            <dt style="color:var(--fvd-muted);">Estado cuenta</dt><dd style="margin:0;"><?= htmlspecialchars($activoTxt, ENT_QUOTES, 'UTF-8') ?></dd>
        </dl>
    </div>

    <h2>Datos personales</h2>
    <form method="post" class="fvd-perfil-form" enctype="multipart/form-data" style="max-width:32rem;margin-top:0.75rem;">
        <input type="hidden" name="_action" value="datos">
        <?php if ($hasFoto): ?>
            <label for="foto_input">Foto de perfil (JPG, PNG, GIF, WebP · máx. 5&nbsp;MB)</label>
            <input class="fvd-input" type="file" id="foto_input" name="foto" accept="image/jpeg,image/png,image/gif,image/webp" style="max-width:100%;padding:0.5rem;">
            <p style="margin:0.5rem 0 0.35rem;color:var(--fvd-muted);font-size:0.9em;">Vista previa</p>
            <div id="foto_preview_new" style="min-height:120px;padding:0.5rem;border:1px dashed var(--fvd-border);border-radius:10px;background:rgba(0,0,0,.15);color:var(--fvd-muted);font-size:0.85em;margin-bottom:0.75rem;">
                Elija una imagen para previsualizarla aquí.
            </div>
        <?php endif; ?>
        <?php if ($hasFotoCarnet): ?>
            <label for="foto_carnet_input">Foto tipo carnet</label>
            <input class="fvd-input" type="file" id="foto_carnet_input" name="foto_carnet" accept="image/jpeg,image/png,image/gif,image/webp" style="max-width:100%;padding:0.5rem;">
            <div id="foto_carnet_preview" style="min-height:100px;padding:0.5rem;border:1px dashed var(--fvd-border);border-radius:10px;background:rgba(0,0,0,.12);color:var(--fvd-muted);font-size:0.85em;margin-bottom:0.75rem;">Vista previa foto carnet.</div>
        <?php endif; ?>
        <?php if ($hasFotoCedula): ?>
            <label for="foto_cedula_input">Imagen de la cédula (foto o escaneo legible)</label>
            <input class="fvd-input" type="file" id="foto_cedula_input" name="foto_cedula" accept="image/jpeg,image/png,image/gif,image/webp" style="max-width:100%;padding:0.5rem;">
            <div id="foto_cedula_preview" style="min-height:100px;padding:0.5rem;border:1px dashed var(--fvd-border);border-radius:10px;background:rgba(0,0,0,.12);color:var(--fvd-muted);font-size:0.85em;margin-bottom:0.75rem;">Vista previa cédula.</div>
        <?php endif; ?>
        <?php if (isset($allowedUpdate['nombre'])): ?>
            <label for="nombre">Nombre</label>
            <input class="fvd-input" id="nombre" name="nombre" value="<?= htmlspecialchars((string) ($row['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <?php if (isset($allowedUpdate['apellidos'])): ?>
            <label for="apellidos">Apellidos</label>
            <input class="fvd-input" id="apellidos" name="apellidos" value="<?= htmlspecialchars((string) ($row['apellidos'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <?php if (isset($allowedUpdate['email'])): ?>
            <label for="email">Correo electrónico (usuario)</label>
            <input class="fvd-input" type="email" id="email" name="email" required autocomplete="email" value="<?= htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <?php if (isset($allowedUpdate['telefono'])): ?>
            <label for="telefono">Teléfono</label>
            <input class="fvd-input" id="telefono" name="telefono" value="<?= htmlspecialchars((string) ($row['telefono'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <?php if ($hasSexo && isset($allowedUpdate['sexo'])): ?>
            <label for="sexo">Sexo</label>
            <select class="fvd-input" id="sexo" name="sexo" style="max-width:28rem;">
                <option value="" <?= $sexoVal === '' ? ' selected' : '' ?>>— No indicado —</option>
                <option value="M" <?= $sexoVal === 'M' ? ' selected' : '' ?>>Masculino</option>
                <option value="F" <?= $sexoVal === 'F' ? ' selected' : '' ?>>Femenino</option>
            </select>
        <?php endif; ?>
        <?php if (isset($allowedUpdate['fecha_nacimiento'])): ?>
            <label for="fecha_nacimiento">Fecha de nacimiento</label>
            <input class="fvd-input" type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= htmlspecialchars((string) ($row['fecha_nacimiento'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <?php if (isset($allowedUpdate['documento_identidad'])): ?>
            <label for="documento_identidad">Cédula / documento de identidad (número)</label>
            <input class="fvd-input" id="documento_identidad" name="documento_identidad" value="<?= htmlspecialchars((string) ($row['documento_identidad'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" inputmode="numeric" autocomplete="off">
        <?php endif; ?>
        <?php if (isset($allowedUpdate['direccion'])): ?>
            <label for="direccion">Dirección</label>
            <textarea class="fvd-input" id="direccion" name="direccion" rows="2"><?= htmlspecialchars((string) ($row['direccion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        <?php endif; ?>
        <?php if (isset($allowedUpdate['ciudad'])): ?>
            <label for="ciudad">Ciudad</label>
            <input class="fvd-input" id="ciudad" name="ciudad" value="<?= htmlspecialchars((string) ($row['ciudad'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>

        <button type="submit" class="fvd-btn fvd-btn--primary" style="margin-top:1rem;">Guardar cambios</button>
    </form>

    <h2 style="margin-top:2rem;">Cambiar contraseña</h2>
    <form method="post" style="max-width:32rem;margin-top:0.75rem;">
        <input type="hidden" name="_action" value="password">
        <label for="password_actual">Contraseña actual</label>
        <input class="fvd-input" type="password" id="password_actual" name="password_actual" required autocomplete="current-password">
        <label for="password_nueva">Nueva contraseña</label>
        <input class="fvd-input" type="password" id="password_nueva" name="password_nueva" required autocomplete="new-password" minlength="8">
        <label for="password_nueva2">Repita la nueva contraseña</label>
        <input class="fvd-input" type="password" id="password_nueva2" name="password_nueva2" required autocomplete="new-password" minlength="8">
        <button type="submit" class="fvd-btn fvd-btn--secondary" style="margin-top:1rem;">Actualizar contraseña</button>
    </form>
</div>

<script src="<?= htmlspecialchars(url('assets/js/file-preview.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof FilePreview === 'undefined') { return; }
    var fp = new FilePreview({ previewSize: 200, maxFileSize: 5 * 1024 * 1024 });
    var i1 = document.getElementById('foto_input');
    if (i1 && document.getElementById('foto_preview_new')) { fp.init('foto_input', 'foto_preview_new', 'image'); }
    var i2 = document.getElementById('foto_carnet_input');
    if (i2 && document.getElementById('foto_carnet_preview')) { fp.init('foto_carnet_input', 'foto_carnet_preview', 'image'); }
    var i3 = document.getElementById('foto_cedula_input');
    if (i3 && document.getElementById('foto_cedula_preview')) { fp.init('foto_cedula_input', 'foto_cedula_preview', 'image'); }
});
</script>

<?php
require __DIR__ . '/includes/layout_footer.php';
