<?php

declare(strict_types=1);

/**
 * Definiciones compartidas: informes de atletas (misma tabla que afiliación / indicadores).
 */

if (!function_exists('fvd_rep_atletas_telefono_regex')) {
    function fvd_rep_atletas_telefono_regex(): string
    {
        return '/(telefono|tel[eé]fono|celular|movil|m[oó]vil|whatsapp|wasap)/iu';
    }

    /**
     * @return array<string, true>
     */
    function fvd_rep_atletas_columnas_ocultas_lc(): array
    {
        return array_fill_keys(
            [
                'id', 'asociacion', 'movimiento', 'torneo_id', 'categ', 'alta_desde_delegado',
                'profesion', 'direccion', 'email', 'fechnac', 'fechfvd', 'fechact',
                'cedula_img', 'created_at', 'updated_at',
            ],
            true
        );
    }

    /**
     * @return list<string>
     */
    function fvd_rep_atletas_columnas_orden(): array
    {
        return [
            'foto', 'numfvd', 'cedula', 'nombre', 'sexo', 'asociacion_nombre', 'estatus',
            'afiliacion', 'anualidad', 'carnet', 'traspaso', 'inscripcion',
        ];
    }

    /**
     * @return array<string, string>
     */
    function fvd_rep_atletas_columnas_etiquetas_lc(): array
    {
        return [
            'foto'              => 'Foto',
            'numfvd'            => 'Nº FVD',
            'cedula'            => 'Cédula',
            'nombre'            => 'Nombre',
            'sexo'              => 'Sexo',
            'asociacion_nombre' => 'Asociación',
            'estatus'           => 'Estatus',
            'afiliacion'        => 'Afiliación',
            'anualidad'         => 'Anualidad',
            'carnet'            => 'Carnet',
            'traspaso'          => 'Traspaso',
            'inscripcion'       => 'Inscripción',
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    function fvd_rep_atletas_strip_telefonos(array &$rows): void
    {
        $rx = fvd_rep_atletas_telefono_regex();
        foreach ($rows as &$rowClean) {
            if (!is_array($rowClean)) {
                continue;
            }
            foreach (array_keys($rowClean) as $colName) {
                if (preg_match($rx, (string) $colName) === 1) {
                    unset($rowClean[$colName]);
                }
            }
        }
        unset($rowClean);
    }

    /**
     * @param array<string, mixed>|null $sampleRow
     * @return array{columns: list<string>, labels: array<string, string>, logical: array<string, string>}
     */
    function fvd_rep_atletas_resolve_columnas(?array $sampleRow): array
    {
        if ($sampleRow === null || $sampleRow === []) {
            return ['columns' => [], 'labels' => [], 'logical' => []];
        }
        $rx = fvd_rep_atletas_telefono_regex();
        $ocultas = fvd_rep_atletas_columnas_ocultas_lc();
        $orden = fvd_rep_atletas_columnas_orden();
        $etiquetas = fvd_rep_atletas_columnas_etiquetas_lc();
        $columns = [];
        $labels = [];
        $logical = [];
        foreach ($orden as $logicalLc) {
            $resolvedKey = null;
            foreach (array_keys($sampleRow) as $rk) {
                $rkStr = (string) $rk;
                if (strtolower($rkStr) === $logicalLc) {
                    $resolvedKey = $rkStr;
                    break;
                }
            }
            if ($resolvedKey === null) {
                continue;
            }
            if (preg_match($rx, $resolvedKey) === 1) {
                continue;
            }
            if (isset($ocultas[strtolower($resolvedKey)])) {
                continue;
            }
            $columns[] = $resolvedKey;
            $labels[$resolvedKey] = $etiquetas[$logicalLc] ?? $resolvedKey;
            $logical[$resolvedKey] = $logicalLc;
        }

        return ['columns' => $columns, 'labels' => $labels, 'logical' => $logical];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{doc: string, nombre: string, operacion: string}>
     */
    function fvd_rep_atletas_movimientos_sidebar_rows(array $rows): array
    {
        $movimientosSolicitados = [];
        foreach ($rows as $rMov) {
            if (!is_array($rMov)) {
                continue;
            }
            $nombreMov = trim((string) ($rMov['nombre'] ?? ''));
            $docMov = (int) ($rMov['numfvd'] ?? 0) > 0
                ? (string) ((int) $rMov['numfvd'])
                : trim((string) ($rMov['cedula'] ?? ''));
            $ops = [
                'carnet'      => 'Carnet solicitado',
                'traspaso'    => 'Traspaso solicitado',
                'afiliacion'  => 'Afiliación solicitada',
                'anualidad'   => 'Anualidad solicitada',
                'inscripcion' => 'Inscripción solicitada',
            ];
            foreach ($ops as $campoOp => $labelOp) {
                if ((int) ($rMov[$campoOp] ?? 0) !== 1) {
                    continue;
                }
                $movimientosSolicitados[] = [
                    'doc'       => $docMov !== '' ? $docMov : '—',
                    'nombre'    => $nombreMov !== '' ? $nombreMov : '—',
                    'operacion' => $labelOp,
                ];
            }
        }

        return $movimientosSolicitados;
    }
}
