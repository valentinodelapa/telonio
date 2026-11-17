-- =====================================================
-- Dichiarativi 0.10 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già le
-- colonne e le tabelle in schema.sql). Idempotente.

-- Modelli ISA: catalogo dei campi anche per codice dell'ISA (i campi del
-- quadro degli elementi specifici cambiano da un ISA all'altro) e formati
-- delle specifiche ISA fino a 8 caratteri (VAL11D2).

ALTER TABLE `campo_modello` ADD COLUMN IF NOT EXISTS `isa` CHAR(5) NOT NULL DEFAULT '' COMMENT 'Modello ISA: codice dell''ISA (es. EM01A); vuoto per gli altri modelli' AFTER `anno`;
ALTER TABLE `campo_modello` MODIFY `codice` CHAR(8) NOT NULL COMMENT 'Quadro (2), rigo (3 cifre), colonna (3); ISA: quadro (3), rigo (3), colonna (2)';
ALTER TABLE `campo_modello` MODIFY `formato` VARCHAR(8) NULL COMMENT 'Formato del campo-valore (AN, NU, NP, CB, ..., degli ISA VAL11, NU11, ...); NULL se non indicato dalle specifiche';
ALTER TABLE `campo_modello` DROP INDEX IF EXISTS `uk_campo_modello`;
ALTER TABLE `campo_modello` ADD UNIQUE KEY `uk_campo_modello` (`modello`, `anno`, `isa`, `codice`);

-- Elenco degli ISA in vigore con le attività (per scegliere l'ISA dal codice
-- ATECO del contribuente).

CREATE TABLE IF NOT EXISTS `indice_isa` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `anno` SMALLINT UNSIGNED NOT NULL COMMENT 'Anno del modello (anno d''imposta + 1)',
  `codice` CHAR(5) NOT NULL COMMENT 'Codice dell''ISA (es. EM01A)',
  `descrizione` VARCHAR(255) NOT NULL,
  `attivita` TEXT NOT NULL COMMENT 'Codici ATECO 2025 delle attività dell''ISA, separati da spazi (es. 47.11.02)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_indice_isa` (`anno`, `codice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Indici sintetici di affidabilità fiscale in vigore e loro attività (tabella 1 delle istruzioni ISA)';

-- Modello ISA come dichiarazione del modello "ISA" collegata alla
-- dichiarazione Redditi a cui è allegato.

ALTER TABLE `dichiarazione` ADD COLUMN IF NOT EXISTS `isa` CHAR(5) NULL COMMENT 'Modello ISA: codice dell''ISA applicato' AFTER `trimestre`;
ALTER TABLE `dichiarazione` ADD COLUMN IF NOT EXISTS `dichiarazione_redditi_id` INT UNSIGNED NULL COMMENT 'Modello ISA: dichiarazione Redditi a cui è allegato' AFTER `isa`;
-- indice proprio per la chiave esterna del soggetto, prima di sostituire la chiave univoca che la serviva
ALTER TABLE `dichiarazione` ADD INDEX IF NOT EXISTS `idx_dichiarazione_soggetto` (`soggetto_id`);
ALTER TABLE `dichiarazione` DROP INDEX IF EXISTS `uk_dichiarazione`;
ALTER TABLE `dichiarazione` ADD UNIQUE KEY `uk_dichiarazione` (`soggetto_id`, `modello`, `anno`, `trimestre`, `isa`);
ALTER TABLE `dichiarazione` DROP FOREIGN KEY IF EXISTS `fk_dichiarazione_redditi`;
ALTER TABLE `dichiarazione` ADD CONSTRAINT `fk_dichiarazione_redditi` FOREIGN KEY (`dichiarazione_redditi_id`) REFERENCES `dichiarazione` (`id`) ON DELETE CASCADE;

-- Modello ISA: risultati del calcolo del software ufficiale "Il tuo ISA".

ALTER TABLE `dichiarazione` ADD COLUMN IF NOT EXISTS `calcolo_isa` MEDIUMTEXT NULL COMMENT 'Modello ISA: record P, Q ed R restituiti dal software di calcolo (con punteggio e indicatori), allegati al file Redditi' AFTER `annotazioni`;
ALTER TABLE `dichiarazione` ADD COLUMN IF NOT EXISTS `esito_isa` MEDIUMTEXT NULL COMMENT 'Modello ISA: esito del calcolo in XML (indicatori e punteggi)' AFTER `calcolo_isa`;
ALTER TABLE `dichiarazione` ADD COLUMN IF NOT EXISTS `report_isa` TEXT NULL COMMENT 'Modello ISA: messaggi del software di calcolo (controlli)' AFTER `esito_isa`;
ALTER TABLE `dichiarazione` ADD COLUMN IF NOT EXISTS `impronta_isa` CHAR(64) NULL COMMENT 'Modello ISA: impronta SHA-256 dei dati calcolati (calcolo da rifare se i quadri cambiano)' AFTER `report_isa`;

-- Modello ISA: le annotazioni sono le note aggiuntive per l'Agenzia (record R, fino a 1.768 caratteri).

ALTER TABLE `dichiarazione` MODIFY `annotazioni` VARCHAR(1768) NULL COMMENT 'Note della dichiarazione; nel modello ISA le note aggiuntive per l''Agenzia (record R)';
