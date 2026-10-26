-- =====================================================
-- GestioneStudio - scadenzario e adempimenti dei clienti
-- =====================================================
-- Richiede le tabelle dei moduli CommSuite (studio, utente) e Anagrafica
-- (soggetto). Le scadenze degli altri moduli non sono copiate: si
-- calcolano dai dati dei moduli (FonteScadenze) e qui se ne conserva solo
-- lo stato di lavorazione.

-- -----------------------------------------------------
-- Calendario degli adempimenti
-- Righe senza studio: calendario fiscale comune a tutti gli studi,
-- popolato e aggiornato con "sisma calendario-fiscale" da
-- Database/sorgenti, con id stabili assegnati nelle sorgenti (adempimenti
-- sotto 1000, termini = adempimento * 100 + ordine). Le righe non più
-- presenti nelle sorgenti restano con attivo = 0. Righe con studio:
-- adempimenti propri dello studio (id dal 1000, termini dal 100000).
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `adempimento` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NULL COMMENT 'NULL = calendario fiscale comune',
  `codice` VARCHAR(30) NOT NULL,
  `denominazione` VARCHAR(150) NOT NULL,
  `categoria` CHAR(3) NOT NULL COMMENT 'Enumerations\\CategoriaAdempimento',
  `ambito` CHAR(3) NOT NULL DEFAULT 'TUT' COMMENT 'Enumerations\\AmbitoAdempimento: soggetti a cui si propone',
  `fonte` VARCHAR(255) NULL COMMENT 'Norma che fissa il termine',
  `slittamento_festivi` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Termine di sabato o festivo al primo giorno lavorativo (art. 7, c. 1, lett. h, D.L. 70/2011)',
  `proroga_agosto` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Termini dal 1° al 20 agosto al 20 agosto (art. 37, c. 11-bis, D.L. 223/2006)',
  `attivo` TINYINT(1) NOT NULL DEFAULT 1,
  `note` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_adempimento_studio` (`studio_id`, `codice`),
  CONSTRAINT `fk_adempimento_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Adempimenti del calendario fiscale e dello studio';

-- Termine dell'adempimento per un periodo dell'anno di riferimento A
-- (periodo d'imposta): giorno e mese dell'anno A + anni_successivi.
CREATE TABLE IF NOT EXISTS `termine_adempimento` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `adempimento_id` INT UNSIGNED NOT NULL,
  `ordine` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `periodo` VARCHAR(50) NOT NULL COMMENT 'Periodo di riferimento (es. gennaio, 1° trimestre, anno)',
  `mese` TINYINT UNSIGNED NOT NULL,
  `giorno` TINYINT UNSIGNED NOT NULL COMMENT '0 = ultimo giorno del mese',
  `anni_successivi` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Anno del termine rispetto all''anno di riferimento',
  PRIMARY KEY (`id`),
  KEY `idx_termine_adempimento` (`adempimento_id`, `ordine`),
  CONSTRAINT `fk_termine_adempimento` FOREIGN KEY (`adempimento_id`) REFERENCES `adempimento` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=100000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Termini ordinari degli adempimenti';

-- Proroga di un termine per un anno di riferimento, per tutti o per i soli
-- soggetti con i requisiti della condizione (es. soggetti ISA). Senza studio:
-- proroghe di legge del calendario fiscale (Database/sorgenti); con studio:
-- inserite dallo studio, prevalgono.
CREATE TABLE IF NOT EXISTS `proroga_adempimento` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NULL COMMENT 'NULL = proroga del calendario fiscale comune',
  `termine_adempimento_id` INT UNSIGNED NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL COMMENT 'Anno di riferimento',
  `data` DATE NOT NULL COMMENT 'Nuovo termine',
  `condizione` CHAR(3) NOT NULL DEFAULT 'TUT' COMMENT 'Enumerations\\CondizioneProroga: soggetti a cui si applica',
  `provvedimento` VARCHAR(255) NOT NULL,
  `note` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_proroga_termine` (`termine_adempimento_id`, `anno`),
  KEY `idx_proroga_studio` (`studio_id`),
  CONSTRAINT `fk_proroga_termine` FOREIGN KEY (`termine_adempimento_id`) REFERENCES `termine_adempimento` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_proroga_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Proroghe dei termini per anno';

-- -----------------------------------------------------
-- Adempimenti dei clienti
-- -----------------------------------------------------

-- Adempimento a cui è tenuto il soggetto, storicizzato: valgono i termini
-- che cadono nella validità; una variazione (es. nuovo responsabile da una
-- data) chiude la versione precedente. Si eliminano con il soggetto.
CREATE TABLE IF NOT EXISTS `assegnazione_adempimento` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `adempimento_id` INT UNSIGNED NOT NULL,
  `responsabile_id` INT UNSIGNED NULL COMMENT 'Operatore dello studio',
  `valido_dal` DATE NOT NULL,
  `valido_al` DATE NULL,
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assegnazione_decorrenza` (`soggetto_id`, `adempimento_id`, `valido_dal`),
  KEY `idx_assegnazione_studio` (`studio_id`, `adempimento_id`),
  KEY `idx_assegnazione_responsabile` (`responsabile_id`),
  CONSTRAINT `fk_assegnazione_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_assegnazione_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assegnazione_adempimento` FOREIGN KEY (`adempimento_id`) REFERENCES `adempimento` (`id`),
  CONSTRAINT `fk_assegnazione_responsabile` FOREIGN KEY (`responsabile_id`) REFERENCES `utente` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Adempimenti dei clienti dello studio';

-- -----------------------------------------------------
-- Stato di lavorazione delle scadenze
-- Una riga per scadenza (di qualsiasi modulo) su cui lo studio ha
-- registrato uno stato, un responsabile o una nota; le altre sono da fare.
-- Le righe del soggetto si eliminano con il soggetto.
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `lavorazione_scadenza` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `modulo` VARCHAR(30) NOT NULL COMMENT 'Modulo della scadenza (CommSuite\\Enumerations\\ModuloSuite)',
  `riferimento` VARCHAR(100) NOT NULL COMMENT 'Riferimento della scadenza nel modulo',
  `soggetto_id` INT UNSIGNED NULL,
  `data_scadenza` DATE NOT NULL COMMENT 'Termine alla registrazione dello stato',
  `descrizione` VARCHAR(255) NOT NULL,
  `stato` CHAR(1) NOT NULL DEFAULT 'D' COMMENT 'Enumerations\\StatoLavorazione',
  `responsabile_id` INT UNSIGNED NULL,
  `note` TEXT NULL,
  `data_completamento` DATE NULL,
  `utente_completamento_id` INT UNSIGNED NULL,
  `data_modifica` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lavorazione_scadenza` (`studio_id`, `modulo`, `riferimento`),
  KEY `idx_lavorazione_soggetto` (`soggetto_id`),
  CONSTRAINT `fk_lavorazione_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_lavorazione_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lavorazione_responsabile` FOREIGN KEY (`responsabile_id`) REFERENCES `utente` (`id`),
  CONSTRAINT `fk_lavorazione_utente_completamento` FOREIGN KEY (`utente_completamento_id`) REFERENCES `utente` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stato di lavorazione delle scadenze dello studio';
