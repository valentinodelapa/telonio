-- =====================================================
-- Imu - imposta municipale propria (art. 1, commi 738-783, L. 160/2019)
-- =====================================================
-- Richiede le tabelle dei moduli Telonio (studio), Anagrafica (soggetto,
-- immobile) e Deleghe (archivio dei versamenti). Gli immobili, i dati
-- catastali e i possessi sono quelli dell'Anagrafica: il modulo aggiunge
-- solo i dati propri dell'imposta. Le aliquote sono percentuali (0,86 =
-- 0,86%), gli importi in euro con due decimali.

-- -----------------------------------------------------
-- Prospetti delle aliquote
-- Righe senza studio: prospetti pubblicati dal Dipartimento delle Finanze
-- (comuni a tutti gli studi, importati con "sisma aliquote-imu"). Righe con
-- studio: aliquote inserite dallo studio (anni senza prospetto, correzioni),
-- che prevalgono su quelle pubblicate.
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `prospetto_aliquote` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NULL COMMENT 'NULL = prospetto pubblicato dal Dipartimento delle Finanze',
  `codice_catastale` CHAR(4) NOT NULL COMMENT 'Codice catastale del comune',
  `denominazione_comune` VARCHAR(100) NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL,
  `ex_comune` VARCHAR(100) NULL COMMENT 'Comune nato da fusione con aliquote differenziate: ex comune a cui si riferisce',
  `id_prospetto` INT UNSIGNED NULL COMMENT 'ID del prospetto nel portale del federalismo fiscale',
  `numero_delibera` VARCHAR(20) NULL,
  `data_delibera` DATE NULL,
  `data_pubblicazione` DATE NULL,
  `file_prospetto` VARCHAR(100) NULL COMMENT 'Nome del PDF pubblicato',
  `url_prospetto` VARCHAR(255) NULL,
  `aliquota_abitazione_principale` DECIMAL(6,4) NULL COMMENT 'A/1, A/8, A/9 e pertinenze',
  `aliquota_rurali_strumentali` DECIMAL(6,4) NULL,
  `aliquota_gruppo_d` DECIMAL(6,4) NULL COMMENT 'Escluso D/10; 0,76 riservato allo Stato',
  `aliquota_terreni_agricoli` DECIMAL(6,4) NULL,
  `aliquota_aree_fabbricabili` DECIMAL(6,4) NULL,
  `aliquota_altri_fabbricati` DECIMAL(6,4) NULL,
  `aliquota_beni_merce` DECIMAL(6,4) NULL COMMENT 'Solo 2020-2021: dal 2022 esenti',
  `assimilazione_anziani` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Assimilazione all''abitazione principale (comma 741, lett. c, n. 6)',
  `detrazione_abitazione_principale` DECIMAL(10,2) NOT NULL DEFAULT 200.00,
  `importo_minimo` DECIMAL(10,2) NOT NULL DEFAULT 12.00 COMMENT 'Versamento annuo minimo (regolamento comunale, in mancanza 12 euro)',
  `esenzioni` TEXT NULL COMMENT 'Esenzioni e agevolazioni indicate dal comune (testo del prospetto)',
  `note` TEXT NULL,
  `data_importazione` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_prospetto_aliquote_comune` (`codice_catastale`, `anno`),
  KEY `idx_prospetto_aliquote_studio` (`studio_id`),
  CONSTRAINT `fk_prospetto_aliquote_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Aliquote IMU per comune e anno';

CREATE TABLE IF NOT EXISTS `aliquota_differenziata` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `prospetto_aliquote_id` INT UNSIGNED NOT NULL,
  `fattispecie` CHAR(2) NULL COMMENT 'Fattispecie di base a cui si riferisce (NULL = non indicata)',
  `descrizione` TEXT NOT NULL COMMENT 'Condizioni come riportate nel prospetto',
  `aliquota` DECIMAL(6,4) NOT NULL,
  `ordine` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_aliquota_differenziata_prospetto` (`prospetto_aliquote_id`, `ordine`),
  CONSTRAINT `fk_aliquota_differenziata_prospetto` FOREIGN KEY (`prospetto_aliquote_id`) REFERENCES `prospetto_aliquote` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Aliquote differenziate del prospetto (fattispecie con condizioni)';

-- -----------------------------------------------------
-- Dati IMU degli immobili dell'Anagrafica
-- -----------------------------------------------------

-- Caratteristiche dell'immobile rilevanti per l'IMU, valide per tutti i
-- possessori; storicizzate (es. inagibilità dal giorno della perizia,
-- valore venale dell'area fabbricabile al 1° gennaio di ogni anno).
CREATE TABLE IF NOT EXISTS `caratteristiche_imu` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `immobile_id` INT UNSIGNED NOT NULL,
  `valido_dal` DATE NOT NULL,
  `valido_al` DATE NULL,
  `storico_artistico` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Riduzione del 50% della base (comma 747, lett. a)',
  `inagibile` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Riduzione del 50% della base (comma 747, lett. b)',
  `rurale_strumentale` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Annotazione di ruralità (D/10 lo è per categoria)',
  `bene_merce` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Fabbricato costruito e destinato alla vendita dall''impresa costruttrice',
  `area_fabbricabile` TINYINT(1) NOT NULL DEFAULT 0,
  `valore_venale` DECIMAL(14,2) NULL COMMENT 'Area fabbricabile: valore venale in comune commercio al 1° gennaio',
  `valore_contabile` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Fabbricato del gruppo D senza rendita, posseduto da impresa: valore dalle scritture contabili (comma 746)',
  `ex_comune` VARCHAR(100) NULL COMMENT 'Ex comune di ubicazione nei comuni nati da fusione con aliquote differenziate',
  `esenzione` CHAR(2) NULL COMMENT 'Esenzione oggettiva (Enumerations\\EsenzioneImu)',
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_caratteristiche_imu_decorrenza` (`immobile_id`, `valido_dal`),
  CONSTRAINT `fk_caratteristiche_imu_immobile` FOREIGN KEY (`immobile_id`) REFERENCES `immobile` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Caratteristiche IMU dell''immobile, storicizzate';

-- Agevolazioni che dipendono dal possessore, storicizzate.
CREATE TABLE IF NOT EXISTS `agevolazione_imu` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `immobile_id` INT UNSIGNED NOT NULL,
  `valido_dal` DATE NOT NULL,
  `valido_al` DATE NULL,
  `comodato_parenti` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Comodato a parenti in linea retta di primo grado con i requisiti (comma 747, lett. c)',
  `conduzione_agricola` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Posseduto e condotto da coltivatore diretto o IAP (commi 741 lett. d, 758 lett. a)',
  `assimilazione` CHAR(2) NULL COMMENT 'Assimilazione all''abitazione principale (comma 741, lett. c)',
  `pensionato_estero` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Pensionato residente all''estero in regime di convenzione (L. 178/2020, c. 48)',
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_agevolazione_imu_decorrenza` (`soggetto_id`, `immobile_id`, `valido_dal`),
  KEY `idx_agevolazione_imu_immobile` (`immobile_id`),
  CONSTRAINT `fk_agevolazione_imu_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_agevolazione_imu_immobile` FOREIGN KEY (`immobile_id`) REFERENCES `immobile` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Agevolazioni IMU del possessore, storicizzate';

-- Aliquota scelta per l'immobile del contribuente nell'anno: una aliquota
-- differenziata del prospetto o un'aliquota indicata dallo studio.
CREATE TABLE IF NOT EXISTS `aliquota_applicata` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `immobile_id` INT UNSIGNED NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL,
  `aliquota_differenziata_id` INT UNSIGNED NULL,
  `aliquota` DECIMAL(6,4) NULL COMMENT 'Aliquota indicata dallo studio (se non è una differenziata del prospetto)',
  `motivo` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aliquota_applicata` (`soggetto_id`, `immobile_id`, `anno`),
  KEY `idx_aliquota_applicata_immobile` (`immobile_id`),
  CONSTRAINT `fk_aliquota_applicata_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_aliquota_applicata_immobile` FOREIGN KEY (`immobile_id`) REFERENCES `immobile` (`id`),
  CONSTRAINT `fk_aliquota_applicata_differenziata` FOREIGN KEY (`aliquota_differenziata_id`) REFERENCES `aliquota_differenziata` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Aliquota applicata all''immobile del contribuente per anno';

-- -----------------------------------------------------
-- Calcolo annuale per contribuente
-- -----------------------------------------------------

-- Ultima elaborazione dell'anno: i righi sono il prospetto di calcolo, i
-- versamenti sono registrati nell'archivio di Deleghe con origine "Imu" e
-- riferimento "imu-<anno>".
CREATE TABLE IF NOT EXISTS `calcolo_imu` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL,
  `unica_soluzione` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Versamento in unica soluzione entro il 16 giugno',
  `imposta_annua` DECIMAL(13,2) NOT NULL DEFAULT 0,
  `acconto` DECIMAL(13,2) NOT NULL DEFAULT 0 COMMENT 'Versamenti di giugno',
  `saldo` DECIMAL(13,2) NOT NULL DEFAULT 0 COMMENT 'Versamenti di dicembre',
  `avvisi` TEXT NULL COMMENT 'Uno per riga',
  `differenze` TEXT NULL COMMENT 'Differenze con i versamenti già in deleghe non modificabili, una per riga',
  `data_elaborazione` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_calcolo_imu` (`soggetto_id`, `anno`),
  CONSTRAINT `fk_calcolo_imu_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_calcolo_imu_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Calcolo IMU del contribuente per anno';

-- Rigo del prospetto: un immobile del contribuente in un periodo
-- omogeneo dell'anno (stessi possesso, classamento e caratteristiche).
CREATE TABLE IF NOT EXISTS `rigo_calcolo_imu` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `calcolo_imu_id` INT UNSIGNED NOT NULL,
  `immobile_id` INT UNSIGNED NOT NULL,
  `dal` DATE NOT NULL,
  `al` DATE NOT NULL,
  `mesi` TINYINT UNSIGNED NOT NULL,
  `mesi_acconto` TINYINT UNSIGNED NOT NULL COMMENT 'Mesi del primo semestre',
  `titolo` VARCHAR(3) NOT NULL,
  `quota` DECIMAL(7,4) NOT NULL,
  `utilizzo` VARCHAR(2) NULL,
  `categoria` VARCHAR(4) NULL,
  `fattispecie` CHAR(2) NULL COMMENT 'NULL = non imponibile o esente',
  `esenzione` VARCHAR(150) NULL COMMENT 'Motivo dell''esenzione o della non imponibilità',
  `codice_comune` CHAR(4) NOT NULL,
  `base_imponibile` DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'Intera, prima di quota e riduzioni',
  `riduzione_base` DECIMAL(5,4) NOT NULL DEFAULT 1 COMMENT 'Coefficiente: 0,5 per ciascuna riduzione del comma 747',
  `riduzione_imposta` DECIMAL(5,4) NOT NULL DEFAULT 1 COMMENT 'Coefficiente: 0,75 canone concordato, 0,5 pensionato estero',
  `aliquota` DECIMAL(6,4) NULL,
  `aliquota_acconto` DECIMAL(6,4) NULL COMMENT 'Aliquota dell''anno precedente',
  `origine_aliquota` VARCHAR(150) NULL,
  `imposta_annua` DECIMAL(13,2) NOT NULL DEFAULT 0 COMMENT 'Al lordo della detrazione',
  `imposta_acconto` DECIMAL(13,2) NOT NULL DEFAULT 0,
  `quota_stato_annua` DECIMAL(13,2) NOT NULL DEFAULT 0 COMMENT 'Gruppo D: parte riservata allo Stato',
  `quota_stato_acconto` DECIMAL(13,2) NOT NULL DEFAULT 0,
  `detrazione_annua` DECIMAL(13,2) NOT NULL DEFAULT 0,
  `detrazione_acconto` DECIMAL(13,2) NOT NULL DEFAULT 0,
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rigo_calcolo_imu_calcolo` (`calcolo_imu_id`),
  CONSTRAINT `fk_rigo_calcolo_imu_calcolo` FOREIGN KEY (`calcolo_imu_id`) REFERENCES `calcolo_imu` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rigo_calcolo_imu_immobile` FOREIGN KEY (`immobile_id`) REFERENCES `immobile` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Prospetto di calcolo IMU per immobile e periodo';

-- -----------------------------------------------------
-- Tabelle di riferimento (comuni a tutti gli studi)
-- Popolate con il comando "sisma tabelle-imu" (Database/sorgenti e
-- circolare n. 9/1993 dalla Gazzetta Ufficiale).
-- -----------------------------------------------------

-- Coefficienti di aggiornamento dei costi dei fabbricati del gruppo D a
-- valore contabile (comma 746, decreti annuali del MEF): il coefficiente
-- dell'anno di costo più vecchio vale anche per i costi precedenti.
CREATE TABLE IF NOT EXISTS `coefficiente_fabbricati_d` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `anno_imposta` SMALLINT UNSIGNED NOT NULL,
  `anno_costo` SMALLINT UNSIGNED NOT NULL,
  `coefficiente` DECIMAL(5,2) NOT NULL,
  `fonte` VARCHAR(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coefficiente_fabbricati_d` (`anno_imposta`, `anno_costo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Coefficienti dei fabbricati del gruppo D a valore contabile';

-- Comuni nei quali i terreni agricoli sono esenti (comma 758): aree
-- montane o di collina della circolare n. 9/1993 (lett. d) e isole minori
-- dell'allegato A alla L. 448/2001 (lett. b); parziale = esenzione solo per
-- una parte del territorio.
CREATE TABLE IF NOT EXISTS `comune_esenzione_terreni` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codice_catastale` CHAR(4) NOT NULL,
  `motivo` CHAR(1) NOT NULL COMMENT 'M = montano o collinare (circ. 9/1993), I = isola minore',
  `parziale` TINYINT(1) NOT NULL DEFAULT 0,
  `denominazione` VARCHAR(100) NOT NULL COMMENT 'Come indicata nella fonte',
  `provincia` VARCHAR(50) NULL COMMENT 'Come indicata nella fonte',
  `fonte` VARCHAR(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_comune_esenzione_terreni` (`codice_catastale`, `motivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Comuni con terreni agricoli esenti da IMU';

-- -----------------------------------------------------
-- Altri dati IMU degli immobili e dei contribuenti
-- -----------------------------------------------------

-- Costi di acquisizione e incrementativi di un fabbricato a valore
-- contabile, per anno di sostenimento.
CREATE TABLE IF NOT EXISTS `costo_contabile_imu` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `immobile_id` INT UNSIGNED NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL,
  `importo` DECIMAL(14,2) NOT NULL,
  `descrizione` VARCHAR(150) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_costo_contabile_imu_immobile` (`immobile_id`, `anno`),
  CONSTRAINT `fk_costo_contabile_imu_immobile` FOREIGN KEY (`immobile_id`) REFERENCES `immobile` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Costi contabili dei fabbricati del gruppo D senza rendita';

-- Acconto versato senza la procedura (es. dal contribuente) o, per il 2020,
-- pari alla metà di IMU e TASI 2019: il saldo si calcola su questo importo.
CREATE TABLE IF NOT EXISTS `acconto_versato_imu` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL,
  `codice_comune` CHAR(4) NOT NULL,
  `codice_tributo` CHAR(4) NOT NULL,
  `importo` DECIMAL(13,2) NOT NULL,
  `data_versamento` DATE NULL,
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_acconto_versato_imu` (`soggetto_id`, `anno`, `codice_comune`, `codice_tributo`),
  CONSTRAINT `fk_acconto_versato_imu_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Acconti IMU versati fuori dalla procedura';

-- Immobile di un ente non commerciale utilizzato con modalità non
-- commerciali (quadro B della dichiarazione IMU ENC, D.M. 19 novembre 2012,
-- n. 200): attività svolte e rapporti per la percentuale di imponibilità
-- in caso di utilizzo misto. Corrispettivi in euro, rapporti in percentuale.
CREATE TABLE IF NOT EXISTS `utilizzo_misto_enc` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `immobile_id` INT UNSIGNED NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL,
  `attivita` VARCHAR(40) NOT NULL COMMENT 'Codici delle attività svolte separati da virgola (AttivitaEnc)',
  `esente` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Interamente utilizzato con modalità non commerciali',
  `tipo_concessione` CHAR(1) NULL COMMENT '1 comodato, 2 immobile strutturalmente funzionale',
  `comodatario` VARCHAR(100) NULL,
  `didattica_corrispettivo_medio` DECIMAL(9,2) NULL COMMENT 'Cm',
  `didattica_costo_medio_studente` DECIMAL(9,2) NULL COMMENT 'Cms pubblicato dal Ministero',
  `didattica_superficie` DECIMAL(5,2) NULL,
  `didattica_superficie_giorni` DECIMAL(5,2) NULL,
  `didattica_soggetti` DECIMAL(5,2) NULL,
  `didattica_soggetti_giorni` DECIMAL(5,2) NULL,
  `didattica_giorni` DECIMAL(5,2) NULL,
  `altre_corrispettivo_medio` DECIMAL(9,2) NULL COMMENT 'Cenc',
  `altre_corrispettivo_mercato` DECIMAL(9,2) NULL COMMENT 'Cm delle analoghe attività svolte con modalità commerciali',
  `altre_superficie` DECIMAL(5,2) NULL,
  `altre_superficie_giorni` DECIMAL(5,2) NULL,
  `altre_soggetti` DECIMAL(5,2) NULL,
  `altre_soggetti_giorni` DECIMAL(5,2) NULL,
  `altre_giorni` DECIMAL(5,2) NULL,
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_utilizzo_misto_enc` (`soggetto_id`, `immobile_id`, `anno`),
  KEY `idx_utilizzo_misto_enc_immobile` (`immobile_id`),
  CONSTRAINT `fk_utilizzo_misto_enc_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_utilizzo_misto_enc_immobile` FOREIGN KEY (`immobile_id`) REFERENCES `immobile` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Immobili degli enti non commerciali con attività non commerciali';

-- -----------------------------------------------------
-- Dichiarazione IMU (comma 768-bis): una per contribuente, anno e comune
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `dichiarazione_imu` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL,
  `codice_comune` CHAR(4) NOT NULL,
  `denominazione_comune` VARCHAR(100) NOT NULL,
  `modello` CHAR(2) NOT NULL COMMENT 'EC persone fisiche ed enti commerciali, NC enti non commerciali',
  `tipologia` CHAR(1) NOT NULL DEFAULT 'N' COMMENT 'N nuova, S sostitutiva',
  `stato` CHAR(1) NOT NULL DEFAULT 'B' COMMENT 'B bozza, P presentata',
  `dichiarante_id` INT UNSIGNED NULL COMMENT 'Rappresentante che firma, se diverso dal contribuente',
  `codice_carica` VARCHAR(2) NULL,
  `intermediario_id` INT UNSIGNED NULL COMMENT 'Intermediario che trasmette (Deleghe)',
  `data_impegno` DATE NULL,
  `data_dichiarazione` DATE NULL,
  `data_presentazione` DATE NULL,
  `protocollo` VARCHAR(50) NULL,
  `annotazioni` VARCHAR(500) NULL,
  `imposta_dovuta` DECIMAL(13,2) NULL COMMENT 'ENC quadro C',
  `eccedenza_precedente` DECIMAL(13,2) NULL,
  `eccedenza_compensata` DECIMAL(13,2) NULL,
  `rate_versate` DECIMAL(13,2) NULL,
  `credito_rimborso` DECIMAL(13,2) NULL COMMENT 'ENC quadro D',
  `credito_compensazione` DECIMAL(13,2) NULL,
  `data_inserimento` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dichiarazione_imu_soggetto` (`soggetto_id`, `anno`),
  CONSTRAINT `fk_dichiarazione_imu_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_dichiarazione_imu_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_dichiarazione_imu_dichiarante` FOREIGN KEY (`dichiarante_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_dichiarazione_imu_intermediario` FOREIGN KEY (`intermediario_id`) REFERENCES `intermediario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Dichiarazioni IMU per contribuente, anno e comune';

-- Quadro descrittivo di un immobile: una vicenda (acquisto, cessione,
-- inizio o termine di un'agevolazione, ...) con i dati alla sua data.
CREATE TABLE IF NOT EXISTS `rigo_dichiarazione_imu` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dichiarazione_imu_id` INT UNSIGNED NOT NULL,
  `immobile_id` INT UNSIGNED NOT NULL,
  `quadro` CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'ENC: A immobili imponibili, B esenti o parzialmente imponibili',
  `numero_ordine` SMALLINT UNSIGNED NOT NULL,
  `progressivo_immobile` SMALLINT UNSIGNED NOT NULL,
  `caratteristica` CHAR(1) NOT NULL,
  `valore` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `percentuale_possesso` DECIMAL(5,2) NOT NULL,
  `detrazione` DECIMAL(9,2) NULL,
  `equiparazione` CHAR(1) NULL COMMENT '1 alloggio sociale, 2 alloggio di servizio',
  `riduzione` CHAR(1) NOT NULL DEFAULT '0',
  `esenzione` CHAR(1) NOT NULL DEFAULT '0',
  `acquisto` TINYINT(1) NOT NULL DEFAULT 0,
  `cessione` TINYINT(1) NOT NULL DEFAULT 0,
  `altro` TINYINT(1) NOT NULL DEFAULT 0,
  `altro_descrizione` VARCHAR(100) NULL,
  `data_variazione` DATE NULL,
  `inizio_termine` CHAR(1) NULL COMMENT 'I inizio, T termine dell''agevolazione',
  `agenzia_entrate` VARCHAR(24) NULL,
  `estremi_titolo` VARCHAR(24) NULL,
  `non_utilizzabile_tipo` CHAR(1) NULL,
  `non_utilizzabile_autorita` VARCHAR(100) NULL,
  `non_utilizzabile_data` DATE NULL,
  `motivo` VARCHAR(255) NULL COMMENT 'Perché il rigo è dichiarato (proposta automatica)',
  PRIMARY KEY (`id`),
  KEY `idx_rigo_dichiarazione_imu` (`dichiarazione_imu_id`, `quadro`, `numero_ordine`),
  CONSTRAINT `fk_rigo_dichiarazione_imu_dichiarazione` FOREIGN KEY (`dichiarazione_imu_id`) REFERENCES `dichiarazione_imu` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rigo_dichiarazione_imu_immobile` FOREIGN KEY (`immobile_id`) REFERENCES `immobile` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Immobili delle dichiarazioni IMU';
