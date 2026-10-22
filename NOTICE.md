# NOTICE

Questo progetto (`CommSuite`) è rilasciato sotto licenza **MIT** ed è un software open source indipendente, non affiliato, sponsorizzato né approvato da fornitori commerciali di software gestionale o dall'Amministrazione finanziaria.

Di seguito sono riportate le attribuzioni e le licenze relative ai componenti software di terze parti e alle banche dati pubbliche utilizzate dai moduli della suite.

---

## Componenti software di terze parti

*   **SismaFramework** (`www/SismaFramework`): Copyright (c) 2020-present Valentino de Lapa, rilasciato sotto licenza **MIT**. Per le attribuzioni interne al framework (tra cui SimpleORM sotto Apache License 2.0, Symfony e Doctrine ORM sotto licenza MIT) si rimanda a [`www/SismaFramework/NOTICE.md`](www/SismaFramework/NOTICE.md).
*   **PSR Log** (`psr/log`): Copyright (c) PHP Framework Interoperability Group, rilasciato sotto licenza **MIT**.
*   **Bootstrap** (`v5.3.3`) e **Bootstrap Icons** (`v1.11.3`): Copyright (c) 2011-2024 The Bootstrap Authors, rilasciati sotto licenza **MIT** (caricati tramite CDN).

Le utilità di sistema utilizzate all'interno del container Docker per l'importazione delle tabelle e la conversione dei modelli PDF (`poppler-utils` / `pdftocairo` / `pdftotext` e `libarchive-tools` / `bsdtar`) sono invocate esclusivamente come eseguibili di sistema esterni e non sono distribuite né collegate staticamente o dinamicamente al codice sorgente di questo repository.

---

## Banche dati pubbliche, tabelle di decodifica e modulistica ufficiale

I moduli della suite importano e utilizzano dati di riferimento e modelli pubblicati da enti istituzionali italiani ed europei, riutilizzabili ai sensi dell'art. 52 del D.Lgs. 82/2005 (Codice dell'Amministrazione Digitale), del D.Lgs. 36/2006 sul riutilizzo dell'informazione del settore pubblico e delle rispettive condizioni di utilizzo delle fonti:

*   **Ministero dell'Interno — Anagrafe Nazionale della Popolazione Residente (ANPR)**:
    *   *Fonte*: [Tabelle di decodifica ANPR](https://www.anagrafenazionale.interno.it/area-tecnica/tabelle-di-decodifica/) (Tabella 03 — Archivio storico dei comuni; Tabella 02 — Stati esteri).
    *   *Utilizzo*: popolare le tabelle di riferimento `comune` e `nazione` del modulo `Anagrafica` (`www/Anagrafica/Database/dati-riferimento.sql`).
*   **Istituto Nazionale di Statistica (ISTAT)**:
    *   *Fonte*: [Classificazione delle attività economiche ATECO 2025 e tabella operativa di riclassificazione ATECO 2007 agg. 2022 → ATECO 2025](https://www.istat.it/classificazione/ateco-2025/).
    *   *Licenza della fonte*: **Creative Commons Attribuzione (CC BY)**.
    *   *Utilizzo*: popolare la tabella `codice_ateco` del modulo `Anagrafica` (`www/Anagrafica/Database/dati-riferimento.sql`).
*   **Agenzia delle Entrate**:
    *   *Fonti*:
        *   [Tabelle dei codici tributo e altri codici per il modello F24](https://www.agenziaentrate.gov.it/portale/strumenti/codici-attivita-e-tributo/f24-codici-tributo-per-i-versamenti/tabelle-dei-codici-tributo-e-altri-codici-per-il-modello-f24/riepilogo-tabelle) e servizio di ricerca codici tributo (codici tributo, causali contributo, sedi ed enti previdenziali/territoriali, codici ufficio).
        *   Modelli ufficiali di versamento F24 (Ordinario, Semplificato, Elementi identificativi, Accise) e relative specifiche tecniche per la trasmissione telematica Entratel (Provvedimenti del Direttore dell'Agenzia delle Entrate).
    *   *Utilizzo*: tabelle di riferimento (`www/Deleghe/Database/dati-riferimento.sql`), sfondi di stampa in formato SVG (`www/Deleghe/Application/Assets/svg/`) e generazione dei tracciati telematici nel modulo `Deleghe`.
*   **Ministero dell'Economia e delle Finanze (MEF) — Dipartimento delle Finanze**:
    *   *Fonte*: [Portale della Fiscalità Locale — Prospetti delle aliquote IMU](https://www1.finanze.gov.it/finanze2/dipartimentopolitichefiscali/fiscalitalocale/nuova_imu/sceltaregione.htm).
    *   *Utilizzo*: importazione dei prospetti comunali delle aliquote IMU nel modulo `Imu` tramite il comando `sisma aliquote-imu`.
*   **Banca Centrale Europea (BCE / ECB)**:
    *   *Fonte*: [ECB Data Portal](https://data.ecb.europa.eu/data/datasets/FM/FM.B.U2.EUR.4F.KR.MRR_FR.LEV) — Tasso sulle operazioni di rifinanziamento principali (*Main refinancing operations*).
    *   *Utilizzo*: popolare la tabella `tasso_riferimento` del modulo `Deleghe` per il calcolo delle sanzioni civili INPS e INAIL.
*   **Codici ISO 3166-1 (`www/Anagrafica/Database/sorgenti/iso3166-1.csv`)**:
    *   *Fonte*: Tabella di corrispondenza tra codici ISO 3166-1 alpha-3 e alpha-2 estratta dal pacchetto Debian [`iso-codes`](https://salsa.debian.org/iso-codes-team/iso-codes) (`data/iso_3166-1.json`), rilasciato sotto licenza **LGPL-2.1+**, integrata con il codice `XK` (Kosovo) utilizzato dalle specifiche UE e FatturaPA.
