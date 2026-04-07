<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/fvdmasteradmin/services/AuthService.php';

/**
 * Invitaciones de registro (atleta / club): token opaco, expiración y un solo uso.
 */
final class InvitacionService
{
    private const DDL = <<<'SQL'
CREATE TABLE IF NOT EXISTS `fvd_invitaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token` varchar(64) NOT NULL COMMENT 'Token opaco (hex); único en URL',
  `tipo` enum('atleta','club') NOT NULL DEFAULT 'atleta',
  `documento` varchar(48) NOT NULL COMMENT 'Cédula o RIF normalizado (anti-duplicado)',
  `email_destino` varchar(255) DEFAULT NULL,
  `asociacion_emisor_id` int DEFAULT NULL COMMENT 'Delegado: su asociación; FVD: NULL',
  `estado` enum('pendiente','aceptada') NOT NULL DEFAULT 'pendiente',
  `un_solo_uso` tinyint(1) NOT NULL DEFAULT 1,
  `usada_en` datetime DEFAULT NULL,
  `expira_en` datetime NOT NULL,
  `titulo_memo` varchar(255) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por_user_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fvd_inv_token` (`token`),
  KEY `idx_fvd_inv_expira` (`expira_en`),
  KEY `idx_fvd_inv_emisor` (`asociacion_emisor_id`),
  KEY `idx_fvd_inv_estado` (`estado`),
  KEY `idx_fvd_inv_doc_tipo` (`tipo`,`documento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    public static function ensureTable(PDO $pdo): void
    {
        try {
            $pdo->exec(self::DDL);
        } catch (PDOException $e) {
            error_log('[InvitacionService] ensureTable: ' . $e->getMessage());
        }
    }

    /**
     * Token opaco (64 hex); no usa id incremental en la URL.
     */
    public static function generarTokenSeguro(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function normalizarCedula(string $raw): string
    {
        $d = preg_replace('/\D+/', '', $raw);

        return $d !== null && $d !== '' ? $d : '';
    }

    public static function normalizarRif(string $raw): string
    {
        $u = strtoupper(preg_replace('/\s+/', '', trim($raw)));
        $u = str_replace(['-', '.'], '', $u);

        return $u;
    }

    public static function existeCedulaAtleta(PDO $pdo, string $cedulaNormalizada): bool
    {
        if ($cedulaNormalizada === '') {
            return false;
        }
        $sql = 'SELECT id FROM atletas WHERE REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(cedula)), \'-\', \'\'), \'.\', \'\'), \' \', \'\'), \'V\', \'\') = :d LIMIT 1';
        $st = $pdo->prepare($sql);
        $st->execute([':d' => $cedulaNormalizada]);

        return $st->fetchColumn() !== false;
    }

    public static function existeRifAsociacion(PDO $pdo, string $rifNormalizado): bool
    {
        if ($rifNormalizado === '') {
            return false;
        }
        $sql = 'SELECT id FROM asociaciones WHERE numreg IS NOT NULL AND TRIM(numreg) <> \'\' AND '
            . 'REPLACE(REPLACE(REPLACE(UPPER(TRIM(numreg)), \'-\', \'\'), \'.\', \'\'), \' \', \'\') = :r LIMIT 1';
        $st = $pdo->prepare($sql);
        $st->execute([':r' => $rifNormalizado]);

        return $st->fetchColumn() !== false;
    }

    /**
     * Estado para UI: pendiente (activa), aceptada (usada), expirada.
     */
    public static function estadoVisual(array $row): string
    {
        $usada = !empty($row['usada_en']) || (($row['estado'] ?? '') === 'aceptada');
        if ($usada) {
            return 'aceptada';
        }
        $exp = isset($row['expira_en']) ? strtotime((string) $row['expira_en']) : false;
        if ($exp !== false && $exp < time()) {
            return 'expirada';
        }

        return 'pendiente';
    }

    /**
     * @return array<string, mixed> misma fila + _invite_url + _estado_badge
     */
    public static function filaVista(array $row): array
    {
        $out = $row;
        $out['_invite_url'] = self::urlPublicaInvitacion((string) ($row['token'] ?? ''));
        $out['_estado_badge'] = self::etiquetaEstado($row);

        return $out;
    }

    /**
     * @return array{estado:string, badge_class:string, label:string}
     */
    public static function etiquetaEstado(array $row): array
    {
        $ui = self::estadoVisual($row);
        if ($ui === 'pendiente') {
            return ['estado' => 'pendiente', 'badge_class' => 'fvd-inv-badge fvd-inv-badge--activa', 'label' => 'Activa'];
        }
        if ($ui === 'aceptada') {
            return ['estado' => 'aceptada', 'badge_class' => 'fvd-inv-badge fvd-inv-badge--usada', 'label' => 'Usada'];
        }

        return ['estado' => 'expirada', 'badge_class' => 'fvd-inv-badge fvd-inv-badge--expirada', 'label' => 'Expirada'];
    }

    public static function urlPublicaInvitacion(string $token): string
    {
        if (!function_exists('full_url')) {
            require_once dirname(__DIR__, 2) . '/config/paths.php';
        }

        return full_url('invitacion.php?token=' . rawurlencode($token));
    }

    /**
     * Envío de notificación (correo). La lógica vive aquí, no en index del módulo.
     *
     * @return bool true si se omitió por falta de correo o si mail() devolvió éxito
     */
    public static function sendInvitation(
        string $emailDestino,
        string $inviteUrl,
        string $tipoEtiqueta,
        ?string $memo = null
    ): bool {
        $to = trim($emailDestino);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        $subj = 'Invitación FVD — ' . $tipoEtiqueta;
        $lines = [
            'Ha recibido una invitación para completar su registro en la Federación Venezolana de Damas.',
            '',
            'Enlace (copie y pegue en el navegador):',
            $inviteUrl,
            '',
        ];
        if ($memo !== null && trim($memo) !== '') {
            $lines[] = 'Nota: ' . trim($memo);
            $lines[] = '';
        }
        $lines[] = 'Si no solicitó este correo, puede ignorarlo.';
        $body = implode("\r\n", $lines);

        $headers = "MIME-Version: 1.0\r\nContent-type: text/plain; charset=UTF-8\r\n";

        $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subj) . '?=', $body, $headers);
        if (!$ok) {
            error_log('[InvitacionService] sendInvitation: mail() falló para ' . $to);
        }

        return $ok;
    }

    /**
     * Filtros para {@see QueryHelper::selectPaginado}('fvd_invitaciones', ...).
     *
     * @return array<string, scalar|null>
     */
    public static function filtrosListado(string $estadoUi): array
    {
        $estadoUi = trim($estadoUi);
        if (!in_array($estadoUi, ['pendiente', 'aceptada', 'expirada', ''], true)) {
            $estadoUi = '';
        }
        $f = ['__estado' => $estadoUi];
        if (\AuthService::role() === \AuthService::ROLE_ASO_ADMIN || \AuthService::isDelegadoAsociacion()) {
            $aid = \AuthService::idAsociacion();
            $f['__emisor_id'] = ($aid !== null && $aid > 0) ? $aid : -1;
        }

        return $f;
    }

    /**
     * Crea invitación tras validar duplicados en BD y rol.
     *
     * @return array{url:string, id:int}
     */
    public static function crearInvitacion(
        PDO $pdo,
        string $tipo,
        string $documentoRaw,
        ?string $emailDestino,
        int $validezDias,
        ?string $tituloMemo
    ): array {
        if (!\AuthService::checkAccess([
            \AuthService::ROLE_FVD_ADMIN,
            \AuthService::ROLE_ASO_ADMIN,
            \AuthService::ROLE_DELEGADO_ASOC,
        ])) {
            throw new RuntimeException('Solo administrador o delegado pueden crear invitaciones.');
        }

        self::ensureTable($pdo);

        $tipo = $tipo === 'club' ? 'club' : 'atleta';
        $docNorm = $tipo === 'atleta'
            ? self::normalizarCedula($documentoRaw)
            : self::normalizarRif($documentoRaw);

        if ($docNorm === '') {
            throw new InvalidArgumentException('Indique una cédula o RIF válido.');
        }

        if ($tipo === 'atleta' && self::existeCedulaAtleta($pdo, $docNorm)) {
            throw new InvalidArgumentException('Ya existe un atleta con esa cédula.');
        }
        if ($tipo === 'club' && self::existeRifAsociacion($pdo, $docNorm)) {
            throw new InvalidArgumentException('Ya existe una asociación con ese RIF / número de registro.');
        }

        $stDup = $pdo->prepare(
            'SELECT id FROM fvd_invitaciones WHERE tipo = :t AND documento = :d AND estado = \'pendiente\'
             AND expira_en > NOW() AND (un_solo_uso = 0 OR usada_en IS NULL) LIMIT 1'
        );
        $stDup->execute([':t' => $tipo, ':d' => $docNorm]);
        if ($stDup->fetchColumn() !== false) {
            throw new InvalidArgumentException('Ya existe una invitación pendiente vigente para ese documento.');
        }

        $validezDias = max(1, min(365, $validezDias));
        $tz = new \DateTimeZone(function_exists('env') ? (string) env('APP_TIMEZONE', 'America/Caracas') : 'America/Caracas');
        $expira = (new \DateTimeImmutable('now', $tz))->modify('+' . $validezDias . ' days')->format('Y-m-d H:i:s');

        $emisor = null;
        if (\AuthService::role() === \AuthService::ROLE_ASO_ADMIN || \AuthService::isDelegadoAsociacion()) {
            $emisor = \AuthService::idAsociacion();
            if ($emisor === null || $emisor <= 0) {
                throw new RuntimeException('Su cuenta no tiene asociación vinculada.');
            }
        }

        $token = self::generarTokenSeguro();
        $uid = \AuthService::userId();

        for ($intent = 0; $intent < 5; ++$intent) {
            try {
                $st = $pdo->prepare(
                    'INSERT INTO fvd_invitaciones (token, tipo, documento, email_destino, asociacion_emisor_id, estado, un_solo_uso, expira_en, titulo_memo, creado_por_user_id)
                     VALUES (:tok, :tipo, :doc, :em, :ae, \'pendiente\', 1, :exp, :memo, :uid)'
                );
                $st->execute([
                    ':tok'  => $token,
                    ':tipo' => $tipo,
                    ':doc'  => $docNorm,
                    ':em'   => $emailDestino !== null && trim($emailDestino) !== '' ? trim($emailDestino) : null,
                    ':ae'   => $emisor,
                    ':exp'  => $expira,
                    ':memo' => $tituloMemo !== null && trim($tituloMemo) !== '' ? trim($tituloMemo) : null,
                    ':uid'  => $uid,
                ]);
                $newId = (int) $pdo->lastInsertId();
                $url = self::urlPublicaInvitacion($token);
                $tipoEt = $tipo === 'club' ? 'Nueva asociación / club' : 'Nuevo atleta';
                self::sendInvitation((string) ($emailDestino ?? ''), $url, $tipoEt, $tituloMemo);

                return ['url' => $url, 'id' => $newId];
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    $token = self::generarTokenSeguro();

                    continue;
                }
                error_log('[InvitacionService] crearInvitacion: ' . $e->getMessage());
                throw new RuntimeException('No se pudo guardar la invitación.');
            }
        }

        throw new RuntimeException('No se pudo generar un token único.');
    }

    /**
     * Valida token para página pública u otros flujos.
     *
     * @return array<string, mixed> fila invitación
     */
    public static function validarToken(PDO $pdo, string $token): array
    {
        self::ensureTable($pdo);
        $token = trim($token);
        if (!preg_match('/^[a-fA-F0-9]{64}$/', $token)) {
            throw new InvalidArgumentException('Enlace de invitación no válido.');
        }

        $st = $pdo->prepare('SELECT * FROM fvd_invitaciones WHERE token = :t LIMIT 1');
        $st->execute([':t' => strtolower($token)]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new InvalidArgumentException('Invitación no encontrada.');
        }

        $ui = self::estadoVisual($row);
        if ($ui === 'aceptada') {
            throw new RuntimeException('Esta invitación ya fue utilizada.');
        }
        if ($ui === 'expirada') {
            throw new RuntimeException('Esta invitación ha expirado.');
        }

        return $row;
    }

    /**
     * Marca invitación como usada (un solo uso).
     */
    public static function marcarUsada(PDO $pdo, int $invitacionId): void
    {
        self::ensureTable($pdo);
        $tz = new \DateTimeZone(function_exists('env') ? (string) env('APP_TIMEZONE', 'America/Caracas') : 'America/Caracas');
        $now = (new \DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');
        $st = $pdo->prepare(
            'UPDATE fvd_invitaciones SET estado = \'aceptada\', usada_en = :n WHERE id = :id AND estado = \'pendiente\' AND usada_en IS NULL'
        );
        $st->execute([':n' => $now, ':id' => $invitacionId]);
    }
}
