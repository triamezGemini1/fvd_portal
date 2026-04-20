<?php

declare(strict_types=1);

/** @var string $fvd_delegado_initial_json */
/** @var string $fvd_delegado_boot_error */

?>
<?php if (!empty($fvd_delegado_boot_error)) : ?>
    <p class="fvd-mod-msg" role="alert" style="margin:0.75rem 0;padding:0.75rem;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-size:0.875rem;">
        <?= htmlspecialchars($fvd_delegado_boot_error, ENT_QUOTES, 'UTF-8') ?>
    </p>
<?php endif; ?>
<div class="fvd-delegado-app-shell">
    <div
        id="fvd-delegado-app"
        class="min-h-0"
        data-initial-state="<?= htmlspecialchars($fvd_delegado_initial_json ?? '{}', ENT_QUOTES, 'UTF-8') ?>"
    >
        <p style="padding:1rem;color:#334155;font-size:0.9rem;max-width:42rem;line-height:1.45;">
            Cargando panel del delegado… Si este mensaje no desaparece, abra la consola del navegador (F12) y compruebe errores de red o de JavaScript. En local hace falta <code>npm run build</code> si no usa el servidor Vite.
        </p>
    </div>
</div>
<noscript>
    <p class="fvd-mod-msg" style="margin-top:0.75rem">Active JavaScript para ver el panel de delegación compacto.</p>
</noscript>
