-- =====================================================
-- Contabilita 0.17 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già
-- la colonna in schema.sql). Idempotente.

-- Destinazione dei conti economici degli enti non commerciali (attività
-- commerciale, istituzionale o costi promiscui, art. 144 TUIR) per il
-- modello Redditi ENC e l'IRAP degli enti.

ALTER TABLE `conto` ADD COLUMN IF NOT EXISTS `destinazione` CHAR(1) NULL COMMENT 'Enti non commerciali: C attività commerciale, I istituzionale, P promiscuo (art. 144 TUIR); NULL = commerciale' AFTER `variazione_redditi`;
