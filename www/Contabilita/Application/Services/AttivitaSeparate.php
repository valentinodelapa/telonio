<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeDetrazioneIva;
use Contabilita\Application\Entities\AttivitaIva;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\AttivitaIvaModel;
use Contabilita\Application\Models\RegistroIvaModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Attività con contabilità separata (art. 36 D.P.R. 633/1972): ogni
 * registro o sezionale IVA appartiene a un'attività; le righe dei
 * registri degli acquisti senza attività (acquisti promiscui) si
 * ripartiscono tra le attività con le quote della riga (criteri
 * oggettivi, art. 36, c. 3); le righe delle vendite e dei corrispettivi
 * di registri senza attività vanno alla prima attività, con l'avviso.
 *
 * Periodicità: quella propria dell'attività (separazione obbligatoria) o
 * quella dell'azienda; l'azienda liquida mensilmente se almeno
 * un'attività è mensile e le attività trimestrali sono liquidate nel mese
 * di fine trimestre (liquidazione anticipata ai fini della
 * compensazione), salvo il quarto trimestre, che si chiude con la
 * dichiarazione annuale.
 */
class AttivitaSeparate
{

    /** @var array<string, AttivitaIva[]> */
    private array $attivita = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return AttivitaIva[] per numero (vuoto con la contabilità unificata)
     */
    public function getAttivita(Soggetto $soggetto, int $anno): array
    {
        return $this->attivita[$soggetto->id . '|' . $anno] ??= (new AttivitaIvaModel($this->dataMapper))->getAttiveNel($soggetto, $anno);
    }

    public function isSeparata(Soggetto $soggetto, int $anno): bool
    {
        return count($this->getAttivita($soggetto, $anno)) > 0;
    }

    public function getPeriodicita(AttivitaIva $attivita, PeriodicitaIva $periodicitaAzienda): PeriodicitaIva
    {
        return $attivita->periodicita ?? $periodicitaAzienda;
    }

    /**
     * Regime speciale agricolo dell'attività o, senza, dell'azienda.
     */
    public function getRegimeDetrazione(Soggetto $soggetto, int $anno, ?AttivitaIva $attivita = null): ?RegimeDetrazioneIva
    {
        return $attivita?->regimeDetrazioneIva ?? (new RegimeIva($this->dataMapper))->getDatiFiscali($soggetto, $anno)?->regimeDetrazioneIva;
    }

    /**
     * L'azienda o una sua attività applica le percentuali di compensazione.
     */
    /**
     * Impresa agricola che applica l'IVA nei modi ordinari per opzione: può
     * avere connesse occasionali (art. 34-bis, rigo VF62).
     */
    public function hasOpzioneAgricola(Soggetto $soggetto, int $anno): bool
    {
        if (count((new RegimeIva($this->dataMapper))->getDatiFiscali($soggetto, $anno)?->getOpzioniOrdinarie() ?? []) > 0) {
            return true;
        }
        return count(array_filter($this->getAttivita($soggetto, $anno), fn(AttivitaIva $attivita) => $attivita->isOpzioneAttivaNel($anno))) > 0;
    }

    public function hasCompensazione(Soggetto $soggetto, int $anno): bool
    {
        foreach ([null, ...array_values($this->getAttivita($soggetto, $anno))] as $attivita) {
            if ($this->getRegimeDetrazione($soggetto, $anno, $attivita)?->isCompensazione() ?? false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Quote della riga per numero di attività (somma 1); vuoto se la riga
     * di un acquisto promiscuo non è ripartita.
     *
     * @return array<int, float>
     */
    public function getQuote(RigaIva $riga, Soggetto $soggetto, int $anno): array
    {
        $attivita = $this->getAttivita($soggetto, $anno);
        if (count($attivita) === 0) {
            return [0 => 1.0];
        }
        $propria = $riga->registroIva->attivitaIva;
        if (($propria !== null) && isset($attivita[$propria->numero])) {
            return [$propria->numero => 1.0];
        }
        if ($riga->registroIva->tipo !== TipoRegistroIva::acquisti) {
            return [array_key_first($attivita) => 1.0];
        }
        $quote = [];
        foreach ($riga->getRipartizione() as $numero => $percentuale) {
            if (isset($attivita[$numero])) {
                $quote[$numero] = $percentuale / 100;
            }
        }
        return abs(array_sum($quote) - 1) < 0.0001 ? $quote : [];
    }

    /**
     * Attività a cui va la ventilazione dei corrispettivi: quella del
     * registro dei corrispettivi (la prima se nessuno è assegnato).
     */
    public function getAttivitaVentilazione(Soggetto $soggetto, int $anno): ?AttivitaIva
    {
        $attivita = $this->getAttivita($soggetto, $anno);
        foreach ((new RegistroIvaModel($this->dataMapper))->getPerTipo($soggetto, TipoRegistroIva::corrispettivi) as $registro) {
            if (($registro->attivitaIva !== null) && isset($attivita[$registro->attivitaIva->numero])) {
                return $attivita[$registro->attivitaIva->numero];
            }
        }
        return count($attivita) === 0 ? null : $attivita[array_key_first($attivita)];
    }

    /**
     * Controllo della ripartizione indicata sulla riga: quote positive
     * di attività esistenti con somma 100.
     */
    public static function verificaRipartizione(array $quote, array $attivita): ?string
    {
        if (count($quote) === 0) {
            return null;
        }
        foreach (array_keys($quote) as $numero) {
            if (isset($attivita[$numero]) === false) {
                return 'Ripartizione: l\'attività n. ' . $numero . ' non esiste.';
            }
        }
        if (abs(array_sum($quote) - 100) > 0.001) {
            return 'Ripartizione: le quote delle attività devono sommare 100.';
        }
        return null;
    }
}
