<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Models\RigaContabileModel;
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
 * Ritenute d'acconto operate dall'azienda come sostituto d'imposta sui
 * compensi di lavoro autonomo e sulle provvigioni (artt. 25 e 25-bis
 * D.P.R. 600/1973) e loro versamento.
 *
 * Le ritenute sono le righe sui conti "Erario c/ritenute da versare" delle
 * registrazioni con il cliente o fornitore (il percipiente), di norma il
 * pagamento al netto della ritenuta: avere meno dare, nel mese della
 * registrazione, cioè del pagamento in cui la ritenuta è operata. Le righe
 * senza controparte (es. il versamento in F24 o un giroconto) non contano.
 *
 * Il versamento è dovuto entro il 16 del mese successivo (art. 18 D.Lgs.
 * 241/1997 e art. 8 D.P.R. 602/1973; dal 2027 artt. 8 e 32 D.Lgs.
 * 33/2025), con il codice tributo 1040, che dal 2017 comprende anche le
 * provvigioni (risoluzione 13/E/2016), il mese di riferimento nel campo
 * della rateazione (00MM) e l'anno; termini festivi e del 16 agosto come
 * per tutti i versamenti unitari (art. 3-quater D.L. 16/2012).
 */
class RitenuteOperate
{

    public const CODICE_TRIBUTO = '1040';
    public const ORIGINE = 'Contabilita';

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return array<int, array{importo: float, righe: array<int, array{riga: RigaContabile, importo: float}>}>
     *         ritenute dei mesi con movimenti, per mese (1-12)
     */
    public function getPerMese(Soggetto $soggetto, int $anno): array
    {
        $mesi = [];
        $movimenti = (new RigaContabileModel($this->dataMapper))->getMovimenti($soggetto, null, new SismaDate($anno . '-01-01'), new SismaDate($anno . '-12-31'));
        foreach ($movimenti as $riga) {
            if (($riga->conto->funzione !== FunzioneConto::ritenuteDaVersare) || ($riga->registrazioneContabile->controparte === null)) {
                continue;
            }
            $mese = (int) $riga->dataRegistrazione->format('n');
            $importo = round($riga->avere - $riga->dare, 2);
            $mesi[$mese] ??= ['importo' => 0.0, 'righe' => []];
            $mesi[$mese]['importo'] = round($mesi[$mese]['importo'] + $importo, 2);
            $mesi[$mese]['righe'][] = ['riga' => $riga, 'importo' => $importo];
        }
        ksort($mesi);
        return $mesi;
    }

    public static function getScadenza(int $anno, int $mese): SismaDate
    {
        return Calendario::termineFiscale((new \DateTimeImmutable(sprintf('%04d-%02d-16', $anno, $mese)))->modify('+1 month'));
    }

    public static function getRiferimento(int $anno, int $mese): string
    {
        return sprintf('ritenute-%04d-%02d', $anno, $mese);
    }

    public static function getNorma(int $anno, int $mese): string
    {
        return self::getScadenza($anno, $mese)->format('Y') >= 2027 ? 'artt. 8 e 32 D.Lgs. 33/2025' : 'art. 18 D.Lgs. 241/1997 e art. 8 D.P.R. 602/1973';
    }

    /**
     * Registra (o aggiorna) il versamento delle ritenute del mese
     * nell'archivio di Deleghe F24; senza ritenute toglie quello registrato.
     */
    public function registra(Soggetto $soggetto, int $anno, int $mese): EsitoRegistrazione
    {
        if (($mese < 1) || ($mese > 12)) {
            throw new \InvalidArgumentException('Mese non valido.');
        }
        $importo = $this->getPerMese($soggetto, $anno)[$mese]['importo'] ?? 0.0;
        if ($importo < 0) {
            throw new \DomainException(sprintf('Ritenute di %02d/%d negative (€ %s): verificare le registrazioni del mese.', $mese, $anno, number_format($importo, 2, ',', '.')));
        }
        $versamenti = [];
        if ($importo > 0) {
            $versamento = new Versamento($this->dataMapper);
            $versamento->scadenza = self::getScadenza($anno, $mese);
            $versamento->sezione = SezioneF24::erario;
            $versamento->codiceTributo = (new CodiceTributoModel($this->dataMapper))->getByCodice(SezioneCodiceTributo::erario, self::CODICE_TRIBUTO)
                    ?? throw new \InvalidArgumentException('Codice tributo ' . self::CODICE_TRIBUTO . ' assente nelle tabelle di Deleghe F24.');
            $versamento->rateazione = sprintf('00%02d', $mese);
            $versamento->annoRiferimento = $anno;
            $versamento->importoDebito = $importo;
            $versamento->descrizione = sprintf('Ritenute lavoro autonomo e provvigioni %02d/%d', $mese, $anno);
            $versamenti[] = $versamento;
        }
        return (new ArchivioVersamenti($this->dataMapper))->registra($soggetto, self::ORIGINE, self::getRiferimento($anno, $mese), $versamenti);
    }
}
