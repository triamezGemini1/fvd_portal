-- Vista de consulta por período / año (no duplica filas de torneosact).
-- Permite informes y JOINs externos (BI, exportaciones) con fecha de referencia unificada.
-- Ejecutar una vez en la base del portal FVD.

DROP VIEW IF EXISTS `v_fvd_torneos_consulta_periodo`;
CREATE VIEW `v_fvd_torneos_consulta_periodo` AS
SELECT
    t.`torneo`,
    t.`nombre`,
    t.`lugar`,
    t.`fechator`,
    t.`estatus`,
    t.`organizacion_id`,
    o.`nombre` AS `organizacion_nombre`,
    t.`grupo_evento_id`,
    t.`finalizado_en`,
    t.`created_at`,
    YEAR(COALESCE(NULLIF(t.`fechator`, '0000-00-00'), DATE(t.`created_at`))) AS `anio_referencia`,
    DATE(COALESCE(NULLIF(t.`fechator`, '0000-00-00'), t.`created_at`)) AS `fecha_referencia`
FROM `torneosact` t
LEFT JOIN `asociaciones` o ON o.`id` = t.`organizacion_id`;
