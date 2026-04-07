<?php
/**
 * Alta y activación de cuentas de portal para filas de la tabla atletas.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/services/AuthService.php';

final class AtletaAccesoService
{
    public static function normalizePhone(string $s): string
    {
        return preg_replace('/\D+/', '', $s) ?? '';
    }

    /**
     * Contraseña inicial: cédula solo dígitos; si es corta, número FVD; último recurso atleta{id}.
     */
    public static function initialPlainPasswordFromAtletaRow(array $a): string
    {
        $ced = preg_replace('/\D+/', '', (string) ($a['cedula'] ?? '')) ?? '';
        if (strlen($ced) >= 4) {
            return $ced;
        }
        $nf = trim((string) ($a['numfvd'] ?? ''));
        if ($nf !== '') {
            return $nf;
        }

        return 'atleta' . (int) ($a['id'] ?? 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function atletasPorEmail(PDO $pdo, string $emailNorm): array
    {
        $st = $pdo->prepare(
            'SELECT * FROM atletas WHERE LOWER(TRIM(COALESCE(email, \'\'))) = :e'
        );
        $st->execute([':e' => $emailNorm]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array{atleta: array<string, mixed>|null, error: string}
     */
    public static function resolverAtletaPorEmailYTelefono(PDO $pdo, string $emailRaw, string $telefonoRaw): array
    {
        $email = strtolower(trim($emailRaw));
        $tel = self::normalizePhone($telefonoRaw);
        if ($email === '' || strlen($tel) < 7) {
            return ['atleta' => null, 'error' => 'Indique un correo válido y un número telefónico completo.'];
        }

        $candidatos = self::atletasPorEmail($pdo, $email);
        $coinciden = [];
        foreach ($candidatos as $row) {
            $dbTel = self::normalizePhone((string) ($row['celular'] ?? ''));
            if ($dbTel !== '' && $dbTel === $tel) {
                $coinciden[] = $row;
            }
        }

        if ($coinciden === []) {
            return ['atleta' => null, 'error' => 'Los datos no coinciden con un atleta registrado. Revise correo y teléfono.'];
        }
        if (count($coinciden) > 1) {
            return ['atleta' => null, 'error' => 'Hay más de un registro con ese correo. Contacte a su asociación o a la FVD.'];
        }

        return ['atleta' => $coinciden[0], 'error' => ''];
    }

    /**
     * Activa o crea fvd_usuarios vinculado al atleta; contraseña inicial según documento / numfvd.
     *
     * @return array{ok: bool, message: string, code: string}
     */
    public static function procesarSolicitudAcceso(PDO $pdo, string $emailRaw, string $telefonoRaw): array
    {
        $res = self::resolverAtletaPorEmailYTelefono($pdo, $emailRaw, $telefonoRaw);
        if ($res['atleta'] === null) {
            return ['ok' => false, 'message' => $res['error'], 'code' => 'no_match'];
        }

        $atleta = $res['atleta'];
        $emailUsuario = strtolower(trim((string) ($atleta['email'] ?? '')));
        if ($emailUsuario === '') {
            return ['ok' => false, 'message' => 'El registro del atleta no tiene correo electrónico. Contacte a su asociación.', 'code' => 'no_email'];
        }

        $atletaId = (int) ($atleta['id'] ?? 0);
        if ($atletaId <= 0) {
            return ['ok' => false, 'message' => 'Registro inválido.', 'code' => 'bad_row'];
        }

        $plain = self::initialPlainPasswordFromAtletaRow($atleta);
        $hash = password_hash($plain, PASSWORD_DEFAULT);
        if ($hash === false) {
            return ['ok' => false, 'message' => 'No se pudo generar la clave. Intente más tarde.', 'code' => 'hash_fail'];
        }

        $nombre = trim((string) ($atleta['nombre'] ?? ''));
        $idAsoc = $atleta['asociacion'] ?? null;
        $idAsocSql = $idAsoc === null || $idAsoc === '' ? null : (int) $idAsoc;

        $st = $pdo->prepare(
            'SELECT id, atleta_id, activo FROM fvd_usuarios WHERE LOWER(TRIM(email)) = :e LIMIT 1'
        );
        $st->execute([':e' => $emailUsuario]);
        $u = $st->fetch(PDO::FETCH_ASSOC);

        if ($u) {
            $uidAtleta = isset($u['atleta_id']) && $u['atleta_id'] !== null && $u['atleta_id'] !== ''
                ? (int) $u['atleta_id']
                : null;
            if ($uidAtleta !== null && $uidAtleta !== $atletaId) {
                return [
                    'ok' => false,
                    'message' => 'Este correo ya está asociado a otra cuenta. Contacte al administrador.',
                    'code' => 'email_conflict',
                ];
            }
            if ($uidAtleta === null) {
                return [
                    'ok' => false,
                    'message' => 'Este correo pertenece a un usuario del sistema que no es ficha de atleta. Use otro correo o contacte al administrador.',
                    'code' => 'staff_email',
                ];
            }

            if ((int) ($u['activo'] ?? 0) === 1) {
                return [
                    'ok' => true,
                    'message' => 'Su acceso ya estaba habilitado. Inicie sesión con su correo y la contraseña que haya definido (la inicial era el número de documento sin letras ni puntos, o su número FVD).',
                    'code' => 'already_active',
                ];
            }

            $up = $pdo->prepare(
                'UPDATE fvd_usuarios SET activo = 1, password_hash = :h, nombre = COALESCE(NULLIF(:n, \'\'), nombre),
                 id_asociacion = COALESCE(:ida, id_asociacion), atleta_id = :aid, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $up->execute([
                ':h' => $hash,
                ':n' => $nombre,
                ':ida' => $idAsocSql,
                ':aid' => $atletaId,
                ':id' => (int) $u['id'],
            ]);

            return [
                'ok' => true,
                'message' => 'Acceso habilitado. Usuario: su correo electrónico. Contraseña inicial: el número de su documento de identidad (solo dígitos); si no aplica, use su número FVD. Podrá cambiarla en «Mi perfil».',
                'code' => 'activated',
            ];
        }

        $ins = $pdo->prepare(
            'INSERT INTO fvd_usuarios (email, password_hash, nombre, rol, id_asociacion, atleta_id, activo)
             VALUES (:e, :h, :n, :rol, :ida, :aid, 1)'
        );
        $ins->execute([
            ':e' => $emailUsuario,
            ':h' => $hash,
            ':n' => $nombre !== '' ? $nombre : null,
            ':rol' => AuthService::ROLE_USUARIO,
            ':ida' => $idAsocSql,
            ':aid' => $atletaId,
        ]);

        return [
            'ok' => true,
            'message' => 'Acceso creado. Usuario: su correo electrónico. Contraseña inicial: el número de su documento de identidad (solo dígitos); si no aplica, use su número FVD. Cámbiela en «Mi perfil» tras ingresar.',
            'code' => 'created',
        ];
    }
}
