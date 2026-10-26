<?php

namespace Anagrafica\Application\Enumerations;

/**
 * Regime fiscale e contabile del titolare di partita IVA, storicizzato nei
 * dati fiscali. Determina gli adempimenti (es. i forfetari non liquidano
 * l'IVA) ed è letto dagli altri moduli con DatiFiscaliModel::getVigenteAl.
 *
 * - ordinario: contabilità ordinaria (obbligatoria per le società di
 *   capitali; per opzione o superamento dei limiti negli altri casi);
 * - semplificato: contabilità semplificata delle imprese minori (art. 18
 *   D.P.R. 600/1973, art. 66 TUIR) e dei lavoratori autonomi (art. 3 D.P.R.
 *   695/1996);
 * - forfetario: persone fisiche, art. 1, commi 54-89, L. 190/2014 (dal 2015);
 * - vantaggio: regime fiscale di vantaggio per l'imprenditoria giovanile,
 *   art. 27, commi 1 e 2, D.L. 98/2011 (dal 2012, solo per chi già lo
 *   applicava dopo il 2015);
 * - forfetario degli enti non commerciali per l'attività commerciale (art.
 *   145 TUIR);
 * - associazioni sportive dilettantistiche e senza fine di lucro che
 *   optano per la L. 398/1991.
 */
enum RegimeFiscale: string
{

    case ordinario = 'ORD';
    case semplificato = 'SEM';
    case forfetario = 'FOR';
    case vantaggio = 'VAN';
    case forfetarioEnc = 'ENC';
    case associazioniSportive = 'L398';

    public function getLabel(): string
    {
        return match ($this) {
            self::ordinario => 'Ordinario (contabilità ordinaria)',
            self::semplificato => 'Semplificato (contabilità semplificata)',
            self::forfetario => 'Forfetario (L. 190/2014)',
            self::vantaggio => 'Di vantaggio (art. 27 D.L. 98/2011)',
            self::forfetarioEnc => 'Forfetario degli enti non commerciali (art. 145 TUIR)',
            self::associazioniSportive => 'Associazioni sportive e senza fine di lucro (L. 398/1991)',
        };
    }

    /**
     * Regime ammesso per il tipo di soggetto: forfetario e di vantaggio
     * solo per le persone fisiche con attività, l'ordinario per le società
     * di capitali, i regimi degli enti solo per gli enti non commerciali.
     */
    public function isApplicabile(TipoSoggetto $tipoSoggetto, ?TipoAttivita $tipoAttivita = null, bool $enteNonCommerciale = false): bool
    {
        if ($tipoSoggetto === TipoSoggetto::personaFisica) {
            return ($tipoAttivita !== null) && in_array($this, [self::ordinario, self::semplificato, self::forfetario, self::vantaggio], true);
        }
        return match ($tipoSoggetto) {
            TipoSoggetto::societaCapitali => $this === self::ordinario,
            TipoSoggetto::societaPersone => in_array($this, [self::ordinario, self::semplificato], true),
            default => in_array($this, [self::ordinario, self::semplificato], true) || ($enteNonCommerciale && in_array($this, [self::forfetarioEnc, self::associazioniSportive], true)),
        };
    }

    /**
     * @return RegimeFiscale[]
     */
    public static function perSoggetto(TipoSoggetto $tipoSoggetto, ?TipoAttivita $tipoAttivita = null, bool $enteNonCommerciale = false): array
    {
        return array_values(array_filter(self::cases(), fn(self $regime) => $regime->isApplicabile($tipoSoggetto, $tipoAttivita, $enteNonCommerciale)));
    }

    /**
     * Il regime prevede le liquidazioni periodiche dell'IVA: non le
     * prevedono il forfetario (operazioni senza rivalsa dell'IVA, comma 58)
     * e il regime di vantaggio (esonero, art. 27, c. 2, D.L. 98/2011).
     */
    public function isLiquidazioneIva(): bool
    {
        return ($this !== self::forfetario) && ($this !== self::vantaggio);
    }

    /**
     * Prima data da cui il regime è previsto dalla legge (null = nessun
     * limite rilevante per lo storico).
     */
    public function getDecorrenzaMinima(): ?string
    {
        return match ($this) {
            self::forfetario => '2015-01-01',
            self::vantaggio => '2012-01-01',
            default => null,
        };
    }

    /**
     * Esclusi dall'applicazione degli ISA (i forfetari e i contribuenti in
     * regime di vantaggio ne sono esclusi per legge).
     */
    public function isEsclusoDagliIsa(): bool
    {
        return ($this === self::forfetario) || ($this === self::vantaggio);
    }

    /**
     * Periodicità imposta dal regime: la L. 398/1991 prevede la liquidazione
     * e il versamento trimestrale dell'IVA (art. 9 D.P.R. 544/1999).
     */
    public function getPeriodicitaObbligata(): ?PeriodicitaIva
    {
        return $this === self::associazioniSportive ? PeriodicitaIva::trimestrale : null;
    }
}
