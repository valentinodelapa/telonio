-- =====================================================
-- Dichiarativi 0.20 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" dopo gli script di Contabilita
-- (che rinomina la tabella attivita_iva in attivita). Idempotente.

-- Modulo della dichiarazione IVA dell'attività con contabilità separata: colonna dell'attività del soggetto
ALTER TABLE `modulo_dichiarazione` CHANGE COLUMN IF EXISTS `attivita_iva_id` `attivita_id` INT UNSIGNED NULL COMMENT 'Attività con contabilità separata (Contabilita) di cui il modulo riepiloga le operazioni';
