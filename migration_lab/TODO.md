# TODO - Migración integral a Migration Lab

## Fase 1 — Base del laboratorio
- [x] Crear `migration_lab/README.md`
- [x] Crear estructura estándar:
  - [x] `migration_lab/modules/`
  - [x] `migration_lab/manifests/`
  - [x] `migration_lab/docs/`
  - [x] `migration_lab/scripts/`

## Fase 2 — Inventario y planeación por módulo
- [ ] Inventariar `admin/modules/*` y `modules/*`
- [ ] Definir matriz de mapeo `<modulo_origen> -> <modulo_migrated_lab>`
- [ ] Generar manifiestos por módulo en `migration_lab/manifests/`

## Fase 3 — Migración técnica por módulo
- [ ] Migrar `asociaciones`
- [ ] Migrar `atletas` (consolidar desde `modules/atletas_migrated`)
- [ ] Migrar `invitaciones`
- [ ] Migrar `solicitudes_delegado`
- [ ] Migrar `torneo_inscripcion`
- [ ] Migrar `torneos`
- [ ] Revisar módulos solo en `modules/`:
  - [ ] `costos`
  - [ ] `deuda_asociacion`
  - [ ] `inscripcion_torneo`
  - [ ] `inscripciones`
  - [ ] `relacion_pago`

## Fase 4 — Compatibilidad y pruebas (pendiente por decisión de usuario)
- [ ] Shims/compatibilidad temporal
- [ ] Smoke tests desde `migration_lab`
- [ ] Validación sintaxis PHP

## Fase 5 — PR por tarea
- [ ] PR: estructura base laboratorio
- [ ] PR: inventario + manifests iniciales
- [ ] PR: cada módulo migrado
