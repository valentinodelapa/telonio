<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Enumerations\TipoAttivita;
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
 * Contabilità dei contribuenti nel regime forfetario (art. 1, cc. 54-89,
 * L. 190/2014; dal 2027 artt. 232-239 D.Lgs. 117/2026), che non liquidano
 * l'IVA:
 *
 * - soglie dei ricavi e compensi percepiti nell'anno (per cassa, come nel
 *   quadro LM: ComponentiReddito::perCassa): oltre 85.000 euro il regime
 *   cessa dall'anno successivo (c. 54, lett. a, e c. 71; dal 2027 artt.
 *   232, c. 1, lett. a, e 238), oltre 100.000 euro cessa nell'anno stesso
 *   e l'IVA è dovuta dalle operazioni che superano il limite (c. 71; art.
 *   238, c. 1);
 * - IVA degli acquisti di cui sono debitori in inversione contabile,
 *   compresi gli acquisti intracomunitari (codici di integrazione delle
 *   righe IVA): versata entro il 16 del secondo mese successivo al
 *   trimestre (c. 58, lett. e-bis, inserita dal D.Lgs. 81/2025; dal 2027
 *   art. 234, c. 1, lett. f), per le operazioni dal 1° ottobre 2025
 *   (relazione illustrativa; altri commenti indicano il 13 giugno 2025),
 *   prima entro il 16 del mese successivo (c. 60; art. 234, c. 3).
 *
 * Per il versamento trimestrale non risultano codici tributo dedicati: si
 * propone quello dell'ultimo mese del trimestre (6003, 6006, 6009, 6012),
 * indicato dalla prassi, da verificare prima della delega.
 */
class RegimeForfetario
{

    public const ORIGINE = 'Contabilita';
    public const SOGLIA_REQUISITO = 85000.0;
    public const SOGLIA_CESSAZIONE = 100000.0;
    public const DECORRENZA_TRIMESTRALE = '2025-10-01';

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public function isForfetario(Soggetto $soggetto, int $anno): bool
    {
        return (new RegimeIva($this->dataMapper))->getDatiFiscali($soggetto, $anno)?->regimeFiscale === RegimeFiscale::forfetario;
    }

    /**
     * Ricavi o compensi percepiti nell'anno (per cassa).
     */
    public function getPercepiti(Soggetto $soggetto, int $anno): float
    {
        $professionista = (new RegimeIva($this->dataMapper))->getDatiFiscali($soggetto, $anno)?->tipoAttivita === TipoAttivita::lavoroAutonomo;
        $componenti = new ComponentiReddito($this->dataMapper);
        $componenti->perCassa($soggetto, $anno, $professionista);
        return $componenti->getTotaliCassa()['ricavi'];
    }

    /**
     * Esito delle soglie: null entro gli 85.000 euro, altrimenti il
     * messaggio.
     */
    public static function verificaSoglie(float $percepiti, int $anno): ?string
    {
        $norma2027 = $anno >= 2027;
        if ($percepiti > self::SOGLIA_CESSAZIONE) {
            return sprintf('Ricavi e compensi percepiti oltre 100.000 euro (€ %s): il regime forfetario cessa nel %d stesso e l\'IVA è dovuta dalle operazioni che hanno superato il limite (%s).',
                    number_format($percepiti, 2, ',', '.'), $anno, $norma2027 ? 'art. 238, c. 1, D.Lgs. 117/2026' : 'art. 1, c. 71, L. 190/2014');
        }
        if ($percepiti > self::SOGLIA_REQUISITO) {
            return sprintf('Ricavi e compensi percepiti oltre 85.000 euro (€ %s): il regime forfetario cessa dal %d (%s).',
                    number_format($percepiti, 2, ',', '.'), $anno + 1, $norma2027 ? 'artt. 232, c. 1, lett. a), e 238, c. 1, D.Lgs. 117/2026' : 'art. 1, cc. 54 e 71, L. 190/2014');
        }
        return null;
    }

    /**
     * IVA dovuta sugli acquisti in inversione contabile per periodo di
     * versamento (mese fino a settembre 2025, poi trimestre).
     *
     * @return array<string, array{descrizione: string, imposta: float, documenti: int, scadenza: SismaDate, codice: string, trimestrale: bool}>
     */
    public function getIvaDovuta(Soggetto $soggetto, int $anno): array
    {
        $periodi = [];
        $documenti = [];
        foreach ((new RigaIvaModel($this->dataMapper))->getPerPeriodo($soggetto, new SismaDate($anno . '-01-01'), new SismaDate($anno . '-12-31')) as $riga) {
            if (($riga->registroIva->tipo !== TipoRegistroIva::acquisti) || ($riga->codiceIva->inversioneContabile === false)) {
                continue;
            }
            $chiave = self::getPeriodo($riga->dataCompetenzaIva);
            $periodi[$chiave] ??= self::descriviPeriodo($chiave, $anno) + ['imposta' => 0.0, 'documenti' => 0];
            $periodi[$chiave]['imposta'] = round($periodi[$chiave]['imposta'] + $riga->imposta, 2);
            $documenti[$chiave][$riga->registrazioneContabile->id] = true;
        }
        foreach ($periodi as $chiave => &$periodo) {
            $periodo['documenti'] = count($documenti[$chiave]);
        }
        unset($periodo);
        ksort($periodi);
        return $periodi;
    }

    /**
     * Periodo di versamento dell'operazione: "M01".."M12" o "T1".."T4".
     */
    public static function getPeriodo(\DateTimeInterface $data): string
    {
        $mese = (int) $data->format('n');
        return $data->format('Y-m-d') >= self::DECORRENZA_TRIMESTRALE ? 'T' . intdiv($mese + 2, 3) : sprintf('M%02d', $mese);
    }

    /**
     * @return array{descrizione: string, scadenza: SismaDate, codice: string, trimestrale: bool}
     */
    public static function descriviPeriodo(string $chiave, int $anno): array
    {
        $numero = (int) substr($chiave, 1);
        $trimestrale = $chiave[0] === 'T';
        $ultimoMese = $trimestrale ? $numero * 3 : $numero;
        // mensile: 16 del mese successivo; trimestrale: 16 del secondo mese successivo al trimestre
        $termine = (new \DateTimeImmutable(sprintf('%04d-%02d-16', $anno, $ultimoMese)))->modify($trimestrale ? '+2 months' : '+1 month');
        return ['descrizione' => $trimestrale ? $numero . '° trimestre ' . $anno : sprintf('%02d/%d', $numero, $anno), 'scadenza' => Calendario::termineFiscale($termine),
            'codice' => sprintf('60%02d', $ultimoMese), 'trimestrale' => $trimestrale];
    }

    public static function getRiferimento(int $anno, string $chiave): string
    {
        return 'iva-forfetario-' . $anno . '-' . $chiave;
    }

    /**
     * Registra (o aggiorna) il versamento dell'IVA del periodo
     * nell'archivio di Deleghe F24.
     */
    public function registra(Soggetto $soggetto, int $anno, string $chiave): EsitoRegistrazione
    {
        if (preg_match('/^(M(0[1-9]|1[0-2])|T[1-4])$/', $chiave) !== 1) {
            throw new \InvalidArgumentException('Periodo non valido.');
        }
        $periodo = $this->getIvaDovuta($soggetto, $anno)[$chiave] ?? null;
        $versamenti = [];
        if (($periodo !== null) && ($periodo['imposta'] > 0)) {
            $versamento = new Versamento($this->dataMapper);
            $versamento->scadenza = $periodo['scadenza'];
            $versamento->sezione = SezioneF24::erario;
            $versamento->codiceTributo = (new CodiceTributoModel($this->dataMapper))->getByCodice(SezioneCodiceTributo::erario, $periodo['codice'])
                    ?? throw new \InvalidArgumentException('Codice tributo ' . $periodo['codice'] . ' assente nelle tabelle di Deleghe F24.');
            $versamento->annoRiferimento = $anno;
            $versamento->importoDebito = $periodo['imposta'];
            $versamento->descrizione = 'IVA acquisti in inversione contabile (forfetario) ' . $periodo['descrizione'];
            $versamenti[] = $versamento;
        }
        return (new ArchivioVersamenti($this->dataMapper))->registra($soggetto, self::ORIGINE, self::getRiferimento($anno, $chiave), $versamenti);
    }
}
