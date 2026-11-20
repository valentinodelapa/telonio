-- =====================================================
-- Contabilita 0.14 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già la
-- colonna in schema.sql). Idempotente.

-- Variazione del valore della produzione del quadro IC del modello IRAP a
-- cui concorre il conto (es. IC43, IC46, IC51:99); vuoto: nessuna.

ALTER TABLE `conto` ADD COLUMN IF NOT EXISTS `rigo_irap` VARCHAR(8) NULL COMMENT 'Variazione del valore della produzione del quadro IC del modello IRAP (es. IC43, IC46, IC51:99); NULL = nessuna' AFTER `rigo_isa`;

-- Nei piani delle aziende copiati dal piano standard (stesso codice e stessa
-- descrizione) la variazione dei compensi agli amministratori, se non già
-- indicata (il piano standard la riceve da dati-riferimento.sql con il nuovo
-- conto "IMU").

UPDATE `conto` SET `rigo_irap` = 'IC43' WHERE `codice` = '22.01.0009' AND `descrizione` = 'Compensi agli amministratori' AND `rigo_irap` IS NULL;
