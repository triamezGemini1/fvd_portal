<?php
/** @var ?array $row */
/** @var string $selfUrl */
$r = $row ?? [];
?>

<h1>Ajustar montos de deuda</h1>
<p class="fvd-atl-muted" style="margin-top:0">Torneo #<?= (int) ($r['torneo_id'] ?? 0) ?> · Asociación #<?= (int) ($r['asociacion_id'] ?? 0) ?></p>

<form method="post" action="<?= htmlspecialchars($selfUrl . '?action=form&tid=' . (int) $r['torneo_id'] . '&aid=' . (int) $r['asociacion_id'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="_action" value="save">
    <input type="hidden" name="torneo_id" value="<?= (int) $r['torneo_id'] ?>">
    <input type="hidden" name="asociacion_id" value="<?= (int) $r['asociacion_id'] ?>">

    <div class="fvd-df-grid">
        <?php
        $fields = [
            'total_inscritos' => 'Total inscritos', 'monto_inscritos' => 'Monto inscr.',
            'total_afiliados' => 'Total afil.', 'monto_afiliados' => 'Monto afil.',
            'total_carnets' => 'Total carnets', 'monto_carnets' => 'Monto carnets',
            'total_traspasos' => 'Total trasp.', 'monto_traspasos' => 'Monto trasp.',
            'total_anualidad' => 'Total anual.', 'monto_anualidad' => 'Monto anual.',
            'monto_total' => 'Monto total',
        ];
        foreach ($fields as $f => $lab):
        ?>
            <div>
                <label for="<?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></label>
                <input class="fvd-input" type="number" step="0.01" id="<?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) ($r[$f] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
        <?php endforeach; ?>
    </div>

    <div class="fvd-mod-actions">
        <button type="submit">Guardar</button>
        <a href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>">Volver</a>
    </div>
</form>
