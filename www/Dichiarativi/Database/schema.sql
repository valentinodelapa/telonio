-- =====================================================
-- Dichiarativi - dichiarazioni fiscali (IVA annuale, Redditi PF)
-- =====================================================
-- Richiede le tabelle dei moduli Telonio (studio), Anagrafica (soggetto),
-- Deleghe (intermediario, archivio dei versamenti e dei crediti) e
-- Contabilita (registri e liquidazioni IVA da cui si propone la
-- dichiarazione IVA). I dati dei quadri sono conservati per codice del
-- campo del modello (es. VE020001), come nel record a struttura variabile
-- della fornitura telematica; il catalogo dei campi è per modello e anno
-- del modello ed è ricavato dalle specifiche tecniche dell'Agenzia delle
-- Entrate (dati-riferimento.sql, "sisma tabelle-dichiarativi").

-- -----------------------------------------------------
-- Catalogo dei campi non posizionali dei modelli
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `campo_modello` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `modello` VARCHAR(3) NOT NULL COMMENT 'IVA dichiarazione IVA annuale, RPF Redditi persone fisiche',
  `anno` SMALLINT UNSIGNED NOT NULL COMMENT 'Anno del modello (anno d''imposta + 1)',
  `codice` CHAR(8) NOT NULL COMMENT 'Quadro (2), rigo (3 cifre), colonna (3)',
  `quadro` CHAR(2) NOT NULL,
  `descrizione` VARCHAR(500) NOT NULL DEFAULT '',
  `formato` VARCHAR(4) NULL COMMENT 'Formato del campo-valore (AN, NU, NP, CB, ...); NULL se non indicato dalle specifiche',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_campo_modello` (`modello`, `anno`, `codice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Campi dei modelli di dichiarazione dalle specifiche tecniche';

CREATE TABLE IF NOT EXISTS `quadro_modello` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `modello` VARCHAR(3) NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL,
  `quadro` CHAR(2) NOT NULL,
  `denominazione` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_quadro_modello` (`modello`, `anno`, `quadro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Denominazioni dei quadri dei modelli di dichiarazione';

-- -----------------------------------------------------
-- Dichiarazioni dei contribuenti
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `dichiarazione` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `modello` VARCHAR(3) NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL COMMENT 'Anno d''imposta',
  `trimestre` TINYINT UNSIGNED NULL COMMENT 'Modelli trimestrali (IVA TR): trimestre da 1 a 3',
  `tipologia` CHAR(1) NOT NULL DEFAULT 'O' COMMENT 'O ordinaria, C correttiva nei termini, I integrativa',
  `stato` CHAR(1) NOT NULL DEFAULT 'B' COMMENT 'B bozza, P presentata',
  `natura_giuridica` TINYINT UNSIGNED NULL COMMENT 'Codice della tabella delle istruzioni (soggetti diversi dalle persone fisiche)',
  `dichiarante_id` INT UNSIGNED NULL COMMENT 'Persona fisica che sottoscrive, se diversa dal contribuente',
  `codice_carica` VARCHAR(2) NULL,
  `intermediario_id` INT UNSIGNED NULL COMMENT 'Intermediario che trasmette (Deleghe)',
  `impegno` CHAR(1) NULL COMMENT '1 predisposta dal contribuente, 2 da chi effettua l''invio',
  `data_impegno` DATE NULL,
  `flag_conferma` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Trasmissione confermata nonostante i controlli di rispondenza',
  `visto_professionista_id` INT UNSIGNED NULL COMMENT 'Professionista che appone il visto di conformità',
  `visto_caf_id` INT UNSIGNED NULL COMMENT 'CAF che appone il visto di conformità (in alternativa al professionista)',
  `visto_responsabile_caf_id` INT UNSIGNED NULL COMMENT 'Responsabile dell''assistenza fiscale del CAF',
  `esonero_visto` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Esonero dal visto di conformità (premialità ISA)',
  `verifica_accertamenti` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Verificati gli atti notificati nei due anni precedenti',
  `attestazione_patrimoniale` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Dichiarazione sostitutiva dell''art. 38-bis, c. 3 (rimborsi senza garanzia)',
  `adempimento_collaborativo` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Regime di adempimento collaborativo (D.Lgs. 128/2015)',
  `concordato_biennale` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Adesione al concordato preventivo biennale (D.Lgs. 13/2024) prima dell''anno',
  `modalita_saldo` CHAR(1) NOT NULL DEFAULT 'U' COMMENT 'U unica soluzione, R rate, D differito al termine dei redditi',
  `numero_rate` TINYINT UNSIGNED NULL,
  `data_differimento` DATE NULL COMMENT 'Data del versamento differito',
  `maggiorazione_ulteriore` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Versamento nei 30 giorni successivi al termine: ulteriore 0,40%',
  `data_presentazione` DATE NULL,
  `protocollo` VARCHAR(50) NULL,
  `data_elaborazione` DATETIME NULL COMMENT 'Ultima proposta dei quadri',
  `avvisi` TEXT NULL,
  `annotazioni` VARCHAR(500) NULL,
  `otto_per_mille` VARCHAR(2) NULL COMMENT 'Redditi: istituzione beneficiaria dell''otto per mille (NULL = scelta non effettuata)',
  `finalita_stato` TINYINT UNSIGNED NULL COMMENT 'Otto per mille allo Stato: finalità da 1 a 6',
  `cinque_per_mille` VARCHAR(2) NULL COMMENT 'Redditi: finalità del cinque per mille',
  `beneficiario_cinque_per_mille` VARCHAR(11) NULL COMMENT 'Codice fiscale del beneficiario del cinque per mille',
  `due_per_mille` VARCHAR(3) NULL COMMENT 'Redditi: codice del partito politico del due per mille',
  `data_inserimento` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dichiarazione` (`soggetto_id`, `modello`, `anno`, `trimestre`),
  KEY `idx_dichiarazione_studio` (`studio_id`, `anno`),
  CONSTRAINT `fk_dichiarazione_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_dichiarazione_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_dichiarazione_dichiarante` FOREIGN KEY (`dichiarante_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_dichiarazione_intermediario` FOREIGN KEY (`intermediario_id`) REFERENCES `intermediario` (`id`),
  CONSTRAINT `fk_dichiarazione_visto` FOREIGN KEY (`visto_professionista_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_dichiarazione_visto_caf` FOREIGN KEY (`visto_caf_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_dichiarazione_visto_responsabile` FOREIGN KEY (`visto_responsabile_caf_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Dichiarazioni per contribuente, modello e anno d''imposta';

-- Valore di un campo dei quadri, in forma canonica: interi senza
-- separatori, decimali con il punto, date AAAA-MM-GG, caselle "1".
CREATE TABLE IF NOT EXISTS `valore_dichiarazione` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dichiarazione_id` INT UNSIGNED NOT NULL,
  `modulo` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `codice` CHAR(8) NOT NULL,
  `valore` VARCHAR(255) NOT NULL,
  `manuale` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Inserito o corretto a mano: la proposta non lo sovrascrive',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_valore_dichiarazione` (`dichiarazione_id`, `modulo`, `codice`),
  CONSTRAINT `fk_valore_dichiarazione` FOREIGN KEY (`dichiarazione_id`) REFERENCES `dichiarazione` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Dati dei quadri delle dichiarazioni, per campo del modello';

-- Moduli oltre al primo: attività con contabilità separata, soggetti
-- partecipanti a operazioni straordinarie, prima frazione d'anno delle
-- procedure concorsuali, con il soggetto della suite da cui proporli.
CREATE TABLE IF NOT EXISTS `modulo_dichiarazione` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dichiarazione_id` INT UNSIGNED NOT NULL,
  `numero` SMALLINT UNSIGNED NOT NULL,
  `tipo` CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'A attività separata, T soggetto trasformato, F prima frazione d''anno, G società del gruppo IVA (modello IVA TR)',
  `soggetto_fonte_id` INT UNSIGNED NULL COMMENT 'Soggetto dalla cui contabilità si propone il modulo',
  `descrizione` VARCHAR(255) NULL,
  `data_operazione` DATE NULL COMMENT 'Data di efficacia dell''operazione straordinaria: si propongono le operazioni precedenti',
  `attivita_iva_id` INT UNSIGNED NULL COMMENT 'Attività con contabilità separata (Contabilita) di cui il modulo riepiloga le operazioni',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_modulo_dichiarazione` (`dichiarazione_id`, `numero`),
  CONSTRAINT `fk_modulo_dichiarazione` FOREIGN KEY (`dichiarazione_id`) REFERENCES `dichiarazione` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_modulo_dichiarazione_fonte` FOREIGN KEY (`soggetto_fonte_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_modulo_dichiarazione_attivita` FOREIGN KEY (`attivita_iva_id`) REFERENCES `attivita_iva` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Moduli della dichiarazione oltre al primo';

-- Sottoscrizione dell'organo di controllo contabile, in alternativa al
-- visto di conformità (fino a cinque componenti).
CREATE TABLE IF NOT EXISTS `sottoscrizione_controllo` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dichiarazione_id` INT UNSIGNED NOT NULL,
  `ordine` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `tipo_soggetto` TINYINT UNSIGNED NOT NULL COMMENT '1 revisore, 2 responsabile della revisione, 3 società di revisione, 4 sindaco',
  `soggetto_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sottoscrizione_controllo` (`dichiarazione_id`, `ordine`),
  CONSTRAINT `fk_sottoscrizione_dichiarazione` FOREIGN KEY (`dichiarazione_id`) REFERENCES `dichiarazione` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sottoscrizione_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Sottoscrizioni dell''organo di controllo delle dichiarazioni';

CREATE TABLE IF NOT EXISTS `atto_accertamento` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno_imposta` SMALLINT UNSIGNED NOT NULL,
  `data_notifica` DATE NOT NULL,
  `estremi` VARCHAR(60) NULL COMMENT 'Numero dell''atto',
  `importo_dichiarato` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Imposta dovuta o credito dichiarato',
  `importo_accertato` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_atto_accertamento_soggetto` (`soggetto_id`, `data_notifica`),
  CONSTRAINT `fk_atto_accertamento_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_atto_accertamento_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Avvisi di accertamento o rettifica per i rischi dei rimborsi IVA (art. 38-bis, c. 4, lett. b)';

CREATE TABLE IF NOT EXISTS `rettifica_detrazione` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dichiarazione_id` INT UNSIGNED NOT NULL,
  `modulo` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `rigo` TINYINT UNSIGNED NOT NULL COMMENT 'Rigo del prospetto D, da 1 a 5',
  `importo` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Più: aumento della detrazione; meno: diminuzione',
  `nota` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rettifica_detrazione` (`dichiarazione_id`, `modulo`, `rigo`),
  CONSTRAINT `fk_rettifica_detrazione_dichiarazione` FOREIGN KEY (`dichiarazione_id`) REFERENCES `dichiarazione` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Prospetto D delle rettifiche della detrazione (rigo VF70)';

CREATE TABLE IF NOT EXISTS `garanzia_rimborso` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno_imposta` SMALLINT UNSIGNED NOT NULL,
  `tipo` CHAR(1) NOT NULL COMMENT 'T titoli di Stato, B banca, I impresa commerciale, A assicurazione, C consorzio fidi, G capogruppo',
  `garante` VARCHAR(120) NULL,
  `estremi` VARCHAR(60) NULL,
  `importo` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `data_rilascio` DATE NOT NULL,
  `compensazione_gruppo` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Eccedenze compensate nella liquidazione di gruppo (art. 6 D.M. 13/12/1979)',
  `data_rimborso` DATE NULL COMMENT 'Esecuzione del rimborso o presentazione della dichiarazione per le compensazioni di gruppo: da qui decorre la durata',
  `data_richiesta_documenti` DATE NULL COMMENT 'Notifica della richiesta di documenti per il rimborso (art. 57, c. 3)',
  `data_consegna_documenti` DATE NULL COMMENT 'Consegna dei documenti: oltre i quindici giorni differisce la decadenza',
  `data_scadenza` DATE NULL,
  `data_svincolo` DATE NULL,
  PRIMARY KEY (`id`),
  KEY `idx_garanzia_rimborso_soggetto` (`soggetto_id`, `data_rilascio`),
  KEY `idx_garanzia_rimborso_studio` (`studio_id`, `data_scadenza`),
  CONSTRAINT `fk_garanzia_rimborso_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_garanzia_rimborso_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Garanzie dei rimborsi IVA (art. 38-bis, c. 5)';

CREATE TABLE IF NOT EXISTS `certificazione_unica` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno_imposta` SMALLINT UNSIGNED NOT NULL,
  `codice_fiscale_sostituto` VARCHAR(16) NOT NULL,
  `denominazione_sostituto` VARCHAR(100) NOT NULL,
  `conguagliata` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Compresa nel conguaglio di un altro sostituto: non si dichiara',
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_certificazione_unica_soggetto` (`soggetto_id`, `anno_imposta`),
  CONSTRAINT `fk_certificazione_unica_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_certificazione_unica_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Certificazioni Uniche (lavoro dipendente, pensione e assimilati) dei contribuenti';

CREATE TABLE IF NOT EXISTS `valore_certificazione_unica` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `certificazione_unica_id` INT UNSIGNED NOT NULL,
  `punto` SMALLINT UNSIGNED NOT NULL,
  `valore` DECIMAL(14,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_valore_certificazione_unica` (`certificazione_unica_id`, `punto`),
  CONSTRAINT `fk_valore_certificazione_unica` FOREIGN KEY (`certificazione_unica_id`) REFERENCES `certificazione_unica` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Punti della Certificazione Unica (importi, giorni, codici)';

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

-- Aliquote delle addizionali regionale e comunale all'IRPEF per anno
-- d'imposta, dalle tabelle ufficiali (sorgenti del modulo, importate con
-- "sisma tabelle-dichiarativi").

CREATE TABLE IF NOT EXISTS `addizionale_regionale` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `anno` SMALLINT UNSIGNED NOT NULL COMMENT 'Anno d''imposta',
  `codice` CHAR(2) NOT NULL COMMENT 'Codice regione o provincia autonoma (rigo RV4)',
  `regione` VARCHAR(40) NOT NULL,
  `scaglioni` VARCHAR(120) NOT NULL COMMENT 'limite:aliquota separati da |, ultimo senza limite',
  `modalita` VARCHAR(12) NOT NULL COMMENT 'progressiva per scaglioni, intero: aliquota dello scaglione sull''intero reddito',
  `esenzione_reddito` DECIMAL(12,2) NULL COMMENT 'Esenzione con reddito complessivo fino alla soglia',
  `soglia_aliquota` VARCHAR(30) NULL COMMENT 'soglia:aliquota sull''intero reddito fino alla soglia',
  `detrazioni` VARCHAR(120) NULL COMMENT 'importo:oltre:fino separati da |',
  `detrazione_crescente` VARCHAR(40) NULL COMMENT 'da:ampiezza:massimo',
  `deduzione` VARCHAR(40) NULL COMMENT 'importo:fino',
  `casi_particolari` TEXT NULL COMMENT 'Agevolazioni non calcolate (figli, disabilità)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_addizionale_regionale` (`anno`, `codice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Aliquote dell''addizionale regionale all''IRPEF (tabella delle istruzioni del modello Redditi PF)';

CREATE TABLE IF NOT EXISTS `addizionale_comunale` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `anno` SMALLINT UNSIGNED NOT NULL COMMENT 'Anno d''imposta (saldo; vale anche per l''acconto dell''anno dopo)',
  `codice_catastale` CHAR(4) NOT NULL,
  `aliquota_unica` DECIMAL(5,3) NULL,
  `aliquote` VARCHAR(40) NULL COMMENT 'Aliquote per scaglione separate da |',
  `tipologia` TINYINT UNSIGNED NULL COMMENT '1 quattro scaglioni (15.000, 28.000, 50.000), 2 tre scaglioni (28.000, 50.000)',
  `esenzione` DECIMAL(12,2) NULL COMMENT 'Esenzione con imponibile fino alla soglia',
  `tipizzazione` VARCHAR(4) NULL COMMENT 'Codice dei casi particolari (legenda della tabella)',
  `esenzione_casi` DECIMAL(12,2) NULL COMMENT 'Soglia dei casi particolari',
  `casi_particolari` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_addizionale_comunale` (`anno`, `codice_catastale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Aliquote dell''addizionale comunale all''IRPEF (elenco pubblicato dall''Agenzia delle Entrate)';
