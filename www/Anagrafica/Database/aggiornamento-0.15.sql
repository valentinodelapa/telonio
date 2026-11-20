-- =====================================================
-- Anagrafica 0.15 - aggiornamento dei database creati con le versioni precedenti
-- =====================================================
-- Eseguito da "sisma aggiorna-database" (le nuove installazioni hanno già la
-- tabella in schema.sql). Idempotente.
--
-- Opzioni delle società di capitali per la tassazione di gruppo: consolidato
-- nazionale (artt. 117-129 Tuir) e trasparenza fiscale (artt. 115 e 116),
-- per gli esercizi dall'anno di inizio all'anno di fine.

CREATE TABLE IF NOT EXISTS `opzione_tassazione` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `soggetto_id` INT UNSIGNED NOT NULL COMMENT 'Società che ha esercitato l''opzione',
  `tipo_opzione` VARCHAR(4) NOT NULL COMMENT 'CN consolidato nazionale (artt. 117-129 Tuir), T115 trasparenza (art. 115), T116 trasparenza delle s.r.l. con soci persone fisiche (art. 116)',
  `consolidante_id` INT UNSIGNED NULL COMMENT 'Consolidato: società consolidante (la società stessa se è la consolidante)',
  `anno_inizio` SMALLINT UNSIGNED NOT NULL,
  `anno_fine` SMALLINT UNSIGNED NOT NULL,
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_opzione_tassazione_soggetto` (`soggetto_id`),
  KEY `idx_opzione_tassazione_consolidante` (`consolidante_id`),
  CONSTRAINT `fk_opzione_tassazione_soggetto` FOREIGN KEY (`soggetto_id`) REFERENCES `soggetto` (`id`),
  CONSTRAINT `fk_opzione_tassazione_consolidante` FOREIGN KEY (`consolidante_id`) REFERENCES `soggetto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
