-- =====================================================
-- GestioneStudio 0.2 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già le
-- colonne in schema.sql). Idempotente.
--
-- Fonte normativa degli adempimenti che cambia da una data (testi unici
-- tributari dal 1 gennaio 2027): le scadenze da quella data citano la nuova
-- fonte. I valori del calendario comune li scrive dati-riferimento.sql,
-- eseguito subito dopo.

CREATE TABLE IF NOT EXISTS `adempimento` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fonte` VARCHAR(255) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `adempimento`
  ADD COLUMN IF NOT EXISTS `nuova_fonte` VARCHAR(255) NULL COMMENT 'Norma che fissa il termine dalla data nuova_fonte_dal (es. testi unici dal 2027)' AFTER `fonte`,
  ADD COLUMN IF NOT EXISTS `nuova_fonte_dal` DATE NULL COMMENT 'Termini dal giorno indicato: si cita nuova_fonte' AFTER `nuova_fonte`;
