-- =====================================================
-- Contabilita 0.11 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già la
-- colonna in schema.sql). Idempotente.
--
-- Metodo del plafond degli esportatori abituali per esercizio: solare
-- (operazioni dell'anno precedente) o mensile (dodici mesi precedenti).
--
-- Registro dei beni ammortizzabili (cespite) e ratei e risconti per le
-- scritture di assestamento (rateo_risconto). Funzioni dei conti di ratei,
-- risconti, risultato d'esercizio e aperture e chiusure nei piani delle
-- aziende copiati dal piano standard (stesso codice e descrizione), se il
-- conto non ha già una funzione.

CREATE TABLE IF NOT EXISTS `esercizio_contabile` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chiuso` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `esercizio_contabile`
  ADD COLUMN IF NOT EXISTS `plafond_mensile` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Plafond degli esportatori abituali con il metodo mensile' AFTER `chiuso`;

CREATE TABLE IF NOT EXISTS `cespite` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `codice` VARCHAR(20) NOT NULL,
  `descrizione` VARCHAR(255) NOT NULL,
  `conto_cespite_id` INT UNSIGNED NOT NULL COMMENT 'Immobilizzazione',
  `conto_fondo_id` INT UNSIGNED NOT NULL COMMENT 'Fondo ammortamento',
  `conto_ammortamento_id` INT UNSIGNED NOT NULL COMMENT 'Quota di ammortamento (costo)',
  `data_acquisto` DATE NOT NULL,
  `data_entrata_funzione` DATE NULL COMMENT 'Se diversa dall''acquisto: l''ammortamento parte dal suo esercizio',
  `costo` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `percentuale` DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT 'Coefficiente annuo (D.M. 31 dicembre 1988)',
  `riduzione_primo_esercizio` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Coefficiente ridotto alla metà nel primo esercizio',
  `deduzione_integrale` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Beni fino a 516,46 euro dedotti per intero nel primo esercizio',
  `percentuale_fiscale` DECIMAL(5,2) NULL COMMENT 'Coefficiente fiscale massimo, se diverso da quello civilistico',
  `percentuale_deducibile` DECIMAL(5,2) NOT NULL DEFAULT 100 COMMENT 'Parte deducibile delle quote (autovetture, telefonia)',
  `limite_costo_fiscale` DECIMAL(15,2) NULL COMMENT 'Costo fiscalmente rilevante massimo (autovetture)',
  `fondo_iniziale` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Ammortamenti effettuati alla fine dell''esercizio indicato',
  `esercizio_fondo_iniziale` SMALLINT UNSIGNED NULL,
  `registrazione_acquisto_id` INT UNSIGNED NULL,
  `data_dismissione` DATE NULL,
  `prezzo_dismissione` DECIMAL(15,2) NULL,
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cespite_codice` (`soggetto_id`, `codice`),
  KEY `idx_cespite_studio` (`studio_id`),
  CONSTRAINT `fk_cespite_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_cespite_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_cespite_conto_cespite` FOREIGN KEY (`conto_cespite_id`) REFERENCES `conto` (`id`),
  CONSTRAINT `fk_cespite_conto_fondo` FOREIGN KEY (`conto_fondo_id`) REFERENCES `conto` (`id`),
  CONSTRAINT `fk_cespite_conto_ammortamento` FOREIGN KEY (`conto_ammortamento_id`) REFERENCES `conto` (`id`),
  CONSTRAINT `fk_cespite_registrazione` FOREIGN KEY (`registrazione_acquisto_id`) REFERENCES `registrazione_contabile` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registro dei beni ammortizzabili (art. 16 D.P.R. 600/1973)';

CREATE TABLE IF NOT EXISTS `rateo_risconto` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL COMMENT 'Esercizio (anno di chiusura) dell''assestamento',
  `conto_id` INT UNSIGNED NOT NULL COMMENT 'Conto economico del costo o ricavo',
  `descrizione` VARCHAR(255) NOT NULL,
  `importo` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Importo dell''intero periodo di competenza',
  `competenza_dal` DATE NOT NULL,
  `competenza_al` DATE NOT NULL,
  `registrato_nell_esercizio` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 risconto (registrato nell''esercizio), 0 rateo (registrato nel successivo)',
  PRIMARY KEY (`id`),
  KEY `idx_rateo_risconto_soggetto` (`soggetto_id`, `anno`),
  KEY `idx_rateo_risconto_studio` (`studio_id`),
  CONSTRAINT `fk_rateo_risconto_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_rateo_risconto_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_rateo_risconto_conto` FOREIGN KEY (`conto_id`) REFERENCES `conto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Ratei e risconti dell''esercizio per le scritture di assestamento';

UPDATE `conto` SET `funzione` = 'RAA' WHERE `codice` = '08.01.0001' AND `descrizione` = 'Ratei attivi' AND `funzione` IS NULL;
UPDATE `conto` SET `funzione` = 'RIA' WHERE `codice` = '08.01.0002' AND `descrizione` = 'Risconti attivi' AND `funzione` IS NULL;
UPDATE `conto` SET `funzione` = 'RAP' WHERE `codice` = '14.01.0001' AND `descrizione` = 'Ratei passivi' AND `funzione` IS NULL;
UPDATE `conto` SET `funzione` = 'RIP' WHERE `codice` = '14.01.0002' AND `descrizione` = 'Risconti passivi' AND `funzione` IS NULL;
UPDATE `conto` SET `funzione` = 'UTE' WHERE `codice` = '10.02.0001' AND `descrizione` = 'Utile dell''esercizio' AND `funzione` IS NULL;
UPDATE `conto` SET `funzione` = 'PEE' WHERE `codice` = '10.02.0002' AND `descrizione` = 'Perdita dell''esercizio' AND `funzione` IS NULL;
UPDATE `conto` SET `funzione` = 'SPA' WHERE `codice` = '40.01.0001' AND `descrizione` = 'Stato patrimoniale di apertura' AND `funzione` IS NULL;
UPDATE `conto` SET `funzione` = 'SPC' WHERE `codice` = '40.01.0002' AND `descrizione` = 'Stato patrimoniale di chiusura' AND `funzione` IS NULL;
UPDATE `conto` SET `funzione` = 'CEC' WHERE `codice` = '40.01.0003' AND `descrizione` = 'Conto economico' AND `funzione` IS NULL;
UPDATE `conto` SET `funzione` = 'PLV' WHERE `codice` = '20.02.0002' AND `descrizione` = 'Plusvalenze ordinarie' AND `funzione` IS NULL;
UPDATE `conto` SET `funzione` = 'MNV' WHERE `codice` = '27.01.0003' AND `descrizione` = 'Minusvalenze ordinarie' AND `funzione` IS NULL;
UPDATE `conto` SET `funzione` = 'CSO' WHERE `codice` = '10.01.0001' AND `descrizione` = 'Capitale sociale' AND `funzione` IS NULL;
UPDATE `conto` SET `funzione` = 'CNE' WHERE `codice` = '10.01.0006' AND `descrizione` = 'Capitale netto (imprese individuali e società di persone)' AND `funzione` IS NULL;
UPDATE `conto` SET `funzione` = 'RLE' WHERE `codice` = '10.01.0002' AND `descrizione` = 'Riserva legale' AND `funzione` IS NULL;
UPDATE `conto` SET `funzione` = 'RST' WHERE `codice` = '10.01.0003' AND `descrizione` = 'Riserva straordinaria' AND `funzione` IS NULL;
UPDATE `conto` SET `funzione` = 'UNU' WHERE `codice` = '10.01.0005' AND `descrizione` = 'Utili (perdite) portati a nuovo' AND `funzione` IS NULL;
