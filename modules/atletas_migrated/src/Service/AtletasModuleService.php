<?php

declare(strict_types=1);

final class AtletasModuleService
{
    public function resolveAction(array $get): string
    {
        $rawAction = isset($get['action']) ? trim((string) $get['action']) : '';
        $impliesAtletasList = isset($get['page']) || isset($get['cedula'])
            || (isset($get['q']) && trim((string) $get['q']) !== '');

        if ($rawAction === '') {
            return $impliesAtletasList ? 'list' : 'form';
        }

        return $rawAction;
    }

    public function resolveId(array $get): ?int
    {
        return isset($get['id']) ? (int) $get['id'] : null;
    }

    public function buildAtletasSiteFlag(): bool
    {
        $sn = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

        return str_contains($sn, '/fvdmasteradmin/modules/atletas/')
            || str_contains($sn, '/modules/atletas_migrated/public/');
    }

    public function buildSearchApiUrl(bool $fvdAtletasSite): string
    {
        $url = $fvdAtletasSite
            ? url('modules/atletas_migrated/public/search_api.php')
            : admin_module_url('atletas/search_api.php');

        return fvd_return_preserve_query_params($url);
    }

    public function buildExportUrl(bool $fvdAtletasSite): string
    {
        $url = $fvdAtletasSite
            ? url('modules/atletas_migrated/public/export.php')
            : admin_module_url('atletas/export.php');

        return fvd_return_preserve_query_params($url);
    }

    public function buildReportBaseUrl(bool $fvdAtletasSite): string
    {
        return $fvdAtletasSite
            ? url('modules/atletas_migrated/public/')
            : admin_module_url('atletas/');
    }
}
