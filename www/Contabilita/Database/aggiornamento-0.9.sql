-- =====================================================
-- Contabilita 0.9 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già le
-- colonne in schema.sql). Idempotente.
--
-- Codici IVA: norma del Testo unico IVA (D.Lgs. 10/2026, dal 1 gennaio 2027)
-- e ultimo giorno di validità (inversione contabile per cellulari, prodotti
-- elettronici, quote di emissione, gas ed energia limitata alle operazioni
-- fino al 31 dicembre 2026: art. 17, c. 8, D.P.R. 633/1972; art. 64, c. 8,
-- D.Lgs. 10/2026). I valori dei codici della suite li scrive
-- dati-riferimento.sql, eseguito subito dopo.
--
-- Scadenze e rate delle partite (scadenza_partita) e termini di pagamento
-- abituali delle controparti. Le partite esistenti restano senza rate:
-- valgono come rata unica alla loro scadenza.
--
-- Liquidazioni dei trimestrali per natura dell'attività (art. 74, c. 4,
-- D.P.R. 633/1972): senza interessi, quarto trimestre entro il 16 febbraio.

CREATE TABLE IF NOT EXISTS `codice_iva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `norma` VARCHAR(255) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `codice_iva`
  ADD COLUMN IF NOT EXISTS `norma_testo_unico` VARCHAR(255) NULL COMMENT 'Norma dal 1 gennaio 2027 (Testo unico IVA, D.Lgs. 10/2026)' AFTER `norma`,
  ADD COLUMN IF NOT EXISTS `valido_fino` DATE NULL COMMENT 'Ultimo giorno delle operazioni a cui si applica (es. inversione contabile fino al 31/12/2026)' AFTER `norma_testo_unico`;

CREATE TABLE IF NOT EXISTS `controparte` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codice_iva_id` INT UNSIGNED NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `controparte`
  ADD COLUMN IF NOT EXISTS `giorni_pagamento` VARCHAR(100) NULL COMMENT 'Termini di pagamento abituali: giorni dalla data del documento (30,60,90)' AFTER `codice_iva_id`,
  ADD COLUMN IF NOT EXISTS `fine_mese_pagamento` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Scadenze dei termini a fine mese' AFTER `giorni_pagamento`,
  ADD COLUMN IF NOT EXISTS `modalita_pagamento` CHAR(4) NULL COMMENT 'Modalità di pagamento abituale (MP01-MP23)' AFTER `fine_mese_pagamento`;

-- -----------------------------------------------------
-- Rate delle partite dei documenti (scadenze di pagamento): dai dati di
-- pagamento della fattura elettronica, dai termini della controparte o
-- indicate nella prima nota. Gli abbinamenti restano sulla partita; la
-- partita senza rate vale come rata unica alla sua scadenza.
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `scadenza_partita` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `soggetto_id` INT UNSIGNED NOT NULL COMMENT 'Copiato dalla partita',
  `partita_id` INT UNSIGNED NOT NULL,
  `numero` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `data_scadenza` DATE NOT NULL,
  `importo` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `modalita` CHAR(4) NULL COMMENT 'Modalità di pagamento della fattura elettronica (MP01-MP23)',
  PRIMARY KEY (`id`),
  KEY `idx_scadenza_partita_partita` (`partita_id`, `numero`),
  KEY `idx_scadenza_partita_soggetto` (`soggetto_id`, `data_scadenza`),
  CONSTRAINT `fk_scadenza_partita_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_scadenza_partita_partita` FOREIGN KEY (`partita_id`) REFERENCES `partita` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Rate (scadenze di pagamento) delle partite dei documenti';

CREATE TABLE IF NOT EXISTS `liquidazione_iva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `periodicita` CHAR(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `liquidazione_iva`
  ADD COLUMN IF NOT EXISTS `trimestrale_speciale` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Trimestrale per natura (art. 74, c. 4): senza interessi, quarto trimestre entro il 16 febbraio (6034)' AFTER `periodicita`;
