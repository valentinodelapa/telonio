-- =====================================================
-- Contabilita 0.3 - aggiornamento dei database creati con le versioni 0.1 e 0.2
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già
-- tabelle e colonne in schema.sql). Idempotente.
--
-- Tipologia dell'inversione contabile sulle righe IVA degli acquisti (rigo
-- del quadro VJ della dichiarazione IVA), qualifica delle operazioni
-- attive (VF34, VE40), aliquota teorica degli acquisti con dichiarazione
-- d'intento (VF35), importazioni (VF28, VC), unità locale dei registri
-- (VT) e pro-rata di detrazione provvisorio applicato nelle liquidazioni
-- periodiche. Attività con contabilità separata (art. 36) con le
-- liquidazioni per attività e la ripartizione degli acquisti promiscui;
-- IVA di gruppo (art. 73) con le liquidazioni e l'acconto del gruppo e la
-- comunicazione delle liquidazioni periodiche del gruppo. Le tabelle nuove
-- non referenziate da colonne esistenti sono create da schema.sql.

CREATE TABLE IF NOT EXISTS `attivita_iva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `numero` TINYINT UNSIGNED NOT NULL COMMENT 'Ordine dei moduli della dichiarazione IVA (1 = primo modulo)',
  `descrizione` VARCHAR(100) NOT NULL,
  `codice_ateco_id` INT UNSIGNED NULL COMMENT 'Codice attività del rigo VA2 del modulo',
  `periodicita` CHAR(1) NULL COMMENT 'M mensile, T trimestrale; NULL = periodicità dell''azienda',
  `dal_anno` SMALLINT UNSIGNED NOT NULL,
  `al_anno` SMALLINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attivita_iva` (`soggetto_id`, `numero`),
  KEY `idx_attivita_iva_studio` (`studio_id`),
  CONSTRAINT `fk_attivita_iva_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_attivita_iva_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_attivita_iva_ateco` FOREIGN KEY (`codice_ateco_id`) REFERENCES `codice_ateco` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Attività IVA con contabilità separata';

CREATE TABLE IF NOT EXISTS `gruppo_iva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `controllante_id` INT UNSIGNED NOT NULL,
  `anno_inizio` SMALLINT UNSIGNED NOT NULL COMMENT 'Primo anno della liquidazione di gruppo (opzione nel quadro VG della dichiarazione dell''anno precedente)',
  `anno_revoca` SMALLINT UNSIGNED NULL COMMENT 'Primo anno senza liquidazione di gruppo (revoca nel VG8)',
  `maggioranza_id` INT UNSIGNED NULL COMMENT 'Soggetto che possiede la maggioranza della controllante e rinuncia alla liquidazione di gruppo (VG1)',
  `data_possesso_maggioranza` DATE NULL,
  `percentuale_maggioranza` DECIMAL(5,2) NULL,
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_gruppo_iva_studio` (`studio_id`),
  CONSTRAINT `fk_gruppo_iva_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_gruppo_iva_controllante` FOREIGN KEY (`controllante_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_gruppo_iva_maggioranza` FOREIGN KEY (`maggioranza_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Liquidazioni IVA di gruppo (art. 73)';

CREATE TABLE IF NOT EXISTS `liquidazione_gruppo_iva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `gruppo_iva_id` INT UNSIGNED NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL,
  `periodo` TINYINT UNSIGNED NOT NULL,
  `periodicita` CHAR(1) NOT NULL,
  `trimestre_anticipato` TINYINT UNSIGNED NULL COMMENT 'Trimestre delle società trimestrali compreso nel mese di fine trimestre',
  `trimestre_separato` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Liquidazione del trimestre delle società trimestrali non anticipata al mese',
  `debiti_trasferiti` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Compresi gli interessi dei trimestrali (VP4 della comunicazione del gruppo)',
  `crediti_trasferiti` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP5 della comunicazione del gruppo',
  `interessi_trasferiti` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `debito_precedente` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `credito_precedente` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `credito_anno_precedente` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Credito di gruppo dell''anno precedente compensato',
  `acconto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `importo_dovuto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `credito_finale` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `credito_trimestrale` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Credito del trimestre chiesto con il modello IVA TR della controllante, non riportato',
  `debito_rinviato` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `scadenza` DATE NULL,
  `data_elaborazione` DATETIME NOT NULL,
  `avvisi` TEXT NULL,
  `differenze` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_liquidazione_gruppo_iva` (`gruppo_iva_id`, `anno`, `periodo`, `trimestre_separato`),
  KEY `idx_liquidazione_gruppo_iva_studio` (`studio_id`, `anno`),
  CONSTRAINT `fk_liquidazione_gruppo_iva_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_liquidazione_gruppo_iva_gruppo` FOREIGN KEY (`gruppo_iva_id`) REFERENCES `gruppo_iva` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Liquidazioni periodiche del gruppo IVA';

ALTER TABLE `riga_iva` ADD COLUMN IF NOT EXISTS `qualifica` CHAR(1) NULL COMMENT 'Operazioni attive: O oro, E esente occasionale, Q 27-quinquies, A bene ammortizzabile, P esente equiparata, F fuori campo senza detrazione (VF34, VE40)' AFTER `conto_id`;
ALTER TABLE `riga_iva` ADD COLUMN IF NOT EXISTS `aliquota_teorica` DECIMAL(5,2) NULL COMMENT 'Acquisti con dichiarazione d''intento: aliquota dell''IVA non assolta (VF35)' AFTER `conto_id`;
ALTER TABLE `registrazione_contabile` ADD COLUMN IF NOT EXISTS `importazione` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Acquisto con documento doganale (VF28 campi 3-4, VC colonna 2)' AFTER `totale_documento`;
ALTER TABLE `registro_iva` ADD COLUMN IF NOT EXISTS `unita_locale_id` INT UNSIGNED NULL COMMENT 'Sede operativa (Anagrafica) delle operazioni del registro: regione nel quadro VT';
ALTER TABLE `registro_iva` ADD CONSTRAINT `fk_registro_iva_unita_locale` FOREIGN KEY IF NOT EXISTS (`unita_locale_id`) REFERENCES `indirizzo` (`id`) ON DELETE SET NULL;
ALTER TABLE `riga_iva` ADD COLUMN IF NOT EXISTS `natura_inversione` VARCHAR(4) NULL COMMENT 'Acquisti in inversione contabile: tipologia N6.1-N6.9 (rigo del quadro VJ della dichiarazione IVA)' AFTER `conto_id`;

-- -----------------------------------------------------
-- Percentuale di detrazione provvisoria dell'anno (pro-rata, art. 19,
-- c. 5, e art. 19-bis D.P.R. 633/1972), applicata nelle liquidazioni
-- periodiche: quella definitiva dell'anno precedente
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `prorata_iva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL,
  `percentuale` TINYINT UNSIGNED NOT NULL DEFAULT 100 COMMENT 'Percentuale di detrazione (0-100, arrotondata all''unità)',
  `origine` VARCHAR(30) NULL COMMENT 'Modulo che l''ha proposta (Dichiarativi), NULL se inserita a mano',
  `note` VARCHAR(255) NULL,
  `attivita_iva_id` INT UNSIGNED NULL COMMENT 'Attività con contabilità separata (pro-rata per attività)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prorata_iva` (`soggetto_id`, `anno`, `attivita_iva_id`),
  CONSTRAINT `fk_prorata_iva_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_prorata_iva_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_prorata_iva_attivita` FOREIGN KEY (`attivita_iva_id`) REFERENCES `attivita_iva` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Pro-rata di detrazione provvisorio per azienda e anno';

ALTER TABLE `registro_iva` ADD COLUMN IF NOT EXISTS `attivita_iva_id` INT UNSIGNED NULL COMMENT 'Attività con contabilità separata; NULL con attività separate = registro degli acquisti promiscui';
ALTER TABLE `registro_iva` ADD CONSTRAINT `fk_registro_iva_attivita` FOREIGN KEY IF NOT EXISTS (`attivita_iva_id`) REFERENCES `attivita_iva` (`id`) ON DELETE SET NULL;
ALTER TABLE `riga_iva` ADD COLUMN IF NOT EXISTS `ripartizione` VARCHAR(255) NULL COMMENT 'Acquisti promiscui tra attività separate: numero attività e percentuale (es. 1:60;2:40)' AFTER `aliquota_teorica`;
ALTER TABLE `liquidazione_iva` ADD COLUMN IF NOT EXISTS `trimestre_anticipato` TINYINT UNSIGNED NULL COMMENT 'Attività separate trimestrali: trimestre liquidato con il mese di fine trimestre';
ALTER TABLE `liquidazione_iva` ADD COLUMN IF NOT EXISTS `gruppo_iva_id` INT UNSIGNED NULL COMMENT 'IVA di gruppo: risultato trasferito alla controllante, non versato';
ALTER TABLE `liquidazione_iva` ADD CONSTRAINT `fk_liquidazione_iva_gruppo` FOREIGN KEY IF NOT EXISTS (`gruppo_iva_id`) REFERENCES `gruppo_iva` (`id`);
ALTER TABLE `acconto_iva` ADD COLUMN IF NOT EXISTS `gruppo_iva_id` INT UNSIGNED NULL COMMENT 'Acconto del gruppo IVA versato dalla controllante';
ALTER TABLE `acconto_iva` ADD CONSTRAINT `fk_acconto_iva_gruppo` FOREIGN KEY IF NOT EXISTS (`gruppo_iva_id`) REFERENCES `gruppo_iva` (`id`);
ALTER TABLE `acconto_iva` DROP INDEX IF EXISTS `uq_acconto_iva`, ADD UNIQUE KEY `uq_acconto_iva` (`soggetto_id`, `anno`, `gruppo_iva_id`);
ALTER TABLE `comunicazione_lipe` ADD COLUMN IF NOT EXISTS `gruppo_iva_id` INT UNSIGNED NULL COMMENT 'Comunicazione della controllante per la liquidazione del gruppo IVA';
ALTER TABLE `comunicazione_lipe` ADD CONSTRAINT `fk_comunicazione_lipe_gruppo` FOREIGN KEY IF NOT EXISTS (`gruppo_iva_id`) REFERENCES `gruppo_iva` (`id`);
ALTER TABLE `comunicazione_lipe` DROP INDEX IF EXISTS `uq_comunicazione_lipe`, ADD UNIQUE KEY `uq_comunicazione_lipe` (`soggetto_id`, `anno`, `trimestre`, `gruppo_iva_id`);
ALTER TABLE `modulo_lipe` ADD COLUMN IF NOT EXISTS `liquidazione_gruppo_iva_id` INT UNSIGNED NULL COMMENT 'Modulo della comunicazione del gruppo IVA';
ALTER TABLE `modulo_lipe` ADD CONSTRAINT `fk_modulo_lipe_liquidazione_gruppo` FOREIGN KEY IF NOT EXISTS (`liquidazione_gruppo_iva_id`) REFERENCES `liquidazione_gruppo_iva` (`id`) ON DELETE SET NULL;
ALTER TABLE `liquidazione_iva` ADD COLUMN IF NOT EXISTS `trimestre_separato` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Attività separate: liquidazione del trimestre delle attività trimestrali non anticipata al mese';
ALTER TABLE `liquidazione_iva` DROP INDEX IF EXISTS `uq_liquidazione_iva`, ADD UNIQUE KEY `uq_liquidazione_iva` (`soggetto_id`, `anno`, `periodo`, `trimestre_separato`);
ALTER TABLE `liquidazione_iva` ADD COLUMN IF NOT EXISTS `credito_trimestrale` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Credito del trimestre chiesto a rimborso o in compensazione con il modello IVA TR, non riportato' AFTER `credito_finale`;
