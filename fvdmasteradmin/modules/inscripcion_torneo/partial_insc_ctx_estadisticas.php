<?php
declare(strict_types=1);
/**
 * Contexto de torneo(s) + badges de estadísticas por género (cohorte atletas / deuda).
 *
 * @var int $fvdInscFilterTorneoId torneo activo (0 = sin)
 * @var string $fvdInscSelfUrl URL base del módulo (sin query)
 * @var array<string, mixed>|null $fvdInscStatsGen resultado de InscripcionService::estadisticasPorGeneroTorneoAsociacion o null
 * @var list<array<string, mixed>> $fvdInscCtxTorneos filas delegado (torneo, nombre, nombre_corta) o []
 * @var list<array<string, mixed>> $fvdInscTorneosChips listado admin (id, nombre) o []
 * @var bool $fvdInscEsDelegado
 * @var bool $fvdInscEsFvdAdmin
 * @var int $fvdInscAsocIdQuery asociación en query (FVD admin) o 0
 * @var int $fvdInscCampeonatoId campeonato en query o 0
 * @var string $fvdInscTorneoNombre etiqueta del torneo activo
 */
$fvdInscFilterTorneoId = isset($fvdInscFilterTorneoId) ? (int) $fvdInscFilterTorneoId : 0;
$fvdInscSelfUrl = isset($fvdInscSelfUrl) ? (string) $fvdInscSelfUrl : '';
$fvdInscStatsGen = isset($fvdInscStatsGen) && is_array($fvdInscStatsGen) ? $fvdInscStatsGen : null;
$fvdInscCtxTorneos = isset($fvdInscCtxTorneos) && is_array($fvdInscCtxTorneos) ? $fvdInscCtxTorneos : [];
$fvdInscTorneosChips = isset($fvdInscTorneosChips) && is_array($fvdInscTorneosChips) ? $fvdInscTorneosChips : [];
$fvdInscEsDelegado = !empty($fvdInscEsDelegado);
$fvdInscEsFvdAdmin = !empty($fvdInscEsFvdAdmin);
$fvdInscAsocIdQuery = isset($fvdInscAsocIdQuery) ? (int) $fvdInscAsocIdQuery : 0;
$fvdInscCampeonatoId = isset($fvdInscCampeonatoId) ? (int) $fvdInscCampeonatoId : 0;
$fvdInscTorneoNombre = isset($fvdInscTorneoNombre) ? trim((string) $fvdInscTorneoNombre) : '';

if (!function_exists('url')) {
    require_once dirname(__DIR__, 3) . '/config/paths.php';
}

$fvdInscBuildQs = static function (array $base): string {
    $q = array_merge($_GET, $base);
    unset($q['fvd_err'], $q['fvd_ok'], $q['p']);

    return http_build_query($q);
};

$fvdInscTorneoLabel = $fvdInscTorneoNombre !== ''
    ? $fvdInscTorneoNombre
    : ($fvdInscFilterTorneoId > 0 ? 'Torneo #' . $fvdInscFilterTorneoId : 'Sin torneo');
?>
<section class="fvd-insc-ctx" aria-label="Torneo en contexto y estadísticas">
    <div class="fvd-insc-ctx__torneo-box fvd-card" style="padding:12px 14px;margin-bottom:12px">
        <div class="fvd-insc-ctx__torneo-head">
            <strong class="fvd-insc-ctx__torneo-title">Torneo en contexto</strong>
            <?php if ($fvdInscFilterTorneoId > 0): ?>
                <span class="fvd-insc-ctx__torneo-active"><?= htmlspecialchars($fvdInscTorneoLabel, ENT_QUOTES, 'UTF-8') ?>
                    <span class="fvd-muted" style="font-weight:600">(#<?= $fvdInscFilterTorneoId ?>)</span>
                </span>
            <?php else: ?>
                <span class="fvd-muted">Seleccione un torneo para ver inscritos y estadísticas.</span>
            <?php endif; ?>
        </div>
        <?php if ($fvdInscEsDelegado && $fvdInscCtxTorneos !== [] && $fvdInscCampeonatoId > 0): ?>
            <div class="fvd-insc-ctx__chips" role="tablist" aria-label="Cambiar de torneo">
                <?php foreach ($fvdInscCtxTorneos as $tg): ?>
                    <?php
                    $tgId = (int) ($tg['torneo'] ?? 0);
                    if ($tgId <= 0) {
                        continue;
                    }
                    $nomL = trim((string) ($tg['nombre'] ?? ''));
                    $nomC = trim((string) ($tg['nombre_corta'] ?? ''));
                    $disp = $nomL !== '' ? $nomL : ($nomC !== '' ? $nomC : ('Torneo #' . $tgId));
                    $on = $fvdInscFilterTorneoId === $tgId;
                    $href = $fvdInscSelfUrl . '?' . $fvdInscBuildQs(['torneo_id' => $tgId, 'campeonato_id' => $fvdInscCampeonatoId]);
                    ?>
                    <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                       class="fvd-insc-ctx__chip<?= $on ? ' fvd-insc-ctx__chip--on' : '' ?>"
                       <?= $on ? 'aria-current="page"' : '' ?>><?= htmlspecialchars($disp, ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
            </div>
        <?php elseif (!$fvdInscEsDelegado && $fvdInscTorneosChips !== []): ?>
            <div class="fvd-insc-ctx__chips" role="tablist" aria-label="Elegir torneo">
                <?php foreach ($fvdInscTorneosChips as $t): ?>
                    <?php
                    $cid = (int) ($t['id'] ?? 0);
                    if ($cid <= 0) {
                        continue;
                    }
                    $nom = trim((string) ($t['nombre'] ?? ''));
                    if ($nom === '') {
                        $nom = 'Torneo #' . $cid;
                    }
                    $on = $fvdInscFilterTorneoId === $cid;
                    $qs = ['torneo_id' => $cid];
                    if ($fvdInscEsFvdAdmin && $fvdInscAsocIdQuery > 0) {
                        $qs['asociacion_id'] = $fvdInscAsocIdQuery;
                    }
                    if ($fvdInscCampeonatoId > 0) {
                        $qs['campeonato_id'] = $fvdInscCampeonatoId;
                    }
                    $href = $fvdInscSelfUrl . '?' . $fvdInscBuildQs($qs);
                    ?>
                    <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                       class="fvd-insc-ctx__chip<?= $on ? ' fvd-insc-ctx__chip--on' : '' ?>"
                       <?= $on ? 'aria-current="page"' : '' ?>><span class="fvd-insc-ctx__chip-id">#<?= $cid ?></span> <?= htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($fvdInscStatsGen !== null && $fvdInscFilterTorneoId > 0): ?>
        <?php
        $tot = $fvdInscStatsGen['totales'] ?? [];
        $segM = $fvdInscStatsGen['M'] ?? [];
        $segF = $fvdInscStatsGen['F'] ?? [];
        ?>
        <div class="fvd-insc-ctx__stats fvd-insc-ctx__stats--one-row fvd-card" style="padding:10px 14px;margin-bottom:12px">
            <div class="fvd-insc-ctx__stats-row" role="group" aria-label="Resumen asociación y desglose por género">
                <div class="fvd-insc-ctx__stats-labels">
                    <strong class="fvd-insc-ctx__stats-title">Resumen asociación</strong>
                    <span class="fvd-insc-ctx__stats-leyenda fvd-muted">Ins inscripción · Afi afiliación · Car carnet · Tra traspaso · Anu anualidad (con afiliación)</span>
                </div>
                <div class="fvd-insc-ctx__stats-total fvd-insc-ctx__stats-total--row">
                    <span class="fvd-insc-ctx__pill fvd-insc-ctx__pill--total">Total insc.: <strong><?= (int) ($tot['total_inscritos'] ?? 0) ?></strong></span>
                    <span class="fvd-insc-ctx__pill">Afi. <?= (int) ($tot['total_afiliados'] ?? 0) ?></span>
                    <span class="fvd-insc-ctx__pill">Car. <?= (int) ($tot['total_carnets'] ?? 0) ?></span>
                    <span class="fvd-insc-ctx__pill">Tra. <?= (int) ($tot['total_traspasos'] ?? 0) ?></span>
                    <span class="fvd-insc-ctx__pill">Anu. <?= (int) ($tot['total_anualidad'] ?? 0) ?></span>
                </div>
                <div class="fvd-insc-ctx__stats-grid fvd-insc-ctx__stats-grid--row" aria-label="Por género">
                    <?php
                    $fvdInscSegRow = static function (string $label, array $s): void {
                        ?>
                    <div class="fvd-insc-ctx__seg-col fvd-insc-ctx__seg-col--row">
                        <span class="fvd-insc-ctx__seg-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                        <div class="fvd-insc-ctx__seg-badges">
                            <span class="fvd-insc-ctx__mini" title="Inscripción">Ins <?= (int) ($s['inscritos'] ?? 0) ?></span>
                            <span class="fvd-insc-ctx__mini" title="Afiliación">Afi <?= (int) ($s['afiliados'] ?? 0) ?></span>
                            <span class="fvd-insc-ctx__mini" title="Carnet">Car <?= (int) ($s['carnets'] ?? 0) ?></span>
                            <span class="fvd-insc-ctx__mini" title="Traspaso">Tra <?= (int) ($s['traspasos'] ?? 0) ?></span>
                            <span class="fvd-insc-ctx__mini" title="Anualidad">Anu <?= (int) ($s['anualidad'] ?? 0) ?></span>
                        </div>
                    </div>
                        <?php
                    };
                    $fvdInscSegRow('Masculino', $segM);
                    $fvdInscSegRow('Femenino', $segF);
                    ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
