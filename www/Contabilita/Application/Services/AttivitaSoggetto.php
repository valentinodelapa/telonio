<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\Attivita;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\NaturaAttivita;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\AttivitaModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Attività del soggetto come dimensione della contabilità (Attivita): le
 * righe contabili hanno l'attività o sono comuni, e ogni imposta ripartisce
 * le righe comuni con il proprio criterio:
 *
 * - IVA: attività separate (art. 36 D.P.R. 633/1972, Services\AttivitaSeparate)
 *   e, per gli enti non commerciali, detrazione solo per la quota degli
 *   acquisti imputabile all'attività commerciale, con criteri oggettivi
 *   coerenti con la natura dei beni e servizi (art. 19-ter D.P.R. 633/1972,
 *   nel testo vigente dal 13 dicembre 2025 al 31 dicembre 2026): quote della
 *   riga IVA, obbligatorie per gli acquisti comuni (quotaCommerciale);
 * - imposte sui redditi: quote della riga se indicate, altrimenti il
 *   rapporto tra i ricavi commerciali e il totale dei ricavi e proventi
 *   (art. 144, c. 4, TUIR; Services\ComponentiReddito);
 * - IRAP degli enti: la stessa parte istituzionale (art. 10, c. 2, D.Lgs.
 *   446/1997).
 *
 * Proposta dell'attività della riga al salvataggio: quella indicata, poi
 * quella del registro IVA della registrazione, poi quella predefinita del
 * conto; altrimenti la riga è comune. Una riga indicata come comune ha la
 * ripartizione (vuota o con le quote) e non riceve proposte. I ricavi non
 * possono essere comuni.
 */
class AttivitaSoggetto
{

    /** @var array<string, Attivita[]> */
    private array $attivita = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Attività dell'anno, di ogni natura (vuoto: un'attività implicita).
     *
     * @return Attivita[] per numero
     */
    public function getAttivita(Soggetto $soggetto, int $anno): array
    {
        return $this->attivita[$soggetto->id . '|' . $anno] ??= (new AttivitaModel($this->dataMapper))->getAttiveNel($soggetto, $anno);
    }

    /**
     * Più attività nell'anno: le righe hanno l'attività o sono comuni.
     */
    public function isPlurima(Soggetto $soggetto, int $anno): bool
    {
        return count($this->getAttivita($soggetto, $anno)) >= 2;
    }

    /**
     * Ente non commerciale con l'attività istituzionale nell'anno.
     */
    public function hasIstituzionale(Soggetto $soggetto, int $anno): bool
    {
        return count(array_filter($this->getAttivita($soggetto, $anno), fn(Attivita $attivita) => $attivita->natura === NaturaAttivita::istituzionale)) > 0;
    }

    /**
     * Attività proposta per la riga: quella indicata, del registro IVA, del
     * conto (solo se è dell'anno); null per le righe comuni o per i
     * soggetti con un'attività sola.
     */
    public function proponi(RegistrazioneContabile $registrazione, RigaContabile $riga): ?Attivita
    {
        $anno = (int) $registrazione->dataRegistrazione->format('Y');
        if ($this->isPlurima($registrazione->soggetto, $anno) === false) {
            return null;
        }
        $elenco = $this->getAttivita($registrazione->soggetto, $anno);
        foreach ([$riga->attivita, $registrazione->registroIva?->attivita, $riga->conto->attivita] as $attivita) {
            if (($attivita !== null) && isset($elenco[$attivita->numero]) && ($elenco[$attivita->numero]->id === $attivita->id)) {
                return $attivita;
            }
        }
        return null;
    }

    /**
     * Attività delle righe economiche (proposta) e quote delle righe comuni
     * dalle righe IVA dello stesso conto; quota commerciale delle righe IVA
     * degli acquisti degli enti.
     *
     * @param RigaIva[] $righeIva
     * @param RigaContabile[] $righe
     */
    public function applica(RegistrazioneContabile $registrazione, array $righeIva, array $righe = []): void
    {
        $this->applicaQuotaCommerciale($registrazione, $righeIva);
        foreach ($righe as $riga) {
            if ($riga->conto->natura->isEconomico() === false) {
                $riga->attivita = null;
                $riga->ripartizione = null;
                continue;
            }
            // riga indicata come comune (ripartizione vuota o con le quote): nessuna proposta
            if (($riga->attivita === null) && ($riga->ripartizione !== null)) {
                continue;
            }
            $riga->attivita = $this->proponi($registrazione, $riga);
            if ($riga->attivita !== null) {
                $riga->ripartizione = null;
                continue;
            }
            if ($riga->ripartizione === null) {
                foreach ($righeIva as $rigaIva) {
                    if (($rigaIva->conto?->id === $riga->conto->id) && ($rigaIva->ripartizione !== null)) {
                        $riga->ripartizione = $rigaIva->ripartizione;
                        break;
                    }
                }
            }
        }
    }

    /**
     * Enti non commerciali: quota commerciale delle righe IVA degli
     * acquisti dall'attività del registro o dalle quote della riga (null per
     * le imprese e per le righe comuni senza quote, che il controllo
     * segnala).
     *
     * @param RigaIva[] $righeIva
     */
    public function applicaQuotaCommerciale(RegistrazioneContabile $registrazione, array $righeIva): void
    {
        $anno = (int) $registrazione->dataRegistrazione->format('Y');
        $ente = $this->hasIstituzionale($registrazione->soggetto, $anno);
        foreach ($righeIva as $riga) {
            $riga->quotaCommerciale = $ente && ($registrazione->registroIva?->tipo === TipoRegistroIva::acquisti)
                    ? self::getQuotaCommerciale($registrazione->registroIva?->attivita, $riga->getRipartizione(), $this->getAttivita($registrazione->soggetto, $anno))
                    : null;
        }
    }

    /**
     * Quota commerciale di un acquisto: 1 o 0 dall'attività del registro,
     * altrimenti la somma delle quote delle attività commerciali; null per
     * l'acquisto comune senza quote.
     *
     * @param array<int, float> $ripartizione percentuali per numero di attività
     * @param Attivita[] $elenco attività per numero
     */
    public static function getQuotaCommerciale(?Attivita $attivitaRegistro, array $ripartizione, array $elenco): ?float
    {
        if ($attivitaRegistro !== null) {
            return $attivitaRegistro->isCommerciale() ? 1.0 : 0.0;
        }
        if (count($ripartizione) === 0) {
            return null;
        }
        $quota = 0.0;
        foreach ($ripartizione as $numero => $percentuale) {
            if (isset($elenco[$numero]) && $elenco[$numero]->isCommerciale()) {
                $quota += $percentuale / 100;
            }
        }
        return round($quota, 6);
    }

    /**
     * Controlli dell'attività delle righe: ricavi di un soggetto con più
     * attività senza attività; acquisti comuni degli enti senza le quote
     * dell'IVA (art. 19-ter: la quota detraibile si determina con criteri
     * oggettivi); quote delle righe comuni.
     *
     * @param RigaIva[] $righeIva
     * @param RigaContabile[] $righe
     * @return string[]
     */
    public function verifica(RegistrazioneContabile $registrazione, array $righeIva, array $righe): array
    {
        $anno = (int) $registrazione->dataRegistrazione->format('Y');
        if ($this->isPlurima($registrazione->soggetto, $anno) === false) {
            return [];
        }
        $elenco = $this->getAttivita($registrazione->soggetto, $anno);
        $errori = [];
        foreach ($righe as $indice => $riga) {
            $comune = ($riga->attivita === null) && ($riga->ripartizione !== null);
            if (($riga->conto->natura === NaturaConto::ricavo) && ($comune || ($this->proponi($registrazione, $riga) === null))) {
                $errori[] = sprintf('Riga %d: indicare l\'attività del ricavo (conto %s): i ricavi non possono essere comuni a più attività.', $indice + 1, $riga->conto->codice);
            }
            $errore = self::verificaQuote($riga->getRipartizione(), $elenco);
            if ($errore !== null) {
                $errori[] = sprintf('Riga %d: %s', $indice + 1, lcfirst($errore));
            }
        }
        if ($this->hasIstituzionale($registrazione->soggetto, $anno) && ($registrazione->registroIva?->tipo === TipoRegistroIva::acquisti) && ($registrazione->registroIva->attivita === null)) {
            foreach ($righeIva as $indice => $riga) {
                if (count($riga->getRipartizione()) === 0) {
                    $errori[] = sprintf('Riga IVA %d: acquisto comune all\'attività commerciale e a quella istituzionale: indicare le quote delle attività, con criteri oggettivi coerenti con la natura del bene o del servizio (art. 19-ter D.P.R. 633/1972), oppure usare il registro di una delle attività.', $indice + 1);
                }
            }
        }
        return $errori;
    }

    /**
     * Quote positive di attività esistenti con somma 100 (vuote: nessun
     * controllo).
     *
     * @param array<int, float> $quote
     * @param Attivita[] $elenco
     */
    public static function verificaQuote(array $quote, array $elenco): ?string
    {
        if (count($quote) === 0) {
            return null;
        }
        foreach (array_keys($quote) as $numero) {
            if (isset($elenco[$numero]) === false) {
                return 'Ripartizione: l\'attività n. ' . $numero . ' non esiste.';
            }
        }
        if (abs(array_sum($quote) - 100) > 0.001) {
            return 'Ripartizione: le quote delle attività devono sommare 100.';
        }
        return null;
    }
}
