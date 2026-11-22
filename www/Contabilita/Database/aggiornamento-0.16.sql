-- =====================================================
-- Contabilita 0.16 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già
-- la colonna in schema.sql). Idempotente.

-- Causale dei compensi soggetti a ritenuta d'acconto della controparte, per
-- la Certificazione Unica e il 770.

ALTER TABLE `controparte` ADD COLUMN IF NOT EXISTS `causale_cu` VARCHAR(2) NULL COMMENT 'Causale dei compensi con ritenuta d''acconto (Certificazione Unica: A, M, Q...)' AFTER `modalita_pagamento`;
