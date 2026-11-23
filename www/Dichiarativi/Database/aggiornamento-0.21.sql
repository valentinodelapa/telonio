-- =====================================================
-- Dichiarativi 0.21 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database". Idempotente.

-- Prospetto D, rigo 3: valore e aliquota delle rimanenze corretti a mano (NULL = proposta della suite)
ALTER TABLE `rettifica_detrazione` ADD COLUMN IF NOT EXISTS `valore_rimanenze` DECIMAL(15,2) NULL COMMENT 'Rigo 3: valore delle rimanenze corretto a mano (NULL = proposta)' AFTER `nota`;
ALTER TABLE `rettifica_detrazione` ADD COLUMN IF NOT EXISTS `aliquota_rimanenze` DECIMAL(5,2) NULL COMMENT 'Rigo 3: aliquota media delle rimanenze corretta a mano (NULL = proposta)' AFTER `valore_rimanenze`;
