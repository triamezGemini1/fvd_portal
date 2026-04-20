-- Acceso de prueba para delegado.miranda4@test.fvd (ajuste torneo_id y token según entorno).
-- delegado_id referencia delegados.id (no usuarios).
-- La tabla usa visto_en (timestamp), no columna "visto".

-- Reemplaza @TORNEO_ID por el ID del torneo activo (torneosact.torneo), p. ej. 123:
SET @TORNEO_ID := 123;
SET @TOKEN := 'TOKEN_PRUEBA_MIRANDA_2026';

INSERT INTO fvd_delegado_notif_torneo (delegado_id, torneo_id, invitacion_archivo, access_token, asociacion_id)
SELECT d.id, @TORNEO_ID, NULL, @TOKEN, d.asociacion_id
FROM delegados d
WHERE LOWER(TRIM(d.email_acceso)) = 'delegado.miranda4@test.fvd'
  AND COALESCE(d.activo, 0) = 1
LIMIT 1
ON DUPLICATE KEY UPDATE
  access_token = VALUES(access_token),
  asociacion_id = COALESCE(VALUES(asociacion_id), fvd_delegado_notif_torneo.asociacion_id);
