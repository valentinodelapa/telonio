-- =====================================================
-- Anagrafica - archivio unico dei soggetti dello studio
-- =====================================================
-- Richiede le tabelle del modulo CommSuite (studio).
-- Le colonne "valido_dal"/"valido_al" storicizzano i dati che variano nel
-- tempo: valido_al NULL indica la versione tuttora vigente.

-- -----------------------------------------------------
-- Tabelle di riferimento (comuni a tutti gli studi)
-- Popolate e aggiornate dalle tabelle ANPR con il comando
-- "sisma tabelle-riferimento": id_anpr è la chiave di aggiornamento, le
-- righe non vengono mai cancellate perché referenziate dai soggetti.
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `nazione` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_anpr` INT UNSIGNED NOT NULL COMMENT 'Identificativo ANPR (tabella 2, stati esteri)',
  `codice_iso` CHAR(2) NULL COMMENT 'ISO 3166-1 alpha-2',
  `codice_iso3` CHAR(3) NULL COMMENT 'ISO 3166-1 alpha-3',
  `codice_catastale` CHAR(4) NULL COMMENT 'Codice Z... usato nel codice fiscale dei nati all''estero',
  `denominazione` VARCHAR(150) NOT NULL,
  `valido_dal` DATE NOT NULL,
  `valido_al` DATE NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nazione_id_anpr` (`id_anpr`),
  KEY `idx_nazione_codice_iso` (`codice_iso`),
  KEY `idx_nazione_codice_catastale` (`codice_catastale`),
  KEY `idx_nazione_denominazione` (`denominazione`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `comune` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_anpr` INT UNSIGNED NOT NULL COMMENT 'Identificativo ANPR (archivio comuni)',
  `codice_catastale` CHAR(4) NOT NULL COMMENT 'Codice attribuito dall''Agenzia delle Entrate',
  `codice_istat` CHAR(6) NULL,
  `denominazione` VARCHAR(150) NOT NULL,
  `sigla_provincia` CHAR(2) NULL,
  `valido_dal` DATE NOT NULL,
  `valido_al` DATE NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_comune_id_anpr` (`id_anpr`),
  KEY `idx_comune_codice_catastale` (`codice_catastale`, `valido_dal`),
  KEY `idx_comune_denominazione` (`denominazione`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `codice_ateco` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `classificazione` CHAR(4) NOT NULL COMMENT '2022 = ATECO 2007 agg. 2022, 2025 = ATECO 2025',
  `codice` VARCHAR(8) NOT NULL,
  `livello` TINYINT UNSIGNED NOT NULL COMMENT '1 sezione ... 6 sottocategoria',
  `codice_padre` VARCHAR(8) NULL,
  `titolo` VARCHAR(500) NOT NULL,
  `codice_raccordo` VARCHAR(8) NULL COMMENT 'Solo ATECO 2022: sottocategoria ATECO 2025 suggerita dall''ISTAT',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_codice_ateco` (`classificazione`, `codice`),
  KEY `idx_codice_ateco_livello` (`classificazione`, `livello`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Classificazioni ATECO (ISTAT), importate con sisma tabelle-riferimento';

-- -----------------------------------------------------
-- Soggetti
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `soggetto` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `tipo_soggetto` VARCHAR(2) NOT NULL COMMENT 'PF, SP, SC, EN',
  `codice` VARCHAR(20) NULL COMMENT 'Codice cliente interno dello studio',
  `codice_fiscale` VARCHAR(16) NOT NULL,
  `nominativo` VARCHAR(255) NOT NULL COMMENT 'Valore corrente, sincronizzato da dati_fiscali',
  `partita_iva` CHAR(11) NULL COMMENT 'Valore corrente, sincronizzato da dati_fiscali',
  `tipo_attivita` VARCHAR(3) NULL COMMENT 'Valore corrente, sincronizzato da dati_fiscali',
  `cliente_studio` TINYINT(1) NOT NULL DEFAULT 1,
  `cognome` VARCHAR(100) NULL,
  `nome` VARCHAR(100) NULL,
  `sesso` CHAR(1) NULL,
  `data_nascita` DATE NULL,
  `comune_nascita_id` INT UNSIGNED NULL,
  `nazione_nascita_id` INT UNSIGNED NULL,
  `cittadinanza_id` INT UNSIGNED NULL,
  `stato_civile` VARCHAR(2) NULL,
  `data_decesso` DATE NULL COMMENT 'Solo persone fisiche: data di apertura della successione',
  `data_costituzione` DATE NULL,
  `data_cessazione` DATE NULL,
  `note` TEXT NULL,
  `data_inserimento` DATETIME NOT NULL,
  `data_modifica` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_soggetto_studio_codice_fiscale` (`studio_id`, `codice_fiscale`),
  KEY `idx_soggetto_studio_nominativo` (`studio_id`, `nominativo`),
  KEY `idx_soggetto_studio_partita_iva` (`studio_id`, `partita_iva`),
  KEY `idx_soggetto_studio_tipo_attivita` (`studio_id`, `tipo_attivita`),
  CONSTRAINT `fk_soggetto_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_soggetto_comune_nascita` FOREIGN KEY (`comune_nascita_id`) REFERENCES `comune` (`id`),
  CONSTRAINT `fk_soggetto_nazione_nascita` FOREIGN KEY (`nazione_nascita_id`) REFERENCES `nazione` (`id`),
  CONSTRAINT `fk_soggetto_cittadinanza` FOREIGN KEY (`cittadinanza_id`) REFERENCES `nazione` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Anagrafica unica: persone fisiche, società ed enti';

CREATE TABLE IF NOT EXISTS `dati_fiscali` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `valido_dal` DATE NOT NULL,
  `valido_al` DATE NULL,
  `denominazione` VARCHAR(255) NULL,
  `natura_giuridica` VARCHAR(4) NULL,
  `tipo_attivita` VARCHAR(3) NULL COMMENT 'Solo persone fisiche: IMP, IFA, LAV',
  `partita_iva` CHAR(11) NULL,
  `codice_ateco_id` INT UNSIGNED NULL,
  `descrizione_attivita` VARCHAR(255) NULL,
  `ente_non_commerciale` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Ente non commerciale (art. 73, c. 1, lett. c, TUIR)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dati_fiscali_decorrenza` (`soggetto_id`, `valido_dal`),
  CONSTRAINT `fk_dati_fiscali_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_dati_fiscali_codice_ateco` FOREIGN KEY (`codice_ateco_id`) REFERENCES `codice_ateco` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Storico di denominazione, natura giuridica, partita IVA e attività';

CREATE TABLE IF NOT EXISTS `indirizzo` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `tipo_indirizzo` VARCHAR(3) NOT NULL,
  `valido_dal` DATE NOT NULL,
  `valido_al` DATE NULL,
  `presso` VARCHAR(100) NULL,
  `indirizzo` VARCHAR(150) NOT NULL,
  `numero_civico` VARCHAR(20) NULL,
  `frazione` VARCHAR(100) NULL,
  `cap` VARCHAR(10) NULL,
  `comune_id` INT UNSIGNED NULL,
  `localita_estera` VARCHAR(100) NULL,
  `nazione_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_indirizzo_decorrenza` (`soggetto_id`, `tipo_indirizzo`, `valido_dal`),
  CONSTRAINT `fk_indirizzo_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_indirizzo_comune` FOREIGN KEY (`comune_id`) REFERENCES `comune` (`id`),
  CONSTRAINT `fk_indirizzo_nazione` FOREIGN KEY (`nazione_id`) REFERENCES `nazione` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recapito` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `tipo_recapito` VARCHAR(3) NOT NULL,
  `valore` VARCHAR(255) NOT NULL,
  `principale` TINYINT(1) NOT NULL DEFAULT 0,
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_recapito_soggetto` (`soggetto_id`, `tipo_recapito`),
  CONSTRAINT `fk_recapito_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conti correnti intestati al soggetto (addebito delle deleghe F24, ...).
CREATE TABLE IF NOT EXISTS `conto_corrente` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `soggetto_id` INT UNSIGNED NOT NULL COMMENT 'Intestatario',
  `iban` VARCHAR(34) NOT NULL,
  `descrizione` VARCHAR(100) NULL COMMENT 'Banca o filiale',
  `predefinito` TINYINT(1) NOT NULL DEFAULT 0,
  `attivo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = conto estinto, mantenuto per lo storico',
  PRIMARY KEY (`id`),
  KEY `idx_conto_corrente_soggetto` (`soggetto_id`),
  CONSTRAINT `fk_conto_corrente_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `legame` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `soggetto_id` INT UNSIGNED NOT NULL COMMENT 'Soggetto presso cui è ricoperto il ruolo',
  `soggetto_collegato_id` INT UNSIGNED NOT NULL COMMENT 'Soggetto che ricopre il ruolo',
  `ruolo` VARCHAR(3) NOT NULL,
  `valido_dal` DATE NOT NULL,
  `valido_al` DATE NULL,
  `quota_partecipazione` DECIMAL(7,4) NULL COMMENT 'Percentuale',
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_legame_decorrenza` (`soggetto_id`, `soggetto_collegato_id`, `ruolo`, `valido_dal`),
  KEY `idx_legame_collegato` (`soggetto_collegato_id`),
  CONSTRAINT `fk_legame_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_legame_soggetto_collegato` FOREIGN KEY (`soggetto_collegato_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Cariche sociali, partecipazioni e rappresentanza, storicizzate';

CREATE TABLE IF NOT EXISTS `documento_identita` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `tipo_documento` VARCHAR(2) NOT NULL,
  `numero` VARCHAR(30) NOT NULL,
  `rilasciato_da` VARCHAR(100) NULL,
  `data_rilascio` DATE NULL,
  `data_scadenza` DATE NULL,
  PRIMARY KEY (`id`),
  KEY `idx_documento_soggetto` (`soggetto_id`),
  CONSTRAINT `fk_documento_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Immobili
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `immobile` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `tipo_catasto` CHAR(1) NOT NULL COMMENT 'F = fabbricati, T = terreni',
  `comune_id` INT UNSIGNED NOT NULL,
  `sezione` VARCHAR(5) NULL,
  `foglio` VARCHAR(10) NOT NULL,
  `particella` VARCHAR(10) NOT NULL,
  `subalterno` VARCHAR(10) NULL,
  `indirizzo` VARCHAR(150) NULL,
  `numero_civico` VARCHAR(20) NULL,
  `piano` VARCHAR(10) NULL,
  `interno` VARCHAR(10) NULL,
  `descrizione` VARCHAR(255) NULL,
  `partita_tavolare` VARCHAR(7) NULL COMMENT 'Catasto tavolare (libro fondiario)',
  `corpo_tavolare` VARCHAR(3) NULL,
  `comune_catastale` VARCHAR(50) NULL COMMENT 'Comune catastale (catasto tavolare di Trento e Bolzano)',
  `porzione_materiale` VARCHAR(12) NULL,
  `note` TEXT NULL,
  `data_inserimento` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_immobile_identificativo` (`studio_id`, `tipo_catasto`, `foglio`, `particella`),
  CONSTRAINT `fk_immobile_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_immobile_comune` FOREIGN KEY (`comune_id`) REFERENCES `comune` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Archivio immobili dello studio (unità del catasto fabbricati e particelle dei terreni)';

CREATE TABLE IF NOT EXISTS `dati_catastali` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `immobile_id` INT UNSIGNED NOT NULL,
  `valido_dal` DATE NOT NULL,
  `valido_al` DATE NULL,
  `categoria` VARCHAR(4) NULL COMMENT 'Solo fabbricati, es. A/2',
  `classe` VARCHAR(10) NULL,
  `consistenza` DECIMAL(10,2) NULL COMMENT 'Vani, mq o mc secondo la categoria',
  `superficie` DECIMAL(12,2) NULL COMMENT 'mq',
  `rendita` DECIMAL(12,2) NULL COMMENT 'Solo fabbricati',
  `qualita_coltura` VARCHAR(50) NULL COMMENT 'Solo terreni',
  `reddito_dominicale` DECIMAL(12,2) NULL,
  `reddito_agrario` DECIMAL(12,2) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dati_catastali_decorrenza` (`immobile_id`, `valido_dal`),
  CONSTRAINT `fk_dati_catastali_immobile` FOREIGN KEY (`immobile_id`) REFERENCES `immobile` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Storico del classamento e delle rendite';

CREATE TABLE IF NOT EXISTS `possesso` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `immobile_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `titolo` VARCHAR(3) NOT NULL,
  `quota` DECIMAL(7,4) NOT NULL COMMENT 'Percentuale',
  `valido_dal` DATE NOT NULL,
  `valido_al` DATE NULL,
  `utilizzo` VARCHAR(2) NULL,
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_possesso_decorrenza` (`immobile_id`, `soggetto_id`, `titolo`, `valido_dal`),
  KEY `idx_possesso_soggetto` (`soggetto_id`),
  CONSTRAINT `fk_possesso_immobile` FOREIGN KEY (`immobile_id`) REFERENCES `immobile` (`id`),
  CONSTRAINT `fk_possesso_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Storico di titolarità, quote e utilizzo degli immobili';
