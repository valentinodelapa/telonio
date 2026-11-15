-- =====================================================
-- Dichiarativi 0.9 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già la
-- colonna in schema.sql). Idempotente.

-- Modello Redditi: scelta di trasmettere la dichiarazione di un soggetto ISA
-- senza il modello ISA (la suite non lo compila), che barra la casella di
-- omessa presentazione dei campi 179 e 180 del record B.

ALTER TABLE `dichiarazione` ADD COLUMN IF NOT EXISTS `omesso_isa` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Redditi: omessa presentazione del modello ISA nella dichiarazione (campi 179 e 180 del record B)' AFTER `concordato_biennale`;
