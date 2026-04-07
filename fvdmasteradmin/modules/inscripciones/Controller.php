<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/FvdModuleController.php';

/**
 * El modelo Inscripcion del legado opera sobre la tabla atletas (no hay tabla propia).
 * Este controlador solo centraliza enlaces al flujo existente.
 */
class InscripcionesController extends FvdModuleController
{
    public function legacyGestionUrl(): string
    {
        $b = rtrim((string) env('APP_BASE_PATH', ''), '/');

        return $b . '/inscripciones/gestionar.php';
    }

    public function legacyIndexUrl(): string
    {
        $b = rtrim((string) env('APP_BASE_PATH', ''), '/');

        return $b . '/inscripciones/index.php';
    }
}
