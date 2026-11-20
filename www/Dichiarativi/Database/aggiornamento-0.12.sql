-- =====================================================
-- Dichiarativi 0.12 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già la
-- colonna in schema.sql). Idempotente.

-- Casella "Canone RAI" del frontespizio di Redditi SP e SC.

ALTER TABLE `dichiarazione` ADD COLUMN IF NOT EXISTS `canone_rai` CHAR(1) NULL COMMENT 'Redditi SP e SC: casella "Canone RAI" (campo 69 del record B): 1 apparecchi radio, 2 radiotelevisivi, 3 nessun apparecchio' AFTER `revoca_cpb`;
