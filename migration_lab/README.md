# Migration Lab (FVD Portal)

Este laboratorio centraliza los módulos migrados para ejecutar pruebas desde una sola carpeta local.

## Objetivo

- Tener un punto único de pruebas para todos los módulos migrados.
- Estandarizar estructura por módulo.
- Facilitar validación, mantenimiento y evolución incremental.
- Mantener compatibilidad temporal con rutas existentes mientras se completa la migración.

## Estructura inicial

- `modules/`: contendrá los módulos migrados o espejos de trabajo.
- `manifests/`: manifiestos de migración por módulo.
- `docs/`: guías de prueba, checklist y notas de rollback.
- `scripts/`: utilidades de validación y soporte.

## Estado

Inicializado. Pendiente poblar con módulos adicionales y checklist de pruebas.
