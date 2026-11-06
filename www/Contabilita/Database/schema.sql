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
-- Attività con contabilità separata (art. 36 D.P.R. 633/1972): ogni
-- registro o sezionale IVA appartiene a un'attività; liquidazioni
-- periodiche distinte, compensate nel periodo, con la periodicità propria
-- dell'attività se la separazione è obbligatoria
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `attivita_iva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `numero` TINYINT UNSIGNED NOT NULL COMMENT 'Ordine dei moduli della dichiarazione IVA (1 = primo modulo)',
  `descrizione` VARCHAR(100) NOT NULL,
  `codice_ateco_id` INT UNSIGNED NULL COMMENT 'Codice attività del rigo VA2 del modulo',
  `periodicita` CHAR(1) NULL COMMENT 'M mensile, T trimestrale; NULL = periodicità dell''azienda',
  `regime_detrazione_iva` CHAR(3) NULL COMMENT 'Regime speciale agricolo dell''attività (rigo VF30 del suo modulo)',
  `dal_anno` SMALLINT UNSIGNED NOT NULL,
  `al_anno` SMALLINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attivita_iva` (`soggetto_id`, `numero`),
  KEY `idx_attivita_iva_studio` (`studio_id`),
  CONSTRAINT `fk_attivita_iva_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_attivita_iva_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_attivita_iva_ateco` FOREIGN KEY (`codice_ateco_id`) REFERENCES `codice_ateco` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Attività IVA con contabilità separata';

-- -----------------------------------------------------
-- IVA di gruppo (art. 73, c. 3, D.P.R. 633/1972; D.M. 13 dicembre 1979):
-- opzione della controllante valida fino a revoca, controllate con anno
-- di ingresso e di uscita (dal 1° gennaio) o cessazione del controllo in
-- corso d'anno; liquidazioni periodiche di gruppo della controllante
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `gruppo_iva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `controllante_id` INT UNSIGNED NOT NULL,
  `anno_inizio` SMALLINT UNSIGNED NOT NULL COMMENT 'Primo anno della liquidazione di gruppo (opzione nel quadro VG della dichiarazione dell''anno precedente)',
  `anno_revoca` SMALLINT UNSIGNED NULL COMMENT 'Primo anno senza liquidazione di gruppo (revoca nel VG8)',
  `maggioranza_id` INT UNSIGNED NULL COMMENT 'Soggetto che possiede la maggioranza della controllante e rinuncia alla liquidazione di gruppo (VG1)',
  `data_possesso_maggioranza` DATE NULL,
  `percentuale_maggioranza` DECIMAL(5,2) NULL,
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_gruppo_iva_studio` (`studio_id`),
  CONSTRAINT `fk_gruppo_iva_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_gruppo_iva_controllante` FOREIGN KEY (`controllante_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_gruppo_iva_maggioranza` FOREIGN KEY (`maggioranza_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Liquidazioni IVA di gruppo (art. 73)';

CREATE TABLE IF NOT EXISTS `partecipante_gruppo_iva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gruppo_iva_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno_ingresso` SMALLINT UNSIGNED NOT NULL,
  `anno_uscita` SMALLINT UNSIGNED NULL COMMENT 'Primo anno fuori dalla liquidazione di gruppo (fuoriuscita dal 1° gennaio)',
  `data_cessazione` DATE NULL COMMENT 'Cessazione del controllo in corso d''anno: ultimo mese o trimestre di controllo quello precedente',
  `data_possesso` DATE NULL COMMENT 'Inizio del possesso ininterrotto della maggioranza (VG2-VG7 campo 1)',
  `percentuale_possesso` DECIMAL(5,2) NULL,
  `detentore_id` INT UNSIGNED NULL COMMENT 'Soggetto che possiede la maggioranza (di default la controllante)',
  `detentore_estero` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Detentore non residente senza posizione IVA in Italia',
  `solo_catena` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Partecipa alla catena di controllo ma non alla compensazione (VG sezione II)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_partecipante_gruppo_iva` (`gruppo_iva_id`, `soggetto_id`, `anno_ingresso`),
  KEY `idx_partecipante_gruppo_iva_soggetto` (`soggetto_id`),
  CONSTRAINT `fk_partecipante_gruppo_iva_gruppo` FOREIGN KEY (`gruppo_iva_id`) REFERENCES `gruppo_iva` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_partecipante_gruppo_iva_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_partecipante_gruppo_iva_detentore` FOREIGN KEY (`detentore_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Società controllate partecipanti alla liquidazione IVA di gruppo';

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
  `unita_locale_id` INT UNSIGNED NULL COMMENT 'Sede operativa (Anagrafica) delle operazioni del registro: regione nel quadro VT',
  `attivita_iva_id` INT UNSIGNED NULL COMMENT 'Attività con contabilità separata; NULL con attività separate = registro degli acquisti promiscui',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_registro_iva` (`soggetto_id`, `tipo`, `sezionale`),
  KEY `idx_registro_iva_studio` (`studio_id`),
  CONSTRAINT `fk_registro_iva_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_registro_iva_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_registro_iva_unita_locale` FOREIGN KEY (`unita_locale_id`) REFERENCES `indirizzo` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_registro_iva_attivita` FOREIGN KEY (`attivita_iva_id`) REFERENCES `attivita_iva` (`id`) ON DELETE SET NULL
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
  `importazione` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Acquisto con documento doganale (VF28 campi 3-4, VC colonna 2)',
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
  `altri_prodotti` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Rivendita di beni fuori dalle categorie ammesse alla ventilazione (limite del 50%, art. 4 D.M. 24/2/1973)',
  `conto_id` INT UNSIGNED NULL COMMENT 'Costo o ricavo di contropartita',
  `natura_inversione` VARCHAR(4) NULL COMMENT 'Acquisti in inversione contabile: tipologia N6.1-N6.9 (rigo del quadro VJ della dichiarazione IVA)',
  `qualifica` CHAR(1) NULL COMMENT 'Operazioni attive: O oro, E esente occasionale, Q 27-quinquies, A bene ammortizzabile, P esente equiparata, F fuori campo senza detrazione, S prestazione verso soggetti non stabiliti dell''art. 30, c. 2, lett. d), C conferimento agricolo, K connessa occasionale (VF34, VE40, TD5, VE sezione 1, VF62)',
  `percentuale_compensazione` DECIMAL(4,2) NULL COMMENT 'Vendite in regime agricolo: percentuale di compensazione dei prodotti della Tabella A, parte I',
  `operazione_diversa` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Acquisti in regime agricolo destinati alle operazioni diverse (art. 34, c. 5)',
  `aliquota_teorica` DECIMAL(5,2) NULL COMMENT 'Acquisti con dichiarazione d''intento: aliquota dell''IVA non assolta (VF35)',
  `ripartizione` VARCHAR(255) NULL COMMENT 'Acquisti promiscui tra attività separate: numero attività e percentuale (es. 1:60;2:40)',
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
  `versamenti_auto` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'IVA versata con F24 elementi identificativi sulle auto di provenienza UE (D.L. 262/2006)',
  `crediti_imposta` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Crediti d''imposta particolari scomputati dal versamento',
  `acconto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `interessi` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Trimestrali: 1% (art. 7, c. 3, D.P.R. 542/1999)',
  `importo_dovuto` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Da versare nel periodo',
  `credito_finale` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Riportato al periodo successivo',
  `credito_trimestrale` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Credito del trimestre chiesto a rimborso o in compensazione con il modello IVA TR, non riportato',
  `debito_rinviato` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Non superiore a 100 euro, versato con il periodo successivo',
  `scadenza` DATE NULL,
  `data_elaborazione` DATETIME NOT NULL,
  `avvisi` TEXT NULL,
  `differenze` TEXT NULL COMMENT 'Differenze con i versamenti già in deleghe non modificabili',
  `registrazione_contabile_id` INT UNSIGNED NULL COMMENT 'Giroconto della liquidazione in prima nota',
  `trimestre_anticipato` TINYINT UNSIGNED NULL COMMENT 'Attività separate trimestrali: trimestre liquidato con il mese di fine trimestre',
  `gruppo_iva_id` INT UNSIGNED NULL COMMENT 'IVA di gruppo: risultato trasferito alla controllante, non versato',
  `trimestre_separato` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Attività separate: liquidazione del trimestre delle attività trimestrali non anticipata al mese',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_liquidazione_iva` (`soggetto_id`, `anno`, `periodo`, `trimestre_separato`),
  KEY `idx_liquidazione_iva_studio` (`studio_id`, `anno`),
  CONSTRAINT `fk_liquidazione_iva_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_liquidazione_iva_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_liquidazione_iva_registrazione` FOREIGN KEY (`registrazione_contabile_id`) REFERENCES `registrazione_contabile` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_liquidazione_iva_gruppo` FOREIGN KEY (`gruppo_iva_id`) REFERENCES `gruppo_iva` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Liquidazioni periodiche IVA';

-- -----------------------------------------------------
-- Liquidazione di ciascuna attività con contabilità separata compresa
-- nella liquidazione periodica dell'azienda (trimestrali anticipate al
-- mese di fine trimestre)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `liquidazione_attivita` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `liquidazione_iva_id` INT UNSIGNED NOT NULL,
  `attivita_iva_id` INT UNSIGNED NOT NULL,
  `periodicita` CHAR(1) NOT NULL,
  `periodo` TINYINT UNSIGNED NOT NULL COMMENT 'Mese o trimestre dell''attività',
  `iva_esigibile` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `iva_detraibile` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `iva_ventilata` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `percentuale_detrazione` TINYINT UNSIGNED NOT NULL DEFAULT 100,
  `interessi` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Attività trimestrali: 1% dell''IVA dovuta del trimestre',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_liquidazione_attivita` (`liquidazione_iva_id`, `attivita_iva_id`),
  CONSTRAINT `fk_liquidazione_attivita_liquidazione` FOREIGN KEY (`liquidazione_iva_id`) REFERENCES `liquidazione_iva` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_liquidazione_attivita_attivita` FOREIGN KEY (`attivita_iva_id`) REFERENCES `attivita_iva` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Liquidazioni periodiche per attività separata';

-- -----------------------------------------------------
-- Liquidazioni periodiche del gruppo IVA (art. 73): debiti e crediti
-- trasferiti dalle società partecipanti, versamento della controllante
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `liquidazione_gruppo_iva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `gruppo_iva_id` INT UNSIGNED NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL,
  `periodo` TINYINT UNSIGNED NOT NULL,
  `periodicita` CHAR(1) NOT NULL,
  `trimestre_anticipato` TINYINT UNSIGNED NULL COMMENT 'Trimestre delle società trimestrali compreso nel mese di fine trimestre',
  `trimestre_separato` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Liquidazione del trimestre delle società trimestrali non anticipata al mese',
  `debiti_trasferiti` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Compresi gli interessi dei trimestrali (VP4 della comunicazione del gruppo)',
  `crediti_trasferiti` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP5 della comunicazione del gruppo',
  `interessi_trasferiti` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `debito_precedente` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `credito_precedente` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `credito_anno_precedente` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Credito di gruppo dell''anno precedente compensato',
  `acconto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `importo_dovuto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `credito_finale` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `credito_trimestrale` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Credito del trimestre chiesto con il modello IVA TR della controllante, non riportato',
  `debito_rinviato` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `scadenza` DATE NULL,
  `data_elaborazione` DATETIME NOT NULL,
  `avvisi` TEXT NULL,
  `differenze` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_liquidazione_gruppo_iva` (`gruppo_iva_id`, `anno`, `periodo`, `trimestre_separato`),
  KEY `idx_liquidazione_gruppo_iva_studio` (`studio_id`, `anno`),
  CONSTRAINT `fk_liquidazione_gruppo_iva_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_liquidazione_gruppo_iva_gruppo` FOREIGN KEY (`gruppo_iva_id`) REFERENCES `gruppo_iva` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Liquidazioni periodiche del gruppo IVA';


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
  `gruppo_iva_id` INT UNSIGNED NULL COMMENT 'Acconto del gruppo IVA versato dalla controllante',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_acconto_iva` (`soggetto_id`, `anno`, `gruppo_iva_id`),
  KEY `idx_acconto_iva_studio` (`studio_id`, `anno`),
  CONSTRAINT `fk_acconto_iva_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_acconto_iva_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_acconto_iva_gruppo` FOREIGN KEY (`gruppo_iva_id`) REFERENCES `gruppo_iva` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Acconti IVA';

-- -----------------------------------------------------
-- Comunicazioni delle liquidazioni periodiche IVA (art. 21-bis D.L.
-- 78/2010, fornitura IVP18): una per trimestre, con un modulo per ogni
-- mese dei mensili o uno per il trimestre dei trimestrali
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `comunicazione_lipe` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL,
  `trimestre` TINYINT UNSIGNED NOT NULL,
  `progressivo` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Identificativo della comunicazione nella fornitura e nel nome del file',
  `stato` CHAR(1) NOT NULL DEFAULT 'B' COMMENT 'B bozza, I inviata',
  `dichiarante_id` INT UNSIGNED NULL COMMENT 'Firmatario diverso dal contribuente (obbligatorio per i soggetti diversi dalle persone fisiche)',
  `codice_carica` VARCHAR(2) NULL COMMENT 'Codice di carica del dichiarante (1-9, 11-15)',
  `intermediario_id` INT UNSIGNED NULL COMMENT 'Intermediario che trasmette (Deleghe)',
  `impegno` CHAR(1) NULL COMMENT 'Impegno alla presentazione: 1 predisposta dal contribuente, 2 da chi invia',
  `data_impegno` DATE NULL,
  `data_invio` DATE NULL,
  `protocollo` VARCHAR(50) NULL,
  `data_elaborazione` DATETIME NOT NULL,
  `avvisi` TEXT NULL,
  `gruppo_iva_id` INT UNSIGNED NULL COMMENT 'Comunicazione della controllante per la liquidazione del gruppo IVA',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_comunicazione_lipe` (`soggetto_id`, `anno`, `trimestre`, `gruppo_iva_id`),
  KEY `idx_comunicazione_lipe_studio` (`studio_id`, `anno`),
  CONSTRAINT `fk_comunicazione_lipe_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_comunicazione_lipe_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_comunicazione_lipe_dichiarante` FOREIGN KEY (`dichiarante_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_comunicazione_lipe_intermediario` FOREIGN KEY (`intermediario_id`) REFERENCES `intermediario` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_comunicazione_lipe_gruppo` FOREIGN KEY (`gruppo_iva_id`) REFERENCES `gruppo_iva` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Comunicazioni delle liquidazioni periodiche IVA';

CREATE TABLE IF NOT EXISTS `modulo_lipe` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `comunicazione_lipe_id` INT UNSIGNED NOT NULL,
  `numero_modulo` TINYINT UNSIGNED NOT NULL,
  `mese` TINYINT UNSIGNED NULL COMMENT 'Rigo VP1: mese dei mensili',
  `trimestre` TINYINT UNSIGNED NULL COMMENT 'Rigo VP1: trimestre (5 = quarto dei trimestrali)',
  `liquidazione_iva_id` INT UNSIGNED NULL,
  `operazioni_attive` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP2',
  `operazioni_passive` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP3',
  `iva_esigibile` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP4',
  `iva_detratta` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP5',
  `debito_precedente` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP7',
  `credito_precedente` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP8',
  `credito_anno_precedente` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP9',
  `versamenti_auto` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP10',
  `crediti_imposta` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP11',
  `interessi` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP12',
  `metodo_acconto` CHAR(1) NULL COMMENT 'VP13 col. 1: 1 storico, 2 previsionale, 3 analitico, 4 settori particolari',
  `acconto` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP13 col. 2',
  `importo_da_versare` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP14 col. 1',
  `importo_a_credito` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'VP14 col. 2',
  `liquidazione_gruppo_iva_id` INT UNSIGNED NULL COMMENT 'Modulo della comunicazione del gruppo IVA',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_modulo_lipe` (`comunicazione_lipe_id`, `numero_modulo`),
  CONSTRAINT `fk_modulo_lipe_comunicazione` FOREIGN KEY (`comunicazione_lipe_id`) REFERENCES `comunicazione_lipe` (`id`),
  CONSTRAINT `fk_modulo_lipe_liquidazione` FOREIGN KEY (`liquidazione_iva_id`) REFERENCES `liquidazione_iva` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_modulo_lipe_liquidazione_gruppo` FOREIGN KEY (`liquidazione_gruppo_iva_id`) REFERENCES `liquidazione_gruppo_iva` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Moduli del quadro VP delle comunicazioni delle liquidazioni periodiche';

-- -----------------------------------------------------
-- Stampe definitive con le pagine numerate per anno del libro giornale
-- (per esercizio, art. 2215 c.c.) e dei registri IVA (per anno solare,
-- art. 39 D.P.R. 633/1972): periodi contigui, registrazioni dei periodi
-- stampati non più modificabili
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `stampa_definitiva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `tipo` CHAR(1) NOT NULL COMMENT 'G libro giornale, R registro IVA',
  `esercizio_contabile_id` INT UNSIGNED NULL COMMENT 'Libro giornale: esercizio',
  `registro_iva_id` INT UNSIGNED NULL COMMENT 'Registro IVA stampato',
  `anno` SMALLINT UNSIGNED NOT NULL COMMENT 'Anno della numerazione delle pagine',
  `dal` DATE NOT NULL,
  `al` DATE NOT NULL,
  `prima_pagina` INT UNSIGNED NOT NULL,
  `ultima_pagina` INT UNSIGNED NOT NULL,
  `righe_per_pagina` SMALLINT UNSIGNED NOT NULL COMMENT 'Per rigenerare le stesse pagine nella ristampa',
  `data_stampa` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_stampa_definitiva_libro` (`soggetto_id`, `tipo`, `anno`, `dal`),
  KEY `idx_stampa_definitiva_studio` (`studio_id`),
  CONSTRAINT `fk_stampa_definitiva_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_stampa_definitiva_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_stampa_definitiva_esercizio` FOREIGN KEY (`esercizio_contabile_id`) REFERENCES `esercizio_contabile` (`id`),
  CONSTRAINT `fk_stampa_definitiva_registro` FOREIGN KEY (`registro_iva_id`) REFERENCES `registro_iva` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stampe definitive del libro giornale e dei registri IVA';

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

-- -----------------------------------------------------
-- Percentuale di detrazione provvisoria dell'anno (pro-rata, art. 19,
-- c. 5, e art. 19-bis D.P.R. 633/1972), applicata nelle liquidazioni
-- periodiche: quella definitiva dell'anno precedente
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `prorata_iva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL,
  `percentuale` TINYINT UNSIGNED NOT NULL DEFAULT 100 COMMENT 'Percentuale di detrazione (0-100, arrotondata all''unità)',
  `origine` VARCHAR(30) NULL COMMENT 'Modulo che l''ha proposta (Dichiarativi), NULL se inserita a mano',
  `note` VARCHAR(255) NULL,
  `attivita_iva_id` INT UNSIGNED NULL COMMENT 'Attività con contabilità separata (pro-rata per attività)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prorata_iva` (`soggetto_id`, `anno`, `attivita_iva_id`),
  CONSTRAINT `fk_prorata_iva_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_prorata_iva_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_prorata_iva_attivita` FOREIGN KEY (`attivita_iva_id`) REFERENCES `attivita_iva` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Pro-rata di detrazione provvisorio per azienda e anno';

CREATE TABLE IF NOT EXISTS `credito_trimestrale_iva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL COMMENT 'Con il gruppo IVA la controllante',
  `gruppo_iva_id` INT UNSIGNED NULL COMMENT 'Credito del gruppo IVA chiesto dalla controllante',
  `anno` SMALLINT UNSIGNED NOT NULL,
  `trimestre` TINYINT UNSIGNED NOT NULL COMMENT 'Da 1 a 3',
  `importo` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_credito_trimestrale_iva` (`soggetto_id`, `anno`, `trimestre`),
  CONSTRAINT `fk_credito_trimestrale_iva_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_credito_trimestrale_iva_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_credito_trimestrale_iva_gruppo` FOREIGN KEY (`gruppo_iva_id`) REFERENCES `gruppo_iva` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Credito del trimestre chiesto con il modello IVA TR, escluso dal riporto delle liquidazioni';
