-- =====================================================
-- Imu 0.4 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già la
-- colonna in schema.sql). Idempotente.
--
-- Alloggi regolarmente assegnati dagli IACP: detrazione dell'abitazione
-- principale (art. 1, comma 749, secondo periodo, L. 160/2019).

ALTER TABLE `caratteristiche_imu` ADD COLUMN IF NOT EXISTS `alloggio_iacp` TINYINT(1) NOT NULL DEFAULT 0
  COMMENT 'Alloggio regolarmente assegnato dall''IACP o ente equivalente: detrazione (comma 749)' AFTER `bene_merce`;
