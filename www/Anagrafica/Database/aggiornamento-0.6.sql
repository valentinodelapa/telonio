-- =====================================================
-- Anagrafica 0.6 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già la
-- colonna in schema.sql). Idempotente.
--
-- Ventilazione dei corrispettivi dei commercianti al minuto (art. 24, c. 3,
-- D.P.R. 633/1972, D.M. 24 febbraio 1973) nei dati fiscali storicizzati:
-- usata dalla liquidazione IVA del modulo Contabilita.

CREATE TABLE IF NOT EXISTS `dati_fiscali` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `dati_fiscali`
  ADD COLUMN IF NOT EXISTS `ventilazione_corrispettivi` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Ventilazione dei corrispettivi (art. 24, c. 3, D.P.R. 633/1972, D.M. 24/2/1973)' AFTER `trasparenza_fiscale`;
