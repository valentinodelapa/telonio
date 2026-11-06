-- =====================================================
-- Dichiarativi 0.3 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già la
-- tabella in schema.sql). Idempotente.
--
-- Archivio delle garanzie dei rimborsi IVA (art. 38-bis, c. 5).

CREATE TABLE IF NOT EXISTS `garanzia_rimborso` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno_imposta` SMALLINT UNSIGNED NOT NULL,
  `tipo` CHAR(1) NOT NULL COMMENT 'T titoli di Stato, B banca, I impresa commerciale, A assicurazione, C consorzio fidi, G capogruppo',
  `garante` VARCHAR(120) NULL,
  `estremi` VARCHAR(60) NULL,
  `importo` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `data_rilascio` DATE NOT NULL,
  `data_rimborso` DATE NULL COMMENT 'Esecuzione del rimborso, da cui decorre la durata',
  `data_scadenza` DATE NULL,
  `data_svincolo` DATE NULL,
  PRIMARY KEY (`id`),
  KEY `idx_garanzia_rimborso_soggetto` (`soggetto_id`, `data_rilascio`),
  KEY `idx_garanzia_rimborso_studio` (`studio_id`, `data_scadenza`),
  CONSTRAINT `fk_garanzia_rimborso_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_garanzia_rimborso_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Garanzie dei rimborsi IVA (art. 38-bis, c. 5)';
