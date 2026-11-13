-- =====================================================
-- Anagrafica 0.13 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già la
-- colonna in schema.sql). Idempotente.
--
-- Trimestrali per natura dell'attività (art. 74, c. 4, D.P.R. 633/1972; dal
-- 2027 art. 139, c. 4, D.Lgs. 10/2026): autotrasportatori di cose per conto
-- di terzi, distributori di carburante e servizi al pubblico autorizzati.

CREATE TABLE IF NOT EXISTS `dati_fiscali` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ventilazione_corrispettivi` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `dati_fiscali`
  ADD COLUMN IF NOT EXISTS `trimestrale_speciale` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Trimestrale per natura dell''attività (art. 74, c. 4, D.P.R. 633/1972): senza interessi, quarto trimestre entro il 16 febbraio' AFTER `ventilazione_corrispettivi`;
