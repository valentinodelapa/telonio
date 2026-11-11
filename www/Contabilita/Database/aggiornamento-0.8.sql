-- =====================================================
-- Contabilita 0.8 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già le
-- modifiche in schema.sql). Idempotente.
--
-- Partite aperte del partitario clienti e fornitori e loro abbinamenti.
-- Dopo l'aggiornamento eseguire "sisma partite-aperte" per creare le
-- partite delle registrazioni già presenti (abbinamenti dalla più vecchia,
-- segnati come da verificare nello scadenzario).

CREATE TABLE IF NOT EXISTS `partita` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `controparte_id` INT UNSIGNED NOT NULL,
  `conto_id` INT UNSIGNED NOT NULL COMMENT 'Sottoconto del partitario',
  `registrazione_contabile_id` INT UNSIGNED NOT NULL COMMENT 'Registrazione che apre la partita',
  `tipo` CHAR(1) NOT NULL DEFAULT 'D' COMMENT 'D documento, I insoluto, A acconto, N nota di credito da abbinare',
  `cliente` TINYINT(1) NOT NULL DEFAULT 1,
  `data` DATE NOT NULL COMMENT 'Data di registrazione',
  `numero_documento` VARCHAR(30) NULL,
  `data_documento` DATE NULL,
  `data_scadenza` DATE NULL,
  `importo` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Positivo, nel segno del tipo',
  `partita_origine_id` INT UNSIGNED NULL COMMENT 'Documento riaperto dall''insoluto',
  PRIMARY KEY (`id`),
  KEY `idx_partita_controparte` (`controparte_id`, `data`),
  KEY `idx_partita_soggetto` (`soggetto_id`, `data`),
  KEY `idx_partita_registrazione` (`registrazione_contabile_id`),
  CONSTRAINT `fk_partita_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_partita_controparte` FOREIGN KEY (`controparte_id`) REFERENCES `controparte` (`id`),
  CONSTRAINT `fk_partita_conto` FOREIGN KEY (`conto_id`) REFERENCES `conto` (`id`),
  CONSTRAINT `fk_partita_registrazione` FOREIGN KEY (`registrazione_contabile_id`) REFERENCES `registrazione_contabile` (`id`),
  CONSTRAINT `fk_partita_origine` FOREIGN KEY (`partita_origine_id`) REFERENCES `partita` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Partite aperte del partitario clienti e fornitori';

CREATE TABLE IF NOT EXISTS `abbinamento` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `controparte_id` INT UNSIGNED NOT NULL,
  `partita_id` INT UNSIGNED NOT NULL COMMENT 'Partita chiusa',
  `registrazione_contabile_id` INT UNSIGNED NOT NULL COMMENT 'Registrazione che chiude',
  `data` DATE NOT NULL COMMENT 'Data della registrazione che chiude',
  `tipo` CHAR(3) NOT NULL DEFAULT 'DEN' COMMENT 'DEN denaro, RIT ritenuta, CMP compensazione, NC nota di credito, ACC acconto, ABB abbuono o perdita',
  `importo` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `partita_collegata_id` INT UNSIGNED NULL COMMENT 'Acconto o nota usati, o documento chiuso da questi',
  `automatico` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Creato dalla migrazione, da verificare',
  PRIMARY KEY (`id`),
  KEY `idx_abbinamento_controparte` (`controparte_id`, `data`),
  KEY `idx_abbinamento_soggetto` (`soggetto_id`, `data`),
  KEY `idx_abbinamento_partita` (`partita_id`),
  KEY `idx_abbinamento_registrazione` (`registrazione_contabile_id`),
  CONSTRAINT `fk_abbinamento_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_abbinamento_controparte` FOREIGN KEY (`controparte_id`) REFERENCES `controparte` (`id`),
  CONSTRAINT `fk_abbinamento_partita` FOREIGN KEY (`partita_id`) REFERENCES `partita` (`id`),
  CONSTRAINT `fk_abbinamento_registrazione` FOREIGN KEY (`registrazione_contabile_id`) REFERENCES `registrazione_contabile` (`id`),
  CONSTRAINT `fk_abbinamento_collegata` FOREIGN KEY (`partita_collegata_id`) REFERENCES `partita` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Abbinamenti di incassi, pagamenti, note e compensazioni alle partite';


-- Funzioni dei conti delle ritenute d'acconto (modi di chiusura proposti
-- negli abbinamenti), sul piano modello e sui piani copiati che non le
-- hanno ancora assegnate.

UPDATE `conto` c
JOIN (SELECT `piano_conti_id` FROM `conto` GROUP BY `piano_conti_id` HAVING SUM(`funzione` = 'RTS') = 0) p ON p.`piano_conti_id` = c.`piano_conti_id`
SET c.`funzione` = 'RTS'
WHERE c.`codice` = '06.03.0006' AND c.`descrizione` = 'Erario c/ritenute subite' AND c.`funzione` IS NULL;

UPDATE `conto` c
JOIN (SELECT `piano_conti_id` FROM `conto` GROUP BY `piano_conti_id` HAVING SUM(`funzione` = 'RTV') = 0) p ON p.`piano_conti_id` = c.`piano_conti_id`
SET c.`funzione` = 'RTV'
WHERE c.`codice` = '13.04.0002' AND c.`descrizione` = 'Erario c/ritenute da versare' AND c.`funzione` IS NULL;
