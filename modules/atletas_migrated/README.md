# Módulo Atletas Migrado (Motor + Fuentes)

Este directorio concentra la migración estructural del módulo de atletas para aislar su funcionamiento y facilitar mantenimiento, mejoras y evolución.

## Estructura

- `config/bootstrap.php`: carga de entorno/base compartida.
- `public/`: entrypoint y endpoints del módulo.
- `src/`: clases de controlador/servicios del módulo.
- `views/`: vistas del módulo.
- `assets/js/`: scripts frontend del módulo.
- `templates/components/`: componentes de plantilla.
- `.migration-manifest.json`: mapa fuente → destino de la migración.

## Estrategia

1. Copiar el motor funcional desde rutas actuales.
2. Mover vistas y recursos frontend.
3. Mantener compatibilidad temporal con rutas antiguas (shims).
4. Validar flujo crítico y luego cobertura completa.

## Estado

Migración en progreso.
