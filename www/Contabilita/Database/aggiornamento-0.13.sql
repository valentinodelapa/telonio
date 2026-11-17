-- =====================================================
-- Contabilita 0.13 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già la
-- colonna in schema.sql). Idempotente.

-- Rigo del quadro F dei modelli ISA a cui concorre il conto (o un suo campo
-- "di cui"); vuoto: dalla voce di bilancio.

ALTER TABLE `conto` ADD COLUMN IF NOT EXISTS `rigo_isa` VARCHAR(8) NULL COMMENT 'Rigo o campo "di cui" del quadro F dei modelli ISA (es. F12, F14:2, NO); NULL = dalla voce di bilancio' AFTER `rigo_redditi`;

-- Nei piani delle aziende copiati dal piano standard (stesso codice e stessa
-- descrizione) i righi dei conti del piano standard, se non già indicati
-- (il piano standard li riceve da dati-riferimento.sql).

UPDATE `conto` SET `rigo_isa` = 'F14:2' WHERE `codice` = '22.01.0001' AND `descrizione` = 'Consulenze' AND `rigo_isa` IS NULL;
UPDATE `conto` SET `rigo_isa` = 'F13:2' WHERE `codice` = '23.01.0001' AND `descrizione` = 'Fitti passivi' AND `rigo_isa` IS NULL;
UPDATE `conto` SET `rigo_isa` = 'F13:4' WHERE `codice` = '23.01.0002' AND `descrizione` = 'Canoni di leasing' AND `rigo_isa` IS NULL;
UPDATE `conto` SET `rigo_isa` = 'F13:3' WHERE `codice` = '23.01.0003' AND `descrizione` = 'Noleggi' AND `rigo_isa` IS NULL;
UPDATE `conto` SET `rigo_isa` = 'F17:8' WHERE `codice` = '27.01.0001' AND `descrizione` = 'Imposte e tasse diverse' AND `rigo_isa` IS NULL;
