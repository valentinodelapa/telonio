<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Enumerations\NaturaIva;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\RigaIvaModel;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Models\CodiceTributoModel;
use Deleghe\Application\Services\ArchivioVersamenti;
use Deleghe\Application\Services\EsitoRegistrazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;
use Telonio\Application\HelperClasses\Calendario;

/**
 * Imposta di bollo assolta in modo virtuale sulle fatture elettroniche
 * emesse (art. 6, c. 2, D.M. 17 giugno 2014), indicata nelle registrazioni
 * delle vendite (letta dai DatiBollo della fattura importata o inserita a
 * mano), e suo versamento per trimestre solare della data del documento:
 *
 * - termini: ultimo giorno del secondo mese dopo il trimestre (31 maggio,
 *   30 novembre, fine febbraio dell'anno dopo per il quarto), del terzo per
 *   il secondo trimestre (30 settembre), con lo slittamento al primo giorno
 *   lavorativo;
 * - codici tributo 2521-2524 (risoluzione 42/E/2019);
 * - senza interessi e sanzioni, l'imposta del primo trimestre inferiore a
 *   5.000 euro si versa nel termine del secondo e quella del primo e del
 *   secondo trimestre, se insieme inferiore a 5.000 euro, nel termine del
 *   terzo (art. 17, c. 1-bis, D.L. 124/2019; dal 2027 art. 152, c. 2,
 *   D.Lgs. 123/2025), ciascuna con il codice del suo trimestre.
 *
 * L'importo da versare è quello calcolato dall'Agenzia delle entrate sulle
 * fatture inviate al Sistema di interscambio, che integra quelle senza
 * l'annotazione del bollo: il riepilogo segnala le fatture con operazioni
 * senza IVA oltre 77,47 euro e senza bollo, da verificare.
 */
class ImpostaBolloFatture
{

    public const ORIGINE = 'Contabilita';
    public const IMPORTO = 2.0;
    /** importo delle operazioni senza IVA oltre il quale la fattura sconta il bollo */
    public const SOGLIA_FATTURA = 77.47;
    /** imposta sotto la quale il versamento del trimestre si differisce */
    public const SOGLIA_DIFFERIMENTO = 5000.0;
    public const CODICI_TRIBUTO = [1 => '2521', 2 => '2522', 3 => '2523', 4 => '2524'];
    /**
     * Nature senza IVA soggette al bollo: escluse le esportazioni di merci e
     * le cessioni intracomunitarie (art. 15 della tabella B del D.P.R.
     * 642/1972 e art. 66, c. 5, D.L. 331/1993; dal 2027 art. 19, c. 3 e 6,
     * della tabella dell'allegato 3 del D.Lgs. 123/2025), San Marino, il
     * regime del margine e l'inversione contabile, in cui l'IVA è applicata.
     */
    public const NATURE_SOGGETTE = [NaturaIva::escluse, NaturaIva::nonSoggetteTerritorialita, NaturaIva::nonSoggetteAltri, NaturaIva::assimilateEsportazioni,
        NaturaIva::dichiarazioniIntento, NaturaIva::nonImponibiliAltre, NaturaIva::esenti];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Fatture e note emesse nell'anno (data del documento), con il bollo
     * indicato e, senza bollo, quelle da verificare.
     *
     * @return array{trimestri: array<int, array{fatture: int, importo: float}>, daVerificare: array<int, array{registrazione: RegistrazioneContabile, senzaIva: float}>}
     */
    public function riepiloga(Soggetto $soggetto, int $anno): array
    {
        $trimestri = array_fill_keys([1, 2, 3, 4], ['fatture' => 0, 'importo' => 0.0]);
        $documenti = [];
        // per le vendite la competenza IVA è la data del documento
        foreach ((new RigaIvaModel($this->dataMapper))->getPerPeriodo($soggetto, new SismaDate($anno . '-01-01'), new SismaDate($anno . '-12-31')) as $riga) {
            $registrazione = $riga->registrazioneContabile;
            if ($registrazione->causale->getTipoRegistroIva() !== TipoRegistroIva::vendite) {
                continue;
            }
            $documenti[$registrazione->id] ??= ['registrazione' => $registrazione, 'senzaIva' => 0.0];
            if (in_array($riga->codiceIva->natura, self::NATURE_SOGGETTE, true)) {
                $documenti[$registrazione->id]['senzaIva'] = round($documenti[$registrazione->id]['senzaIva'] + abs($riga->imponibile), 2);
            }
        }
        $daVerificare = [];
        foreach ($documenti as $documento) {
            $registrazione = $documento['registrazione'];
            if (($registrazione->impostaBollo ?? 0) > 0) {
                $data = $registrazione->dataDocumento ?? $registrazione->dataCompetenzaIva;
                $trimestre = intdiv((int) $data->format('n') - 1, 3) + 1;
                $trimestri[$trimestre]['fatture']++;
                $trimestri[$trimestre]['importo'] = round($trimestri[$trimestre]['importo'] + $registrazione->impostaBollo, 2);
            } elseif ($documento['senzaIva'] > self::SOGLIA_FATTURA) {
                $daVerificare[] = $documento;
            }
        }
        return ['trimestri' => $trimestri, 'daVerificare' => $daVerificare];
    }

    /**
     * Termine ordinario del trimestre (D.M. 17 giugno 2014, art. 6, c. 2).
     */
    public static function getScadenza(int $anno, int $trimestre): SismaDate
    {
        $data = match ($trimestre) {
            1 => $anno . '-05-31',
            2 => $anno . '-09-30',
            3 => $anno . '-11-30',
            4 => (new \DateTimeImmutable(($anno + 1) . '-02-01'))->modify('last day of this month')->format('Y-m-d'),
            default => throw new \InvalidArgumentException('Trimestre non valido.'),
        };
        return Calendario::termineFiscale(new \DateTimeImmutable($data), false);
    }

    /**
     * Versamenti dei trimestri con imposta, con i differimenti sotto i
     * 5.000 euro.
     *
     * @param array<int, float> $importi imposta per trimestre (1-4)
     * @return array<int, array{codice: string, scadenza: SismaDate, importo: float, differito: bool}> per trimestre
     */
    public static function pianoVersamenti(int $anno, array $importi): array
    {
        $primo = round($importi[1] ?? 0.0, 2);
        $secondo = round($importi[2] ?? 0.0, 2);
        $termine = [1 => 1, 2 => 2, 3 => 3, 4 => 4];
        if (round($primo + $secondo, 2) < self::SOGLIA_DIFFERIMENTO) {
            $termine[1] = $termine[2] = 3;
        } elseif ($primo < self::SOGLIA_DIFFERIMENTO) {
            $termine[1] = 2;
        }
        $piano = [];
        foreach (self::CODICI_TRIBUTO as $trimestre => $codice) {
            $importo = round($importi[$trimestre] ?? 0.0, 2);
            if ($importo > 0) {
                $piano[$trimestre] = ['codice' => $codice, 'scadenza' => self::getScadenza($anno, $termine[$trimestre]), 'importo' => $importo, 'differito' => $termine[$trimestre] !== $trimestre];
            }
        }
        return $piano;
    }

    public static function getRiferimento(int $anno): string
    {
        return 'bollo-fatture-' . $anno;
    }

    public static function getNormaDifferimento(\DateTimeInterface $scadenza): string
    {
        return (int) $scadenza->format('Y') >= 2027 ? 'art. 152, c. 2, D.Lgs. 123/2025' : 'art. 17, c. 1-bis, D.L. 124/2019';
    }

    /**
     * Registra (o aggiorna) i versamenti dell'anno nell'archivio di Deleghe
     * F24, uno per trimestre con imposta.
     */
    public function registra(Soggetto $soggetto, int $anno): EsitoRegistrazione
    {
        $importi = array_map(fn(array $trimestre) => $trimestre['importo'], $this->riepiloga($soggetto, $anno)['trimestri']);
        $codici = new CodiceTributoModel($this->dataMapper);
        $versamenti = [];
        foreach (self::pianoVersamenti($anno, $importi) as $trimestre => $dati) {
            $versamento = new Versamento($this->dataMapper);
            $versamento->scadenza = $dati['scadenza'];
            $versamento->sezione = SezioneF24::erario;
            $versamento->codiceTributo = $codici->getByCodice(SezioneCodiceTributo::erario, $dati['codice'])
                    ?? throw new \InvalidArgumentException('Codice tributo ' . $dati['codice'] . ' assente nelle tabelle di Deleghe F24.');
            $versamento->annoRiferimento = $anno;
            $versamento->importoDebito = $dati['importo'];
            $versamento->descrizione = sprintf('Bollo fatture elettroniche %d° trimestre %d', $trimestre, $anno);
            $versamenti[] = $versamento;
        }
        return (new ArchivioVersamenti($this->dataMapper))->registra($soggetto, self::ORIGINE, self::getRiferimento($anno), $versamenti);
    }
}
