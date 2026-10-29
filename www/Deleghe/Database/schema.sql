-- =====================================================
-- Deleghe - deleghe di pagamento F24
-- =====================================================
-- Richiede le tabelle dei moduli Telonio (studio) e Anagrafica (soggetto,
-- conto_corrente). Gli importi sono in euro con due decimali.

-- -----------------------------------------------------
-- Tabelle di riferimento (comuni a tutti gli studi)
-- Popolate e aggiornate con il comando "sisma tabelle-f24" dall'archivio
-- delle tabelle F24 dell'Agenzia delle Entrate e dalle sorgenti del modulo
-- (Database/sorgenti). Le righe referenziate non vengono mai cancellate:
-- quelle non più presenti nella fonte restano con in_uso = 0.
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `codice_tributo` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sezione` CHAR(1) NOT NULL COMMENT 'E erario, R regioni, L IMU e tributi locali, A accise, I elementi identificativi',
  `codice` CHAR(4) NOT NULL,
  `descrizione` VARCHAR(500) NOT NULL,
  `tipo_tributo` VARCHAR(100) NULL COMMENT 'Classificazione della tabella AdE (es. IMU, TASI, Erario)',
  `modalita` CHAR(1) NOT NULL DEFAULT 'D' COMMENT 'D solo debito, R solo credito, E debito e credito',
  `solo_agente_riscossione` TINYINT(1) NOT NULL DEFAULT 0,
  `formato_rateazione` VARCHAR(4) NOT NULL DEFAULT '0000' COMMENT '0000, 00MM, NNRR, FFFF (NNRR facoltativa), 00T0/00T1/00T2/00T3/00T4 (codice territoriale)',
  `anno_richiesto` TINYINT(1) NOT NULL DEFAULT 1,
  `anno_massimo` TINYINT NOT NULL DEFAULT 0 COMMENT 'Anni oltre quello di versamento ammessi per l''anno di riferimento',
  `codice_ufficio` CHAR(1) NOT NULL DEFAULT 'N' COMMENT 'O obbligatorio, F facoltativo, N non previsto',
  `codice_atto` CHAR(1) NOT NULL DEFAULT 'N',
  `identificativo_operazione` CHAR(1) NOT NULL DEFAULT 'N',
  `elementi_identificativi` CHAR(1) NULL COMMENT 'F24 ELIDE: 1 obbligatorio, 2 facoltativo, 3 non compilare, 4 ID telematico, 6 ID concessione demaniale',
  `tabelle_ente` VARCHAR(20) NULL COMMENT 'Sezione IMU: tabelle del codice ente (T1, T2, T4, T5, T6)',
  `ente_accise` CHAR(1) NULL COMMENT 'F24 Accise: codice ente (D accise, M monopoli, A assicurazioni, G Cassa Geometri, P INPGI, E ENPAPI, R ruoli)',
  `provincia_richiesta` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'F24 Accise: sigla della provincia',
  `mese_richiesto` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'F24 Accise: mese del periodo di riferimento',
  `codice_identificativo_ente` VARCHAR(150) NULL COMMENT 'F24 Accise: codice identificativo richiesto dall''ente (descrizione); NULL = non richiesto',
  `compensabile_crediti_pa` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Pagabile con i crediti certificati verso la PA (art. 28-quinquies DPR 602/1973, D.M. 14/1/2014)',
  `in_uso` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_codice_tributo` (`sezione`, `codice`),
  KEY `idx_codice_tributo_codice` (`codice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Codici tributo F24 con le regole di compilazione (tabelle AdE)';

CREATE TABLE IF NOT EXISTS `codice_tabella_f24` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tabella` VARCHAR(4) NOT NULL COMMENT 'T0 regioni, T1 enti locali, T2 province, T6 province diritti camerali, SINP sedi INPS, SINA sedi INAIL, SENP sedi ENPALS, ENTI altri enti, TELI tipi ELIDE, UFFI uffici, FINP formati matricola INPS',
  `codice` VARCHAR(10) NOT NULL,
  `descrizione` VARCHAR(255) NOT NULL,
  `dati` VARCHAR(255) NULL COMMENT 'Informazioni aggiuntive (es. espressione regolare del formato matricola INPS)',
  `in_uso` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_codice_tabella_f24` (`tabella`, `codice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tabelle di decodifica del modello F24 (AdE)';

CREATE TABLE IF NOT EXISTS `causale_contributo` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ente` VARCHAR(4) NOT NULL COMMENT 'INPS o codice dell''altro ente previdenziale (tabella ENTI)',
  `causale` VARCHAR(4) NOT NULL,
  `descrizione` VARCHAR(500) NOT NULL,
  `formati_matricola` VARCHAR(40) NULL COMMENT 'INPS: formati ammessi della matricola (tabella FINP), separati da virgola',
  `regola_posizione` VARCHAR(255) NULL,
  `periodo_da` CHAR(1) NOT NULL DEFAULT 'O' COMMENT 'O obbligatorio, F facoltativo, N non previsto',
  `periodo_a` CHAR(1) NOT NULL DEFAULT 'O',
  `regola_periodo_da` VARCHAR(255) NULL,
  `regola_periodo_a` VARCHAR(255) NULL,
  `modalita` CHAR(1) NOT NULL DEFAULT 'D',
  `compensabile_crediti_pa` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Pagabile con i crediti certificati verso la PA (D.M. 14/1/2014)',
  `in_uso` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_causale_contributo` (`ente`, `causale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Causali contributo INPS e degli altri enti previdenziali (AdE)';

CREATE TABLE IF NOT EXISTS `tasso_interesse_legale` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `valido_dal` DATE NOT NULL,
  `tasso` DECIMAL(6,3) NOT NULL COMMENT 'Percentuale annua',
  `provvedimento` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tasso_interesse_legale` (`valido_dal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Saggio degli interessi legali (art. 1284 c.c.), da Database/sorgenti';

CREATE TABLE IF NOT EXISTS `tasso_riferimento` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `valido_dal` DATE NOT NULL,
  `tasso` DECIMAL(6,3) NOT NULL COMMENT 'Tasso delle operazioni di rifinanziamento principali BCE, percentuale',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tasso_riferimento` (`valido_dal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tasso ufficiale di riferimento (BCE) per le sanzioni civili INPS e INAIL, dal servizio dati BCE';

CREATE TABLE IF NOT EXISTS `regola_ravvedimento` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sezione` CHAR(1) NOT NULL,
  `codice` CHAR(4) NOT NULL COMMENT 'Codice tributo regolarizzato',
  `codice_sanzione` CHAR(4) NULL COMMENT 'NULL se la sanzione si somma al tributo',
  `codice_interessi` CHAR(4) NULL COMMENT 'NULL se gli interessi si sommano al tributo',
  `dichiarazione` VARCHAR(8) NOT NULL COMMENT 'Dichiarazione per il termine della riduzione a 1/8',
  `fonte` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_regola_ravvedimento` (`sezione`, `codice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Codici di sanzione e interessi per il ravvedimento, da Database/sorgenti';

CREATE TABLE IF NOT EXISTS `rateazione_secondo_acconto` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `periodo_imposta` SMALLINT UNSIGNED NOT NULL,
  `prima_rata` DATE NOT NULL COMMENT 'Termine differito del versamento in unica soluzione o della prima rata',
  `numero_rate` TINYINT UNSIGNED NOT NULL COMMENT 'Numero massimo di rate mensili',
  `codici` VARCHAR(255) NOT NULL COMMENT 'Codici tributo del secondo acconto (sezione erario), separati da spazi',
  `norma` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rateazione_secondo_acconto` (`periodo_imposta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Norme straordinarie sul versamento differito o rateale del secondo acconto, da Database/sorgenti';

-- -----------------------------------------------------
-- Intermediari e invii telematici
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `intermediario` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL COMMENT 'Intermediario abilitato Entratel (fornitore del flusso)',
  `telefono` VARCHAR(12) NULL,
  `email` VARCHAR(55) NULL,
  `conto_addebito_id` INT UNSIGNED NULL COMMENT 'Conto dell''intermediario per gli invii con addebito unico',
  `predefinito` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_intermediario_studio` (`studio_id`),
  CONSTRAINT `fk_intermediario_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_intermediario_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_intermediario_conto` FOREIGN KEY (`conto_addebito_id`) REFERENCES `conto_corrente` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invio_telematico` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `intermediario_id` INT UNSIGNED NOT NULL,
  `data_creazione` DATETIME NOT NULL,
  `addebito_intermediario` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Flusso Y: addebito unico sul conto dell''intermediario',
  `numero_deleghe` SMALLINT UNSIGNED NOT NULL,
  `totale` DECIMAL(15,2) NOT NULL,
  `nome_file` VARCHAR(100) NOT NULL,
  `contenuto` MEDIUMTEXT NOT NULL COMMENT 'File generato (record A, M, V, Z)',
  PRIMARY KEY (`id`),
  KEY `idx_invio_telematico_studio` (`studio_id`, `data_creazione`),
  CONSTRAINT `fk_invio_telematico_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_invio_telematico_intermediario` FOREIGN KEY (`intermediario_id`) REFERENCES `intermediario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Deleghe
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `delega` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL COMMENT 'Contribuente',
  `modello` CHAR(1) NOT NULL DEFAULT 'O' COMMENT 'O ordinario, S semplificato, E elementi identificativi (ELIDE), A accise',
  `scadenza` DATE NOT NULL COMMENT 'Data di versamento',
  `descrizione` VARCHAR(255) NULL,
  `stato` CHAR(1) NOT NULL DEFAULT 'B' COMMENT 'B bozza, C confermata, T trasmessa, Q quietanzata',
  `codice_fiscale_coobbligato` VARCHAR(16) NULL COMMENT 'Secondo codice fiscale (coobbligato, erede, genitore, ...)',
  `codice_identificativo` CHAR(2) NULL COMMENT 'Codice identificativo del secondo codice fiscale',
  `conto_addebito_id` INT UNSIGNED NULL,
  `codice_ufficio` VARCHAR(3) NULL COMMENT 'Sezione Erario',
  `codice_atto` VARCHAR(11) NULL COMMENT 'Sezione Erario',
  `identificativo_operazione` VARCHAR(18) NULL COMMENT 'Sezione IMU e altri tributi locali',
  `detrazione_imu` DECIMAL(13,2) NULL COMMENT 'Detrazione abitazione principale (sezione IMU)',
  `invio_telematico_id` INT UNSIGNED NULL,
  `data_quietanza` DATE NULL,
  `protocollo` VARCHAR(50) NULL COMMENT 'Protocollo telematico o riferimento della quietanza',
  `note` TEXT NULL,
  `data_inserimento` DATETIME NOT NULL,
  `data_modifica` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_delega_studio_scadenza` (`studio_id`, `scadenza`),
  KEY `idx_delega_soggetto_scadenza` (`soggetto_id`, `scadenza`),
  CONSTRAINT `fk_delega_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_delega_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_delega_conto` FOREIGN KEY (`conto_addebito_id`) REFERENCES `conto_corrente` (`id`),
  CONSTRAINT `fk_delega_invio` FOREIGN KEY (`invio_telematico_id`) REFERENCES `invio_telematico` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crediti del contribuente da utilizzare in compensazione (es. credito IVA
-- annuale, eccedenze IRPEF/IRES, crediti INPS): i dati sono quelli del rigo
-- a credito del modello; gli utilizzi sono i versamenti con credito_id e il
-- residuo è l'importo meno la somma degli utilizzi.
CREATE TABLE IF NOT EXISTS `credito` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `sezione` CHAR(3) NOT NULL COMMENT 'ERA, INP, REG, LOC, INA, PRE',
  `codice_tributo_id` INT UNSIGNED NULL,
  `codice_ente` VARCHAR(5) NULL,
  `codice_sede` VARCHAR(5) NULL,
  `causale` VARCHAR(4) NULL,
  `posizione` VARCHAR(17) NULL,
  `cifra_controllo` CHAR(2) NULL,
  `numero_riferimento` VARCHAR(6) NULL,
  `rateazione` CHAR(4) NULL,
  `anno_riferimento` SMALLINT UNSIGNED NULL,
  `periodo_da` CHAR(7) NULL,
  `periodo_a` CHAR(7) NULL,
  `importo` DECIMAL(13,2) NOT NULL,
  `utilizzabile_dal` DATE NULL COMMENT 'Prima data di versamento in cui il credito si può compensare',
  `descrizione` VARCHAR(100) NULL,
  `origine` VARCHAR(30) NULL COMMENT 'Modulo che ha generato il credito',
  `riferimento_origine` VARCHAR(50) NULL,
  `note` TEXT NULL,
  `data_inserimento` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_credito_soggetto` (`soggetto_id`),
  KEY `idx_credito_origine` (`origine`, `riferimento_origine`),
  CONSTRAINT `fk_credito_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_credito_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_credito_codice_tributo` FOREIGN KEY (`codice_tributo_id`) REFERENCES `codice_tributo` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pagamento rateale del saldo e del primo acconto (art. 20, c. 1, D.Lgs.
-- 241/1997): i versamenti originari hanno piano_rateazione_id e non sono più
-- da versare; le rate (quote e interessi) sono versamenti con origine
-- "Rateazione" e riferimento "rateazione-<id>".
CREATE TABLE IF NOT EXISTS `piano_rateazione` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `scadenza` DATE NOT NULL COMMENT 'Scadenza dei versamenti originari (prima rata)',
  `numero_rate` TINYINT UNSIGNED NOT NULL,
  `date_rate` TEXT NOT NULL COMMENT 'JSON: date delle rate (Y-m-d)',
  `descrizione` VARCHAR(100) NULL,
  `data_inserimento` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_piano_rateazione_soggetto` (`soggetto_id`),
  CONSTRAINT `fk_piano_rateazione_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_piano_rateazione_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Archivio dei versamenti del contribuente: ogni riga è un rigo del modello
-- F24 con la sua data di versamento. Senza delega è "da versare"; la
-- generazione delle deleghe la colloca nella delega della sua scadenza.
-- Le colonne usate dipendono dalla sezione (Enumerations\SezioneF24::getCampi):
--   Erario         codice tributo, rateazione, anno
--   INPS           codice sede, causale, matricola (posizione), periodo
--   Regioni        codice regione (codice ente), codice tributo, rateazione, anno
--   Tributi locali codice comune/ente, ravvedimento, immobili variati,
--                  acconto, saldo, numero immobili, codice tributo, rateazione, anno
--   INAIL          codice sede, codice ditta (posizione), cifra di controllo,
--                  numero di riferimento, causale
--   Altri enti     codice ente, codice sede, causale, codice posizione, periodo
--   ELIDE          tipo, elementi identificativi, codice tributo, anno
--   Accise         provincia, codice tributo, codice identificativo, mese, anno
-- origine/riferimento_origine identificano i versamenti generati da un altro
-- modulo (es. IMU) o da un ravvedimento: vengono sostituiti a ogni ricalcolo
-- finché non sono in una delega confermata. credito_id collega l'utilizzo
-- in compensazione al credito dell'archivio.
CREATE TABLE IF NOT EXISTS `versamento` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `scadenza` DATE NOT NULL COMMENT 'Data di versamento',
  `delega_id` INT UNSIGNED NULL COMMENT 'NULL = da versare (non ancora in una delega)',
  `descrizione` VARCHAR(100) NULL,
  `sezione` CHAR(3) NOT NULL COMMENT 'ERA, INP, REG, LOC, INA, PRE, ELI, ACC',
  `ordine` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `codice_tributo_id` INT UNSIGNED NULL,
  `codice_ente` VARCHAR(5) NULL,
  `codice_sede` VARCHAR(5) NULL,
  `causale` VARCHAR(4) NULL,
  `posizione` VARCHAR(17) NULL COMMENT 'Matricola INPS, codice ditta INAIL, codice posizione',
  `cifra_controllo` CHAR(2) NULL COMMENT 'INAIL',
  `numero_riferimento` VARCHAR(6) NULL COMMENT 'INAIL',
  `rateazione` CHAR(4) NULL COMMENT 'Rateazione, regione/provincia o mese di riferimento',
  `anno_riferimento` SMALLINT UNSIGNED NULL,
  `periodo_da` CHAR(7) NULL COMMENT 'MM/AAAA',
  `periodo_a` CHAR(7) NULL COMMENT 'MM/AAAA',
  `ravvedimento` TINYINT(1) NOT NULL DEFAULT 0,
  `immobili_variati` TINYINT(1) NOT NULL DEFAULT 0,
  `acconto` TINYINT(1) NOT NULL DEFAULT 0,
  `saldo` TINYINT(1) NOT NULL DEFAULT 0,
  `numero_immobili` SMALLINT UNSIGNED NULL,
  `tipo_elide` CHAR(1) NULL COMMENT 'F24 ELIDE: tipo (tabella TELI)',
  `elementi_identificativi` VARCHAR(17) NULL COMMENT 'F24 ELIDE',
  `provincia` CHAR(2) NULL COMMENT 'F24 Accise',
  `codice_identificativo_ente` VARCHAR(14) NULL COMMENT 'F24 Accise: codice richiesto dall''ente',
  `mese_riferimento` TINYINT UNSIGNED NULL COMMENT 'F24 Accise',
  `numero_certificazione` VARCHAR(16) NULL COMMENT 'Erario: credito certificato verso la PA (codice PPAA)',
  `detrazione_imu` DECIMAL(13,2) NULL COMMENT 'Detrazione abitazione principale compresa nel rigo (riportata nella delega)',
  `importo_debito` DECIMAL(13,2) NOT NULL DEFAULT 0,
  `importo_credito` DECIMAL(13,2) NOT NULL DEFAULT 0,
  `credito_id` INT UNSIGNED NULL COMMENT 'Credito dell''archivio utilizzato in compensazione',
  `origine` VARCHAR(30) NULL COMMENT 'Modulo che ha generato la riga',
  `riferimento_origine` VARCHAR(50) NULL COMMENT 'Riferimento nel modulo di origine',
  `codice_fiscale_coobbligato` VARCHAR(16) NULL COMMENT 'Secondo codice fiscale richiesto nella delega (es. defunto per l''imposta di successione)',
  `codice_identificativo` CHAR(2) NULL COMMENT 'Codice identificativo del secondo codice fiscale richiesto',
  `piano_rateazione_id` INT UNSIGNED NULL COMMENT 'Versamento originario pagato a rate: non più da versare',
  PRIMARY KEY (`id`),
  KEY `idx_versamento_soggetto_scadenza` (`soggetto_id`, `scadenza`),
  KEY `idx_versamento_delega` (`delega_id`, `sezione`, `ordine`),
  KEY `idx_versamento_origine` (`origine`, `riferimento_origine`),
  CONSTRAINT `fk_versamento_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_versamento_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_versamento_delega` FOREIGN KEY (`delega_id`) REFERENCES `delega` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_versamento_codice_tributo` FOREIGN KEY (`codice_tributo_id`) REFERENCES `codice_tributo` (`id`),
  CONSTRAINT `fk_versamento_credito` FOREIGN KEY (`credito_id`) REFERENCES `credito` (`id`),
  CONSTRAINT `fk_versamento_piano_rateazione` FOREIGN KEY (`piano_rateazione_id`) REFERENCES `piano_rateazione` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Calcolo del ravvedimento operoso di un versamento omesso o tardivo: i
-- versamenti (tributo, sanzione, interessi) sono generati nella delega con
-- origine "Ravvedimento" e riferimento "ravvedimento-<id>". Se il versamento
-- omesso è nell'archivio, versamento_omesso_id lo collega: non risulta più
-- da versare.
CREATE TABLE IF NOT EXISTS `ravvedimento` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `delega_id` INT UNSIGNED NOT NULL,
  `versamento_omesso_id` INT UNSIGNED NULL,
  `sezione` CHAR(3) NOT NULL,
  `codice_tributo_id` INT UNSIGNED NOT NULL,
  `codice_ente` VARCHAR(5) NULL,
  `rateazione` CHAR(4) NULL,
  `anno_riferimento` SMALLINT UNSIGNED NULL,
  `acconto` TINYINT(1) NOT NULL DEFAULT 0,
  `saldo` TINYINT(1) NOT NULL DEFAULT 0,
  `numero_immobili` SMALLINT UNSIGNED NULL,
  `importo_tributo` DECIMAL(13,2) NOT NULL,
  `scadenza_originale` DATE NOT NULL,
  `termine_dichiarazione` DATE NOT NULL COMMENT 'Termine per la riduzione a 1/8',
  `termine_dichiarazione_successiva` DATE NULL COMMENT 'Solo violazioni fino al 31/08/2024: termine per la riduzione a 1/7',
  `codice_sanzione_id` INT UNSIGNED NULL COMMENT 'NULL = sanzione sommata al tributo',
  `codice_interessi_id` INT UNSIGNED NULL COMMENT 'NULL = interessi sommati al tributo',
  `circostanza` CHAR(1) NOT NULL DEFAULT 'N' COMMENT 'N nessun atto, S schema d''atto, V verbale di constatazione, D schema d''atto dopo il verbale',
  `giorni_ritardo` SMALLINT UNSIGNED NOT NULL,
  `percentuale_sanzione` DECIMAL(9,6) NOT NULL COMMENT 'Sanzione prima della riduzione da ravvedimento',
  `riduzione` TINYINT UNSIGNED NOT NULL COMMENT 'Denominatore della riduzione (10, 9, 8, 7, 6)',
  `sanzione` DECIMAL(13,2) NOT NULL,
  `interessi` DECIMAL(13,2) NOT NULL,
  `dettaglio_interessi` TEXT NOT NULL COMMENT 'JSON: periodi, giorni, tasso, importo',
  PRIMARY KEY (`id`),
  KEY `idx_ravvedimento_delega` (`delega_id`),
  CONSTRAINT `fk_ravvedimento_delega` FOREIGN KEY (`delega_id`) REFERENCES `delega` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ravvedimento_versamento_omesso` FOREIGN KEY (`versamento_omesso_id`) REFERENCES `versamento` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ravvedimento_codice_tributo` FOREIGN KEY (`codice_tributo_id`) REFERENCES `codice_tributo` (`id`),
  CONSTRAINT `fk_ravvedimento_codice_sanzione` FOREIGN KEY (`codice_sanzione_id`) REFERENCES `codice_tributo` (`id`),
  CONSTRAINT `fk_ravvedimento_codice_interessi` FOREIGN KEY (`codice_interessi_id`) REFERENCES `codice_tributo` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sanzioni civili per omesso o ritardato versamento di contributi INPS e
-- premi INAIL (art. 116, c. 8, L. 388/2000): calcolo con il tasso di
-- riferimento; con una causale di versamento genera il rigo nella delega
-- (origine "SanzioniCivili"), altrimenti resta un prospetto.
CREATE TABLE IF NOT EXISTS `sanzione_civile` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `delega_id` INT UNSIGNED NOT NULL,
  `versamento_tardivo_id` INT UNSIGNED NULL COMMENT 'Versamento scaduto dell''archivio pagato in ritardo con la delega',
  `sezione` CHAR(3) NOT NULL COMMENT 'INP o INA',
  `riferimento` VARCHAR(100) NOT NULL COMMENT 'Contributi a cui si riferisce (causale, periodo, ...)',
  `importo_contributi` DECIMAL(13,2) NOT NULL,
  `scadenza_originale` DATE NOT NULL,
  `regime` CHAR(1) NOT NULL COMMENT 'O omissione, T evasione denunciata (pagamento entro 30 giorni), N evasione denunciata (entro 90 giorni), E evasione',
  `spontaneo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Pagamento spontaneo in unica soluzione, prima di contestazioni',
  `causale` VARCHAR(4) NULL COMMENT 'Causale per versare la sanzione nella delega',
  `codice_sede` VARCHAR(5) NULL,
  `posizione` VARCHAR(17) NULL,
  `periodo_da` CHAR(7) NULL,
  `periodo_a` CHAR(7) NULL,
  `giorni_ritardo` SMALLINT UNSIGNED NOT NULL,
  `sanzione` DECIMAL(13,2) NOT NULL,
  `tetto_applicato` TINYINT(1) NOT NULL DEFAULT 0,
  `dettaglio` TEXT NOT NULL COMMENT 'JSON: periodi, giorni, tasso annuo, importo',
  PRIMARY KEY (`id`),
  KEY `idx_sanzione_civile_delega` (`delega_id`),
  CONSTRAINT `fk_sanzione_civile_delega` FOREIGN KEY (`delega_id`) REFERENCES `delega` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sanzione_civile_versamento_tardivo` FOREIGN KEY (`versamento_tardivo_id`) REFERENCES `versamento` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
