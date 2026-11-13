-- =====================================================
-- Contabilita 0.10 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già la
-- colonna in schema.sql). Idempotente.
--
-- Imposta di bollo assolta in modo virtuale sulle fatture elettroniche
-- emesse (art. 6, c. 2, D.M. 17 giugno 2014): versata per trimestre con i
-- codici tributo 2521-2524. Le registrazioni esistenti restano senza bollo.

CREATE TABLE IF NOT EXISTS `registrazione_contabile` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `totale_documento` DECIMAL(15,2) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `registrazione_contabile`
  ADD COLUMN IF NOT EXISTS `imposta_bollo` DECIMAL(10,2) NULL COMMENT 'Imposta di bollo virtuale sulla fattura elettronica emessa (art. 6, c. 2, D.M. 17 giugno 2014)' AFTER `totale_documento`;
