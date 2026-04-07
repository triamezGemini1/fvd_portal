<?php

declare(strict_types=1);

$fvdRoot = dirname(__DIR__);
require_once $fvdRoot . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();

$fvd_required_roles = [AuthService::ROLE_FVD_ADMIN, AuthService::ROLE_ASO_ADMIN];
AuthService::requireRoles($fvd_required_roles);

$fvd_page_title = 'Atletas federados';
require_once $fvdRoot . '/services/QueryHelper.php';

$appBase = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
$uploadsFs = dirname($fvdRoot) . DIRECTORY_SEPARATOR . 'crud_atletas' . DIRECTORY_SEPARATOR . 'uploads';
$uploadsUrl = $appBase . '/crud_atletas/uploads';
require_once $fvdRoot . '/services/MediaService.php';
$media = new MediaService($uploadsFs, $uploadsUrl);

$pdo = fvd_db();

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 20;

$params = [];
$searchSql = '';

if ($q !== '') {
    $params[':fvd_nom'] = '%' . $q . '%';
    $cedLike = '%' . $q . '%';
    $digitsOnly = preg_replace('/\D+/', '', $q);
    if ($digitsOnly !== '' && strlen($digitsOnly) >= 1) {
        $cedLike = $digitsOnly . '%';
    }
    $params[':fvd_ced'] = $cedLike;
    $searchSql = ' AND (a.cedula LIKE :fvd_ced OR a.nombre LIKE :fvd_nom)';
}

$countSql = 'SELECT COUNT(*) FROM atletas a WHERE 1=1' . $searchSql;
$dataSql = 'SELECT a.id, a.cedula, a.nombre, a.sexo, a.numfvd, a.estatus, a.celular, a.email, a.foto,
    s.nombre AS asociacion_nombre
    FROM atletas a
    LEFT JOIN asociaciones s ON a.asociacion = s.id
    WHERE 1=1' . $searchSql . ' ORDER BY a.id DESC';

$result = QueryHelper::paginateWithAsociacionScope(
    $pdo,
    $countSql,
    $dataSql,
    $params,
    $page,
    $perPage,
    'a.asociacion'
);

$rows = $result['rows'];
$total = $result['total'];
$pages = $result['pages'];
$curPage = $result['page'];

require $fvdRoot . '/includes/layout_header.php';
?>
<style>
    /* Listado claro: pastel basado en identidad FVD (azul #2E3092, amarillo #FFF200, rojo #BE123C) */
    .fvd-atl-listado-root {
        --atl-azul: #2E3092;
        --atl-azul-oscuro: #1a1c5e;
        --atl-amarillo-suave: #fffce8;
        --atl-bg-1: #e8eaf9;
        --atl-bg-2: #f3f4ff;
        --atl-text: #12143a;
        --atl-text-muted: #3d4270;
        --atl-border: rgba(46, 48, 146, 0.28);
        --atl-table-head: #d4d8f0;
        --atl-table-row-hover: #eef0fc;
        --atl-card: #ffffff;
        width: 100%;
        max-width: var(--fvd-max);
        margin: 0 auto;
        padding: 1rem 1.15rem 1.35rem;
        border-radius: 12px;
        border: 1px solid var(--atl-border);
        background: linear-gradient(160deg, var(--atl-bg-1) 0%, var(--atl-bg-2) 45%, var(--atl-amarillo-suave) 100%);
        box-shadow: 0 6px 28px rgba(46, 48, 146, 0.12);
    }
    .fvd-atl-listado-root h1 {
        color: var(--atl-azul-oscuro);
        text-shadow: none;
        font-weight: 700;
    }
    .fvd-atl-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: flex-end;
        margin-bottom: 12px;
    }
    .fvd-atl-listado-root .fvd-atl-toolbar label {
        font-size: 0.8125rem;
        color: var(--atl-text-muted);
        display: block;
        margin-bottom: 2px;
        font-weight: 600;
    }
    .fvd-atl-toolbar input.fvd-input { max-width: 16rem; }
    .fvd-atl-listado-root .fvd-atl-toolbar input.fvd-input {
        background: var(--atl-card);
        color: var(--atl-text);
        border: 1px solid var(--atl-border);
        box-shadow: inset 0 1px 2px rgba(46, 48, 146, 0.06);
    }
    .fvd-atl-listado-root .fvd-atl-toolbar select.fvd-input {
        background: #ffffff;
        color: #111827;
        color-scheme: light;
        border: 1px solid var(--atl-border);
        box-shadow: inset 0 1px 2px rgba(46, 48, 146, 0.06);
    }
    .fvd-atl-listado-root .fvd-atl-toolbar select.fvd-input option {
        background: #ffffff;
        color: #111827;
    }
    .fvd-atl-listado-root .fvd-atl-toolbar input.fvd-input::placeholder {
        color: #6b7199;
        opacity: 1;
    }
    .fvd-atl-table-wrap {
        width: 100%;
        overflow-x: auto;
        background: var(--atl-card);
        border: 1px solid var(--atl-border);
        border-radius: 10px;
        box-shadow: 0 2px 12px rgba(46, 48, 146, 0.08);
    }
    table.fvd-atl-table {
        width: 100%;
        border-collapse: collapse;
        font-size: var(--fvd-font-body);
        color: var(--atl-text);
    }
    table.fvd-atl-table th,
    table.fvd-atl-table td {
        padding: 8px 10px;
        border-bottom: 1px solid rgba(46, 48, 146, 0.15);
        text-align: left;
        vertical-align: middle;
    }
    table.fvd-atl-table th {
        background: var(--atl-table-head);
        color: var(--atl-azul-oscuro);
        font-weight: 700;
        white-space: nowrap;
        border-bottom: 2px solid rgba(46, 48, 146, 0.22);
    }
    table.fvd-atl-table td {
        color: var(--atl-text);
        background: var(--atl-card);
    }
    table.fvd-atl-table tbody tr:hover td { background: var(--atl-table-row-hover); }
    table.fvd-atl-table tr.fvd-atl-row--suspendido td {
        background: #ffe8ec !important;
        color: #7f1d1d;
    }
    table.fvd-atl-table tr.fvd-atl-row--suspendido:hover td {
        background: #ffd6dd !important;
    }
    .fvd-atl-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        display: block;
        border: 2px solid rgba(46, 48, 146, 0.25);
    }
    .fvd-atl-pager {
        margin-top: 12px;
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
        font-size: 0.8125rem;
        color: var(--atl-text-muted);
        font-weight: 500;
    }
    .fvd-atl-pager span { color: var(--atl-text-muted); }
    .fvd-atl-pager a {
        color: var(--atl-azul-oscuro);
        text-decoration: none;
        padding: 6px 12px;
        border: 1px solid var(--atl-border);
        border-radius: 8px;
        background: var(--atl-card);
        font-weight: 600;
    }
    .fvd-atl-pager a:hover {
        background: #fffef0;
        border-color: var(--atl-azul);
        color: var(--atl-azul);
        text-decoration: none;
    }
    .fvd-atl-muted { color: var(--atl-text-muted); font-size: 0.75rem; line-height: 1.45; }
    .fvd-atl-listado-root .fvd-atl-muted strong { color: var(--atl-azul-oscuro); }
    .fvd-atl-toolbar .fvd-btn {
        display: inline-flex;
        align-items: center;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        border: 1px solid transparent;
        text-decoration: none;
    }
    .fvd-atl-toolbar .fvd-btn--primary {
        background: var(--atl-azul);
        color: #fff;
        border-color: var(--atl-azul);
        box-shadow: 0 2px 8px rgba(46, 48, 146, 0.35);
    }
    .fvd-atl-toolbar .fvd-btn--primary:hover {
        filter: brightness(1.06);
        color: #fff;
    }
    .fvd-atl-toolbar .fvd-btn--secondary {
        background: var(--atl-card);
        color: var(--atl-azul-oscuro);
        border-color: var(--atl-border);
    }
    .fvd-atl-toolbar .fvd-btn--secondary:hover {
        background: var(--atl-amarillo-suave);
        border-color: var(--atl-azul);
    }
</style>

<div class="fvd-atl-listado-root">
    <h1>Atletas federados</h1>
    <p class="fvd-atl-muted">Búsqueda por <strong>cédula</strong> o <strong>nombre</strong> (campo <code>nombre</code> en base de datos).</p>

    <form class="fvd-atl-toolbar" method="get" action="">
        <div>
            <label for="fvd_q">Buscar (cédula / nombre)</label>
            <input class="fvd-input" type="search" id="fvd_q" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Cédula o nombre">
        </div>
        <button type="submit" class="fvd-btn fvd-btn--primary" style="padding:6px 12px;font-size:var(--fvd-font-body);">Buscar</button>
        <a href="<?= htmlspecialchars($appBase . '/fvdmasteradmin/atletas/listado.php', ENT_QUOTES, 'UTF-8') ?>" class="fvd-btn fvd-btn--secondary" style="padding:6px 12px;font-size:var(--fvd-font-body);text-decoration:none;display:inline-flex;align-items:center;">Limpiar</a>
    </form>

    <div class="fvd-atl-table-wrap">
        <table class="fvd-atl-table" aria-label="Listado de atletas">
            <thead>
            <tr>
                <th scope="col" style="width:44px;">Foto</th>
                <th scope="col">Cédula</th>
                <th scope="col">Nombre</th>
                <th scope="col">Sexo</th>
                <th scope="col">Nº FVD</th>
                <th scope="col">Asociación</th>
                <th scope="col">Estatus</th>
                <th scope="col">Contacto</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr>
                    <td colspan="8" style="padding:14px;color:#3d4270;font-weight:500;">No hay registros que coincidan.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $est = isset($row['estatus']) ? trim((string) $row['estatus']) : '';
                    $isSuspendido = strcasecmp($est, 'Suspendido') === 0;
                    $rowClass = $isSuspendido ? ' class="fvd-atl-row--suspendido"' : '';
                    $nombreEsc = htmlspecialchars((string) ($row['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr<?= $rowClass ?>>
                        <td>
                            <?= $media->imgTag(
                                isset($row['foto']) ? (string) $row['foto'] : null,
                                $nombreEsc,
                                [
                                    'class' => 'fvd-atl-avatar',
                                    'width' => '32',
                                    'height' => '32',
                                ]
                            ) ?>
                        </td>
                        <td><?= htmlspecialchars((string) ($row['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $nombreEsc ?></td>
                        <td><?= htmlspecialchars((string) ($row['sexo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($row['numfvd'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($row['asociacion_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($est, ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if (!empty($row['celular'])): ?>
                                <span><?= htmlspecialchars((string) $row['celular'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                            <?php if (!empty($row['email'])): ?>
                                <?php if (!empty($row['celular'])): ?><br><?php endif; ?>
                                <span class="fvd-atl-muted"><?= htmlspecialchars((string) $row['email'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php
    $pagerBase = $appBase . '/fvdmasteradmin/atletas/listado.php?';
    $qArg = $q !== '' ? 'q=' . rawurlencode($q) . '&' : '';
    ?>
    <nav class="fvd-atl-pager" aria-label="Paginación">
        <span><?= (int) $total ?> registro(s) · página <?= (int) $curPage ?> de <?= (int) $pages ?></span>
        <?php if ($curPage > 1): ?>
            <a href="<?= htmlspecialchars($pagerBase . $qArg . 'page=' . ($curPage - 1), ENT_QUOTES, 'UTF-8') ?>">Anterior</a>
        <?php endif; ?>
        <?php if ($curPage < $pages): ?>
            <a href="<?= htmlspecialchars($pagerBase . $qArg . 'page=' . ($curPage + 1), ENT_QUOTES, 'UTF-8') ?>">Siguiente</a>
        <?php endif; ?>
    </nav>
</div>
<?php
require $fvdRoot . '/includes/layout_footer.php';
