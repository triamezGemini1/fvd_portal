<?php
/**
 * Vista de la propia ficha (tabla atletas) para cuentas con fvd_usuarios.atleta_id.
 */

declare(strict_types=1);

require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../config/db.php';

$projRoot = dirname(__DIR__, 2);
if (!function_exists('url')) {
    require_once $projRoot . '/config/paths.php';
}

AuthService::ensureSession();
AuthService::requireLogin();

if (!AuthService::isAthletePortalUser()) {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso denegado</title></head><body><p>Esta página es solo para cuentas de atleta. Si necesita acceso, use la solicitud de primer acceso.</p></body></html>';
    exit;
}

$atletaId = AuthService::atletaId();
if ($atletaId === null || $atletaId <= 0) {
    http_response_code(403);
    exit;
}

$pdo = fvd_db();
$st = $pdo->prepare(
    'SELECT a.*, s.nombre AS asociacion_nombre
     FROM atletas a
     LEFT JOIN asociaciones s ON a.asociacion = s.id
     WHERE a.id = :id LIMIT 1'
);
$st->execute([':id' => $atletaId]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>No encontrado</title></head><body><p>No se encontró su ficha. Contacte a su asociación.</p></body></html>';
    exit;
}

$fvd_page_title = 'Mi ficha de atleta';
$fvd_sidebar_active = 'mi_ficha';

$labels = [
    'cedula' => 'Documento / cédula',
    'nombre' => 'Nombre',
    'sexo' => 'Sexo',
    'numfvd' => 'Número FVD',
    'asociacion_nombre' => 'Asociación',
    'estatus' => 'Estatus',
    'email' => 'Correo',
    'celular' => 'Teléfono',
    'fechnac' => 'Fecha de nacimiento',
    'fechfvd' => 'Fecha FVD',
    'fechact' => 'Actualización',
    'afiliacion' => 'Afiliación',
    'anualidad' => 'Anualidad',
    'carnet' => 'Carnet',
    'categ' => 'Categoría',
    'profesion' => 'Profesión',
    'direccion' => 'Dirección',
    'inscripcion' => 'Inscripción',
    'traspaso' => 'Traspaso',
];

$skipKeys = ['id', 'foto', 'cedula_img', 'torneo_id', 'asociacion'];

require __DIR__ . '/../includes/layout_header.php';

$uploadsBase = url('crud_atletas/uploads/');
$foto = isset($row['foto']) ? trim((string) $row['foto']) : '';
$cedImg = isset($row['cedula_img']) ? trim((string) $row['cedula_img']) : '';
?>

<h1>Mi ficha de atleta</h1>
<p class="fvd-dash__intro">Datos registrados en el sistema. Para cambiar contraseña o datos de cuenta use «Mi perfil». Los cambios en esta ficha los realiza su asociación.</p>

<div class="fvd-card" style="margin-bottom:1rem;">
    <?php if ($foto !== ''): ?>
        <p><strong>Foto</strong></p>
        <p><img src="<?= htmlspecialchars($uploadsBase . $foto, ENT_QUOTES, 'UTF-8') ?>" alt="Foto" style="max-width:180px;border-radius:8px;border:1px solid var(--fvd-border);"></p>
    <?php endif; ?>
    <?php if ($cedImg !== ''): ?>
        <p><strong>Imagen de documento</strong></p>
        <p><img src="<?= htmlspecialchars($uploadsBase . $cedImg, ENT_QUOTES, 'UTF-8') ?>" alt="Documento" style="max-width:280px;border-radius:8px;border:1px solid var(--fvd-border);"></p>
    <?php endif; ?>
</div>

<div class="fvd-card">
    <table class="fvd-table">
        <tbody>
        <?php foreach ($row as $key => $val): ?>
            <?php
            if (!is_string($key) || in_array($key, $skipKeys, true)) {
                continue;
            }
            if ($val === null || $val === '') {
                continue;
            }
            $label = $labels[$key] ?? $key;
            ?>
            <tr>
                <th><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></th>
                <td><?= htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
