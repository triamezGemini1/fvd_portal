<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use PDOException;
use Throwable;

/**
 * Consulta al referencial de personas (32M / RNE): por defecto tabla `dpersona` en la BD configurada en {@see PersonaDatabase}.
 * En instalaciones SQL Server con `dbo.persona`, defina `FVD_PERSONA_TABLE=persona` y el DSN adecuado en la conexión externa.
 */
final class PersonaReferencialService
{
    /**
     * @return array{
     *   nombre1: string,
     *   nombre2: string,
     *   apellido1: string,
     *   apellido2: string,
     *   fechnac: ?string,
     *   sexo: string,
     *   nacionalidad: string
     * }|null
     */
    public static function lookupByCedula(string $cedula): ?array
    {
        $trim = trim($cedula);
        if ($trim === '') {
            return null;
        }

        $table = self::tableName();
        $idCol = self::idColumnName();

        $personaDb = dirname(__DIR__, 2) . '/config/persona_database.php';
        if (!is_file($personaDb)) {
            return null;
        }
        require_once $personaDb;
        if (!class_exists('PersonaDatabase', false)) {
            return null;
        }

        try {
            $database = new \PersonaDatabase();
            $conn = $database->getConnection();
            if (!$conn instanceof PDO) {
                return null;
            }

            $sql = 'SELECT Nombre1, Nombre2, Apellido1, Apellido2, FNac, Sexo, NAC
                    FROM `' . $table . '`
                    WHERE `' . $idCol . '` = :ced
                    LIMIT 1';
            $st = $conn->prepare($sql);
            $st->execute([':ced' => $trim]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }

            $fnac = $row['FNac'] ?? null;
            $fechnac = null;
            if ($fnac !== null && $fnac !== '') {
                $ts = strtotime((string) $fnac);
                $fechnac = $ts !== false ? date('Y-m-d', $ts) : null;
            }

            return [
                'nombre1'      => trim((string) ($row['Nombre1'] ?? '')),
                'nombre2'      => trim((string) ($row['Nombre2'] ?? '')),
                'apellido1'    => trim((string) ($row['Apellido1'] ?? '')),
                'apellido2'    => trim((string) ($row['Apellido2'] ?? '')),
                'fechnac'      => $fechnac,
                'sexo'         => trim((string) ($row['Sexo'] ?? '')) !== '' ? trim((string) $row['Sexo']) : 'M',
                'nacionalidad' => trim((string) ($row['NAC'] ?? '')) !== '' ? trim((string) $row['NAC']) : 'V',
            ];
        } catch (PDOException $e) {
            error_log('[PersonaReferencialService] ' . $e->getMessage());

            return null;
        } catch (Throwable $e) {
            error_log('[PersonaReferencialService] ' . $e->getMessage());

            return null;
        }
    }

    private static function tableName(): string
    {
        $t = trim((string) (getenv('FVD_PERSONA_TABLE') ?: ($_ENV['FVD_PERSONA_TABLE'] ?? 'dpersona')));
        if ($t === '' || !preg_match('/^[A-Za-z0-9_]+$/', $t)) {
            return 'dpersona';
        }

        return $t;
    }

    private static function idColumnName(): string
    {
        $c = trim((string) (getenv('FVD_PERSONA_ID_COLUMN') ?: ($_ENV['FVD_PERSONA_ID_COLUMN'] ?? 'IDusuario')));
        if ($c === '' || !preg_match('/^[A-Za-z0-9_]+$/', $c)) {
            return 'IDusuario';
        }

        return $c;
    }
}
