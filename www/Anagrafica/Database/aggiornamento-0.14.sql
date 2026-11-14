-- =====================================================
-- Anagrafica 0.14 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già la
-- colonna in schema.sql). Idempotente.
--
-- Opzione per l'IVA per cassa (art. 32-bis D.L. 83/2012; dal 2027 art. 132
-- D.Lgs. 10/2026; D.M. 11 ottobre 2012): volume d'affari fino a 2 milioni
-- di euro, esigibilità e detrazione al pagamento, comunque dopo un anno.

CREATE TABLE IF NOT EXISTS `dati_fiscali` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `trimestrale_speciale` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `dati_fiscali`
  ADD COLUMN IF NOT EXISTS `iva_per_cassa` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Opzione per l''IVA per cassa (art. 32-bis D.L. 83/2012): esigibilità e detrazione al pagamento, comunque dopo un anno' AFTER `trimestrale_speciale`;
