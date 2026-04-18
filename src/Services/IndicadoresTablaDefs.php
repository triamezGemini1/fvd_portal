<?php

declare(strict_types=1);

namespace FvdPortal\Services;

/**
 * Cabeceras y claves de la tabla `atletas` para indicadores (totales y por asociación).
 * Una sola fuente de verdad: el orden y las etiquetas coinciden con cada renglón consultado.
 */
final class IndicadoresTablaDefs
{
    /**
     * Columnas de métricas: misma clave que devuelven
     * {@see QueryHelper::aggregateIndicadoresAtletasTotales} y
     * {@see QueryHelper::aggregateIndicadoresAtletasPorAsociacion}.
     *
     * @return list<array{key:string,label:string,labelShort:string,title:string}>
     */
    public static function columnasMetricas(): array
    {
        return [
            [
                'key'        => 'total_atletas',
                'label'      => 'Atletas',
                'labelShort' => 'Atl.',
                'title'      => 'Registros en alcance (tabla atletas)',
            ],
            [
                'key'        => 'afiliacion',
                'label'      => 'Afiliados',
                'labelShort' => 'Afil.',
                'title'      => 'atletas.afiliacion = 1',
            ],
            [
                'key'        => 'anualidad',
                'label'      => 'Anualidad',
                'labelShort' => 'Anual.',
                'title'      => 'atletas.anualidad = 1',
            ],
            [
                'key'        => 'carnet',
                'label'      => 'Carnets',
                'labelShort' => 'Carn.',
                'title'      => 'atletas.carnet = 1',
            ],
            [
                'key'        => 'traspaso',
                'label'      => 'Traspaso',
                'labelShort' => 'Trasp.',
                'title'      => 'atletas.traspaso = 1',
            ],
            [
                'key'        => 'inscripcion',
                'label'      => 'Inscritos',
                'labelShort' => 'Insc.',
                'title'      => 'atletas.inscripcion = 1',
            ],
        ];
    }

    /**
     * Extrae enteros por columna de métrica; registra en log si falta alguna clave esperada.
     *
     * @param array<string, mixed> $fila
     * @return array<string, int>
     */
    public static function valoresMetricasInt(array $fila, string $contexto = ''): array
    {
        $out = [];
        foreach (self::columnasMetricas() as $col) {
            $k = $col['key'];
            if (!\array_key_exists($k, $fila)) {
                $ctx = $contexto !== '' ? $contexto . ': ' : '';
                error_log('[IndicadoresTablaDefs] ' . $ctx . 'falta clave "' . $k . '" al alinear cabecera con datos');
            }
            $out[$k] = (int) ($fila[$k] ?? 0);
        }

        return $out;
    }
}
