<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/fvdmasteradmin/services/AuthService.php';
require_once __DIR__ . '/DelegadoTorneoNotifService.php';
require_once __DIR__ . '/TorneoDelegadoTarjetaService.php';

/**
 * Operaciones de torneos centralizadas (notificaciones a delegados, etc.).
 */
final class TorneoService
{
    /**
     * Envía correo masivo a delegados activos con datos del torneo.
     * Registra notificación web (panel delegado) con el PDF de invitación del torneo.
     * Opcional: URL de WhatsApp (api.whatsapp.com) con el mismo texto.
     *
     * @return array{emails_enviados: int, emails_total: int, wa_url: string, web_notif_delegados: int}
     */
    public static function enviarInvitacionMasiva(PDO $pdo, int $torneoId): array
    {
        if (!\AuthService::isSuperAdmin()) {
            throw new RuntimeException('Solo el administrador general puede enviar notificaciones masivas a delegados.');
        }

        if ($torneoId <= 0) {
            throw new RuntimeException('Torneo no válido.');
        }

        $st = $pdo->prepare('SELECT torneo, nombre, lugar, fechator, tipo, clase, rondas FROM torneosact WHERE torneo = :t LIMIT 1');
        $st->execute([':t' => $torneoId]);
        $t = $st->fetch(PDO::FETCH_ASSOC);
        if ($t === false) {
            throw new RuntimeException('Torneo no encontrado.');
        }

        $chk = $pdo->query("SHOW TABLES LIKE 'delegados'");
        if ($chk === false || $chk->fetchColumn() === false) {
            throw new RuntimeException('La tabla delegados no existe. Ejecute install_delegados.sql.');
        }

        $stE = $pdo->query(
            'SELECT email_acceso FROM delegados WHERE activo = 1 AND email_acceso IS NOT NULL AND TRIM(email_acceso) <> \'\''
        );
        $emails = [];
        if ($stE !== false) {
            while ($row = $stE->fetch(PDO::FETCH_ASSOC)) {
                $e = strtolower(trim((string) ($row['email_acceso'] ?? '')));
                if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                    $emails[$e] = true;
                }
            }
        }
        $lista = array_keys($emails);

        $fecha = substr((string) ($t['fechator'] ?? ''), 0, 10);
        $bodyLines = [
            'FVD — Convocatoria / torneo',
            '',
            'Nombre: ' . (string) ($t['nombre'] ?? ''),
            'Fecha: ' . $fecha,
            'Lugar: ' . (string) ($t['lugar'] ?? ''),
            'ID torneo: ' . (int) ($t['torneo'] ?? 0),
            '',
            'Ingrese al panel FVD Master Admin (usuario delegado): verá el aviso con el PDF y el enlace al panel de este torneo.',
        ];
        $body = implode("\r\n", $bodyLines);
        $subj = 'FVD — Torneo: ' . (string) ($t['nombre'] ?? 'Torneo');
        $headers = "MIME-Version: 1.0\r\nContent-type: text/plain; charset=UTF-8\r\n";

        $ok = 0;
        foreach ($lista as $to) {
            if (@mail($to, '=?UTF-8?B?' . base64_encode($subj) . '?=', $body, $headers)) {
                ++$ok;
            } else {
                error_log('[TorneoService] mail falló para ' . $to);
            }
        }

        $waText = implode("\n", $bodyLines);
        $waUrl = 'https://api.whatsapp.com/send?text=' . rawurlencode($waText);

        DelegadoTorneoNotifService::crearNotificacionesParaTorneo($pdo, $torneoId);
        $root = dirname(__DIR__, 2);
        TorneoDelegadoTarjetaService::generarTarjetasParaTorneo($pdo, $torneoId, $root);
        $stNd = $pdo->query('SELECT COUNT(*) FROM delegados WHERE activo = 1');
        $webN = $stNd !== false ? (int) $stNd->fetchColumn() : 0;

        return [
            'emails_enviados'      => $ok,
            'emails_total'         => count($lista),
            'wa_url'               => $waUrl,
            'web_notif_delegados'  => $webN,
        ];
    }
}
