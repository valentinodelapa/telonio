-- =====================================================
-- Anagrafica 0.12 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già la
-- tabella in schema.sql). Idempotente.
--
-- Interventi edilizi degli immobili con detrazione in rate, per le sezioni
-- III A, III C e IV del quadro RP del modello Redditi PF: recupero edilizio,
-- colonnine di ricarica, bonus mobili e risparmio energetico.

CREATE TABLE IF NOT EXISTS `intervento_edilizio` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `immobile_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL COMMENT 'Contribuente che fruisce della detrazione',
  `tipo` VARCHAR(2) NOT NULL COMMENT 'RE recupero edilizio (RP41-RP47), CR colonnine (RP56), AR bonus mobili (RP57), EN risparmio energetico (RP61-RP64)',
  `anno` SMALLINT UNSIGNED NOT NULL COMMENT 'Anno della spesa',
  `tipologia` TINYINT UNSIGNED NULL COMMENT 'Col. 2 di RP41, col. 1 di RP56 e di RP61',
  `codice_fiscale_condominio` VARCHAR(16) NULL,
  `casi_particolari` TINYINT UNSIGNED NULL COMMENT 'Col. 4 di RP41 (1 o 4) e di RP61 (1, 2 o 3)',
  `acquisto_eredita_donazione` TINYINT(1) NOT NULL DEFAULT 0,
  `maggiorazione_sisma` TINYINT(1) NOT NULL DEFAULT 0,
  `codice_percentuale` TINYINT UNSIGNED NULL COMMENT 'Casella "Percentuale" del rigo',
  `opzione2023` TINYINT(1) NOT NULL DEFAULT 0,
  `condominio` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Parti comuni condominiali (casella "Condominio" di RP51-RP53)',
  `spesa` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `percentuale_detrazione` DECIMAL(5,2) NOT NULL,
  `rateazione` TINYINT UNSIGNED NOT NULL,
  `descrizione` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_intervento_edilizio_immobile` (`immobile_id`),
  KEY `idx_intervento_edilizio_soggetto` (`soggetto_id`),
  CONSTRAINT `fk_intervento_edilizio_immobile` FOREIGN KEY (`immobile_id`) REFERENCES `immobile` (`id`),
  CONSTRAINT `fk_intervento_edilizio_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Interventi edilizi con detrazione in rate (quadro RP, sezioni III A, III C e IV)';

-- Canoni scaduti e non percepiti accertati con la convalida di sfratto per
-- morosità (credito d'imposta del rigo CR8 del modello Redditi PF).

CREATE TABLE IF NOT EXISTS `canone_non_percepito` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contratto_locazione_id` INT UNSIGNED NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL COMMENT 'Anno dei canoni non percepiti',
  `importo` DECIMAL(12,2) NOT NULL COMMENT 'Canoni dell''intero contratto',
  `data_convalida` DATE NOT NULL COMMENT 'Convalida dello sfratto per morosità',
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_canone_non_percepito` (`contratto_locazione_id`, `anno`),
  CONSTRAINT `fk_canone_non_percepito_contratto` FOREIGN KEY (`contratto_locazione_id`) REFERENCES `contratto_locazione` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Canoni non percepiti con la convalida di sfratto (credito CR8)';
