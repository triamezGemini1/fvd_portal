-- Añade publicar_landing si su BD fue creada antes de incluir esta columna.
-- Si ya existe: Error 1060 Duplicate column → ignorar.
-- Tras ejecutar, el calendario y el landing podrán ocultar torneos desmarcando "Publicar en el sitio web".

ALTER TABLE `torneosact`
  ADD COLUMN `publicar_landing` tinyint(1) NOT NULL DEFAULT 1
    COMMENT '1 = visible en sitio público (inicio, calendario, en vivo)'
    AFTER `afiche`;
