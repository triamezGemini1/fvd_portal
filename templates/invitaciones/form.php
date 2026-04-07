<?php
declare(strict_types=1);
/** @var string $selfUrl */
/** @var string $fvd_error */
?>
<div class="fvd-inv-form fvd-inv-compact no-print">
    <h2 class="fvd-inv-form__title">Nueva invitación</h2>
    <?php if ($fvd_error !== ''): ?>
        <p class="fvd-mod-msg" role="alert"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-inv-form__grid">
        <input type="hidden" name="_action" value="invitar">
        <div>
            <label for="fvd-inv-tipo" class="fvd-inv-label">Tipo</label>
            <select id="fvd-inv-tipo" name="tipo" class="fvd-input fvd-inv-input" required>
                <option value="atleta">Atleta (cédula)</option>
                <option value="club">Club / asociación (RIF o Nº registro)</option>
            </select>
        </div>
        <div>
            <label for="fvd-inv-doc" class="fvd-inv-label">Cédula o RIF</label>
            <input id="fvd-inv-doc" name="documento" class="fvd-input fvd-inv-input" required autocomplete="off" placeholder="Sin duplicar en BD">
        </div>
        <div>
            <label for="fvd-inv-email" class="fvd-inv-label">Correo (opcional)</label>
            <input id="fvd-inv-email" name="email_destino" type="email" class="fvd-input fvd-inv-input" autocomplete="off" placeholder="Para envío automático">
        </div>
        <div>
            <label for="fvd-inv-dias" class="fvd-inv-label">Válida (días)</label>
            <input id="fvd-inv-dias" name="validez_dias" type="number" class="fvd-input fvd-inv-input" min="1" max="365" value="14" required>
        </div>
        <div class="fvd-inv-form__span2">
            <label for="fvd-inv-memo" class="fvd-inv-label">Nota interna (opcional)</label>
            <input id="fvd-inv-memo" name="titulo_memo" class="fvd-input fvd-inv-input" maxlength="255" placeholder="Referencia breve">
        </div>
        <div class="fvd-inv-form__actions">
            <button type="submit" class="fvd-btn-primary fvd-inv-btn">Generar invitación</button>
        </div>
    </form>
</div>
