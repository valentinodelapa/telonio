-- =====================================================
-- Dichiarativi 0.4 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già le
-- modifiche in schema.sql). Idempotente.
--
-- Garanzie delle eccedenze compensate nella liquidazione IVA di gruppo.

ALTER TABLE `garanzia_rimborso` ADD COLUMN IF NOT EXISTS `compensazione_gruppo` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Eccedenze compensate nella liquidazione di gruppo (art. 6 D.M. 13/12/1979)' AFTER `data_rilascio`;
ALTER TABLE `garanzia_rimborso` MODIFY COLUMN `data_rimborso` DATE NULL COMMENT 'Esecuzione del rimborso o presentazione della dichiarazione per le compensazioni di gruppo: da qui decorre la durata';

-- Differimento della decadenza per il ritardo nella consegna dei documenti.

ALTER TABLE `garanzia_rimborso` ADD COLUMN IF NOT EXISTS `data_richiesta_documenti` DATE NULL COMMENT 'Notifica della richiesta di documenti per il rimborso (art. 57, c. 3)' AFTER `data_rimborso`;
ALTER TABLE `garanzia_rimborso` ADD COLUMN IF NOT EXISTS `data_consegna_documenti` DATE NULL COMMENT 'Consegna dei documenti: oltre i quindici giorni differisce la decadenza' AFTER `data_richiesta_documenti`;

-- Prospetto D delle rettifiche della detrazione (rigo VF70).

CREATE TABLE IF NOT EXISTS `rettifica_detrazione` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dichiarazione_id` INT UNSIGNED NOT NULL,
  `modulo` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `rigo` TINYINT UNSIGNED NOT NULL COMMENT 'Rigo del prospetto D, da 1 a 5',
  `importo` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Più: aumento della detrazione; meno: diminuzione',
  `nota` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rettifica_detrazione` (`dichiarazione_id`, `modulo`, `rigo`),
  CONSTRAINT `fk_rettifica_detrazione_dichiarazione` FOREIGN KEY (`dichiarazione_id`) REFERENCES `dichiarazione` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Prospetto D delle rettifiche della detrazione (rigo VF70)';
