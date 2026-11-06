-- =====================================================
-- Dichiarativi 0.2 - aggiornamento dei database creati con la versione 0.1
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già
-- tabella e colonna in schema.sql). Idempotente.
--
-- Archivio degli atti di accertamento e verifica degli atti nel frontespizio,
-- per le situazioni di rischio dei rimborsi IVA oltre 30.000 euro.

CREATE TABLE IF NOT EXISTS `atto_accertamento` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno_imposta` SMALLINT UNSIGNED NOT NULL,
  `data_notifica` DATE NOT NULL,
  `estremi` VARCHAR(60) NULL COMMENT 'Numero dell''atto',
  `importo_dichiarato` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Imposta dovuta o credito dichiarato',
  `importo_accertato` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_atto_accertamento_soggetto` (`soggetto_id`, `data_notifica`),
  CONSTRAINT `fk_atto_accertamento_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_atto_accertamento_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Avvisi di accertamento o rettifica per i rischi dei rimborsi IVA (art. 38-bis, c. 4, lett. b)';

ALTER TABLE `dichiarazione` ADD COLUMN IF NOT EXISTS `verifica_accertamenti` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Verificati gli atti notificati nei due anni precedenti';
