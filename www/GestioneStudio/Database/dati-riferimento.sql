-- =====================================================
-- GestioneStudio - calendario fiscale
-- =====================================================
-- Generato con "sisma calendario-fiscale --sql" il 26/10/2026 09:33: non modificare a mano.
-- Fonte: GestioneStudio/Database/sorgenti (norma indicata per adempimento).
-- Righe: adempimento 22, termine_adempimento 66, proroga_adempimento 2.
-- Idempotente: aggiorna il calendario fiscale senza toccare i dati degli studi.
-- Da eseguire dopo schema.sql.

SET NAMES utf8mb4;

INSERT INTO `adempimento` (`id`, `studio_id`, `codice`, `denominazione`, `categoria`, `ambito`, `fonte`, `slittamento_festivi`, `proroga_agosto`, `attivo`) VALUES
(1, NULL, 'IVA_MENSILE', 'Liquidazione e versamento IVA mensile', 'VER', 'IVM', 'art. 1, c. 1, D.P.R. 100/1998', 1, 1, 1),
(2, NULL, 'IVA_TRIMESTRALE', 'Liquidazione e versamento IVA trimestrale', 'VER', 'IVT', 'art. 7, c. 1, D.P.R. 542/1999', 1, 1, 1),
(3, NULL, 'IVA_ACCONTO', 'Acconto IVA', 'VER', 'IVO', 'art. 6, c. 2, L. 405/1990', 1, 1, 1),
(4, NULL, 'IVA_SALDO', 'Saldo IVA dell''anno', 'VER', 'IVO', 'art. 6, c. 1, D.P.R. 542/1999', 1, 1, 1),
(5, NULL, 'DICHIARAZIONE_IVA', 'Dichiarazione IVA annuale', 'DIC', 'IVO', 'art. 8, c. 1, D.P.R. 322/1998', 1, 1, 1),
(6, NULL, 'LIPE', 'Comunicazione delle liquidazioni periodiche IVA', 'COM', 'IVO', 'art. 21-bis, c. 1, D.L. 78/2010 (2° trimestre: art. 3, c. 1, D.L. 73/2022)', 1, 1, 1),
(7, NULL, 'RITENUTE', 'Versamento delle ritenute alla fonte', 'VER', 'SOS', 'art. 18, D.Lgs. 241/1997; art. 8, D.P.R. 602/1973', 1, 1, 1),
(8, NULL, 'CERTIFICAZIONE_UNICA', 'Certificazione Unica: trasmissione e consegna', 'COM', 'SOS', 'art. 4, c. 6-quater e 6-quinquies, D.P.R. 322/1998', 1, 1, 1),
(9, NULL, 'MODELLO_770', 'Dichiarazione dei sostituti d''imposta (770)', 'DIC', 'SOS', 'art. 4, c. 4-bis, D.P.R. 322/1998', 1, 1, 1),
(10, NULL, 'REDDITI_PF', 'Dichiarazione dei redditi delle persone fisiche', 'DIC', 'PF', 'art. 2, c. 2, D.P.R. 322/1998', 1, 1, 1),
(11, NULL, 'REDDITI_SP', 'Dichiarazione dei redditi delle società di persone', 'DIC', 'SP', 'art. 2, c. 2, D.P.R. 322/1998', 1, 1, 1),
(12, NULL, 'REDDITI_SC', 'Dichiarazione dei redditi delle società di capitali (esercizio solare)', 'DIC', 'SC', 'art. 2, c. 2, D.P.R. 322/1998', 1, 1, 1),
(13, NULL, 'REDDITI_ENC', 'Dichiarazione dei redditi degli enti non commerciali (esercizio solare)', 'DIC', 'EN', 'art. 2, c. 2, D.P.R. 322/1998', 1, 1, 1),
(14, NULL, 'IRAP', 'Dichiarazione IRAP', 'DIC', 'SOE', 'art. 19, D.Lgs. 446/1997; art. 2, c. 2, D.P.R. 322/1998', 1, 1, 1),
(15, NULL, 'SALDO_PRIMO_ACCONTO', 'Saldo e primo acconto delle imposte sui redditi e dell''IRAP', 'VER', 'TUT', 'art. 17, c. 1, D.P.R. 435/2001', 1, 1, 1),
(16, NULL, 'SECONDO_ACCONTO', 'Secondo acconto delle imposte sui redditi e dell''IRAP', 'VER', 'TUT', 'art. 17, c. 3, D.P.R. 435/2001', 1, 1, 1),
(17, NULL, 'MODELLO_730', 'Dichiarazione dei redditi con il modello 730', 'DIC', 'PFP', 'art. 13, D.M. 164/1999 (art. 16-bis, D.L. 124/2019)', 1, 1, 1),
(18, NULL, 'INPS_IVS', 'Contributi IVS artigiani e commercianti: rate fisse', 'VER', 'ART', 'art. 18, D.Lgs. 241/1997; circolare annuale INPS sui contributi IVS', 1, 1, 1),
(19, NULL, 'BOLLO_FATTURE', 'Imposta di bollo sulle fatture elettroniche', 'VER', 'IVA', 'art. 6, c. 2, D.M. 17 giugno 2014', 1, 1, 1),
(20, NULL, 'INTRASTAT_MENSILE', 'Elenchi riepilogativi Intrastat mensili', 'COM', 'MAN', 'art. 6, D.M. 22 febbraio 2010', 1, 1, 1),
(21, NULL, 'LIBRI_SOCIALI', 'Tassa annuale di vidimazione dei libri sociali', 'VER', 'SC', 'art. 23 della Tariffa allegata al D.P.R. 641/1972 (versamento entro il termine del saldo IVA)', 1, 1, 1),
(22, NULL, 'DIRITTO_CAMERALE', 'Diritto annuale della Camera di commercio', 'VER', 'IMP', 'art. 8, D.M. 359/2001 (termine del primo acconto delle imposte sui redditi)', 1, 1, 1)
ON DUPLICATE KEY UPDATE `studio_id` = VALUES(`studio_id`), `codice` = VALUES(`codice`), `denominazione` = VALUES(`denominazione`), `categoria` = VALUES(`categoria`), `ambito` = VALUES(`ambito`), `fonte` = VALUES(`fonte`), `slittamento_festivi` = VALUES(`slittamento_festivi`), `proroga_agosto` = VALUES(`proroga_agosto`), `attivo` = VALUES(`attivo`);

UPDATE `adempimento` SET `attivo` = 0 WHERE `studio_id` IS NULL AND `id` NOT IN (1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22);

INSERT INTO `termine_adempimento` (`id`, `adempimento_id`, `ordine`, `periodo`, `mese`, `giorno`, `anni_successivi`) VALUES
(101, 1, 1, 'gennaio', 2, 16, 0),
(102, 1, 2, 'febbraio', 3, 16, 0),
(103, 1, 3, 'marzo', 4, 16, 0),
(104, 1, 4, 'aprile', 5, 16, 0),
(105, 1, 5, 'maggio', 6, 16, 0),
(106, 1, 6, 'giugno', 7, 16, 0),
(107, 1, 7, 'luglio', 8, 16, 0),
(108, 1, 8, 'agosto', 9, 16, 0),
(109, 1, 9, 'settembre', 10, 16, 0),
(110, 1, 10, 'ottobre', 11, 16, 0),
(111, 1, 11, 'novembre', 12, 16, 0),
(112, 1, 12, 'dicembre', 1, 16, 1),
(201, 2, 1, '1° trimestre', 5, 16, 0),
(202, 2, 2, '2° trimestre', 8, 16, 0),
(203, 2, 3, '3° trimestre', 11, 16, 0),
(301, 3, 1, 'anno', 12, 27, 0),
(401, 4, 1, 'anno', 3, 16, 1),
(501, 5, 1, 'anno', 4, 30, 1),
(601, 6, 1, '1° trimestre', 5, 31, 0),
(602, 6, 2, '2° trimestre', 9, 30, 0),
(603, 6, 3, '3° trimestre', 11, 30, 0),
(604, 6, 4, '4° trimestre', 2, 0, 1),
(701, 7, 1, 'gennaio', 2, 16, 0),
(702, 7, 2, 'febbraio', 3, 16, 0),
(703, 7, 3, 'marzo', 4, 16, 0),
(704, 7, 4, 'aprile', 5, 16, 0),
(705, 7, 5, 'maggio', 6, 16, 0),
(706, 7, 6, 'giugno', 7, 16, 0),
(707, 7, 7, 'luglio', 8, 16, 0),
(708, 7, 8, 'agosto', 9, 16, 0),
(709, 7, 9, 'settembre', 10, 16, 0),
(710, 7, 10, 'ottobre', 11, 16, 0),
(711, 7, 11, 'novembre', 12, 16, 0),
(712, 7, 12, 'dicembre', 1, 16, 1),
(801, 8, 1, 'anno', 3, 16, 1),
(901, 9, 1, 'anno', 10, 31, 1),
(1001, 10, 1, 'anno', 10, 31, 1),
(1101, 11, 1, 'anno', 10, 31, 1),
(1201, 12, 1, 'anno', 10, 31, 1),
(1301, 13, 1, 'anno', 10, 31, 1),
(1401, 14, 1, 'anno', 10, 31, 1),
(1501, 15, 1, 'anno', 6, 30, 1),
(1601, 16, 1, 'anno', 11, 30, 0),
(1701, 17, 1, 'anno', 9, 30, 1),
(1801, 18, 1, '1ª rata', 5, 16, 0),
(1802, 18, 2, '2ª rata', 8, 16, 0),
(1803, 18, 3, '3ª rata', 11, 16, 0),
(1804, 18, 4, '4ª rata', 2, 16, 1),
(1901, 19, 1, '1° trimestre', 5, 31, 0),
(1902, 19, 2, '2° trimestre', 9, 30, 0),
(1903, 19, 3, '3° trimestre', 11, 30, 0),
(1904, 19, 4, '4° trimestre', 2, 0, 1),
(2001, 20, 1, 'gennaio', 2, 25, 0),
(2002, 20, 2, 'febbraio', 3, 25, 0),
(2003, 20, 3, 'marzo', 4, 25, 0),
(2004, 20, 4, 'aprile', 5, 25, 0),
(2005, 20, 5, 'maggio', 6, 25, 0),
(2006, 20, 6, 'giugno', 7, 25, 0),
(2007, 20, 7, 'luglio', 8, 25, 0),
(2008, 20, 8, 'agosto', 9, 25, 0),
(2009, 20, 9, 'settembre', 10, 25, 0),
(2010, 20, 10, 'ottobre', 11, 25, 0),
(2011, 20, 11, 'novembre', 12, 25, 0),
(2012, 20, 12, 'dicembre', 1, 25, 1),
(2101, 21, 1, 'anno', 3, 16, 0),
(2201, 22, 1, 'anno', 6, 30, 0)
ON DUPLICATE KEY UPDATE `adempimento_id` = VALUES(`adempimento_id`), `ordine` = VALUES(`ordine`), `periodo` = VALUES(`periodo`), `mese` = VALUES(`mese`), `giorno` = VALUES(`giorno`), `anni_successivi` = VALUES(`anni_successivi`);

DELETE `termine_adempimento` FROM `termine_adempimento` JOIN `adempimento` ON `adempimento`.`id` = `termine_adempimento`.`adempimento_id` WHERE `adempimento`.`studio_id` IS NULL AND `termine_adempimento`.`id` NOT IN (101, 102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 112, 201, 202, 203, 301, 401, 501, 601, 602, 603, 604, 701, 702, 703, 704, 705, 706, 707, 708, 709, 710, 711, 712, 801, 901, 1001, 1101, 1201, 1301, 1401, 1501, 1601, 1701, 1801, 1802, 1803, 1804, 1901, 1902, 1903, 1904, 2001, 2002, 2003, 2004, 2005, 2006, 2007, 2008, 2009, 2010, 2011, 2012, 2101, 2201);

DELETE FROM `proroga_adempimento` WHERE `studio_id` IS NULL;

INSERT INTO `proroga_adempimento` (`termine_adempimento_id`, `studio_id`, `anno`, `data`, `condizione`, `provvedimento`, `note`) VALUES
(1501, NULL, 2025, '2026-07-20', 'ISA', 'art. 1-sexies, c. 1 e 2, D.L. 30 aprile 2026, n. 63 (L. 25 giugno 2026, n. 113)', 'Versamenti del 30/06/2026 risultanti dalle dichiarazioni dei redditi, IRAP e IVA (saldo 2025 e primo acconto 2026): entro il 20/07/2026 senza maggiorazione o nei 30 giorni successivi (20/08/2026) con la maggiorazione dello 0,80%. Soggetti ISA, anche con cause di esclusione, con ricavi o compensi entro il limite del decreto di approvazione, forfetari, regime di vantaggio e loro soci (artt. 5, 115 e 116 TUIR).'),
(2201, NULL, 2026, '2026-07-20', 'ISA', 'art. 1-sexies, c. 1 e 2, D.L. 30 aprile 2026, n. 63 (L. 25 giugno 2026, n. 113)', 'Il diritto annuale segue il termine del primo acconto delle imposte sui redditi (art. 8 D.M. 359/2001): entro il 20/07/2026, o entro il 20/08/2026 con la maggiorazione dello 0,80%.')
ON DUPLICATE KEY UPDATE `studio_id` = VALUES(`studio_id`), `anno` = VALUES(`anno`), `data` = VALUES(`data`), `condizione` = VALUES(`condizione`), `provvedimento` = VALUES(`provvedimento`), `note` = VALUES(`note`);

