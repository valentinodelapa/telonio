-- =====================================================
-- Contabilita - contabilità in partita doppia e IVA delle aziende clienti
-- =====================================================
-- Richiede le tabelle dei moduli Telonio (studio), Anagrafica (soggetto,
-- nazione) e Deleghe (archivio dei versamenti). L'azienda è il soggetto
-- dell'Anagrafica di cui lo studio tiene la contabilità: regime contabile
-- e periodicità IVA sono quelli dei dati fiscali storicizzati
-- dell'Anagrafica. Importi in euro con due decimali, aliquote e
-- percentuali in punti percentuali (22,00 = 22%).

-- -----------------------------------------------------
-- Piani dei conti
-- Senza studio e senza soggetto: piani modello della suite (id sotto 1000,
-- da "sisma tabelle-contabilita"); con lo studio e senza soggetto: modelli
-- dello studio; con il soggetto: piano dell'azienda, copiato da un modello
-- all'apertura della contabilità e personalizzabile.
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `piano_conti` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NULL COMMENT 'NULL = piano modello della suite',
  `soggetto_id` INT UNSIGNED NULL COMMENT 'Azienda; NULL = piano modello',
  `denominazione` VARCHAR(100) NOT NULL,
  `descrizione` TEXT NULL,
  `piano_origine_id` INT UNSIGNED NULL COMMENT 'Modello da cui è stato copiato',
  `data_creazione` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_piano_conti_soggetto` (`soggetto_id`),
  KEY `idx_piano_conti_studio` (`studio_id`),
  CONSTRAINT `fk_piano_conti_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_piano_conti_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_piano_conti_origine` FOREIGN KEY (`piano_origine_id`) REFERENCES `piano_conti` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Piani dei conti: modelli della suite e dello studio, piani delle aziende';

-- -----------------------------------------------------
-- Conti su tre livelli: mastro (NN), conto (NN.NN), sottoconto
-- (NN.NN.NNNN). Si registra solo sui sottoconti. La funzione indica i
-- conti usati dalle proposte automatiche (clienti, fornitori, IVA, ...),
-- la voce CEE il raccordo con gli schemi degli artt. 2424 e 2425 c.c.
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `conto` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `piano_conti_id` INT UNSIGNED NOT NULL,
  `parent_conto_id` INT UNSIGNED NULL COMMENT 'Mastro del conto, conto del sottoconto',
  `codice` VARCHAR(20) NOT NULL,
  `descrizione` VARCHAR(150) NOT NULL,
  `livello` CHAR(1) NOT NULL COMMENT 'M mastro, C conto, S sottoconto',
  `natura` CHAR(1) NOT NULL COMMENT 'A attività, P passività e netto, C costo, R ricavo, S conto di sistema',
  `funzione` CHAR(3) NULL COMMENT 'Ruolo nelle proposte automatiche (unico nel piano)',
  `voce_cee` VARCHAR(20) NULL COMMENT 'Voce degli schemi di bilancio (artt. 2424 e 2425 c.c.)',
  `attivo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conto_codice` (`piano_conti_id`, `codice`),
  KEY `idx_conto_parent` (`parent_conto_id`),
  CONSTRAINT `fk_conto_piano` FOREIGN KEY (`piano_conti_id`) REFERENCES `piano_conti` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conto_parent` FOREIGN KEY (`parent_conto_id`) REFERENCES `conto` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=100000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Conti dei piani dei conti';

-- -----------------------------------------------------
-- Codici IVA: aliquota o natura dell'operazione (codici delle specifiche
-- della fattura elettronica), detraibilità di default e inversione
-- contabile. Senza studio: codici della suite (id sotto 1000).
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `codice_iva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NULL COMMENT 'NULL = codice della suite',
  `codice` VARCHAR(10) NOT NULL,
  `descrizione` VARCHAR(150) NOT NULL,
  `aliquota` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `natura` VARCHAR(4) NULL COMMENT 'Natura dell''operazione senza imposta (N1-N7); NULL = imponibile',
  `detraibilita` DECIMAL(5,2) NOT NULL DEFAULT 100.00 COMMENT 'Percentuale detraibile di default sugli acquisti',
  `inversione_contabile` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Acquisto con integrazione dell''imposta (reverse charge, intracomunitari, esteri)',
  `ventilazione` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Corrispettivi da ventilare (art. 24, c. 3, D.P.R. 633/1972): aliquota attribuita nella liquidazione',
  `norma` VARCHAR(255) NULL,
  `attivo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_codice_iva` (`studio_id`, `codice`),
  CONSTRAINT `fk_codice_iva_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Codici IVA della suite e degli studi';

-- -----------------------------------------------------
-- Esercizi contabili dell'azienda (di norma l'anno solare)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `esercizio_contabile` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL COMMENT 'Anno di chiusura dell''esercizio',
  `data_inizio` DATE NOT NULL,
  `data_fine` DATE NOT NULL,
  `chiuso` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Chiuso: registrazioni non più modificabili',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_esercizio_contabile` (`soggetto_id`, `anno`),
  KEY `idx_esercizio_contabile_studio` (`studio_id`),
  CONSTRAINT `fk_esercizio_contabile_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_esercizio_contabile_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Esercizi contabili delle aziende';

-- -----------------------------------------------------
-- Registri IVA dell'azienda: vendite, acquisti, corrispettivi, con
-- eventuali sezionali (art. 23-25 e 24 D.P.R. 633/1972)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `registro_iva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `tipo` CHAR(1) NOT NULL COMMENT 'V vendite, A acquisti, C corrispettivi',
  `sezionale` VARCHAR(10) NOT NULL DEFAULT '' COMMENT 'Vuoto = registro principale',
  `descrizione` VARCHAR(100) NOT NULL,
  `attivo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_registro_iva` (`soggetto_id`, `tipo`, `sezionale`),
  KEY `idx_registro_iva_studio` (`studio_id`),
  CONSTRAINT `fk_registro_iva_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_registro_iva_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registri IVA e sezionali delle aziende';

-- -----------------------------------------------------
-- Clienti e fornitori dell'azienda (separati per azienda), con i
-- sottoconti del partitario
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `controparte` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL COMMENT 'Azienda',
  `denominazione` VARCHAR(255) NOT NULL COMMENT 'Ragione sociale o cognome e nome',
  `cognome` VARCHAR(60) NULL,
  `nome` VARCHAR(60) NULL,
  `codice_fiscale` VARCHAR(16) NULL,
  `partita_iva` VARCHAR(28) NULL COMMENT 'Senza prefisso del paese',
  `nazione_id` INT UNSIGNED NULL COMMENT 'NULL = Italia',
  `indirizzo` VARCHAR(100) NULL,
  `cap` VARCHAR(10) NULL,
  `comune` VARCHAR(60) NULL,
  `provincia` CHAR(2) NULL,
  `pec` VARCHAR(255) NULL,
  `codice_destinatario` VARCHAR(7) NULL,
  `cliente` TINYINT(1) NOT NULL DEFAULT 0,
  `fornitore` TINYINT(1) NOT NULL DEFAULT 0,
  `pubblica_amministrazione` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Scissione dei pagamenti proposta sulle vendite (art. 17-ter D.P.R. 633/1972)',
  `conto_cliente_id` INT UNSIGNED NULL,
  `conto_fornitore_id` INT UNSIGNED NULL,
  `conto_contropartita_id` INT UNSIGNED NULL COMMENT 'Costo o ricavo proposto nelle registrazioni',
  `codice_iva_id` INT UNSIGNED NULL COMMENT 'Codice IVA proposto',
  `note` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_controparte_soggetto` (`soggetto_id`, `denominazione`),
  KEY `idx_controparte_partita_iva` (`soggetto_id`, `partita_iva`),
  KEY `idx_controparte_studio` (`studio_id`),
  CONSTRAINT `fk_controparte_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_controparte_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_controparte_nazione` FOREIGN KEY (`nazione_id`) REFERENCES `nazione` (`id`),
  CONSTRAINT `fk_controparte_conto_cliente` FOREIGN KEY (`conto_cliente_id`) REFERENCES `conto` (`id`),
  CONSTRAINT `fk_controparte_conto_fornitore` FOREIGN KEY (`conto_fornitore_id`) REFERENCES `conto` (`id`),
  CONSTRAINT `fk_controparte_conto_contropartita` FOREIGN KEY (`conto_contropartita_id`) REFERENCES `conto` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_controparte_codice_iva` FOREIGN KEY (`codice_iva_id`) REFERENCES `codice_iva` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Clienti e fornitori delle aziende';

-- -----------------------------------------------------
-- Prima nota: registrazioni con le righe in partita doppia e, per i
-- documenti IVA, le righe del registro
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `registrazione_contabile` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `esercizio_contabile_id` INT UNSIGNED NOT NULL,
  `numero` INT UNSIGNED NOT NULL COMMENT 'Progressivo nell''esercizio',
  `data_registrazione` DATE NOT NULL,
  `causale` CHAR(3) NOT NULL,
  `descrizione` VARCHAR(255) NULL,
  `controparte_id` INT UNSIGNED NULL,
  `numero_documento` VARCHAR(30) NULL,
  `data_documento` DATE NULL,
  `registro_iva_id` INT UNSIGNED NULL,
  `protocollo_iva` INT UNSIGNED NULL COMMENT 'Numero nel registro IVA, progressivo per anno solare',
  `data_competenza_iva` DATE NULL COMMENT 'Data che determina il periodo di liquidazione',
  `esigibilita` CHAR(1) NOT NULL DEFAULT 'I' COMMENT 'I immediata, D differita, S scissione dei pagamenti',
  `totale_documento` DECIMAL(15,2) NULL,
  `origine` VARCHAR(30) NULL COMMENT 'Registrazione generata (es. liquidazione IVA)',
  `riferimento_origine` VARCHAR(50) NULL,
  `data_inserimento` DATETIME NOT NULL,
  `data_modifica` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_registrazione_numero` (`esercizio_contabile_id`, `numero`),
  KEY `idx_registrazione_soggetto_data` (`soggetto_id`, `data_registrazione`),
  KEY `idx_registrazione_iva` (`registro_iva_id`, `data_competenza_iva`),
  KEY `idx_registrazione_controparte` (`controparte_id`),
  KEY `idx_registrazione_studio` (`studio_id`),
  CONSTRAINT `fk_registrazione_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_registrazione_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_registrazione_esercizio` FOREIGN KEY (`esercizio_contabile_id`) REFERENCES `esercizio_contabile` (`id`),
  CONSTRAINT `fk_registrazione_controparte` FOREIGN KEY (`controparte_id`) REFERENCES `controparte` (`id`),
  CONSTRAINT `fk_registrazione_registro_iva` FOREIGN KEY (`registro_iva_id`) REFERENCES `registro_iva` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registrazioni di prima nota';

CREATE TABLE IF NOT EXISTS `riga_contabile` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `registrazione_contabile_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL COMMENT 'Copiato dalla registrazione, per le interrogazioni per periodo',
  `data_registrazione` DATE NOT NULL COMMENT 'Copiata dalla registrazione',
  `ordine` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `conto_id` INT UNSIGNED NOT NULL COMMENT 'Sottoconto',
  `controparte_id` INT UNSIGNED NULL COMMENT 'Cliente o fornitore della riga del partitario',
  `dare` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `avere` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `descrizione` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_riga_contabile_registrazione` (`registrazione_contabile_id`, `ordine`),
  KEY `idx_riga_contabile_conto` (`conto_id`, `data_registrazione`),
  KEY `idx_riga_contabile_soggetto` (`soggetto_id`, `data_registrazione`),
  CONSTRAINT `fk_riga_contabile_registrazione` FOREIGN KEY (`registrazione_contabile_id`) REFERENCES `registrazione_contabile` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_riga_contabile_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_riga_contabile_conto` FOREIGN KEY (`conto_id`) REFERENCES `conto` (`id`),
  CONSTRAINT `fk_riga_contabile_controparte` FOREIGN KEY (`controparte_id`) REFERENCES `controparte` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Righe in partita doppia delle registrazioni';

CREATE TABLE IF NOT EXISTS `riga_iva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `registrazione_contabile_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL COMMENT 'Copiato dalla registrazione, per le interrogazioni per periodo',
  `registro_iva_id` INT UNSIGNED NOT NULL COMMENT 'Copiato dalla registrazione',
  `data_competenza_iva` DATE NOT NULL COMMENT 'Copiata dalla registrazione',
  `ordine` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `codice_iva_id` INT UNSIGNED NOT NULL,
  `imponibile` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Con il segno del documento: negativo nelle note di variazione in diminuzione',
  `imposta` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `detraibilita` DECIMAL(5,2) NOT NULL DEFAULT 100.00 COMMENT 'Percentuale detraibile (solo acquisti)',
  `rivendita` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Acquisto di beni destinati alla rivendita: base della ventilazione dei corrispettivi',
  `conto_id` INT UNSIGNED NULL COMMENT 'Costo o ricavo di contropartita',
  PRIMARY KEY (`id`),
  KEY `idx_riga_iva_registrazione` (`registrazione_contabile_id`, `ordine`),
  KEY `idx_riga_iva_soggetto` (`soggetto_id`, `data_competenza_iva`),
  KEY `idx_riga_iva_registro` (`registro_iva_id`, `data_competenza_iva`),
  CONSTRAINT `fk_riga_iva_registrazione` FOREIGN KEY (`registrazione_contabile_id`) REFERENCES `registrazione_contabile` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_riga_iva_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_riga_iva_registro` FOREIGN KEY (`registro_iva_id`) REFERENCES `registro_iva` (`id`),
  CONSTRAINT `fk_riga_iva_codice` FOREIGN KEY (`codice_iva_id`) REFERENCES `codice_iva` (`id`),
  CONSTRAINT `fk_riga_iva_conto` FOREIGN KEY (`conto_id`) REFERENCES `conto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Righe del registro IVA delle registrazioni';

-- -----------------------------------------------------
-- Corrispettivi giornalieri della registrazione mensile: l'ammontare
-- globale giornaliero per codice IVA (art. 24 D.P.R. 633/1972), annotato
-- con un'unica registrazione per mese (art. 6, c. 4, D.P.R. 695/1996)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `corrispettivo_giornaliero` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `registrazione_contabile_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `data` DATE NOT NULL,
  `codice_iva_id` INT UNSIGNED NOT NULL,
  `importo` DECIMAL(15,2) NOT NULL COMMENT 'Corrispettivi della giornata comprensivi dell''imposta',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_corrispettivo_giornaliero` (`registrazione_contabile_id`, `data`, `codice_iva_id`),
  KEY `idx_corrispettivo_giornaliero_soggetto` (`soggetto_id`, `data`),
  CONSTRAINT `fk_corrispettivo_giornaliero_registrazione` FOREIGN KEY (`registrazione_contabile_id`) REFERENCES `registrazione_contabile` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_corrispettivo_giornaliero_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_corrispettivo_giornaliero_codice` FOREIGN KEY (`codice_iva_id`) REFERENCES `codice_iva` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Corrispettivi giornalieri delle registrazioni mensili';

-- -----------------------------------------------------
-- Liquidazioni periodiche IVA (art. 1 D.P.R. 100/1998, art. 7 D.P.R.
-- 542/1999) e acconto (art. 6, c. 2, L. 405/1990); i versamenti sono
-- registrati nell'archivio di Deleghe F24
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `liquidazione_iva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL,
  `periodo` TINYINT UNSIGNED NOT NULL COMMENT 'Mese (1-12) o trimestre (1-4)',
  `periodicita` CHAR(1) NOT NULL COMMENT 'M mensile, T trimestrale',
  `iva_esigibile` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `iva_detraibile` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `iva_ventilata` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Imposta dei corrispettivi ventilati, compresa nell''IVA esigibile',
  `debito_precedente` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Debito non superiore a 100 euro del periodo precedente',
  `credito_precedente` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `credito_anno_precedente` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Credito annuale compensato nella liquidazione',
  `acconto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `interessi` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Trimestrali: 1% (art. 7, c. 3, D.P.R. 542/1999)',
  `importo_dovuto` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Da versare nel periodo',
  `credito_finale` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Riportato al periodo successivo',
  `debito_rinviato` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Non superiore a 100 euro, versato con il periodo successivo',
  `scadenza` DATE NULL,
  `data_elaborazione` DATETIME NOT NULL,
  `avvisi` TEXT NULL,
  `differenze` TEXT NULL COMMENT 'Differenze con i versamenti già in deleghe non modificabili',
  `registrazione_contabile_id` INT UNSIGNED NULL COMMENT 'Giroconto della liquidazione in prima nota',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_liquidazione_iva` (`soggetto_id`, `anno`, `periodo`),
  KEY `idx_liquidazione_iva_studio` (`studio_id`, `anno`),
  CONSTRAINT `fk_liquidazione_iva_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_liquidazione_iva_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_liquidazione_iva_registrazione` FOREIGN KEY (`registrazione_contabile_id`) REFERENCES `registrazione_contabile` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Liquidazioni periodiche IVA';

CREATE TABLE IF NOT EXISTS `acconto_iva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL,
  `periodicita` CHAR(1) NOT NULL COMMENT 'M mensile (6013), T trimestrale (6035)',
  `metodo` CHAR(1) NOT NULL COMMENT 'S storico, P previsionale, A analitico',
  `base` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Importo su cui si calcola l''88% (storico, previsionale) o liquidazione analitica',
  `importo` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `data_elaborazione` DATETIME NOT NULL,
  `note` TEXT NULL,
  `differenze` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_acconto_iva` (`soggetto_id`, `anno`),
  KEY `idx_acconto_iva_studio` (`studio_id`, `anno`),
  CONSTRAINT `fk_acconto_iva_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_acconto_iva_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Acconti IVA';

-- -----------------------------------------------------
-- Fatture elettroniche importate (formato FatturaPA, file XML o firmati
-- .p7m): un rigo per fattura del file (i lotti ne contengono più di una)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `fattura_elettronica` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `direzione` CHAR(1) NOT NULL COMMENT 'E emessa (vendita), R ricevuta (acquisto)',
  `nome_file` VARCHAR(255) NOT NULL,
  `impronta` CHAR(64) NOT NULL COMMENT 'SHA-256 del documento XML e numero del corpo: impedisce i doppi caricamenti',
  `indice_corpo` SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Posizione della fattura nel lotto',
  `tipo_documento` CHAR(4) NOT NULL COMMENT 'TD01-TD29',
  `numero` VARCHAR(30) NOT NULL,
  `data` DATE NOT NULL,
  `denominazione_controparte` VARCHAR(255) NOT NULL,
  `partita_iva_controparte` VARCHAR(28) NULL,
  `codice_fiscale_controparte` VARCHAR(16) NULL,
  `codice_paese` CHAR(2) NULL,
  `importo_totale` DECIMAL(15,2) NULL,
  `controparte_id` INT UNSIGNED NULL,
  `registrazione_contabile_id` INT UNSIGNED NULL,
  `stato` CHAR(1) NOT NULL DEFAULT 'D' COMMENT 'D da registrare, R registrata, S scartata',
  `contenuto` MEDIUMTEXT NOT NULL COMMENT 'Documento XML (estratto dalla busta .p7m)',
  `data_importazione` DATETIME NOT NULL,
  `avvisi` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fattura_elettronica_impronta` (`soggetto_id`, `impronta`),
  KEY `idx_fattura_elettronica_stato` (`soggetto_id`, `stato`, `data`),
  KEY `idx_fattura_elettronica_studio` (`studio_id`),
  CONSTRAINT `fk_fattura_elettronica_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_fattura_elettronica_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_fattura_elettronica_controparte` FOREIGN KEY (`controparte_id`) REFERENCES `controparte` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fattura_elettronica_registrazione` FOREIGN KEY (`registrazione_contabile_id`) REFERENCES `registrazione_contabile` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Fatture elettroniche importate';
