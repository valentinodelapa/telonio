-- =====================================================
-- Telonio - modulo base della suite
-- =====================================================
-- Studi (tenant), utenti e password, caselle di posta in uscita, registro
-- degli aggiornamenti del database. Lo esegue "sisma aggiorna-database" (come root, prima degli
-- script dei moduli applicativi). I dati demo (studio e utente admin) si
-- caricano con le fixture: sisma fixtures.

CREATE TABLE IF NOT EXISTS `studio` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `denominazione` VARCHAR(255) NOT NULL,
  `codice_fiscale` VARCHAR(16) NOT NULL,
  `partita_iva` CHAR(11) NULL,
  `attivo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Studi professionali che utilizzano la suite (tenant)';

CREATE TABLE IF NOT EXISTS `utente` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `cognome` VARCHAR(100) NOT NULL,
  `nome` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `ruolo` ENUM('amministratore','operatore') NOT NULL DEFAULT 'operatore',
  `attivo` TINYINT(1) NOT NULL DEFAULT 1,
  `ultimo_accesso` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_utente_username` (`username`),
  CONSTRAINT `fk_utente_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utente_id` INT UNSIGNED NOT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'Hash bcrypt',
  `data_creazione` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_password_utente` (`utente_id`, `data_creazione`),
  CONSTRAINT `fk_password_utente` FOREIGN KEY (`utente_id`) REFERENCES `utente` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Caselle da cui lo studio invia i documenti ai clienti, una per tipo
-- (e-mail ordinaria e PEC). La password è cifrata dall'ORM con
-- ENCRYPTION_PASSPHRASE e il vettore d'inizializzazione proprio.
CREATE TABLE IF NOT EXISTS `casella_posta` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `tipo_casella` CHAR(1) NOT NULL COMMENT 'E e-mail ordinaria, P PEC',
  `host` VARCHAR(255) NOT NULL COMMENT 'Server SMTP',
  `porta` SMALLINT UNSIGNED NOT NULL,
  `cifratura` CHAR(1) NOT NULL DEFAULT 'S' COMMENT 'S SSL/TLS, T STARTTLS, N nessuna',
  `utente` VARCHAR(255) NULL COMMENT 'Utente SMTP (NULL senza autenticazione)',
  `password` VARCHAR(1024) NULL COMMENT 'Password SMTP cifrata',
  `password_initialization_vector` VARBINARY(32) NULL,
  `indirizzo` VARCHAR(255) NOT NULL COMMENT 'Indirizzo del mittente',
  `nome_mittente` VARCHAR(255) NULL COMMENT 'NULL: denominazione dello studio',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_casella_posta_studio_tipo` (`studio_id`, `tipo_casella`),
  CONSTRAINT `fk_casella_posta_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Caselle di posta in uscita dello studio (e-mail e PEC)';

CREATE TABLE IF NOT EXISTS `aggiornamento_database` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `modulo` VARCHAR(50) NOT NULL,
  `script` VARCHAR(100) NOT NULL COMMENT 'Nome dello script in <Modulo>/Database (aggiornamento-<versione>.sql)',
  `eseguito_il` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aggiornamento_database` (`modulo`, `script`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Script di aggiornamento dei moduli già applicati, registrati da sisma aggiorna-database';
