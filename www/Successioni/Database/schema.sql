-- =====================================================
-- Successioni - dichiarazione di successione e domanda di volture catastali
-- =====================================================
-- Richiede le tabelle dei moduli CommSuite, Anagrafica (soggetto, immobile,
-- conto_corrente) e Deleghe (intermediario). I beni immobili sono quelli
-- dell'archivio immobili dell'Anagrafica; il modulo registra solo i dati
-- della dichiarazione.

-- -----------------------------------------------------
-- Tabella di riferimento (comune a tutti gli studi): uffici di
-- registrazione dell'Agenzia delle Entrate (testamenti, rinunce), dalle
-- specifiche tecniche SUC13 con "sisma specifiche-successioni".
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `ufficio_registrazione` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codice` VARCHAR(4) NOT NULL,
  `denominazione` VARCHAR(150) NOT NULL,
  `periodo` VARCHAR(60) NULL COMMENT 'Periodo di operatività indicato nelle specifiche',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ufficio_registrazione_codice` (`codice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Uffici di registrazione (specifiche SUC13, UfficiDiRegistrazione)';

CREATE TABLE IF NOT EXISTS `ufficio_trascrizione` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codice` VARCHAR(5) NOT NULL,
  `denominazione` VARCHAR(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ufficio_trascrizione_codice` (`codice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Servizi di pubblicità immobiliare (specifiche SUC13, UfficiDiTrascrizione)';

CREATE TABLE IF NOT EXISTS `comune_catastale_tavolare` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codice` CHAR(3) NOT NULL,
  `denominazione` VARCHAR(80) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_comune_catastale_tavolare_codice` (`codice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Comuni catastali delle province di Trento e Bolzano (specifiche SUC13, ComuniCatastaliTNBZ)';

-- -----------------------------------------------------
-- Dichiarazioni
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `dichiarazione_successione` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `defunto_id` INT UNSIGNED NOT NULL,
  `tipo_dichiarazione` CHAR(1) NOT NULL DEFAULT '0' COMMENT '0 prima, 1-3 sostitutiva/integrativa',
  `anno_prima` SMALLINT UNSIGNED NULL COMMENT 'Estremi della prima dichiarazione (sostitutive)',
  `volume_prima` VARCHAR(5) NULL,
  `numero_prima` VARCHAR(6) NULL,
  `devoluzione_legge` TINYINT(1) NOT NULL DEFAULT 1,
  `devoluzione_testamento` TINYINT(1) NOT NULL DEFAULT 0,
  `testamento_pubblico_ufficiale` VARCHAR(50) NULL,
  `testamento_data_pubblicazione` DATE NULL,
  `testamento_ufficio_id` INT UNSIGNED NULL,
  `testamento_serie` VARCHAR(2) NULL,
  `testamento_numero` VARCHAR(6) NULL,
  `testamento_sottonumero` VARCHAR(3) NULL,
  `testamento_data_registrazione` DATE NULL,
  `testamento_estero` TINYINT(1) NOT NULL DEFAULT 0,
  `legge_estera` TINYINT(1) NOT NULL DEFAULT 0,
  `eventi_eccezionali` TINYINT(1) NOT NULL DEFAULT 0,
  `beneficio_inventario` TINYINT(1) NOT NULL DEFAULT 0,
  `comune_decesso_id` INT UNSIGNED NULL COMMENT 'Comune del decesso (quadro EH)',
  `stato_decesso` VARCHAR(50) NULL COMMENT 'Stato estero del decesso',
  `stato_civile` CHAR(1) NULL COMMENT 'Codice stato civile SUC13 (1-7)',
  `residenza_estera` CHAR(1) NULL,
  `separazione_colpa` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Quadro EH: separazione con addebito al coniuge',
  `presentatore_id` INT UNSIGNED NULL,
  `codice_carica` CHAR(1) NULL,
  `rappresentato_id` INT UNSIGNED NULL,
  `decorrenza_termine` DATE NULL COMMENT 'Decorrenza del termine di presentazione diversa dal decesso',
  `telefono` VARCHAR(20) NULL,
  `email` VARCHAR(100) NULL,
  `senza_volture` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Casi particolari: non dare seguito alle volture',
  `copia_conforme` TINYINT(1) NOT NULL DEFAULT 0,
  `intermediario_id` INT UNSIGNED NULL,
  `impegno` CHAR(1) NULL,
  `data_impegno` DATE NULL,
  `conto_addebito_id` INT UNSIGNED NULL,
  `tempistica_pagamento` CHAR(1) NULL,
  `numero_rate` TINYINT UNSIGNED NULL,
  `pagamento_anticipato_trust` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'EF18ter: il trustee versa l''imposta subito, non all''attribuzione ai beneficiari finali',
  `acconto` INT UNSIGNED NULL,
  `data_versamento` DATE NULL COMMENT 'Data del versamento F24 (unica soluzione o acconto)',
  `ipotecaria_versata` INT UNSIGNED NULL,
  `catastale_versata` INT UNSIGNED NULL,
  `successione_versata` INT UNSIGNED NULL,
  `credito_ipotecaria` INT UNSIGNED NULL,
  `credito_catastale` INT UNSIGNED NULL,
  `credito_successione` INT UNSIGNED NULL,
  `credito_tipo` CHAR(1) NULL COMMENT 'EH sez. III: imposta pagata sull''acquisto della precedente prima casa (1 registro, 2 IVA, 3 IVA Sostegni-bis)',
  `credito_importo` INT UNSIGNED NULL COMMENT 'EH sez. III: credito d''imposta (art. 7 L. 448/1998)',
  `credito_acquisto_ufficio_id` INT UNSIGNED NULL,
  `credito_acquisto_serie` VARCHAR(2) NULL,
  `credito_acquisto_numero` VARCHAR(6) NULL,
  `credito_acquisto_sottonumero` VARCHAR(3) NULL,
  `credito_acquisto_data` DATE NULL,
  `credito_vendita_ufficio_id` INT UNSIGNED NULL,
  `credito_vendita_serie` VARCHAR(2) NULL,
  `credito_vendita_numero` VARCHAR(6) NULL,
  `credito_vendita_sottonumero` VARCHAR(3) NULL,
  `credito_vendita_data` DATE NULL,
  `numero_circoscrizioni` TINYINT UNSIGNED NULL COMMENT 'Circoscrizioni immobiliari (conservatorie): se NULL i comuni distinti degli immobili',
  `prima_casa_opzione` CHAR(1) NULL COMMENT 'EH sez. II: R residenza, A attivita, C cambio residenza, E estero, F forze armate',
  `prima_casa_trasferimento_prima` TINYINT(1) NOT NULL DEFAULT 0,
  `prima_casa_nessuna_altra_comune` TINYINT(1) NOT NULL DEFAULT 1,
  `prima_casa_altra_casa` CHAR(1) NULL COMMENT 'N nessuna altra casa agevolata, V vendita entro un anno',
  `vincolo_protocollo` VARCHAR(30) NULL COMMENT 'EH sez. IV: estremi del vincolo dei beni culturali (A, L)',
  `vincolo_data` DATE NULL,
  `precedente_ufficio` VARCHAR(4) NULL COMMENT 'EH sez. IV: ufficio territoriale della dichiarazione precedente (riduzioni art. 25)',
  `precedente_anno` SMALLINT UNSIGNED NULL,
  `precedente_volume` VARCHAR(5) NULL,
  `precedente_numero` VARCHAR(6) NULL,
  `stato` CHAR(1) NOT NULL DEFAULT 'B',
  `data_dichiarazione` DATE NULL,
  `data_presentazione` DATE NULL,
  `protocollo` VARCHAR(50) NULL,
  `registrazione_volume` VARCHAR(5) NULL COMMENT 'Estremi attribuiti dall''ufficio, per le sostitutive',
  `registrazione_numero` VARCHAR(6) NULL,
  `sostituita_id` INT UNSIGNED NULL COMMENT 'Dichiarazione presentata che questa sostituisce',
  `volture_applicate` DATETIME NULL COMMENT 'Volture riportate nei possessi dell''Anagrafica',
  `annotazioni` TEXT NULL,
  `data_inserimento` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dichiarazione_successione_defunto` (`defunto_id`),
  CONSTRAINT `fk_dichiarazione_successione_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_dichiarazione_successione_defunto` FOREIGN KEY (`defunto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_dichiarazione_successione_comune_decesso` FOREIGN KEY (`comune_decesso_id`) REFERENCES `comune` (`id`),
  CONSTRAINT `fk_dichiarazione_successione_presentatore` FOREIGN KEY (`presentatore_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_dichiarazione_successione_rappresentato` FOREIGN KEY (`rappresentato_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_dichiarazione_successione_testamento_ufficio` FOREIGN KEY (`testamento_ufficio_id`) REFERENCES `ufficio_registrazione` (`id`),
  CONSTRAINT `fk_dichiarazione_successione_intermediario` FOREIGN KEY (`intermediario_id`) REFERENCES `intermediario` (`id`),
  CONSTRAINT `fk_dichiarazione_successione_conto` FOREIGN KEY (`conto_addebito_id`) REFERENCES `conto_corrente` (`id`),
  CONSTRAINT `fk_dichiarazione_successione_sostituita` FOREIGN KEY (`sostituita_id`) REFERENCES `dichiarazione_successione` (`id`),
  CONSTRAINT `fk_dichiarazione_successione_credito_acquisto` FOREIGN KEY (`credito_acquisto_ufficio_id`) REFERENCES `ufficio_registrazione` (`id`),
  CONSTRAINT `fk_dichiarazione_successione_credito_vendita` FOREIGN KEY (`credito_vendita_ufficio_id`) REFERENCES `ufficio_registrazione` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Dichiarazione di successione e domanda di volture catastali (frontespizio, EF, EH)';

-- Quadro EA: eredi, legatari e altri beneficiari
CREATE TABLE IF NOT EXISTS `beneficiario_successione` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dichiarazione_successione_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `ordine` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `tipo_beneficiario` CHAR(1) NOT NULL,
  `grado_parentela` VARCHAR(3) NOT NULL,
  `disabile` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Persona con disabilità grave (art. 3, c. 3, L. 104/1992)',
  `certificatore_disabilita` VARCHAR(35) NULL,
  `rinuncia` TINYINT(1) NOT NULL DEFAULT 0,
  `rinuncia_ufficio_id` INT UNSIGNED NULL,
  `rinuncia_serie` VARCHAR(2) NULL,
  `rinuncia_numero` VARCHAR(6) NULL,
  `rinuncia_sottonumero` VARCHAR(3) NULL,
  `rinuncia_data` DATE NULL,
  `rappresentato_id` INT UNSIGNED NULL COMMENT 'Successione per rappresentazione: soggetto rappresentato',
  `grado_parentela_rappresentato` CHAR(2) NULL,
  `codice_fiscale_trust` VARCHAR(16) NULL COMMENT 'Trust testamentario (tipo 5): codice fiscale del trust',
  `beneficiario_finale_id` INT UNSIGNED NULL COMMENT 'Trust testamentario: beneficiario finale, se individuato',
  PRIMARY KEY (`id`),
  KEY `idx_beneficiario_successione_dichiarazione` (`dichiarazione_successione_id`, `ordine`),
  CONSTRAINT `fk_beneficiario_successione_dichiarazione` FOREIGN KEY (`dichiarazione_successione_id`) REFERENCES `dichiarazione_successione` (`id`),
  CONSTRAINT `fk_beneficiario_successione_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_beneficiario_successione_rappresentato` FOREIGN KEY (`rappresentato_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_beneficiario_successione_beneficiario_finale` FOREIGN KEY (`beneficiario_finale_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_beneficiario_successione_rinuncia_ufficio` FOREIGN KEY (`rinuncia_ufficio_id`) REFERENCES `ufficio_registrazione` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attivo ereditario: quadri EB, EC (immobili dell'Anagrafica), EO, ER
CREATE TABLE IF NOT EXISTS `bene_successione` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dichiarazione_successione_id` INT UNSIGNED NOT NULL,
  `quadro` CHAR(2) NOT NULL COMMENT 'EB, EC, EL, EM, EN, EO, EP, EQ, ER',
  `ordine` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `immobile_id` INT UNSIGNED NULL,
  `codice_diritto` VARCHAR(2) NOT NULL COMMENT 'Diritto del defunto',
  `quota_numeratore` DECIMAL(12,2) NOT NULL DEFAULT 1,
  `quota_denominatore` INT UNSIGNED NOT NULL DEFAULT 1,
  `valore` INT UNSIGNED NULL COMMENT 'Valore della quota del defunto, in euro',
  `valore_esente` INT UNSIGNED NULL COMMENT 'Quadro EO: titoli esenti',
  `valore_precedente` INT UNSIGNED NULL COMMENT 'Valore nelle successioni/donazioni precedenti (art. 25, c. 1)',
  `natura` VARCHAR(2) NULL COMMENT 'Terreni: codice natura (T, LE, ...)',
  `tipologia_terreno` CHAR(1) NULL,
  `diritto_abitazione` CHAR(1) NULL,
  `bene_estero` TINYINT(1) NOT NULL DEFAULT 0,
  `imposta_estero` INT UNSIGNED NULL COMMENT 'Imposta versata all''estero',
  `descrizione` VARCHAR(250) NULL,
  `tipo_cespite` CHAR(2) NULL COMMENT 'Quadro ER',
  `tipo_titolo` CHAR(1) NULL COMMENT 'Quadro EO',
  `codice_fiscale_emittente` VARCHAR(16) NULL,
  `quantita` BIGINT UNSIGNED NULL,
  `codice_titolo` VARCHAR(12) NULL,
  `tipo_documento` CHAR(1) NULL,
  `data_documento` DATE NULL,
  `azienda_id` INT UNSIGNED NULL COMMENT 'Immobile aziendale: azienda del quadro EN',
  `discordanza_intestatario` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Volture: dati dell''intestatario discordanti (quadro EI)',
  `passaggi_senza_atti` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Volture: passaggi intermedi senza atti legali (quadro EI)',
  `data_inventario` DATE NULL COMMENT 'Aziende: data dell''inventario',
  `data_deposito` DATE NULL COMMENT 'Aziende: deposito presso la camera di commercio (quadro EH)',
  `camera_commercio` VARCHAR(35) NULL,
  `marca_modello` VARCHAR(45) NULL COMMENT 'Aeromobili',
  `nazionalita` VARCHAR(20) NULL COMMENT 'Aeromobili e navi',
  `anno_costruzione` SMALLINT UNSIGNED NULL,
  `anno_iscrizione` SMALLINT UNSIGNED NULL COMMENT 'Immatricolazione o iscrizione',
  `numero_iscrizione` VARCHAR(10) NULL,
  `ufficio_iscrizione` VARCHAR(6) NULL COMMENT 'Navi: sigla dell''ufficio di iscrizione',
  `tipo_unita` CHAR(1) NULL COMMENT 'Navi: 1 vela, 2 motore',
  `dimensione` INT UNSIGNED NULL COMMENT 'Navi: lunghezza in cm (vela) o stazza in tonnellate (motore)',
  `stato_estero_id` INT UNSIGNED NULL COMMENT 'Immobili all''estero (EB, EC): Stato in cui si trovano',
  `codice_stato_estero` CHAR(3) NULL COMMENT 'Codice dello Stato estero dell''elenco delle istruzioni',
  `localita_estero` VARCHAR(50) NULL,
  `indirizzo_estero` VARCHAR(80) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bene_successione_dichiarazione` (`dichiarazione_successione_id`, `quadro`, `ordine`),
  CONSTRAINT `fk_bene_successione_dichiarazione` FOREIGN KEY (`dichiarazione_successione_id`) REFERENCES `dichiarazione_successione` (`id`),
  CONSTRAINT `fk_bene_successione_immobile` FOREIGN KEY (`immobile_id`) REFERENCES `immobile` (`id`),
  CONSTRAINT `fk_bene_successione_azienda` FOREIGN KEY (`azienda_id`) REFERENCES `bene_successione` (`id`),
  CONSTRAINT `fk_bene_successione_stato_estero` FOREIGN KEY (`stato_estero_id`) REFERENCES `nazione` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quadro EI: estremi degli atti (trascrizioni o registrazioni) che
-- ricostruiscono la provenienza di un immobile con dati discordanti
CREATE TABLE IF NOT EXISTS `estremo_voltura` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bene_successione_id` INT UNSIGNED NOT NULL,
  `tipo` CHAR(1) NOT NULL COMMENT 'T trascrizione, R registrazione',
  `ufficio_trascrizione_id` INT UNSIGNED NULL,
  `ufficio_registrazione_id` INT UNSIGNED NULL,
  `anno` SMALLINT UNSIGNED NOT NULL,
  `volume` VARCHAR(5) NULL COMMENT 'Registrazioni',
  `numero` VARCHAR(6) NOT NULL COMMENT 'Registro particolare (trascrizioni) o numero (registrazioni)',
  PRIMARY KEY (`id`),
  KEY `idx_estremo_voltura_bene` (`bene_successione_id`),
  CONSTRAINT `fk_estremo_voltura_bene` FOREIGN KEY (`bene_successione_id`) REFERENCES `bene_successione` (`id`),
  CONSTRAINT `fk_estremo_voltura_trascrizione` FOREIGN KEY (`ufficio_trascrizione_id`) REFERENCES `ufficio_trascrizione` (`id`),
  CONSTRAINT `fk_estremo_voltura_registrazione` FOREIGN KEY (`ufficio_registrazione_id`) REFERENCES `ufficio_registrazione` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sezione devoluzione dei beni
CREATE TABLE IF NOT EXISTS `devoluzione_successione` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bene_successione_id` INT UNSIGNED NOT NULL,
  `beneficiario_successione_id` INT UNSIGNED NOT NULL,
  `quota_numeratore` INT UNSIGNED NOT NULL DEFAULT 1,
  `quota_denominatore` INT UNSIGNED NOT NULL DEFAULT 1,
  `valore_quota` DECIMAL(15,2) NULL,
  `codice_diritto` VARCHAR(2) NOT NULL,
  `agevolazione` CHAR(1) NULL,
  `riduzione` CHAR(1) NULL COMMENT 'Riduzione art. 25, c. 1 (1-5)',
  PRIMARY KEY (`id`),
  KEY `idx_devoluzione_successione_bene` (`bene_successione_id`),
  CONSTRAINT `fk_devoluzione_successione_bene` FOREIGN KEY (`bene_successione_id`) REFERENCES `bene_successione` (`id`),
  CONSTRAINT `fk_devoluzione_successione_beneficiario` FOREIGN KEY (`beneficiario_successione_id`) REFERENCES `beneficiario_successione` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quadro ED: passività e oneri
CREATE TABLE IF NOT EXISTS `passivita_successione` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dichiarazione_successione_id` INT UNSIGNED NOT NULL,
  `ordine` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `tipo_passivita` VARCHAR(2) NOT NULL,
  `descrizione` VARCHAR(250) NOT NULL,
  `estremi_documento` VARCHAR(50) NOT NULL,
  `data_documento` DATE NOT NULL,
  `quota_numeratore` INT UNSIGNED NULL,
  `quota_denominatore` INT UNSIGNED NULL,
  `valore` INT UNSIGNED NOT NULL,
  `bene_successione_id` INT UNSIGNED NULL COMMENT 'Cespite cui si riferisce (mutui, debiti bancari e d''impresa)',
  PRIMARY KEY (`id`),
  KEY `idx_passivita_successione_dichiarazione` (`dichiarazione_successione_id`, `ordine`),
  CONSTRAINT `fk_passivita_successione_dichiarazione` FOREIGN KEY (`dichiarazione_successione_id`) REFERENCES `dichiarazione_successione` (`id`),
  CONSTRAINT `fk_passivita_successione_bene` FOREIGN KEY (`bene_successione_id`) REFERENCES `bene_successione` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ripartizione_passivita` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `passivita_successione_id` INT UNSIGNED NOT NULL,
  `beneficiario_successione_id` INT UNSIGNED NOT NULL,
  `quota_numeratore` INT UNSIGNED NOT NULL DEFAULT 1,
  `quota_denominatore` INT UNSIGNED NOT NULL DEFAULT 1,
  `valore_quota` DECIMAL(15,2) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ripartizione_passivita_passivita` (`passivita_successione_id`),
  CONSTRAINT `fk_ripartizione_passivita_passivita` FOREIGN KEY (`passivita_successione_id`) REFERENCES `passivita_successione` (`id`),
  CONSTRAINT `fk_ripartizione_passivita_beneficiario` FOREIGN KEY (`beneficiario_successione_id`) REFERENCES `beneficiario_successione` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quadro EG: allegati (contenuto in base64 in una tabella separata, letto
-- solo per lo scaricamento e il file telematico)
CREATE TABLE IF NOT EXISTS `contenuto_allegato` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contenuto` LONGTEXT NOT NULL COMMENT 'File in base64',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin;

CREATE TABLE IF NOT EXISTS `allegato_successione` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dichiarazione_successione_id` INT UNSIGNED NOT NULL,
  `tipo_allegato` CHAR(2) NOT NULL,
  `nome_file` VARCHAR(100) NOT NULL,
  `descrizione` VARCHAR(100) NULL,
  `tipo_mime` VARCHAR(20) NOT NULL COMMENT 'application/pdf, image/tiff',
  `dimensione` INT UNSIGNED NOT NULL COMMENT 'Byte',
  `contenuto_allegato_id` INT UNSIGNED NOT NULL,
  `data_inserimento` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_allegato_successione_dichiarazione` (`dichiarazione_successione_id`, `tipo_allegato`),
  CONSTRAINT `fk_allegato_successione_dichiarazione` FOREIGN KEY (`dichiarazione_successione_id`) REFERENCES `dichiarazione_successione` (`id`),
  CONSTRAINT `fk_allegato_successione_contenuto` FOREIGN KEY (`contenuto_allegato_id`) REFERENCES `contenuto_allegato` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
