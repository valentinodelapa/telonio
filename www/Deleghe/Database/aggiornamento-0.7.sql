-- =====================================================
-- Deleghe 0.7 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già
-- la colonna in schema.sql). Idempotente.
--
-- Invio della delega al cliente per e-mail o PEC: il canale della consegna
-- distingue la PEC ("E" fino alla 0.6 comprendeva entrambe) e la delega
-- registra l'indirizzo a cui è stata inviata.

ALTER TABLE `delega`
  MODIFY COLUMN `canale_consegna` CHAR(1) NULL COMMENT 'M a mano, E e-mail, P PEC, A area clienti, X altro',
  ADD COLUMN IF NOT EXISTS `destinatario_consegna` VARCHAR(255) NULL COMMENT 'Indirizzo a cui la delega è stata inviata da Telonio (e-mail o PEC)' AFTER `canale_consegna`;
