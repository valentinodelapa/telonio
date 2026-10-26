-- =====================================================
-- Anagrafica 0.5 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Da eseguire come root sui database esistenti (le nuove installazioni hanno
-- già le colonne in schema.sql), poi eliminare Cache/referenceCache.json.
-- Idempotente.
--
-- Regime fiscale dei titolari di partita IVA nei dati fiscali storicizzati:
-- regime (ordinario, semplificato, forfetario, ...), periodicità IVA,
-- applicazione degli ISA, sostituto d'imposta, trasparenza fiscale.
--
-- Alle versioni esistenti si assegna solo il regime certo: ordinario per le
-- società di capitali con partita IVA (contabilità ordinaria obbligatoria).
-- L'ultima query elenca le versioni in vigore con partita IVA da completare
-- (regime e periodicità IVA) con la rettifica dei dati fiscali nella scheda
-- del soggetto: fino ad allora la rettifica le richiede.

ALTER TABLE `dati_fiscali`
  ADD COLUMN IF NOT EXISTS `regime_fiscale` VARCHAR(4) NULL COMMENT 'Con partita IVA: ORD ordinario, SEM semplificato, FOR forfetario, VAN vantaggio, ENC art. 145 TUIR, L398 L. 398/1991' AFTER `ente_non_commerciale`,
  ADD COLUMN IF NOT EXISTS `periodicita_iva` CHAR(1) NULL COMMENT 'M mensile, T trimestrale; NULL se il regime non prevede liquidazioni IVA' AFTER `regime_fiscale`,
  ADD COLUMN IF NOT EXISTS `soggetto_isa` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Applica gli ISA, anche con cause di esclusione' AFTER `periodicita_iva`,
  ADD COLUMN IF NOT EXISTS `sostituto_imposta` TINYINT(1) NOT NULL DEFAULT 0 AFTER `soggetto_isa`,
  ADD COLUMN IF NOT EXISTS `trasparenza_fiscale` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Società di capitali: opzione per la trasparenza (artt. 115-116 TUIR)' AFTER `sostituto_imposta`;

ALTER TABLE `dati_fiscali` COMMENT = 'Storico di denominazione, natura giuridica, partita IVA, attività e regime fiscale';

UPDATE `dati_fiscali` df
  JOIN `soggetto` so ON so.`id` = df.`soggetto_id`
SET df.`regime_fiscale` = 'ORD'
WHERE so.`tipo_soggetto` = 'SC' AND df.`partita_iva` IS NOT NULL AND df.`regime_fiscale` IS NULL;

-- versioni in vigore con partita IVA da completare
SELECT st.`denominazione` AS `studio`, so.`nominativo`, df.`partita_iva`, df.`valido_dal`, df.`regime_fiscale`, df.`periodicita_iva`
FROM `dati_fiscali` df
  JOIN `soggetto` so ON so.`id` = df.`soggetto_id`
  JOIN `studio` st ON st.`id` = so.`studio_id`
WHERE df.`partita_iva` IS NOT NULL AND df.`valido_al` IS NULL
  AND (df.`regime_fiscale` IS NULL OR (df.`regime_fiscale` NOT IN ('FOR', 'VAN') AND df.`periodicita_iva` IS NULL))
ORDER BY st.`denominazione`, so.`nominativo`;
