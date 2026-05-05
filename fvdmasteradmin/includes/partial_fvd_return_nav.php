<?php

declare(strict_types=1);

/**
 * Barra «volver / panel delegado». Incluir desde layout o desde una vista con $fvd_defer_return_bar.
 *
 * @var bool $fvd_master_embed
 * @var string|null $fvd_return_nav_url
 * @var string $fvd_return_nav_label
 */
if (!empty($fvd_master_embed)) {
    return;
}
if (!isset($fvd_return_nav_url) || $fvd_return_nav_url === null || $fvd_return_nav_url === '') {
    return;
}
$__fvd_ret_lbl = isset($fvd_return_nav_label) && is_string($fvd_return_nav_label) && $fvd_return_nav_label !== ''
    ? $fvd_return_nav_label
    : '← Volver al origen';
?>
            <nav class="fvd-return-bar no-print" aria-label="Navegación de retorno">
                <a href="<?= htmlspecialchars($fvd_return_nav_url, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($__fvd_ret_lbl, ENT_QUOTES, 'UTF-8') ?></a>
            </nav>
