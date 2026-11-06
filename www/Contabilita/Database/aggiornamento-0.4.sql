-- =====================================================
-- Contabilita 0.4 - aggiornamento dei database creati con la versione 0.3
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già
-- la tabella in schema.sql). Idempotente.
--
-- Credito chiesto con il modello IVA TR registrato a parte, per non perderlo
-- se la liquidazione non è ancora elaborata o viene eliminata: si recuperano
-- i crediti già annotati sulle liquidazioni. Regime speciale agricolo:
-- percentuale di compensazione e operazioni diverse sulle righe IVA, regime
-- della singola attività separata.

CREATE TABLE IF NOT EXISTS `credito_trimestrale_iva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL COMMENT 'Con il gruppo IVA la controllante',
  `gruppo_iva_id` INT UNSIGNED NULL COMMENT 'Credito del gruppo IVA chiesto dalla controllante',
  `anno` SMALLINT UNSIGNED NOT NULL,
  `trimestre` TINYINT UNSIGNED NOT NULL COMMENT 'Da 1 a 3',
  `importo` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_credito_trimestrale_iva` (`soggetto_id`, `anno`, `trimestre`),
  CONSTRAINT `fk_credito_trimestrale_iva_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_credito_trimestrale_iva_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_credito_trimestrale_iva_gruppo` FOREIGN KEY (`gruppo_iva_id`) REFERENCES `gruppo_iva` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Credito del trimestre chiesto con il modello IVA TR, escluso dal riporto delle liquidazioni';

INSERT IGNORE INTO `credito_trimestrale_iva` (`studio_id`, `soggetto_id`, `anno`, `trimestre`, `importo`)
SELECT `studio_id`, `soggetto_id`, `anno`, IF((`periodicita` = 'T') OR (`trimestre_separato` = 1), `periodo`, `periodo` DIV 3), `credito_trimestrale`
FROM `liquidazione_iva` WHERE `credito_trimestrale` > 0;

INSERT IGNORE INTO `credito_trimestrale_iva` (`studio_id`, `soggetto_id`, `gruppo_iva_id`, `anno`, `trimestre`, `importo`)
SELECT `l`.`studio_id`, `g`.`controllante_id`, `l`.`gruppo_iva_id`, `l`.`anno`, IF((`l`.`periodicita` = 'T') OR (`l`.`trimestre_separato` = 1), `l`.`periodo`, `l`.`periodo` DIV 3), `l`.`credito_trimestrale`
FROM `liquidazione_gruppo_iva` `l` INNER JOIN `gruppo_iva` `g` ON `g`.`id` = `l`.`gruppo_iva_id` WHERE `l`.`credito_trimestrale` > 0;

ALTER TABLE `riga_iva` ADD COLUMN IF NOT EXISTS `percentuale_compensazione` DECIMAL(4,2) NULL COMMENT 'Vendite in regime agricolo: percentuale di compensazione dei prodotti della Tabella A, parte I';
ALTER TABLE `riga_iva` ADD COLUMN IF NOT EXISTS `operazione_diversa` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Acquisti in regime agricolo destinati alle operazioni diverse (art. 34, c. 5)';
ALTER TABLE `attivita_iva` ADD COLUMN IF NOT EXISTS `regime_detrazione_iva` CHAR(3) NULL COMMENT 'Regime speciale agricolo dell''attività (rigo VF30 del suo modulo)';
