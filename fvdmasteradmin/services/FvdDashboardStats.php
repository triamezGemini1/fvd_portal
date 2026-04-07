<?php
/**
 * Conteos del panel con filtro regional vía QueryHelper (rol fvd_admin sin filtro).
 */

declare(strict_types=1);

require_once __DIR__ . '/QueryHelper.php';

class FvdDashboardStats
{
    /**
     * @return array{atletas:int, clubes:int, torneos:int}
     */
    public static function counts(): array
    {
        $defaults = ['atletas' => 0, 'clubes' => 0, 'torneos' => 0];

        try {
            $pdo = fvd_db();
        } catch (PDOException $e) {
            error_log('[FvdDashboardStats] ' . $e->getMessage());

            return $defaults;
        }

        return [
            'atletas' => self::scopedCount($pdo, 'SELECT COUNT(*) FROM atletas WHERE 1=1', 'atletas.asociacion'),
            'clubes'  => self::scopedCount($pdo, 'SELECT COUNT(*) FROM asociaciones WHERE 1=1', 'asociaciones.id'),
            'torneos' => self::scopedCount($pdo, 'SELECT COUNT(*) FROM torneosact WHERE 1=1', 'torneosact.organizacion_id'),
        ];
    }

    private static function scopedCount(PDO $pdo, string $sql, string $qualifiedColumn): int
    {
        $params = [];
        $scope = QueryHelper::asociacionScopeSql($qualifiedColumn, $params);

        try {
            $stmt = $pdo->prepare($sql . $scope);
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('[FvdDashboardStats] count ' . $qualifiedColumn . ': ' . $e->getMessage());

            return 0;
        }
    }
}
