<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use Throwable;

/**
 * Entrada al panel maestro: validación de tokens de notificación, contexto de torneo
 * y reglas de sesión. Sin redirecciones HTTP (la SPA elige la vista según rol).
 */
final class MasterPanelEntryService
{
    /** Misma cadena que en seed SQL / pruebas (vista embebida delegado). */
    public const TEST_DELEGADO_TOKEN = 'TOKEN_PRUEBA_MIRANDA_2026';

    /**
     * Resuelve token en query o sesión, valida delegado vs BD y fija contexto de torneo.
     * Para admin gral sin token de delegado, limpia el modo prueba delegado.
     */
    public static function bootstrapSession(PDO $pdo): void
    {
        if (!class_exists(\AuthService::class, false)) {
            require_once dirname(__DIR__, 2) . '/fvdmasteradmin/services/AuthService.php';
        }
        if (!class_exists(DelegadoTorneoNotifService::class, false)) {
            require_once __DIR__ . '/DelegadoTorneoNotifService.php';
        }

        $tokenMp = trim((string) ($_GET['token'] ?? ''));
        if ($tokenMp === '') {
            $tokenMp = trim((string) ($_SESSION['fvd_master_delegado_notif_token'] ?? ''));
        }

        $isDelegadoPanel = ($tokenMp === self::TEST_DELEGADO_TOKEN);

        if (\AuthService::isAdminGral()) {
            if ($isDelegadoPanel) {
                $_SESSION['fvd_master_delegado_notif_token'] = self::TEST_DELEGADO_TOKEN;
                $tidAd = isset($_GET['ctx_torneo']) ? (int) $_GET['ctx_torneo'] : 0;
                if ($tidAd > 0) {
                    \AuthService::setDelegadoTorneoContext($tidAd);
                }
            } else {
                unset($_SESSION['fvd_master_delegado_notif_token'], $_GET['token']);
            }
        } elseif ($tokenMp !== '') {
            if (!\AuthService::isDelegadoAsociacion()) {
                http_response_code(403);
                header('Content-Type: text/plain; charset=UTF-8');
                echo 'El enlace con token solo aplica a delegados de asociación.';
                exit;
            }

            $notifMp = DelegadoTorneoNotifService::notificacionPorAccessToken($pdo, $tokenMp);
            if (
                $notifMp === null
                || (int) ($notifMp['delegado_id'] ?? 0) !== (int) \AuthService::userId()
            ) {
                http_response_code(403);
                header('Content-Type: text/plain; charset=UTF-8');
                echo 'Token inválido o no corresponde a su cuenta de delegado.';
                exit;
            }
            $_SESSION['fvd_master_delegado_notif_token'] = $tokenMp;
            $tidCtx = (int) ($notifMp['torneo_id'] ?? 0);
            if ($tidCtx > 0) {
                \AuthService::setDelegadoTorneoContext($tidCtx);
                try {
                    $stG = $pdo->prepare('SELECT COALESCE(grupo_evento_id, 0) FROM torneosact WHERE torneo = :t LIMIT 1');
                    $stG->execute([':t' => $tidCtx]);
                    $gTok = (int) $stG->fetchColumn();
                    if ($gTok > 0) {
                        \AuthService::setDelegadoCampeonatoGrupo($gTok);
                    }
                } catch (Throwable $e) {
                    /* sin grupo_evento_id o error puntual */
                }
            }
        } else {
            unset($_SESSION['fvd_master_delegado_notif_token']);
            \AuthService::requireRoles([
                \AuthService::ROLE_FVD_ADMIN,
                \AuthService::ROLE_DELEGADO_ASOC,
            ]);
        }

        if (\AuthService::isAdminGral()) {
            \AuthService::clearAdminPortalDelegadoContext();
        }
    }
}
