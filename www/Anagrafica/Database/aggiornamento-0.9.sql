-- =====================================================
-- Anagrafica 0.9 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già la
-- colonna in schema.sql). Idempotente.
--
-- Opzioni per l'applicazione dell'IVA nei modi ordinari nel settore agricolo,
-- per le opzioni e le revoche del quadro VO della dichiarazione IVA.

ALTER TABLE `dati_fiscali` ADD COLUMN IF NOT EXISTS `opzioni_ordinarie` VARCHAR(23) NULL COMMENT 'Regimi agricoli a cui si è rinunciato per i modi ordinari: codici separati da punto e virgola (AGR, CON, TUR, ENO, OLE, ASS)';
