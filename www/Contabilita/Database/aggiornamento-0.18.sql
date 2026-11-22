-- =====================================================
-- Contabilita 0.18 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già
-- lo schema in schema.sql). Idempotente.
--
-- Attività del soggetto come dimensione unica della contabilità: le
-- attività con contabilità separata dell'IVA (tabella attivita_iva) diventano
-- le attività del soggetto (tabella attivita) con la natura commerciale o
-- istituzionale; le righe contabili hanno l'attività o sono comuni; la
-- destinazione dei conti (Contabilita 0.17) è sostituita dall'attività
-- predefinita del conto.

-- 1. Attività del soggetto (ex attività IVA) con la natura
RENAME TABLE IF EXISTS `attivita_iva` TO `attivita`;
ALTER TABLE `attivita` ADD COLUMN IF NOT EXISTS `natura` CHAR(1) NOT NULL DEFAULT 'C' COMMENT 'C commerciale, I istituzionale (enti non commerciali)' AFTER `descrizione`;
ALTER TABLE `registro_iva` CHANGE COLUMN IF EXISTS `attivita_iva_id` `attivita_id` INT UNSIGNED NULL COMMENT 'Attività del registro; NULL con attività separate = registro degli acquisti promiscui';
ALTER TABLE `liquidazione_attivita` CHANGE COLUMN IF EXISTS `attivita_iva_id` `attivita_id` INT UNSIGNED NOT NULL;
ALTER TABLE `prorata_iva` CHANGE COLUMN IF EXISTS `attivita_iva_id` `attivita_id` INT UNSIGNED NULL COMMENT 'Attività con contabilità separata (pro-rata per attività)';

-- 2. Attività predefinita del conto, attività e quote delle righe, quota commerciale degli acquisti degli enti
ALTER TABLE `conto` ADD COLUMN IF NOT EXISTS `attivita_id` INT UNSIGNED NULL COMMENT 'Attività predefinita delle righe del conto (soggetti con più attività); NULL = righe comuni' AFTER `variazione_redditi`;
ALTER TABLE `conto` ADD CONSTRAINT `fk_conto_attivita` FOREIGN KEY IF NOT EXISTS (`attivita_id`) REFERENCES `attivita` (`id`) ON DELETE SET NULL;
ALTER TABLE `riga_contabile` ADD COLUMN IF NOT EXISTS `attivita_id` INT UNSIGNED NULL COMMENT 'Attività della riga; NULL = comune a più attività (o soggetto con un''attività sola)' AFTER `descrizione`;
ALTER TABLE `riga_contabile` ADD COLUMN IF NOT EXISTS `ripartizione` VARCHAR(255) NULL COMMENT 'Righe comuni: numero attività e percentuale (es. 1:60;2:40)' AFTER `attivita_id`;
ALTER TABLE `riga_contabile` ADD CONSTRAINT `fk_riga_contabile_attivita` FOREIGN KEY IF NOT EXISTS (`attivita_id`) REFERENCES `attivita` (`id`);
ALTER TABLE `riga_iva` ADD COLUMN IF NOT EXISTS `quota_commerciale` DECIMAL(9,6) NULL COMMENT 'Enti non commerciali: quota imputabile all''attività commerciale (IVA detraibile, art. 19-ter); NULL = per intero' AFTER `ripartizione`;

-- 3. Conti con la destinazione (Contabilita 0.17): attività commerciale e istituzionale degli enti, attività predefinita dei conti
SET @destinazione = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conto' AND COLUMN_NAME = 'destinazione');

SET @sql = IF(@destinazione > 0, 'INSERT INTO `attivita` (`studio_id`, `soggetto_id`, `numero`, `descrizione`, `natura`, `dal_anno`)
    SELECT s.`studio_id`, s.`id`, 1, ''Attività commerciale'', ''C'', COALESCE(YEAR(s.`data_costituzione`), 2000)
    FROM `soggetto` s
    WHERE EXISTS (SELECT 1 FROM `conto` c JOIN `piano_conti` p ON p.`id` = c.`piano_conti_id` WHERE p.`soggetto_id` = s.`id` AND c.`destinazione` IS NOT NULL)
      AND NOT EXISTS (SELECT 1 FROM `attivita` a WHERE a.`soggetto_id` = s.`id`)', 'SELECT 1');
PREPARE passo FROM @sql;
EXECUTE passo;
DEALLOCATE PREPARE passo;

SET @sql = IF(@destinazione > 0, 'INSERT INTO `attivita` (`studio_id`, `soggetto_id`, `numero`, `descrizione`, `natura`, `dal_anno`)
    SELECT s.`studio_id`, s.`id`, (SELECT MAX(a.`numero`) + 1 FROM `attivita` a WHERE a.`soggetto_id` = s.`id`), ''Attività istituzionale'', ''I'', COALESCE(YEAR(s.`data_costituzione`), 2000)
    FROM `soggetto` s
    WHERE EXISTS (SELECT 1 FROM `conto` c JOIN `piano_conti` p ON p.`id` = c.`piano_conti_id` WHERE p.`soggetto_id` = s.`id` AND c.`destinazione` = ''I'')
      AND NOT EXISTS (SELECT 1 FROM `attivita` a WHERE a.`soggetto_id` = s.`id` AND a.`natura` = ''I'')', 'SELECT 1');
PREPARE passo FROM @sql;
EXECUTE passo;
DEALLOCATE PREPARE passo;

SET @sql = IF(@destinazione > 0, 'UPDATE `conto` c JOIN `piano_conti` p ON p.`id` = c.`piano_conti_id`
    SET c.`attivita_id` = (SELECT a.`id` FROM `attivita` a WHERE a.`soggetto_id` = p.`soggetto_id` AND a.`natura` = IF(c.`destinazione` = ''I'', ''I'', ''C'') ORDER BY a.`numero` LIMIT 1)
    WHERE c.`destinazione` IN (''C'', ''I'') AND c.`attivita_id` IS NULL', 'SELECT 1');
PREPARE passo FROM @sql;
EXECUTE passo;
DEALLOCATE PREPARE passo;

ALTER TABLE `conto` DROP COLUMN IF EXISTS `destinazione`;

-- 4. Attività delle righe già registrate dei soggetti con attività: dal registro IVA della registrazione, altrimenti dal conto
UPDATE `riga_contabile` r
    JOIN `registrazione_contabile` g ON g.`id` = r.`registrazione_contabile_id`
    JOIN `registro_iva` ri ON ri.`id` = g.`registro_iva_id`
    SET r.`attivita_id` = ri.`attivita_id`
    WHERE r.`attivita_id` IS NULL AND ri.`attivita_id` IS NOT NULL;
UPDATE `riga_contabile` r
    JOIN `conto` c ON c.`id` = r.`conto_id`
    SET r.`attivita_id` = c.`attivita_id`
    WHERE r.`attivita_id` IS NULL AND c.`attivita_id` IS NOT NULL;
