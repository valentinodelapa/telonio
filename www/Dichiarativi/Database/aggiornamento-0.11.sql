-- =====================================================
-- Dichiarativi 0.11 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già la
-- colonna in schema.sql). Idempotente.

-- Revoca delle adesioni al concordato preventivo biennale con la
-- dichiarazione Redditi (casella "Comunicazione CPB" codice 3).

ALTER TABLE `dichiarazione` ADD COLUMN IF NOT EXISTS `revoca_cpb` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Redditi: revoca delle adesioni al concordato preventivo biennale con la dichiarazione (casella "Comunicazione CPB" codice 3, campo 11 del record B)' AFTER `omesso_isa`;
