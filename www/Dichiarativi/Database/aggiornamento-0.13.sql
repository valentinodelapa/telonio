-- =====================================================
-- Dichiarativi 0.13 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già la
-- tabella in schema.sql). Idempotente.

-- Archivio delle perdite fiscali residue alla fine di un periodo d'imposta,
-- per il riporto quando la dichiarazione di quell'anno non è nella suite.

CREATE TABLE IF NOT EXISTS `perdita_pregressa` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studio_id` INT UNSIGNED NOT NULL,
  `soggetto_id` INT UNSIGNED NOT NULL,
  `anno` SMALLINT UNSIGNED NOT NULL COMMENT 'Periodo d''imposta alla cui fine le perdite sono residue',
  `tipo` CHAR(1) NOT NULL DEFAULT 'I' COMMENT 'I perdite d''impresa, C perdite del consolidato nazionale (consolidante)',
  `limitata` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Utilizzabili in misura limitata (art. 84, c. 1, Tuir)',
  `piena` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Utilizzabili in misura piena (art. 84, c. 2, Tuir)',
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_perdita_pregressa` (`soggetto_id`, `anno`, `tipo`),
  CONSTRAINT `fk_perdita_pregressa_studio` FOREIGN KEY (`studio_id`) REFERENCES `studio` (`id`),
  CONSTRAINT `fk_perdita_pregressa_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Perdite fiscali residue alla fine di un periodo d''imposta, per il riporto quando la dichiarazione di quell''anno non è nella suite';
