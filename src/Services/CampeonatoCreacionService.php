<?php

declare(strict_types=1);

namespace FvdPortal\Services;

require_once dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
require_once dirname(__DIR__, 2) . '/fvdmasteradmin/services/PublicSiteData.php';

use PDO;
use Throwable;

/**
 * Crea un campeonato lógico (`fvd_campeonatos`) y varias filas en `torneosact` compartiendo `grupo_evento_id`.
 */
final class CampeonatoCreacionService
{
    public static function grupoEventoColumnExists(PDO $pdo): bool
    {
        return MasterPanelContextService::grupoEventoColumnExists($pdo);
    }

    /**
     * @return array{campeonato_id:int, grupo_evento_id:int, torneo_ids:list<int>}
     */
    public static function crear(PDO $pdo, string $plantilla, string $nombreBase, string $fechator, ?int $organizacionId): array
    {
        NotificacionesDelegadosService::ensureTables($pdo);
        DelegadoTorneoNotifService::ensureCampeonatoGrupoTable($pdo);

        $base = trim($nombreBase);
        if ($base === '') {
            throw new \InvalidArgumentException('Indique el nombre del campeonato.');
        }
        if (!in_array($plantilla, ['adulto_mf', 'sub_categorias'], true)) {
            throw new \InvalidArgumentException('Plantilla de campeonato no válida.');
        }
        $fecha = substr(trim($fechator), 0, 10);
        if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new \InvalidArgumentException('Fecha del evento no válida (AAAA-MM-DD).');
        }

        if (!self::grupoEventoColumnExists($pdo)) {
            throw new \RuntimeException('Falta la columna torneosact.grupo_evento_id. Ejecute install_torneo_grupo_historico.sql.');
        }

        $svc = new \FvdAdminService($pdo);
        $org = $organizacionId !== null && $organizacionId > 0
            ? $organizacionId
            : (int) ($svc->torneosOrganizacionFederacionId() ?? 0);
        if ($org <= 0) {
            throw new \RuntimeException('No se pudo determinar la organización del torneo.');
        }

        $stNextG = $pdo->query('SELECT COALESCE(MAX(grupo_evento_id), 0) + 1 AS g FROM torneosact');
        $nextG = $stNextG !== false ? (int) $stNextG->fetchColumn() : 1;
        if ($nextG <= 0) {
            $nextG = 1;
        }

        $ramas = [];
        if ($plantilla === 'adulto_mf') {
            $ramas = [
                ['tipo' => 1, 'etiqueta' => 'M', 'nombre' => $base . ' (M)'],
                ['tipo' => 2, 'etiqueta' => 'F', 'nombre' => $base . ' (F)'],
            ];
        } else {
            $stCat = $pdo->query(
                'SELECT codigo, etiqueta FROM fvd_campeonato_sub_categoria WHERE activo = 1 ORDER BY orden ASC, id ASC LIMIT 10'
            );
            $cats = $stCat !== false ? $stCat->fetchAll(PDO::FETCH_ASSOC) : [];
            if ($cats === []) {
                $pdo->exec(
                    "INSERT IGNORE INTO fvd_campeonato_sub_categoria (codigo, etiqueta, orden, activo) VALUES
                     ('SUB12','Sub-12',1,1),('SUB15','Sub-15',2,1),('SUB18','Sub-18',3,1)"
                );
                $stCat2 = $pdo->query(
                    'SELECT codigo, etiqueta FROM fvd_campeonato_sub_categoria WHERE activo = 1 ORDER BY orden ASC, id ASC LIMIT 10'
                );
                $cats = $stCat2 !== false ? $stCat2->fetchAll(PDO::FETCH_ASSOC) : [];
            }
            if (count($cats) < 3) {
                throw new \RuntimeException('Configure al menos 3 categorías Sub en fvd_campeonato_sub_categoria.');
            }
            $cats = array_slice($cats, 0, 3);
            foreach ($cats as $c) {
                $lab = trim((string) ($c['etiqueta'] ?? ''));
                if ($lab === '') {
                    $lab = (string) ($c['codigo'] ?? '');
                }
                $ramas[] = [
                    'tipo' => 3,
                    'etiqueta' => $lab,
                    'nombre' => $base . ' — ' . $lab,
                ];
            }
        }

        $persistCols = \FvdAdminService::torneosInsertableColumns();
        $torneoIds = [];

        $campeonatoId = 0;
        $pdo->beginTransaction();
        try {
            $insCamp = $pdo->prepare(
                'INSERT INTO fvd_campeonatos (nombre_base, plantilla, grupo_evento_id) VALUES (:nb, :pl, :g)'
            );
            $insCamp->execute([':nb' => $base, ':pl' => $plantilla, ':g' => $nextG]);
            $campeonatoId = (int) $pdo->lastInsertId();
            if ($campeonatoId <= 0) {
                throw new \RuntimeException('No se pudo crear el registro de campeonato.');
            }

            $insGrupo = $pdo->prepare(
                'INSERT INTO fvd_campeonato_grupo (grupo_evento_id, nombre_nominal) VALUES (:g, :n)
                 ON DUPLICATE KEY UPDATE nombre_nominal = VALUES(nombre_nominal)'
            );
            $insGrupo->execute([':g' => $nextG, ':n' => $base]);

            $insLink = $pdo->prepare(
                'INSERT INTO fvd_campeonato_torneo (campeonato_id, torneo_id, orden, etiqueta) VALUES (:c, :t, :o, :e)'
            );

            $orden = 0;
            foreach ($ramas as $rama) {
                ++$orden;
                $clave = $svc->torneosGenerarClaveTorneo($fecha);
                $data = [
                    'organizacion_id' => $org,
                    'clavetor' => $clave,
                    'nombre' => (string) $rama['nombre'],
                    'lugar' => null,
                    'fechator' => $fecha,
                    'tipo' => (int) $rama['tipo'],
                    'es_campeonato' => 0,
                    'clase' => 1,
                    'tiempo' => 0,
                    'puntos' => 0,
                    'rondas' => 0,
                    'estatus' => 0,
                    'costotor' => null,
                    'ranking' => 0,
                    'pareclub' => 0,
                    'invitacion' => null,
                    'afiche' => null,
                    'publicar_landing' => 1,
                    'grupo_evento_id' => $nextG,
                    'apertura_anual' => 0,
                    'fecha_limite_cambios' => null,
                ];
                if (!\PublicSiteData::torneosactPublicarLandingColumnPresent()) {
                    unset($data['publicar_landing']);
                }
                if (!$svc->torneosactEsCampeonatoColumnExists()) {
                    unset($data['es_campeonato']);
                }
                $newTid = (int) \QueryHelper::insert($pdo, 'torneosact', $data, $persistCols);
                if ($newTid <= 0) {
                    throw new \RuntimeException('No se obtuvo ID de torneo creado.');
                }
                $torneoIds[] = $newTid;
                $insLink->execute([
                    ':c' => $campeonatoId,
                    ':t' => $newTid,
                    ':o' => $orden,
                    ':e' => (string) $rama['etiqueta'],
                ]);
                $svc->torneosPostCreacionInvitacionesDelegados($newTid);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'campeonato_id' => $campeonatoId,
            'grupo_evento_id' => $nextG,
            'torneo_ids' => $torneoIds,
        ];
    }
}
