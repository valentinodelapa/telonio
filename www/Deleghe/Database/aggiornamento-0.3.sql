-- =====================================================
-- Deleghe 0.3 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Da eseguire come root sui database esistenti (le nuove installazioni hanno
-- già tabella e colonna in schema.sql), poi eliminare Cache/referenceCache.json.
-- Idempotente.
--
-- Pagamento rateale del saldo e del primo acconto (art. 20, c. 1, D.Lgs.
-- 241/1997): piani di rateazione e collegamento dei versamenti originari.
-- Sanzioni civili collegate al versamento scaduto pagato in ritardo.
-- Rateazione straordinaria del secondo acconto: norme per periodo d'imposta
-- (le stesse righe di dati-riferimento.sql, da Database/sorgenti).

CREATE TABLE IF NOT EXISTS `piano_rateazione` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `scadenza` DATE NOT NULL COMMENT 'Scadenza dei versamenti originari (prima rata)',
  `numero_rate` TINYINT UNSIGNED NOT NULL,
  `date_rate` TEXT NOT NULL COMMENT 'JSON: date delle rate (Y-m-d)',
  `descrizione` VARCHAR(100) NULL,
  `data_inserimento` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_piano_rateazione_soggetto` (`soggetto_id`),
  CONSTRAINT `fk_piano_rateazione_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_piano_rateazione_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `versamento`
  ADD COLUMN IF NOT EXISTS `piano_rateazione_id` INT UNSIGNED NULL COMMENT 'Versamento originario pagato a rate: non più da versare' AFTER `codice_identificativo`,
  ADD CONSTRAINT `fk_versamento_piano_rateazione` FOREIGN KEY IF NOT EXISTS (`piano_rateazione_id`) REFERENCES `piano_rateazione` (`id`);

ALTER TABLE `sanzione_civile`
  ADD COLUMN IF NOT EXISTS `versamento_tardivo_id` INT UNSIGNED NULL COMMENT 'Versamento scaduto dell''archivio pagato in ritardo con la delega' AFTER `delega_id`,
  ADD CONSTRAINT `fk_sanzione_civile_versamento_tardivo` FOREIGN KEY IF NOT EXISTS (`versamento_tardivo_id`) REFERENCES `versamento` (`id`);

CREATE TABLE IF NOT EXISTS `rateazione_secondo_acconto` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `periodo_imposta` SMALLINT UNSIGNED NOT NULL,
  `prima_rata` DATE NOT NULL COMMENT 'Termine differito del versamento in unica soluzione o della prima rata',
  `numero_rate` TINYINT UNSIGNED NOT NULL COMMENT 'Numero massimo di rate mensili',
  `codici` VARCHAR(255) NOT NULL COMMENT 'Codici tributo del secondo acconto (sezione erario), separati da spazi',
  `norma` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rateazione_secondo_acconto` (`periodo_imposta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Norme straordinarie sul versamento differito o rateale del secondo acconto, da Database/sorgenti';

INSERT INTO `rateazione_secondo_acconto` (`periodo_imposta`, `prima_rata`, `numero_rate`, `codici`, `norma`) VALUES
('2023', '2024-01-16', '5', '4034 1791 1794 1841 4045 4048', 'Art. 4, c. 2, D.L. 145/2023 (circolare n. 31/E del 2023): persone fisiche titolari di partita IVA con ricavi o compensi 2022 non superiori a 170.000 euro'),
('2024', '2025-01-16', '5', '4034 1791 1794 1841 4045 4048', 'Art. 7-quater D.L. 155/2024, conv. L. 189/2024: persone fisiche titolari di partita IVA con ricavi o compensi 2023 non superiori a 170.000 euro')
ON DUPLICATE KEY UPDATE `prima_rata` = VALUES(`prima_rata`), `numero_rate` = VALUES(`numero_rate`), `codici` = VALUES(`codici`), `norma` = VALUES(`norma`);
