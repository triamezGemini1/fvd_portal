<?php

declare(strict_types=1);

namespace FvdPortal\Services;

/**
 * Generación de reportes (CSV ligero; PDF vía Dompdf si está instalado).
 * La presentación de celdas sigue la misma lógica que la vista de listado.
 */
final class ReportService
{
    private const ZIP_ROW_THRESHOLD = 500;

    /**
     * Texto vacío o solo espacios → guion tipográfico (como en la tabla).
     */
    public static function dash(?string $value): string
    {
        $em = "\xE2\x80\x94";
        if ($value === null) {
            return $em;
        }
        $t = trim($value);

        return $t === '' ? $em : $t;
    }

    /**
     * Columna “foto”: si hay archivo se muestra el nombre; si no, el nombre del atleta (regla de la vista).
     */
    public static function atletaFotoONombre(array $row): string
    {
        $foto = isset($row['foto']) ? trim((string) $row['foto']) : '';
        if ($foto !== '') {
            return $foto;
        }
        $nom = isset($row['nombre']) ? trim((string) $row['nombre']) : '';

        return $nom !== '' ? $nom : "\xE2\x80\x94";
    }

    /**
     * Nº FVD: igual que la vista (>0 o guion).
     */
    public static function atletaNumFvdTexto(array $row): string
    {
        $nf = (int) ($row['numfvd'] ?? 0);

        return $nf > 0 ? (string) $nf : "\xE2\x80\x94";
    }

    /**
     * @param list<array<string, mixed>> $rows Filas ya filtradas (p. ej. desde QueryHelper).
     */
    public static function generateAtletasCsv(array $rows, bool $omitAsociacionColumn = false): string
    {
        require_once __DIR__ . '/FvdAdminService.php';

        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            return '';
        }

        fwrite($fh, "\xEF\xBB\xBF");

        $headers = [
            'ID',
            'Foto / nombre',
            'Cédula',
            'Nombre',
            'Sexo',
            'Nº FVD',
            'Categoría',
            'Estatus',
        ];
        if (!$omitAsociacionColumn) {
            array_splice($headers, 6, 0, ['Asociación']);
        }
        fputcsv($fh, $headers, ';');

        foreach ($rows as $r) {
            $rowOut = [
                (string) ((int) ($r['id'] ?? 0)),
                self::atletaFotoONombre($r),
                self::dash(isset($r['cedula']) ? (string) $r['cedula'] : null),
                self::dash(isset($r['nombre']) ? (string) $r['nombre'] : null),
                (string) ((int) ($r['sexo'] ?? 0)),
                self::atletaNumFvdTexto($r),
            ];
            if (!$omitAsociacionColumn) {
                $rowOut[] = self::dash(isset($r['asociacion_nombre']) ? (string) $r['asociacion_nombre'] : null);
            }
            $rowOut[] = \FvdAdminService::atletasCategoriaEtiquetaPorCodigo((int) ($r['categ'] ?? 0));
            $rowOut[] = \FvdAdminService::atletasEstatusEtiqueta((int) ($r['estatus'] ?? 0));
            fputcsv($fh, $rowOut, ';');
        }

        rewind($fh);
        $out = stream_get_contents($fh);
        fclose($fh);

        return $out !== false ? $out : '';
    }

    /**
     * HTML listo para Dompdf o impresión; sin imágenes remotas (columna foto como texto).
     *
     * @param list<array<string, mixed>> $rows
     * @param string $encabezadoHtml Bloque opcional sobre el título (p. ej. logo + nombre de asociación)
     */
    public static function buildAtletasPdfHtml(
        array $rows,
        string $title,
        string $encabezadoHtml = '',
        bool $omitAsociacionColumn = false
    ): string {
        require_once __DIR__ . '/FvdAdminService.php';

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9pt; color: #111; }
        h1 { font-size: 14pt; margin: 0 0 10px; }
        .fvd-rep-asoc-head { display: table; width: 100%; margin: 0 0 12px; border-bottom: 2px solid #003366; padding-bottom: 8px; }
        .fvd-rep-asoc-head__logo { display: table-cell; width: 90px; vertical-align: middle; }
        .fvd-rep-asoc-head__logo img { max-height: 56px; max-width: 84px; object-fit: contain; }
        .fvd-rep-asoc-head__txt { display: table-cell; vertical-align: middle; text-align: center; font-size: 13pt; font-weight: bold; color: #003366; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #003366; color: #fff; }
        tr:nth-child(even) td { background: #f5f5f5; }
    </style>
</head>
<body>
    <?= $encabezadoHtml ?>
    <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <p style="font-size:8pt;color:#444;margin:0 0 8px;">Generado: <?= htmlspecialchars(date('Y-m-d H:i'), ENT_QUOTES, 'UTF-8') ?> · <?= count($rows) ?> registro(s)</p>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Foto / nombre</th>
            <th>Cédula</th>
            <th>Nombre</th>
            <th>Sexo</th>
            <th>Nº FVD</th>
            <?php if (!$omitAsociacionColumn): ?><th>Asociación</th><?php endif; ?>
            <th>Categ.</th>
            <th>Estatus</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= (int) ($r['id'] ?? 0) ?></td>
                <td><?= htmlspecialchars(self::atletaFotoONombre($r), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(self::dash(isset($r['cedula']) ? (string) $r['cedula'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(self::dash(isset($r['nombre']) ? (string) $r['nombre'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= (int) ($r['sexo'] ?? 0) ?></td>
                <td><?= htmlspecialchars(self::atletaNumFvdTexto($r), ENT_QUOTES, 'UTF-8') ?></td>
                <?php if (!$omitAsociacionColumn): ?>
                <td><?= htmlspecialchars(self::dash(isset($r['asociacion_nombre']) ? (string) $r['asociacion_nombre'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                <?php endif; ?>
                <td><?= htmlspecialchars(\FvdAdminService::atletasCategoriaEtiquetaPorCodigo((int) ($r['categ'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(\FvdAdminService::atletasEstatusEtiqueta((int) ($r['estatus'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Si existe Dompdf (composer), devuelve binario PDF; si no, null (usar HTML adjunto).
     */
    public static function renderPdfWithDompdfIfAvailable(string $html): ?string
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            return null;
        }

        require_once $autoload;
        if (!class_exists('\Dompdf\Dompdf')) {
            return null;
        }

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Si el volumen supera el umbral, empaqueta el archivo en un ZIP (ahorro en transferencia).
     *
     * @return array{body: string, filename: string, mime: string}
     */
    public static function packageWithOptionalZip(
        string $fileBody,
        string $downloadFilename,
        string $mimeWithoutZip,
        int $rowCount,
        int $threshold = self::ZIP_ROW_THRESHOLD
    ): array {
        if ($rowCount <= $threshold || !extension_loaded('zip')) {
            return [
                'body'     => $fileBody,
                'filename' => $downloadFilename,
                'mime'     => $mimeWithoutZip,
            ];
        }

        $zip = new \ZipArchive();
        $tmpBase = tempnam(sys_get_temp_dir(), 'fvdrep');
        if ($tmpBase === false) {
            return [
                'body'     => $fileBody,
                'filename' => $downloadFilename,
                'mime'     => $mimeWithoutZip,
            ];
        }

        @unlink($tmpBase);
        $zipPath = $tmpBase . '.zip';

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return [
                'body'     => $fileBody,
                'filename' => $downloadFilename,
                'mime'     => $mimeWithoutZip,
            ];
        }

        $zip->addFromString($downloadFilename, $fileBody);
        $zip->close();

        $bin = @file_get_contents($zipPath);
        @unlink($zipPath);

        if ($bin === false || $bin === '') {
            return [
                'body'     => $fileBody,
                'filename' => $downloadFilename,
                'mime'     => $mimeWithoutZip,
            ];
        }

        $zipName = preg_replace('/\.[^.\/\\\\]+$/', '', $downloadFilename) . '.zip';

        return [
            'body'     => $bin,
            'filename' => $zipName !== '' ? $zipName : 'atletas_export.zip',
            'mime'     => 'application/zip',
        ];
    }
}
