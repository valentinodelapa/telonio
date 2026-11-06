-- =====================================================
-- Anagrafica 0.7 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già le
-- colonne in schema.sql). Idempotente.
--
-- Dati fiscali per il rimborso IVA del modulo Dichiarativi: inizio
-- dell'attività e start-up innovative (rischi dell'art. 38-bis, c. 4), non
-- residenti (art. 17, c. 3) e partecipazione a un Gruppo IVA (art. 70-bis).
-- Regime speciale del settore agricolo (rigo VF30).

ALTER TABLE `dati_fiscali` ADD COLUMN IF NOT EXISTS `data_inizio_attivita` DATE NULL COMMENT 'Inizio dell''attività (rischi dei rimborsi IVA, art. 38-bis, c. 4)';
ALTER TABLE `dati_fiscali` ADD COLUMN IF NOT EXISTS `non_residente_iva` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Non residente identificato direttamente (art. 35-ter) o con rappresentante fiscale (art. 17, c. 3)';
ALTER TABLE `dati_fiscali` ADD COLUMN IF NOT EXISTS `startup_innovativa` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Start-up innovativa (art. 25 D.L. 179/2012), esclusa dai rischi dei rimborsi IVA';
ALTER TABLE `dati_fiscali` ADD COLUMN IF NOT EXISTS `gruppo_iva_art70bis` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Partecipa a un Gruppo IVA (artt. 70-bis e seguenti)';
ALTER TABLE `dati_fiscali` ADD COLUMN IF NOT EXISTS `regime_detrazione_iva` CHAR(3) NULL COMMENT 'Regime speciale agricolo: AGR art. 34, ESO esonerato, CON art. 34-bis, TUR agriturismo, ENO enoturismo, OLE oleoturismo, ASS associazioni';
