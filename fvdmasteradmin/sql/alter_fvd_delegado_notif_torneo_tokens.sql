-- Ejecutar si prefiere migración manual (el código también aplica columnas vía DelegadoTorneoNotifService::ensureTokenColumns).
-- Tokens de acceso por delegado + PDF de tarjeta generado al crear torneo (admin FVD).

ALTER TABLE fvd_delegado_notif_torneo
  ADD COLUMN access_token VARCHAR(64) NULL DEFAULT NULL COMMENT 'Secreto en URL; una tarjeta por delegado' AFTER invitacion_archivo,
  ADD COLUMN asociacion_id INT NULL DEFAULT NULL COMMENT 'asociaciones.id' AFTER access_token,
  ADD COLUMN tarjeta_pdf VARCHAR(255) NULL DEFAULT NULL COMMENT 'Archivo en uploads/' AFTER asociacion_id;

ALTER TABLE fvd_delegado_notif_torneo ADD UNIQUE KEY uk_access_token (access_token);
