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
  `data_rimborso` DATE NULL COMMENT 'Esecuzione del rimborso, da cui decorre la durata',
  `data_scadenza` DATE NULL,
  `data_svincolo` DATE NULL,
  PRIMARY KEY (`id`),
  KEY `idx_garanzia_rimborso_soggetto` (`soggetto_id`, `data_rilascio`),
  KEY `idx_garanzia_rimborso_studio` (`studio_id`, `data_scadenza`),
  CONSTRAINT `fk_garanzia_rimborso_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_garanzia_rimborso_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Garanzie dei rimborsi IVA (art. 38-bis, c. 5)';
