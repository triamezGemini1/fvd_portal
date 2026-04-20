-- Semilla idempotente: fila en fvd_delegado_notif_torneo para delegado.miranda4@test.fvd
-- y el torneo más reciente en torneosact (ajustar torneo en WHERE si hace falta).
-- Ejecutar manualmente en el entorno de desarrollo/pruebas.

INSERT INTO fvd_delegado_notif_torneo (delegado_id, torneo_id, invitacion_archivo, access_token, asociacion_id)
SELECT d.id, t.torneo, NULL, LOWER(REPLACE(UUID(), '-', '')), d.asociacion_id
FROM delegados d
CROSS JOIN (SELECT torneo FROM torneosact ORDER BY torneo DESC LIMIT 1) AS t
WHERE LOWER(TRIM(d.email_acceso)) = 'delegado.miranda4@test.fvd'
  AND COALESCE(d.activo, 0) = 1
LIMIT 1
ON DUPLICATE KEY UPDATE
  asociacion_id = VALUES(asociacion_id),
  access_token = IF(
    fvd_delegado_notif_torneo.access_token IS NULL OR TRIM(fvd_delegado_notif_torneo.access_token) = '',
    VALUES(access_token),
    fvd_delegado_notif_torneo.access_token
  );
