-- =====================================================
-- Anagrafica 0.11 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già le
-- tabelle in schema.sql). Idempotente.
--
-- Coltivatore diretto o IAP iscritto nella previdenza agricola (dati fiscali),
-- per il quadro RA del modello Redditi.

ALTER TABLE `dati_fiscali` ADD COLUMN IF NOT EXISTS `coltivatore_iap` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Coltivatore diretto o IAP iscritto nella previdenza agricola (quadro RA, col. 10; terreni esenti IMU)' AFTER `gruppo_iva_art70bis`;

-- Contratti di locazione e di affitto degli immobili, per i quadri RA, RB e
-- LC del modello Redditi: tipo, canone e sue variazioni, cedolare secca,
-- pertinenze locate insieme, estremi di registrazione e CIN.

CREATE TABLE IF NOT EXISTS `contratto_locazione` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `immobile_id` INT UNSIGNED NOT NULL,
  `tipo` VARCHAR(2) NOT NULL COMMENT 'LL canone libero, EC equo canone, CC concordato, AB Abruzzo, BR locazione breve, AV/AL affitto di terreno vincolistico/libero',
  `conduttore` VARCHAR(255) NULL,
  `codice_fiscale_conduttore` VARCHAR(16) NULL,
  `data_inizio` DATE NOT NULL,
  `data_fine` DATE NULL,
  `canone_annuo` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Per le locazioni brevi: corrispettivo lordo del contratto',
  `cedolare_secca` TINYINT(1) NOT NULL DEFAULT 0,
  `cedolare_revocata_dal` DATE NULL,
  `cedolare_ridotta` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Locazione breve: unità scelta per il 21%',
  `venezia_centro` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Venezia centro, Giudecca, Murano, Burano: canone al 75%',
  `emergenza_calamita` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Concordato in comune con stato di emergenza (RB col. 20 codice 1)',
  `giovane_agricoltore` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Affitto di terreno a giovane CD o IAP (RA col. 7 codice 4)',
  `data_registrazione` DATE NULL,
  `serie_registrazione` VARCHAR(2) NULL,
  `numero_registrazione` VARCHAR(20) NULL,
  `codice_ufficio` VARCHAR(3) NULL,
  `codice_identificativo` VARCHAR(17) NULL,
  `cin` VARCHAR(18) NULL COMMENT 'Codice identificativo nazionale (art. 13-ter D.L. 145/2023)',
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_contratto_locazione_immobile` (`immobile_id`),
  CONSTRAINT `fk_contratto_locazione_immobile` FOREIGN KEY (`immobile_id`) REFERENCES `immobile` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Contratti di locazione dei fabbricati e di affitto dei terreni';

CREATE TABLE IF NOT EXISTS `pertinenza_locata` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contratto_locazione_id` INT UNSIGNED NOT NULL,
  `immobile_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pertinenza_locata` (`contratto_locazione_id`, `immobile_id`),
  KEY `idx_pertinenza_locata_immobile` (`immobile_id`),
  CONSTRAINT `fk_pertinenza_locata_contratto` FOREIGN KEY (`contratto_locazione_id`) REFERENCES `contratto_locazione` (`id`),
  CONSTRAINT `fk_pertinenza_locata_immobile` FOREIGN KEY (`immobile_id`) REFERENCES `immobile` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Pertinenze con rendita autonoma locate insieme all''immobile del contratto';

CREATE TABLE IF NOT EXISTS `variazione_canone` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contratto_locazione_id` INT UNSIGNED NOT NULL,
  `valido_dal` DATE NOT NULL,
  `canone_annuo` DECIMAL(12,2) NOT NULL,
  `rinegoziazione` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Riduzione concordata del canone',
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_variazione_canone` (`contratto_locazione_id`, `valido_dal`),
  CONSTRAINT `fk_variazione_canone_contratto` FOREIGN KEY (`contratto_locazione_id`) REFERENCES `contratto_locazione` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Variazioni del canone annuo (aggiornamento ISTAT, rinegoziazione)';

-- Legami familiari (coniuge, figlio, ascendente, altro familiare) per il
-- prospetto dei familiari a carico del modello Redditi: disabilità,
-- convivenza, percentuale della detrazione dei figli e affidamento.

ALTER TABLE `legame` ADD COLUMN IF NOT EXISTS `disabile` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Figlio con disabilità (L. 104/1992, prospetto dei familiari: casella D)' AFTER `note`;
ALTER TABLE `legame` ADD COLUMN IF NOT EXISTS `convivente` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Familiare convivente (ascendenti e altri familiari a carico)' AFTER `disabile`;
ALTER TABLE `legame` ADD COLUMN IF NOT EXISTS `percentuale_detrazione` VARCHAR(3) NULL COMMENT 'Figli: 100, 50, 0 o C (coniuge mancante); NULL = proposta automatica' AFTER `convivente`;
ALTER TABLE `legame` ADD COLUMN IF NOT EXISTS `affidamento` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Figlio in affidamento con detrazione al 100% (prospetto dei familiari: col. 8)' AFTER `percentuale_detrazione`;
