<?php
declare(strict_types=1);
/** @var array<string, mixed> $r */
$tipo = ($r['tipo'] ?? '') === 'club' ? 'Club' : 'Atleta';
$doc = htmlspecialchars((string) ($r['documento'] ?? ''), ENT_QUOTES, 'UTF-8');
$exp = htmlspecialchars((string) ($r['expira_en'] ?? ''), ENT_QUOTES, 'UTF-8');
$em = trim((string) ($r['email_destino'] ?? ''));
$emDisp = $em !== '' ? htmlspecialchars($em, ENT_QUOTES, 'UTF-8') : '—';
$memo = trim((string) ($r['titulo_memo'] ?? ''));
$memoTrim = $memo;
if ($memoTrim !== '') {
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        $memoTrim = mb_strlen($memoTrim, 'UTF-8') > 26
            ? mb_substr($memoTrim, 0, 25, 'UTF-8') . '…'
            : $memoTrim;
    } elseif (strlen($memoTrim) > 26) {
        $memoTrim = substr($memoTrim, 0, 25) . '…';
    }
}
$memoDisp = $memo !== '' ? htmlspecialchars($memoTrim, ENT_QUOTES, 'UTF-8') : '—';
$url = (string) ($r['_invite_url'] ?? '');
$badge = $r['_estado_badge'] ?? ['badge_class' => 'fvd-inv-badge', 'label' => '—'];
$bc = htmlspecialchars((string) ($badge['badge_class'] ?? ''), ENT_QUOTES, 'UTF-8');
$bl = htmlspecialchars((string) ($badge['label'] ?? ''), ENT_QUOTES, 'UTF-8');
$urlEsc = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
?>
<tr>
    <td><?= $tipo ?></td>
    <td title="<?= $doc ?>"><?= $doc ?></td>
    <td><span class="<?= $bc ?>"><?= $bl ?></span></td>
    <td class="fvd-inv-nowrap"><?= $exp ?></td>
    <td class="fvd-inv-clip" title="<?= $em !== '' ? $emDisp : '' ?>"><?= $emDisp ?></td>
    <td class="fvd-inv-clip" title="<?= $memo !== '' ? htmlspecialchars($memo, ENT_QUOTES, 'UTF-8') : '' ?>"><?= $memoDisp ?></td>
    <td class="fvd-inv-linkcell">
        <input type="hidden" class="fvd-inv-url" value="<?= $urlEsc ?>">
        <button type="button" class="fvd-inv-copy" title="Copiar enlace completo" aria-label="Copiar enlace">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
        </button>
        <span class="fvd-inv-url-hint" title="<?= $urlEsc ?>">enlace…</span>
    </td>
</tr>
