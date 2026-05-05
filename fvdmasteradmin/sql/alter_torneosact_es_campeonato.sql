-- Separa "campeonato (agrupa categorías)" del campo `tipo`, que en torneosact es género del evento:
-- tipo: 1=Masculino, 2=Femenino, 3=Mixto (comentario en install_torneosact.sql).
-- Tras este ALTER, marque es_campeonato=1 solo en eventos que deban vincularse por grupo_evento_id.

ALTER TABLE `torneosact`
  ADD COLUMN `es_campeonato` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=campeonato (varias categorías), 0=torneo' AFTER `tipo`;

-- Opcional (solo si antes usaba el formulario antiguo que guardaba campeonato como tipo=2):
-- UPDATE torneosact SET es_campeonato = 1, tipo = 2 WHERE tipo = 2 AND torneo IN (...);
