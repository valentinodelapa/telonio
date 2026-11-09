-- =====================================================
-- Contabilita 0.7 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già le
-- modifiche in schema.sql). Idempotente.
--
-- Rigo dei quadri RG o RE del modello Redditi in cui confluisce il conto.

ALTER TABLE `conto` ADD COLUMN IF NOT EXISTS `rigo_redditi` VARCHAR(10) NULL COMMENT 'Rigo dei quadri RG o RE del modello Redditi (es. RG15, RG22:3, NO); NULL = dalla voce di bilancio' AFTER `voce_cee`;

-- Percentuale deducibile del costo e rigo della variazione in aumento del
-- quadro RF per la parte indeducibile.

ALTER TABLE `conto` ADD COLUMN IF NOT EXISTS `percentuale_deducibile` DECIMAL(5,2) NULL COMMENT 'Percentuale deducibile del costo (es. 20 auto, 80 telefonia); NULL = interamente deducibile' AFTER `rigo_redditi`;
ALTER TABLE `conto` ADD COLUMN IF NOT EXISTS `variazione_redditi` VARCHAR(10) NULL COMMENT 'Rigo della variazione in aumento del quadro RF per la parte indeducibile (RF16, RF18, RF31:99)' AFTER `percentuale_deducibile`;
