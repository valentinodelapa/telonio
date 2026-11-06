<?php

namespace Dichiarativi\Application\Services\Iva;

use Anagrafica\Application\Entities\Soggetto;
use Deleghe\Application\Entities\Credito;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Models\CreditoModel;
use Deleghe\Application\Models\VersamentoModel;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Enumerations\StatoDichiarazione;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Crediti IVA trimestrali dei modelli IVA TR presentati nell'anno, per la
 * dichiarazione annuale: rimborsi infrannuali richiesti (VL20, VW20 e VS
 * campo 5 del gruppo) e crediti dei primi tre trimestri compensati in F24
 * (VL22, VW22) dagli utilizzi dei crediti 6036-6038 dell'archivio di
 * Deleghe, fino alla data di presentazione della dichiarazione annuale.
 */
class CreditiTrimestrali
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Rimborsi infrannuali richiesti con i modelli IVA TR presentati (TD6
     * del soggetto, TE50 del gruppo per la controllante).
     */
    public function getRimborsiRichiesti(Soggetto $soggetto, int $anno): int
    {
        $totale = 0.0;
        foreach ($this->getQuadriPresentati($soggetto, $anno) as $quadri) {
            $totale += $quadri->getDecimale('TD006001', 1) + $quadri->getDecimale('TE050001', 1);
        }
        return (int) round($totale);
    }

    /**
     * Rimborso infrannuale chiesto dalla controllante per una società del
     * gruppo (rigo TD9 dei moduli del prospetto TE).
     */
    public function getRimborsoSocieta(Soggetto $controllante, int $anno, ?string $partitaIva): int
    {
        $totale = 0.0;
        foreach ($this->getQuadriPresentati($controllante, $anno) as $quadri) {
            foreach ($quadri->getModuli() as $modulo) {
                if (($partitaIva !== null) && ($quadri->get('TD009001', $modulo) === $partitaIva)) {
                    $totale += $quadri->getDecimale('TD009002', $modulo);
                }
            }
        }
        return (int) round($totale);
    }

    /**
     * Crediti dei primi tre trimestri utilizzati in compensazione nelle
     * deleghe F24 fino alla data di presentazione della dichiarazione.
     */
    public function getCompensati(Soggetto $soggetto, int $anno, SismaDate $entro): int
    {
        $centesimi = 0;
        foreach ($this->getCrediti($soggetto, $anno) as $credito) {
            $centesimi += $this->getUtilizzatoEntro($credito, $entro);
        }
        return (int) round($centesimi / 100);
    }

    /**
     * Utilizzi dei crediti trimestrali non compresi in getCompensati():
     * deleghe non trasmesse o successive alla data.
     */
    public function getEsclusi(Soggetto $soggetto, int $anno, SismaDate $entro): int
    {
        $model = new CreditoModel($this->dataMapper);
        $centesimi = 0;
        foreach ($this->getCrediti($soggetto, $anno) as $credito) {
            $centesimi += $model->getUtilizzato($credito) - $this->getUtilizzatoEntro($credito, $entro);
        }
        return (int) round($centesimi / 100);
    }

    /**
     * Parte del credito compensata fino alla data, in centesimi: nelle
     * deleghe trasmesse o quietanzate con scadenza non successiva.
     */
    public function getUtilizzatoEntro(Credito $credito, SismaDate $entro): int
    {
        $centesimi = 0;
        foreach ((new VersamentoModel($this->dataMapper))->getUtilizzi($credito) as $utilizzo) {
            if (in_array($utilizzo->delega?->stato, [StatoDelega::trasmessa, StatoDelega::quietanzata], true) && ($utilizzo->scadenza->format('Y-m-d') <= $entro->format('Y-m-d'))) {
                $centesimi += (int) round($utilizzo->importoCredito * 100);
            }
        }
        return $centesimi;
    }

    /**
     * Crediti utilizzati nelle deleghe con scadenza nell'anno solare, per
     * il limite annuale delle compensazioni.
     */
    public function getCompensatiNellAnno(Soggetto $soggetto, int $anno): int
    {
        $versamenti = new VersamentoModel($this->dataMapper);
        $totale = 0.0;
        foreach ((new CreditoModel($this->dataMapper))->getElenco($soggetto) as $credito) {
            foreach ($versamenti->getUtilizzi($credito) as $utilizzo) {
                if ((int) $utilizzo->scadenza->format('Y') === $anno) {
                    $totale += $utilizzo->importoCredito;
                }
            }
        }
        return (int) round($totale);
    }

    /**
     * Data fino a cui contano le compensazioni: la presentazione della
     * dichiarazione o, finché non è presentata, la sua scadenza.
     */
    public static function getDataLimite(Dichiarazione $dichiarazione): SismaDate
    {
        return $dichiarazione->dataPresentazione ?? $dichiarazione->getScadenza();
    }

    /**
     * @return Credito[]
     */
    private function getCrediti(Soggetto $soggetto, int $anno): array
    {
        $crediti = [];
        foreach ((new CreditoModel($this->dataMapper))->getElenco($soggetto) as $credito) {
            if (($credito->annoRiferimento === $anno) && in_array($credito->codiceTributo?->codice, VersamentiTr::CODICI, true)) {
                $crediti[] = $credito;
            }
        }
        return $crediti;
    }

    /**
     * @return QuadriDichiarazione[]
     */
    private function getQuadriPresentati(Soggetto $soggetto, int $anno): array
    {
        $elenco = [];
        foreach ((new DichiarazioneModel($this->dataMapper))->getTrimestrali($soggetto, ModelloDichiarativo::ivaTr, $anno) as $dichiarazione) {
            if ($dichiarazione->stato === StatoDichiarazione::presentata) {
                $elenco[] = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
            }
        }
        return $elenco;
    }
}
