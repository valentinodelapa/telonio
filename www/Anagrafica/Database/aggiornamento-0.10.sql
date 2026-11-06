-- =====================================================
-- Anagrafica 0.10 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già la
-- colonna in schema.sql). Idempotente.
--
-- Rinuncia all'esonero degli agricoltori (art. 34, c. 6), per le caselle 1 e 2
-- del rigo VO3 della dichiarazione IVA.

ALTER TABLE `dati_fiscali` ADD COLUMN IF NOT EXISTS `rinuncia_esonero` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Rinuncia all''esonero dell''art. 34, c. 6 (rigo VO3, caselle 1 e 2)' AFTER `regime_detrazione_iva`;
