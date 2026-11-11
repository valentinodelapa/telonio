-- =====================================================
-- Dichiarativi 0.6 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già le
-- tabelle in schema.sql). Idempotente.

-- Investimenti e attività finanziarie all'estero del contribuente per anno
-- d'imposta (quadro RW: monitoraggio, IVIE, IVAFE, cripto-attività) con i
-- proventi e le imposte estere per i quadri RM e CE.

CREATE TABLE IF NOT EXISTS `attivita_estera` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno_imposta` SMALLINT UNSIGNED NOT NULL,
  `codice_investimento` TINYINT UNSIGNED NOT NULL COMMENT 'Codice di individuazione del bene (col. 3, da 1 a 21)',
  `codice_stato_estero` CHAR(3) NULL COMMENT 'Codice dello Stato estero del modello Redditi (col. 4)',
  `descrizione` VARCHAR(150) NOT NULL,
  `intermediario` VARCHAR(100) NULL COMMENT 'Intermediario dei conti correnti (giacenza media complessiva)',
  `titolo_possesso` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 proprietà, 2 usufrutto, 3 nuda proprietà, 4 altro',
  `tipo_contribuente` TINYINT UNSIGNED NULL COMMENT '1 delegato, 2 titolare effettivo',
  `quota` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  `criterio_valore` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Col. 6, da 1 a 6',
  `valore_iniziale` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `valore_finale` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Per i conti correnti il valore medio di giacenza',
  `valore_massimo` DECIMAL(15,2) NULL COMMENT 'Valore massimo nell''anno dei conti correnti',
  `data_inizio` DATE NULL COMMENT 'Inizio della detenzione nell''anno (NULL = 1 gennaio)',
  `data_fine` DATE NULL COMMENT 'Fine della detenzione nell''anno (NULL = 31 dicembre)',
  `credito_imposta` DECIMAL(15,2) NULL COMMENT 'Imposta patrimoniale versata all''estero (col. 12)',
  `mesi_abitazione_principale` TINYINT UNSIGNED NULL COMMENT 'Mesi di abitazione principale (detrazione IVIE, col. 13)',
  `codice_quadri` TINYINT UNSIGNED NULL COMMENT 'Col. 14: 1 RL, 2 RM, 3 RT, 4 più quadri, 5 infruttifero',
  `quota_partecipazione` DECIMAL(5,2) NULL COMMENT 'Titolare effettivo (col. 15)',
  `codice_fiscale_entita` VARCHAR(25) NULL COMMENT 'Società o entità del titolare effettivo (col. 17)',
  `cointestatario1` VARCHAR(16) NULL,
  `cointestatario2` VARCHAR(16) NULL,
  `piu_cointestatari` TINYINT(1) NOT NULL DEFAULT 0,
  `solo_monitoraggio` TINYINT(1) NOT NULL DEFAULT 0,
  `tipo_proventi` CHAR(1) NULL COMMENT 'I interessi, D dividendi, A altri redditi di capitale',
  `proventi` DECIMAL(15,2) NULL COMMENT 'Proventi lordi incassati nell''anno',
  `imposta_estera` DECIMAL(15,2) NULL COMMENT 'Imposte pagate all''estero sui proventi',
  `tassazione_ordinaria` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Opzione per la tassazione ordinaria dei proventi (RM31 col. 7, credito CE)',
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_attivita_estera_soggetto` (`soggetto_id`, `anno_imposta`),
  CONSTRAINT `fk_attivita_estera_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_attivita_estera_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Investimenti e attività finanziarie all''estero (quadro RW)';

-- Cessioni di titoli, partecipazioni non qualificate e cripto-attività in
-- regime dichiarativo (quadro RT, sezioni II e V).

CREATE TABLE IF NOT EXISTS `cessione_finanziaria` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno_imposta` SMALLINT UNSIGNED NOT NULL,
  `tipo` CHAR(1) NOT NULL DEFAULT 'T' COMMENT 'T titoli e partecipazioni non qualificate, C cripto-attività',
  `data_cessione` DATE NOT NULL,
  `descrizione` VARCHAR(150) NOT NULL,
  `corrispettivo` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `costo` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Costo o valore di acquisto, anche rideterminato',
  `costo_rideterminato` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Righi RT12 e RT42',
  `paese_privilegiato` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Partecipazioni in Paesi a regime privilegiato (RT16)',
  `anteriore2025` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Cripto-attività detenute dal 2024 o prima',
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cessione_finanziaria_soggetto` (`soggetto_id`, `anno_imposta`),
  CONSTRAINT `fk_cessione_finanziaria_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_cessione_finanziaria_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Cessioni di titoli, partecipazioni e cripto-attività (quadro RT)';

-- Certificazioni Uniche del lavoro autonomo e dei redditi diversi (quadro RL).

CREATE TABLE IF NOT EXISTS `certificazione_autonomo` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno_imposta` SMALLINT UNSIGNED NOT NULL,
  `codice_fiscale_sostituto` VARCHAR(16) NOT NULL,
  `denominazione_sostituto` VARCHAR(100) NOT NULL,
  `causale` VARCHAR(2) NOT NULL COMMENT 'Causale della CU (punto 1)',
  `ammontare_lordo` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `somme_non_soggette` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `ritenute_acconto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `ritenute_imposta` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `ritenute_sospese` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `addizionale_regionale` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `addizionale_comunale` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `spese_inerenti` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Spese inerenti indicate dal contribuente (RL14-RL16, col. 3)',
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_certificazione_autonomo_soggetto` (`soggetto_id`, `anno_imposta`),
  CONSTRAINT `fk_certificazione_autonomo_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_certificazione_autonomo_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Certificazioni Uniche del lavoro autonomo e dei redditi diversi (quadro RL)';

-- Database con la tabella attivita_estera già creata da una versione di sviluppo.
ALTER TABLE `attivita_estera` ADD COLUMN IF NOT EXISTS `tassazione_ordinaria` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Opzione per la tassazione ordinaria dei proventi (RM31 col. 7, credito CE)' AFTER `imposta_estera`;
