-- =====================================================
-- Anagrafica 0.3 - aggiornamento dei database creati con le versioni 0.1 e 0.2
-- =====================================================
-- Da eseguire come root sui database esistenti (le nuove installazioni hanno
-- già la colonna in schema.sql), poi eliminare Cache/referenceCache.json.
-- Idempotente: le versioni che hanno già una causale non vengono toccate.
--
-- Causale dei dati catastali (CausaleClassamento): stabilisce da quando una
-- rendita vale per i tributi; per l'IMU (art. 1, c. 745, L. 160/2019) le
-- variazioni per interventi edilizi valgono dall'ultimazione dei lavori o
-- dall'utilizzo, le altre dal 1° gennaio successivo.
--
-- Causale assegnata alle versioni esistenti:
-- - prima versione di ogni immobile: C (costituzione o primo classamento);
-- - versioni successive dei fabbricati: E (interventi edilizi), cioè con
--   effetto dalla decorrenza come finora: i calcoli già fatti non cambiano;
-- - versioni successive dei terreni: V (altra variazione), perché per i
--   terreni la legge non prevede variazioni con effetto in corso d'anno.
-- L'ultima query elenca le variazioni dei fabbricati da verificare: se non
-- sono dovute a interventi edilizi, cambiare la causale in "Altra
-- variazione" con la rettifica della versione nella scheda dell'immobile.

ALTER TABLE `dati_catastali` ADD COLUMN IF NOT EXISTS `causale` CHAR(1) NULL
  COMMENT 'C costituzione o primo classamento, E variazione per interventi edilizi, V altra variazione' AFTER `valido_al`;

UPDATE `dati_catastali` dc
  JOIN `immobile` i ON i.`id` = dc.`immobile_id`
  JOIN (SELECT `immobile_id`, MIN(`valido_dal`) AS `prima` FROM `dati_catastali` GROUP BY `immobile_id`) p ON p.`immobile_id` = dc.`immobile_id`
SET dc.`causale` = CASE
    WHEN dc.`valido_dal` = p.`prima` THEN 'C'
    WHEN i.`tipo_catasto` = 'T' THEN 'V'
    ELSE 'E'
  END
WHERE dc.`causale` IS NULL;

ALTER TABLE `dati_catastali` MODIFY `causale` CHAR(1) NOT NULL
  COMMENT 'C costituzione o primo classamento, E variazione per interventi edilizi, V altra variazione';

-- variazioni dei fabbricati da verificare
SELECT s.`denominazione` AS `studio`, c.`denominazione` AS `comune`, i.`foglio`, i.`particella`, i.`subalterno`,
       dc.`valido_dal` AS `decorrenza`, dc.`categoria`, dc.`rendita`, dc.`causale`
  FROM `dati_catastali` dc
  JOIN `immobile` i ON i.`id` = dc.`immobile_id`
  JOIN `studio` s ON s.`id` = i.`studio_id`
  JOIN `comune` c ON c.`id` = i.`comune_id`
 WHERE i.`tipo_catasto` = 'F' AND dc.`causale` = 'E'
 ORDER BY s.`denominazione`, c.`denominazione`, i.`foglio`, i.`particella`, i.`subalterno`, dc.`valido_dal`;
