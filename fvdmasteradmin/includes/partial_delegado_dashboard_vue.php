<?php

declare(strict_types=1);

/** @var string $fvd_delegado_initial_json */

?>
<div class="fvd-card">
    <div
        id="fvd-delegado-app"
        class="min-h-0"
        data-initial-state="<?= htmlspecialchars($fvd_delegado_initial_json ?? '{}', ENT_QUOTES, 'UTF-8') ?>"
    ></div>
</div>
<noscript>
    <p class="fvd-mod-msg" style="margin-top:0.75rem">Active JavaScript para ver el panel de delegación compacto.</p>
</noscript>
