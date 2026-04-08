<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $fvdHistoricoFilas */
/** @var array<string,mixed>|null $fvdHistoricoTorneo */
/** @var string $selfUrl */
$fvdHistoricoFilas = $fvdHistoricoFilas ?? [];
$t = $fvdHistoricoTorneo;
$tid = (int) ($t['torneo'] ?? 0);
?>
<nav class="fvd-mis-panel__crumb" aria-label="Ruta" style="margin-bottom:1rem">
    <a href="<?= htmlspecialchars($selfUrl . '?action=evento&id=' . $tid, ENT_QUOTES, 'UTF-8') ?>">← Volver al torneo</a>
</nav>
<h1 class="fvd-mis-panel__title">Histórico de movimientos</h1>
<?php if ($t === null): ?>
    <p class="fvd-mod-msg">Torneo no encontrado.</p>
<?php else: ?>
    <p class="fvd-atl-muted" style="font-size:0.875rem;margin:0 0 1rem">
        <strong><?= htmlspecialchars((string) ($t['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
        · ID <?= $tid ?>
        <?php if (!empty($t['finalizado_en'])): ?>
            · Concluido: <?= htmlspecialchars(substr((string) $t['finalizado_en'], 0, 19), ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
    </p>
    <?php if ($fvdHistoricoFilas === []): ?>
        <p class="fvd-atl-muted">No hay registros (el torneo aún no se ha dado por concluido o no se ejecutó el script SQL del histórico).</p>
    <?php else: ?>
        <div style="overflow-x:auto">
            <table class="fvd-table" style="width:100%;font-size:0.8125rem">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nº FVD</th>
                        <th>Tipo</th>
                        <th>Antes</th>
                        <th>Después</th>
                        <th>Notas</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fvdHistoricoFilas as $h): ?>
                        <tr>
                            <td><?= (int) ($h['id'] ?? 0) ?></td>
                            <td><?= (int) ($h['numfvd'] ?? 0) ?></td>
                            <td><?= htmlspecialchars((string) ($h['tipo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($h['valor_anterior'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($h['valor_nuevo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($h['notas'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(substr((string) ($h['created_at'] ?? ''), 0, 19), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
