-- =====================================================
-- Contabilita 0.15 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già
-- colonna e tabella in schema.sql). Idempotente.

-- Fabbricato del cespite: immobile dell'Anagrafica (categoria catastale per
-- il prospetto delle società di comodo).

ALTER TABLE `cespite` ADD COLUMN IF NOT EXISTS `immobile_id` INT UNSIGNED NULL COMMENT 'Fabbricato: immobile dell''Anagrafica (categoria catastale per le società di comodo)' AFTER `note`;
ALTER TABLE `cespite` ADD CONSTRAINT `fk_cespite_immobile` FOREIGN KEY IF NOT EXISTS (`immobile_id`) REFERENCES `immobile` (`id`) ON DELETE SET NULL;

-- Contratti di locazione finanziaria dell'azienda.

CREATE TABLE IF NOT EXISTS `contratto_leasing` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `descrizione` VARCHAR(255) NOT NULL,
  `concedente` VARCHAR(100) NULL,
  `numero_contratto` VARCHAR(40) NULL,
  `data_inizio` DATE NOT NULL,
  `data_fine` DATE NOT NULL,
  `data_riscatto` DATE NULL COMMENT 'Riscatto o fine anticipata',
  `costo_concedente` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `totale_canoni` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Canoni complessivi, compreso il maxicanone',
  `prezzo_riscatto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `bene_immobile` TINYINT(1) NOT NULL DEFAULT 0,
  `immobile_id` INT UNSIGNED NULL COMMENT 'Immobile dell''Anagrafica (categoria catastale)',
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_contratto_leasing_soggetto` (`soggetto_id`, `data_inizio`),
  CONSTRAINT `fk_contratto_leasing_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_contratto_leasing_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_contratto_leasing_immobile` FOREIGN KEY (`immobile_id`) REFERENCES `immobile` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Contratti di locazione finanziaria: quota interessi (D.M. 24 aprile 1998) e valore dei beni per le società di comodo';
