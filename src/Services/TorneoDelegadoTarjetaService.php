<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use PDOException;

require_once __DIR__ . '/ReportService.php';

/**
 * Tarjeta PDF por delegado/asociación con enlace de acceso al torneo (token + datos del evento).
 */
final class TorneoDelegadoTarjetaService
{
    /**
     * @return array{generados: int, sin_dompdf: bool}
     */
    public static function generarTarjetasParaTorneo(PDO $pdo, int $torneoId, string $projectRoot): array
    {
        if ($torneoId <= 0) {
            return ['generados' => 0, 'sin_dompdf' => false];
        }

        DelegadoTorneoNotifService::ensureTable($pdo);
        DelegadoTorneoNotifService::ensureTokenColumns($pdo);

        $stT = $pdo->prepare(
            'SELECT t.torneo, t.nombre, t.lugar, t.fechator, t.clavetor, t.tipo, t.clase, t.rondas,
                o.nombre AS organizador_nombre
             FROM torneosact t
             LEFT JOIN asociaciones o ON o.id = t.organizacion_id
             WHERE t.torneo = :t LIMIT 1'
        );
        $stT->execute([':t' => $torneoId]);
        $torneo = $stT->fetch(PDO::FETCH_ASSOC);
        if ($torneo === false) {
            return ['generados' => 0, 'sin_dompdf' => false];
        }

        $stN = $pdo->prepare(
            'SELECT n.id, n.access_token, n.asociacion_id, n.tarjeta_pdf, d.asociacion_id AS d_asoc, d.email_acceso, d.nombre_contacto,
                    a.nombre AS asoc_nombre
             FROM fvd_delegado_notif_torneo n
             INNER JOIN delegados d ON d.id = n.delegado_id
             LEFT JOIN asociaciones a ON a.id = COALESCE(n.asociacion_id, d.asociacion_id)
             WHERE n.torneo_id = :t AND n.access_token IS NOT NULL AND TRIM(n.access_token) <> \'\''
        );
        $stN->execute([':t' => $torneoId]);
        $rows = $stN->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $uploadDir = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            error_log('[TorneoDelegadoTarjetaService] No uploads dir');

            return ['generados' => 0, 'sin_dompdf' => false];
        }

        $publicBase = self::publicAppBase();
        $nOk = 0;
        $sinDom = false;

        $upd = $pdo->prepare('UPDATE fvd_delegado_notif_torneo SET tarjeta_pdf = :f WHERE id = :id');

        foreach ($rows as $row) {
            $notifId = (int) ($row['id'] ?? 0);
            $token = trim((string) ($row['access_token'] ?? ''));
            if ($notifId <= 0 || $token === '') {
                continue;
            }

            $oldPdf = isset($row['tarjeta_pdf']) ? trim((string) $row['tarjeta_pdf']) : '';
            if ($oldPdf !== '') {
                $oldPath = $uploadDir . basename($oldPdf);
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $fn = 'tarjeta_torneo_' . $torneoId . '_n' . $notifId . '_' . substr(hash('sha256', $token), 0, 10) . '.pdf';
            $dest = $uploadDir . $fn;

            $accesoUrl = $publicBase . '/fvdmasteradmin/delegado_entrar_torneo.php?token=' . rawurlencode($token);
            $html = self::htmlTarjeta($torneo, $row, $accesoUrl, $token);

            $bin = ReportService::renderPdfWithDompdfIfAvailable($html);
            if ($bin === null || $bin === '') {
                $sinDom = true;
                error_log('[TorneoDelegadoTarjetaService] Dompdf no disponible; omito PDF notif ' . $notifId);

                continue;
            }

            if (file_put_contents($dest, $bin) === false) {
                error_log('[TorneoDelegadoTarjetaService] No se pudo escribir ' . $dest);

                continue;
            }

            try {
                $upd->execute([':f' => $fn, ':id' => $notifId]);
                ++$nOk;
            } catch (PDOException $e) {
                error_log('[TorneoDelegadoTarjetaService] UPDATE tarjeta_pdf: ' . $e->getMessage());
            }
        }

        return ['generados' => $nOk, 'sin_dompdf' => $sinDom];
    }

    public static function publicAppBase(): string
    {
        $e = function_exists('env') ? trim((string) env('APP_PUBLIC_URL', '')) : '';
        if ($e !== '') {
            return rtrim($e, '/');
        }

        $basePath = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return ($https ? 'https://' : 'http://') . $host . $basePath;
    }

    /**
     * @param array<string, mixed> $torneo
     * @param array<string, mixed> $notifRow
     */
    private static function htmlTarjeta(array $torneo, array $notifRow, string $accesoUrl, string $token): string
    {
        $nomTorneo = htmlspecialchars((string) ($torneo['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
        $lugar = htmlspecialchars((string) ($torneo['lugar'] ?? ''), ENT_QUOTES, 'UTF-8');
        $fecha = htmlspecialchars(substr((string) ($torneo['fechator'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8');
        $clave = htmlspecialchars((string) ($torneo['clavetor'] ?? ''), ENT_QUOTES, 'UTF-8');
        $tid = (int) ($torneo['torneo'] ?? 0);
        $org = htmlspecialchars((string) ($torneo['organizador_nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
        $asoc = htmlspecialchars((string) ($notifRow['asoc_nombre'] ?? 'Asociación'), ENT_QUOTES, 'UTF-8');
        $emailDel = htmlspecialchars((string) ($notifRow['email_acceso'] ?? ''), ENT_QUOTES, 'UTF-8');
        $contacto = htmlspecialchars((string) ($notifRow['nombre_contacto'] ?? ''), ENT_QUOTES, 'UTF-8');
        $urlEsc = htmlspecialchars($accesoUrl, ENT_QUOTES, 'UTF-8');
        $tokenHint = htmlspecialchars(substr($token, 0, 12) . '…', ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #0f172a; margin: 24px; }
            h1 { font-size: 16px; color: #0c4a6e; margin: 0 0 8px; border-bottom: 2px solid #fbbf24; padding-bottom: 6px; }
            h2 { font-size: 12px; color: #334155; margin: 14px 0 6px; }
            .box { border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; background: #f8fafc; margin: 8px 0; }
            .label { color: #64748b; font-size: 9px; text-transform: uppercase; letter-spacing: 0.06em; }
            .val { font-weight: 700; color: #0f172a; }
            .link { word-break: break-all; font-size: 9px; color: #1d4ed8; }
            .warn { font-size: 9px; color: #b45309; margin-top: 10px; line-height: 1.35; }
        </style></head><body>
        <h1>Federación Venezolana de Dominó — Invitación al torneo</h1>
        <div class="box">
            <p class="label">Asociación destinataria</p>
            <p class="val">' . $asoc . '</p>
            <p class="label" style="margin-top:8px">Cuenta delegado (acceso al portal)</p>
            <p class="val">' . $emailDel . ($contacto !== '' ? ' · ' . $contacto : '') . '</p>
        </div>
        <h2>Datos del torneo</h2>
        <div class="box">
            <p><span class="label">Nombre</span><br><span class="val">' . $nomTorneo . '</span></p>
            <p><span class="label">ID interno</span> <span class="val">#' . $tid . '</span>
            &nbsp;·&nbsp; <span class="label">Clave</span> <span class="val">' . $clave . '</span></p>
            <p><span class="label">Fecha</span> <span class="val">' . $fecha . '</span>
            &nbsp;·&nbsp; <span class="label">Lugar</span> <span class="val">' . $lugar . '</span></p>
            <p><span class="label">Organiza</span> <span class="val">' . $org . '</span></p>
        </div>
        <h2>Enlace de acceso al panel del torneo</h2>
        <div class="box" style="background:#eff6ff;border-color:#93c5fd">
            <p class="label">Abra este enlace después de iniciar sesión en FVD Master Admin con el usuario delegado indicado arriba.</p>
            <p class="link">' . $urlEsc . '</p>
            <p class="label" style="margin-top:8px">Referencia del token (no comparta)</p>
            <p class="val" style="font-size:10px;font-family:monospace">' . $tokenHint . '</p>
        </div>
        <p class="warn"><strong>Seguridad:</strong> este enlace solo funciona para la cuenta delegado vinculada a «' . $asoc . '». No reenvíe la tarjeta a otras asociaciones. Cada club dispone de su propia tarjeta y token.</p>
        </body></html>';
    }
}
