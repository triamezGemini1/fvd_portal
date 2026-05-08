# TODO - Migración estructural módulo Atletas (Motor + Fuentes)

## Estado general
- [ ] Fase A — Preparación de estructura destino y manifiesto
- [ ] Fase B — Traslado de motor backend (entrypoint/controller/service)
- [ ] Fase C — Traslado de fuentes (views/js/templates)
- [ ] Fase D — Endpoints auxiliares en carpeta destino
- [ ] Fase E — Capa de compatibilidad temporal (shims en rutas antiguas)
- [ ] Fase F — Validación funcional y documentación

## Detalle de tareas

### Fase A
- [ ] Crear carpeta `modules/atletas_migrated/` con subestructura:
  - [ ] `public/`
  - [ ] `src/Controller/`
  - [ ] `src/Service/`
  - [ ] `src/ViewModel/`
  - [ ] `views/`
  - [ ] `assets/js/`
  - [ ] `templates/components/`
  - [ ] `config/`
- [ ] Crear `modules/atletas_migrated/config/bootstrap.php`
- [ ] Crear `modules/atletas_migrated/.migration-manifest.json`
- [ ] Crear `modules/atletas_migrated/README.md`

### Fase B
- [ ] Migrar `admin/modules/atletas/index.php` -> `modules/atletas_migrated/public/index.php`
- [ ] Migrar `admin/modules/atletas/controller/AtletasController.php` -> `modules/atletas_migrated/src/Controller/AtletasController.php`
- [ ] Migrar `admin/modules/atletas/service/AtletasModuleService.php` -> `modules/atletas_migrated/src/Service/AtletasModuleService.php`
- [ ] Crear `modules/atletas_migrated/src/Service/AtletasDomainService.php` (adaptador inicial)

### Fase C
- [ ] Migrar `admin/modules/atletas/list.view.php` -> `modules/atletas_migrated/views/list.view.php`
- [ ] Migrar `assets/js/admin-atletas-list.js` -> `modules/atletas_migrated/assets/js/admin-atletas-list.js`
- [ ] Migrar `templates/components/atleta_table_row.php` -> `modules/atletas_migrated/templates/components/atleta_table_row.php`

### Fase D
- [ ] Crear endpoints espejo en `modules/atletas_migrated/public/`:
  - [ ] `search_api.php`
  - [ ] `export.php`
  - [ ] `carnet_foto_api.php`
  - [ ] `carnet_marcar_api.php`
  - [ ] `delegado_solicitud_una_api.php`

### Fase E
- [ ] Implementar shim en `admin/modules/atletas/index.php` hacia nuevo entrypoint
- [ ] Implementar shims para endpoints auxiliares antiguos

### Fase F
- [ ] Verificación de sintaxis PHP de archivos migrados
- [ ] Pruebas de flujo crítico
- [ ] Ajustes por hallazgos
- [ ] Documentación final de uso/rollback
