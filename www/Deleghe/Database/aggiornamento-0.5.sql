-- =====================================================
-- Deleghe 0.5 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già
-- le colonne in schema.sql). Idempotente.
--
-- Delega consegnata al cliente, che la paga da sé: data e canale della
-- consegna, canale del pagamento registrato con la quietanza.

ALTER TABLE `delega`
  MODIFY COLUMN `stato` CHAR(1) NOT NULL DEFAULT 'B' COMMENT 'B bozza, C confermata, T trasmessa, K consegnata al cliente, Q quietanzata',
  ADD COLUMN IF NOT EXISTS `data_consegna` DATE NULL COMMENT 'Consegna al cliente, che la paga da sé' AFTER `invio_telematico_id`,
  ADD COLUMN IF NOT EXISTS `canale_consegna` CHAR(1) NULL COMMENT 'M a mano, E email/PEC, A area clienti, X altro' AFTER `data_consegna`,
  ADD COLUMN IF NOT EXISTS `canale_pagamento` CHAR(1) NULL COMMENT 'Pagamento del cliente: H home banking, B banca/sportello, P posta, X altro' AFTER `data_quietanza`;
