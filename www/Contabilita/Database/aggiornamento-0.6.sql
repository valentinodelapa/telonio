-- =====================================================
-- Contabilita 0.6 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già le
-- modifiche in schema.sql). Idempotente.
--
-- Opzione per i modi ordinari delle attività separate (quadro VO).

ALTER TABLE `attivita_iva` ADD COLUMN IF NOT EXISTS `opzione_ordinaria` CHAR(3) NULL COMMENT 'Regime agricolo a cui l''attività ha rinunciato per i modi ordinari (quadro VO)' AFTER `regime_detrazione_iva`;
ALTER TABLE `attivita_iva` ADD COLUMN IF NOT EXISTS `opzione_dal` SMALLINT UNSIGNED NULL COMMENT 'Primo anno dell''opzione' AFTER `opzione_ordinaria`;
ALTER TABLE `attivita_iva` ADD COLUMN IF NOT EXISTS `opzione_al` SMALLINT UNSIGNED NULL COMMENT 'Ultimo anno dell''opzione, l''anno dopo è quello della revoca' AFTER `opzione_dal`;

-- -----------------------------------------------------
-- Prodotti agricoli e ittici con la percentuale di compensazione (art. 34,
-- tabella A, parte I): dati della suite, da sorgenti/prodotti-agricoli.csv
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `prodotto_agricolo` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `voce` VARCHAR(30) NULL COMMENT 'Voce doganale',
  `descrizione` VARCHAR(255) NOT NULL,
  `percentuale` DECIMAL(5,2) NOT NULL,
  `dal_anno` SMALLINT UNSIGNED NULL,
  `al_anno` SMALLINT UNSIGNED NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Prodotti agricoli con la percentuale di compensazione';

-- Agricoltori esonerati tra i fornitori, per l'autofattura dell'acquirente.

ALTER TABLE `controparte` ADD COLUMN IF NOT EXISTS `agricoltore_esonerato` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Produttore agricolo esonerato (art. 34, c. 6): l''acquirente emette l''autofattura' AFTER `fornitore`;

-- Conto abituale della controparte sulle righe IVA che non ne hanno uno: serve a
-- riconoscere i beni ammortizzabili (art. 34, c. 5, e rettifica della detrazione).

ALTER TABLE `riga_iva` ADD COLUMN IF NOT EXISTS `coefficiente_elevato` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Coefficiente di ammortamento oltre il 25%: non ammortizzabile per la rettifica della detrazione (art. 19-bis2, c. 5)' AFTER `operazione_diversa`;

UPDATE `riga_iva` ri
INNER JOIN `registrazione_contabile` r ON r.`id` = ri.`registrazione_contabile_id`
INNER JOIN `controparte` c ON c.`id` = r.`controparte_id`
SET ri.`conto_id` = c.`conto_contropartita_id`
WHERE ri.`conto_id` IS NULL AND c.`conto_contropartita_id` IS NOT NULL;
