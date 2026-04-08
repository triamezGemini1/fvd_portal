<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use PDOException;

/**
 * Consultas y HTML para reportes del módulo «Inscripciones» (torneo + asociación).
 */
final class InscripcionesTorneoReportService
{
    public const TIPOS_ATLETAS = ['inscritos', 'carnets', 'afiliados'];

    /**
     * Atletas del club en un torneo según marca (inscripción, carnet o afiliación).
     *
     * @return list<array<string, mixed>>
     */
    public static function atletasParaReporte(PDO $pdo, int $torneoId, int $asociacionId, string $tipo): array
    {
        if ($torneoId <= 0 || $asociacionId <= 0) {
            return [];
        }
        if (!in_array($tipo, self::TIPOS_ATLETAS, true)) {
            return [];
        }

        if ($tipo === 'inscritos') {
            $col = 'inscripcion';
        } elseif ($tipo === 'carnets') {
            $col = 'carnet';
        } else {
            $col = 'afiliacion';
        }

        $legacy = dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
        if (!\class_exists('QueryHelper', false)) {
            require_once $legacy;
        }

        $params = [':tid' => $torneoId, ':aid' => $asociacionId];
        $dataSql = 'SELECT a.id, a.foto, a.cedula, a.nombre, a.sexo, a.numfvd, a.estatus, a.categ,
    a.carnet, a.traspaso,
    s.nombre AS asociacion_nombre
    FROM atletas a
    LEFT JOIN asociaciones s ON a.asociacion = s.id
    WHERE a.torneo_id = :tid AND a.asociacion = :aid
    AND COALESCE(a.`' . $col . '`, 0) = 1 ';
        $countSql = 'SELECT COUNT(*) FROM atletas a WHERE a.torneo_id = :tid AND a.asociacion = :aid AND COALESCE(a.`' . $col . '`, 0) = 1 ';
        \QueryHelper::applyAsociacionScope($countSql, $dataSql, 'a.asociacion', $params);
        $dataSql .= ' ORDER BY a.nombre ASC, a.numfvd ASC';

        try {
            $st = $pdo->prepare($dataSql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[InscripcionesTorneoReportService] atletasParaReporte: ' . $e->getMessage());

            return [];
        }

        return is_array($rows) ? $rows : [];
    }

    /**
     * HTML para PDF: deuda por concepto, listas por concepto, pagos y saldo.
     *
     * @param array<string, mixed>|null $deudaRow
     * @param list<array<string, mixed>> $pagos
     * @param array<string, list<array{atleta_id:int,numfvd:int,nombre:string,cedula:string}>> $detalleConceptos
     */
    public static function buildEstadoCuentaHtml(
        string $torneoNombre,
        string $asocNombre,
        ?array $deudaRow,
        array $pagos,
        array $detalleConceptos,
        float $pagadoEur,
        float $saldoEur,
        bool $tieneMontoTotalEur,
        float $pagadoBs = 0.0,
        float $saldoBs = 0.0
    ): string {
        $fmtEur = static fn (float $v): string => number_format($v, 2, ',', '.');
        $fmtBs = static fn (float $v): string => number_format($v, 2, ',', '.');

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estado de cuenta</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9pt; color: #111; }
        h1 { font-size: 14pt; margin: 0 0 8px; }
        h2 { font-size: 11pt; margin: 14px 0 6px; border-bottom: 1px solid #003366; color: #003366; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #333; padding: 4px 6px; text-align: left; }
        th { background: #003366; color: #fff; }
        tr:nth-child(even) td { background: #f5f5f5; }
        .fvd-sum { font-weight: bold; background: #e8eef5 !important; }
        .muted { color: #444; font-size: 8pt; }
    </style>
</head>
<body>
    <h1>Estado de cuenta — finanzas del torneo</h1>
    <p class="muted">Torneo: <strong><?= htmlspecialchars($torneoNombre, ENT_QUOTES, 'UTF-8') ?></strong><br>
    Asociación: <strong><?= htmlspecialchars($asocNombre, ENT_QUOTES, 'UTF-8') ?></strong><br>
    Generado: <?= htmlspecialchars(date('Y-m-d H:i'), ENT_QUOTES, 'UTF-8') ?></p>

    <h2>1. Deuda generada por concepto</h2>
    <?php if ($deudaRow === null): ?>
        <p>No hay fila de deuda en <code>deuda_asociaciones</code> para este torneo y asociación. Genere o actualice la deuda desde el módulo de deudas.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr><th>Concepto</th><th>Cantidad</th><th>Monto (Bs ref.)</th></tr>
            </thead>
            <tbody>
            <tr><td>Inscripciones</td><td><?= (int) ($deudaRow['total_inscritos'] ?? 0) ?></td><td><?= $fmtBs((float) ($deudaRow['monto_inscritos'] ?? 0)) ?></td></tr>
            <tr><td>Afiliación</td><td><?= (int) ($deudaRow['total_afiliados'] ?? 0) ?></td><td><?= $fmtBs((float) ($deudaRow['monto_afiliados'] ?? 0)) ?></td></tr>
            <tr><td>Carnets</td><td><?= (int) ($deudaRow['total_carnets'] ?? 0) ?></td><td><?= $fmtBs((float) ($deudaRow['monto_carnets'] ?? 0)) ?></td></tr>
            <tr><td>Traspasos</td><td><?= (int) ($deudaRow['total_traspasos'] ?? 0) ?></td><td><?= $fmtBs((float) ($deudaRow['monto_traspasos'] ?? 0)) ?></td></tr>
            <tr><td>Anualidad</td><td><?= (int) ($deudaRow['total_anualidad'] ?? 0) ?></td><td><?= $fmtBs((float) ($deudaRow['monto_anualidad'] ?? 0)) ?></td></tr>
            <tr class="fvd-sum"><td>Total (Bs)</td><td>—</td><td><?= $fmtBs((float) ($deudaRow['monto_total'] ?? 0)) ?></td></tr>
            <?php if ($tieneMontoTotalEur): ?>
            <tr class="fvd-sum"><td>Total deuda (EUR)</td><td>—</td><td><?= $fmtEur((float) ($deudaRow['monto_total_eur'] ?? 0)) ?> €</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <h2>2. Detalle por concepto (atletas)</h2>
        <?php
        $etiquetas = [
            'inscripciones' => 'Inscripciones',
            'afiliacion' => 'Afiliación',
            'carnets' => 'Carnets',
            'traspasos' => 'Traspasos',
            'anualidad' => 'Anualidad',
        ];
        foreach ($etiquetas as $key => $lab):
            $lista = $detalleConceptos[$key] ?? [];
            if ($lista === []) {
                continue;
            }
            ?>
            <h3 style="font-size:10pt;margin:10px 0 4px"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?> (<?= count($lista) ?>)</h3>
            <table>
                <thead><tr><th>Nº FVD</th><th>Cédula</th><th>Nombre</th></tr></thead>
                <tbody>
                <?php foreach ($lista as $r): ?>
                    <tr>
                        <td><?= (int) ($r['numfvd'] ?? 0) ?></td>
                        <td><?= htmlspecialchars((string) ($r['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
    <?php endif; ?>

    <h2>3. Pagos registrados (recibos)</h2>
    <?php if ($pagos === []): ?>
        <p class="muted">Sin pagos registrados en <code>relacion_pagos</code> para este torneo y asociación.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr><th>Fecha</th><th>Seq.</th><th>Tipo</th><th>EUR (contable)</th><th>Bs (ref.)</th><th>Ref.</th></tr>
            </thead>
            <tbody>
            <?php foreach ($pagos as $p): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($p['fecha'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($p['secuencia'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($p['tipo_pago'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $fmtEur((float) ($p['monto_dolares'] ?? 0)) ?></td>
                    <td><?= $fmtBs((float) ($p['monto_total'] ?? 0)) ?></td>
                    <td><?= htmlspecialchars((string) ($p['referencia'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2>4. Resumen</h2>
    <?php if ($tieneMontoTotalEur): ?>
    <table>
        <tbody>
        <tr><th>Total pagado (EUR)</th><td><?= $fmtEur($pagadoEur) ?> €</td></tr>
        <tr><th>Saldo pendiente (EUR)</th><td><?= $fmtEur($saldoEur) ?> €</td></tr>
        </tbody>
    </table>
    <p class="muted">Saldo en EUR: deuda <code>monto_total_eur</code> menos suma de <code>monto_dolares</code> en recibos.</p>
    <?php else: ?>
    <table>
        <tbody>
        <tr><th>Total pagado (Bs ref.)</th><td><?= $fmtBs($pagadoBs) ?></td></tr>
        <tr><th>Saldo pendiente (Bs ref.)</th><td><?= $fmtBs($saldoBs) ?></td></tr>
        </tbody>
    </table>
    <p class="muted">Sin columna EUR en deuda: saldo aproximado en bolívares de referencia (<code>monto_total</code> menos suma de <code>monto_total</code> en recibos).</p>
    <?php endif; ?>
</body>
</html>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Solo PDF de tabla de pagos (misma sección 3 que estado de cuenta).
     *
     * @param list<array<string, mixed>> $pagos
     */
    public static function buildPagosListadoHtml(
        string $torneoNombre,
        string $asocNombre,
        array $pagos
    ): string {
        $fmtEur = static fn (float $v): string => number_format($v, 2, ',', '.');
        $fmtBs = static fn (float $v): string => number_format($v, 2, ',', '.');

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagos detallados</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9pt; color: #111; }
        h1 { font-size: 14pt; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #333; padding: 4px 6px; text-align: left; }
        th { background: #003366; color: #fff; }
        tr:nth-child(even) td { background: #f5f5f5; }
        .muted { color: #444; font-size: 8pt; }
    </style>
</head>
<body>
    <h1>Reporte de pagos — <?= htmlspecialchars($torneoNombre, ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="muted">Asociación: <strong><?= htmlspecialchars($asocNombre, ENT_QUOTES, 'UTF-8') ?></strong> · <?= htmlspecialchars(date('Y-m-d H:i'), ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($pagos === []): ?>
        <p>Sin pagos.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr><th>Fecha</th><th>Seq.</th><th>Tipo</th><th>EUR</th><th>Bs</th><th>Ref.</th></tr>
            </thead>
            <tbody>
            <?php foreach ($pagos as $p): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($p['fecha'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($p['secuencia'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($p['tipo_pago'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $fmtEur((float) ($p['monto_dolares'] ?? 0)) ?></td>
                    <td><?= $fmtBs((float) ($p['monto_total'] ?? 0)) ?></td>
                    <td><?= htmlspecialchars((string) ($p['referencia'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Deuda por concepto sin tabla de atletas (solo montos).
     *
     * @param array<string, mixed>|null $deudaRow
     */
    public static function buildDeudaConceptosHtml(
        string $torneoNombre,
        string $asocNombre,
        ?array $deudaRow,
        bool $tieneMontoTotalEur
    ): string {
        $fmtBs = static fn (float $v): string => number_format($v, 2, ',', '.');
        $fmtEur = static fn (float $v): string => number_format($v, 2, ',', '.');

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Deuda por concepto</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9pt; color: #111; }
        h1 { font-size: 14pt; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px 6px; text-align: left; }
        th { background: #003366; color: #fff; }
        .muted { color: #444; font-size: 8pt; }
    </style>
</head>
<body>
    <h1>Deuda generada por concepto</h1>
    <p class="muted">Torneo: <strong><?= htmlspecialchars($torneoNombre, ENT_QUOTES, 'UTF-8') ?></strong><br>
    Asociación: <strong><?= htmlspecialchars($asocNombre, ENT_QUOTES, 'UTF-8') ?></strong><br>
    <?= htmlspecialchars(date('Y-m-d H:i'), ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($deudaRow === null): ?>
        <p>No hay deuda registrada.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr><th>Concepto</th><th>Cantidad</th><th>Monto (Bs ref.)</th></tr>
            </thead>
            <tbody>
            <tr><td>Inscripciones</td><td><?= (int) ($deudaRow['total_inscritos'] ?? 0) ?></td><td><?= $fmtBs((float) ($deudaRow['monto_inscritos'] ?? 0)) ?></td></tr>
            <tr><td>Afiliación</td><td><?= (int) ($deudaRow['total_afiliados'] ?? 0) ?></td><td><?= $fmtBs((float) ($deudaRow['monto_afiliados'] ?? 0)) ?></td></tr>
            <tr><td>Carnets</td><td><?= (int) ($deudaRow['total_carnets'] ?? 0) ?></td><td><?= $fmtBs((float) ($deudaRow['monto_carnets'] ?? 0)) ?></td></tr>
            <tr><td>Traspasos</td><td><?= (int) ($deudaRow['total_traspasos'] ?? 0) ?></td><td><?= $fmtBs((float) ($deudaRow['monto_traspasos'] ?? 0)) ?></td></tr>
            <tr><td>Anualidad</td><td><?= (int) ($deudaRow['total_anualidad'] ?? 0) ?></td><td><?= $fmtBs((float) ($deudaRow['monto_anualidad'] ?? 0)) ?></td></tr>
            <tr><td><strong>Total Bs</strong></td><td>—</td><td><strong><?= $fmtBs((float) ($deudaRow['monto_total'] ?? 0)) ?></strong></td></tr>
            <?php if ($tieneMontoTotalEur): ?>
            <tr><td><strong>Total EUR</strong></td><td>—</td><td><strong><?= $fmtEur((float) ($deudaRow['monto_total_eur'] ?? 0)) ?> €</strong></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
        <?php

        return (string) ob_get_clean();
    }
}
