-- =====================================================
-- Contabilita 0.2 - aggiornamento dei database creati con la versione 0.1
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già
-- tabelle e colonne in schema.sql). Idempotente.
--
-- Comunicazioni delle liquidazioni periodiche IVA, stampe definitive del
-- libro giornale e dei registri IVA, acquisti di "altri prodotti" per i
-- limiti della ventilazione dei corrispettivi, versamenti auto UE e
-- crediti d'imposta nelle liquidazioni (righi VP10 e VP11).

-- -----------------------------------------------------
-- Comunicazioni delle liquidazioni periodiche IVA (art. 21-bis D.L.
-- 78/2010, fornitura IVP18): una per trimestre, con un modulo per ogni
-- mese dei mensili o uno per il trimestre dei trimestrali
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `comunicazione_lipe` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL,
  `trimestre` TINYINT UNSIGNED NOT NULL,
  `progressivo` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Identificativo della comunicazione nella fornitura e nel nome del file',
  `stato` CHAR(1) NOT NULL DEFAULT 'B' COMMENT 'B bozza, I inviata',
  `dichiarante_id` INT UNSIGNED NULL COMMENT 'Firmatario diverso dal contribuente (obbligatorio per i soggetti diversi dalle persone fisiche)',
  `codice_carica` VARCHAR(2) NULL COMMENT 'Codice di carica del dichiarante (1-9, 11-15)',
  `intermediario_id` INT UNSIGNED NULL COMMENT 'Intermediario che trasmette (Deleghe)',
  `impegno` CHAR(1) NULL COMMENT 'Impegno alla presentazione: 1 predisposta dal contribuente, 2 da chi invia',
  `data_impegno` DATE NULL,
  `data_invio` DATE NULL,
  `protocollo` VARCHAR(50) NULL,
  `data_elaborazione` DATETIME NOT NULL,
  `avvisi` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_comunicazione_lipe` (`soggetto_id`, `anno`, `trimestre`),
  KEY `idx_comunicazione_lipe_studio` (`studio_id`, `anno`),
  CONSTRAINT `fk_comunicazione_lipe_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_comunicazione_lipe_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_comunicazione_lipe_dichiarante` FOREIGN KEY (`dichiarante_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_comunicazione_lipe_intermediario` FOREIGN KEY (`intermediario_id`) REFERENCES `intermediario` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Comunicazioni delle liquidazioni periodiche IVA';

CREATE TABLE IF NOT EXISTS `modulo_lipe` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `comunicazione_lipe_id` INT UNSIGNED NOT NULL,
  `numero_modulo` TINYINT UNSIGNED NOT NULL,
  `mese` TINYINT UNSIGNED NULL COMMENT 'Rigo VP1: mese dei mensili',
  `trimestre` TINYINT UNSIGNED NULL COMMENT 'Rigo VP1: trimestre (5 = quarto dei trimestrali)',
  `liquidazione_iva_id` INT UNSIGNED NULL,
  `operazioni_attive` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP2',
  `operazioni_passive` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP3',
  `iva_esigibile` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP4',
  `iva_detratta` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP5',
  `debito_precedente` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP7',
  `credito_precedente` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP8',
  `credito_anno_precedente` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP9',
  `versamenti_auto` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP10',
  `crediti_imposta` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP11',
  `interessi` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP12',
  `metodo_acconto` CHAR(1) NULL COMMENT 'VP13 col. 1: 1 storico, 2 previsionale, 3 analitico, 4 settori particolari',
  `acconto` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP13 col. 2',
  `importo_da_versare` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP14 col. 1',
  `importo_a_credito` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP14 col. 2',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_modulo_lipe` (`comunicazione_lipe_id`, `numero_modulo`),
  CONSTRAINT `fk_modulo_lipe_comunicazione` FOREIGN KEY (`comunicazione_lipe_id`) REFERENCES `comunicazione_lipe` (`id`),
  CONSTRAINT `fk_modulo_lipe_liquidazione` FOREIGN KEY (`liquidazione_iva_id`) REFERENCES `liquidazione_iva` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Moduli del quadro VP delle comunicazioni delle liquidazioni periodiche';

-- -----------------------------------------------------
-- Stampe definitive con le pagine numerate per anno del libro giornale
-- (per esercizio, art. 2215 c.c.) e dei registri IVA (per anno solare,
-- art. 39 D.P.R. 633/1972): periodi contigui, registrazioni dei periodi
-- stampati non più modificabili
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `stampa_definitiva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `tipo` CHAR(1) NOT NULL COMMENT 'G libro giornale, R registro IVA',
  `esercizio_contabile_id` INT UNSIGNED NULL COMMENT 'Libro giornale: esercizio',
  `registro_iva_id` INT UNSIGNED NULL COMMENT 'Registro IVA stampato',
  `anno` SMALLINT UNSIGNED NOT NULL COMMENT 'Anno della numerazione delle pagine',
  `dal` DATE NOT NULL,
  `al` DATE NOT NULL,
  `prima_pagina` INT UNSIGNED NOT NULL,
  `ultima_pagina` INT UNSIGNED NOT NULL,
  `righe_per_pagina` SMALLINT UNSIGNED NOT NULL COMMENT 'Per rigenerare le stesse pagine nella ristampa',
  `data_stampa` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_stampa_definitiva_libro` (`soggetto_id`, `tipo`, `anno`, `dal`),
  KEY `idx_stampa_definitiva_studio` (`studio_id`),
  CONSTRAINT `fk_stampa_definitiva_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_stampa_definitiva_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_stampa_definitiva_esercizio` FOREIGN KEY (`esercizio_contabile_id`) REFERENCES `esercizio_contabile` (`id`),
  CONSTRAINT `fk_stampa_definitiva_registro` FOREIGN KEY (`registro_iva_id`) REFERENCES `registro_iva` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stampe definitive del libro giornale e dei registri IVA';

-- -----------------------------------------------------
-- Acquisti per la rivendita di altri prodotti (limite del 50% della
-- ventilazione, art. 4 D.M. 24 febbraio 1973)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `riga_iva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rivendita` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `riga_iva`
  ADD COLUMN IF NOT EXISTS `altri_prodotti` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Rivendita di beni fuori dalle categorie ammesse alla ventilazione (limite del 50%, art. 4 D.M. 24/2/1973)' AFTER `rivendita`;

-- -----------------------------------------------------
-- Versamenti auto UE e crediti d'imposta della liquidazione (righi VP10 e
-- VP11 della comunicazione delle liquidazioni periodiche)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `liquidazione_iva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `credito_anno_precedente` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `liquidazione_iva`
  ADD COLUMN IF NOT EXISTS `versamenti_auto` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'IVA versata con F24 elementi identificativi sulle auto di provenienza UE (D.L. 262/2006)' AFTER `credito_anno_precedente`,
  ADD COLUMN IF NOT EXISTS `crediti_imposta` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Crediti d''imposta particolari scomputati dal versamento' AFTER `versamenti_auto`;

ALTER TABLE `modulo_lipe`
  ADD COLUMN IF NOT EXISTS `versamenti_auto` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP10' AFTER `credito_anno_precedente`,
  ADD COLUMN IF NOT EXISTS `crediti_imposta` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP11' AFTER `versamenti_auto`;
