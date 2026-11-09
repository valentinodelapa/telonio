-- =====================================================
-- Dichiarativi 0.5 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già le
-- tabelle in schema.sql). Idempotente.
--
-- Archivio delle Certificazioni Uniche per il quadro RC del modello Redditi e
-- tabelle delle addizionali regionale e comunale.

CREATE TABLE IF NOT EXISTS `certificazione_unica` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno_imposta` SMALLINT UNSIGNED NOT NULL,
  `codice_fiscale_sostituto` VARCHAR(16) NOT NULL,
  `denominazione_sostituto` VARCHAR(100) NOT NULL,
  `conguagliata` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Compresa nel conguaglio di un altro sostituto: non si dichiara',
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_certificazione_unica_soggetto` (`soggetto_id`, `anno_imposta`),
  CONSTRAINT `fk_certificazione_unica_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_certificazione_unica_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Certificazioni Uniche (lavoro dipendente, pensione e assimilati) dei contribuenti';

CREATE TABLE IF NOT EXISTS `valore_certificazione_unica` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `certificazione_unica_id` INT UNSIGNED NOT NULL,
  `punto` SMALLINT UNSIGNED NOT NULL,
  `valore` DECIMAL(14,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_valore_certificazione_unica` (`certificazione_unica_id`, `punto`),
  CONSTRAINT `fk_valore_certificazione_unica` FOREIGN KEY (`certificazione_unica_id`) REFERENCES `certificazione_unica` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Punti della Certificazione Unica (importi, giorni, codici)';

-- Aliquote delle addizionali regionale e comunale all'IRPEF per anno
-- d'imposta, dalle tabelle ufficiali (sorgenti del modulo, importate con
-- "sisma tabelle-dichiarativi").

CREATE TABLE IF NOT EXISTS `addizionale_regionale` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `anno` SMALLINT UNSIGNED NOT NULL COMMENT 'Anno d''imposta',
  `codice` CHAR(2) NOT NULL COMMENT 'Codice regione o provincia autonoma (rigo RV4)',
  `regione` VARCHAR(40) NOT NULL,
  `scaglioni` VARCHAR(120) NOT NULL COMMENT 'limite:aliquota separati da |, ultimo senza limite',
  `modalita` VARCHAR(12) NOT NULL COMMENT 'progressiva per scaglioni, intero: aliquota dello scaglione sull''intero reddito',
  `esenzione_reddito` DECIMAL(12,2) NULL COMMENT 'Esenzione con reddito complessivo fino alla soglia',
  `soglia_aliquota` VARCHAR(30) NULL COMMENT 'soglia:aliquota sull''intero reddito fino alla soglia',
  `detrazioni` VARCHAR(120) NULL COMMENT 'importo:oltre:fino separati da |',
  `detrazione_crescente` VARCHAR(40) NULL COMMENT 'da:ampiezza:massimo',
  `deduzione` VARCHAR(40) NULL COMMENT 'importo:fino',
  `casi_particolari` TEXT NULL COMMENT 'Agevolazioni non calcolate (figli, disabilità)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_addizionale_regionale` (`anno`, `codice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Aliquote dell''addizionale regionale all''IRPEF (tabella delle istruzioni del modello Redditi PF)';

CREATE TABLE IF NOT EXISTS `addizionale_comunale` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `anno` SMALLINT UNSIGNED NOT NULL COMMENT 'Anno d''imposta (saldo; vale anche per l''acconto dell''anno dopo)',
  `codice_catastale` CHAR(4) NOT NULL,
  `aliquota_unica` DECIMAL(5,3) NULL,
  `aliquote` VARCHAR(40) NULL COMMENT 'Aliquote per scaglione separate da |',
  `tipologia` TINYINT UNSIGNED NULL COMMENT '1 quattro scaglioni (15.000, 28.000, 50.000), 2 tre scaglioni (28.000, 50.000)',
  `esenzione` DECIMAL(12,2) NULL COMMENT 'Esenzione con imponibile fino alla soglia',
  `tipizzazione` VARCHAR(4) NULL COMMENT 'Codice dei casi particolari (legenda della tabella)',
  `esenzione_casi` DECIMAL(12,2) NULL COMMENT 'Soglia dei casi particolari',
  `casi_particolari` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_addizionale_comunale` (`anno`, `codice_catastale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Aliquote dell''addizionale comunale all''IRPEF (elenco pubblicato dall''Agenzia delle Entrate)';

-- Scelte per la destinazione dell'otto, del cinque e del due per mille
-- dell'IRPEF del modello Redditi.

ALTER TABLE `dichiarazione` ADD COLUMN IF NOT EXISTS `otto_per_mille` VARCHAR(2) NULL COMMENT 'Redditi: istituzione beneficiaria dell''otto per mille (NULL = scelta non effettuata)' AFTER `annotazioni`;
ALTER TABLE `dichiarazione` ADD COLUMN IF NOT EXISTS `finalita_stato` TINYINT UNSIGNED NULL COMMENT 'Otto per mille allo Stato: finalità da 1 a 6' AFTER `otto_per_mille`;
ALTER TABLE `dichiarazione` ADD COLUMN IF NOT EXISTS `cinque_per_mille` VARCHAR(2) NULL COMMENT 'Redditi: finalità del cinque per mille' AFTER `finalita_stato`;
ALTER TABLE `dichiarazione` ADD COLUMN IF NOT EXISTS `beneficiario_cinque_per_mille` VARCHAR(11) NULL COMMENT 'Codice fiscale del beneficiario del cinque per mille' AFTER `cinque_per_mille`;
ALTER TABLE `dichiarazione` ADD COLUMN IF NOT EXISTS `due_per_mille` VARCHAR(3) NULL COMMENT 'Redditi: codice del partito politico del due per mille' AFTER `beneficiario_cinque_per_mille`;
