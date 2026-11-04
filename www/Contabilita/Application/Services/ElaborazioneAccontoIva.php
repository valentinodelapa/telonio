<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Contabilita\Application\Entities\AccontoIva;
use Contabilita\Application\Entities\GruppoIva;
use Contabilita\Application\Enumerations\MetodoAccontoIva;
use Contabilita\Application\Models\AccontoIvaModel;
use Contabilita\Application\Models\LiquidazioneGruppoIvaModel;
use Contabilita\Application\Models\LiquidazioneIvaModel;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Models\CodiceTributoModel;
use Deleghe\Application\Services\ArchivioVersamenti;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Acconto IVA (art. 6, c. 2, L. 405/1990): proposta della base con i tre
 * metodi e registrazione del versamento (6013 mensili, 6035 trimestrali)
 * entro il 27 dicembre nell'archivio di Deleghe F24.
 *
 * - storico: 88% del dovuto per l'ultimo periodo dell'anno precedente al
 *   lordo dell'acconto allora versato (mensili: liquidazione di dicembre;
 *   trimestrali: il saldo della dichiarazione annuale, qui approssimato
 *   con la liquidazione del quarto trimestre senza interessi);
 * - previsionale: 88% del dovuto presunto, indicato dallo studio;
 * - analitico: liquidazione delle operazioni registrate dal 1° dicembre
 *   (mensili) o dal 1° ottobre (trimestrali) al 20 dicembre, con il
 *   credito del periodo precedente.
 *
 * Non è dovuto sotto 103,29 euro.
 *
 * Per l'IVA di gruppo l'acconto lo determina e versa la controllante per
 * l'intero gruppo (circolare n. 52 del 1991) sulle liquidazioni di gruppo:
 * storico dall'ultimo periodo di gruppo dell'anno precedente, analitico
 * dalle operazioni di tutte le società partecipanti.
 */
class ElaborazioneAccontoIva
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Base proposta per il metodo (null per il previsionale o se mancano
     * i dati), con la descrizione della provenienza.
     *
     * @return array{0: ?float, 1: string}
     */
    public function proponiBase(Soggetto $soggetto, int $anno, PeriodicitaIva $periodicita, MetodoAccontoIva $metodo, ?GruppoIva $gruppo = null): array
    {
        if ($gruppo !== null) {
            return $this->proponiBaseGruppo($gruppo, $anno, $periodicita, $metodo);
        }
        $liquidazioneModel = new LiquidazioneIvaModel($this->dataMapper);
        $ultimo = PeriodiIva::getNumeroPeriodi($periodicita);
        switch ($metodo) {
            case MetodoAccontoIva::storico:
                $precedente = $liquidazioneModel->getPerPeriodo($soggetto, $anno - 1, $ultimo) ?? $liquidazioneModel->getPerPeriodo($soggetto, $anno - 1, $periodicita === PeriodicitaIva::trimestrale ? 12 : 4);
                if ($precedente === null) {
                    return [null, 'Liquidazione dell\'ultimo periodo del ' . ($anno - 1) . ' non elaborata: indicare la base.'];
                }
                $base = round($precedente->importoDovuto - $precedente->interessi + $precedente->acconto + $precedente->debitoRinviato, 2);
                return [max(0.0, $base), 'Dovuto per ' . $precedente->getDescrizionePeriodo() . ' al lordo dell\'acconto'
                    . ($precedente->periodicita === PeriodicitaIva::trimestrale ? ' (per i trimestrali verificare con il saldo della dichiarazione annuale)' : '') . '.'];
            case MetodoAccontoIva::analitico:
                $dal = $periodicita === PeriodicitaIva::trimestrale ? new SismaDate($anno . '-10-01') : new SismaDate($anno . '-12-01');
                $risultato = (new CalcoloLiquidazioneIva($this->dataMapper))->calcola($soggetto, $dal, new SismaDate($anno . '-12-20'));
                $precedente = $liquidazioneModel->getPerPeriodo($soggetto, $anno, $ultimo - 1);
                $base = round($risultato->getIvaPeriodo() - ($precedente?->getCreditoRiportato() ?? 0.0) + ($precedente?->debitoRinviato ?? 0.0), 2);
                return [max(0.0, $base), 'IVA delle operazioni dal ' . $dal->format('d/m/Y') . ' al 20/12/' . $anno . ($precedente === null ? ' (liquidazione del periodo precedente non elaborata: credito non considerato)' : ', con il credito del periodo precedente') . '.'];
            default:
                return [null, 'Indicare il dovuto presunto per l\'ultimo periodo dell\'anno.'];
        }
    }

    /**
     * @return array{0: ?float, 1: string}
     */
    private function proponiBaseGruppo(GruppoIva $gruppo, int $anno, PeriodicitaIva $periodicita, MetodoAccontoIva $metodo): array
    {
        $liquidazioni = new LiquidazioneGruppoIvaModel($this->dataMapper);
        $ultimo = PeriodiIva::getNumeroPeriodi($periodicita);
        switch ($metodo) {
            case MetodoAccontoIva::storico:
                $precedente = $liquidazioni->getPerPeriodo($gruppo, $anno - 1, $ultimo) ?? $liquidazioni->getPerPeriodo($gruppo, $anno - 1, $periodicita === PeriodicitaIva::trimestrale ? 12 : 4);
                if ($precedente === null) {
                    return [null, 'Liquidazione di gruppo dell\'ultimo periodo del ' . ($anno - 1) . ' non elaborata: indicare la base.'];
                }
                return [max(0.0, round($precedente->importoDovuto + $precedente->acconto + $precedente->debitoRinviato, 2)), 'Dovuto del gruppo per ' . $precedente->getDescrizionePeriodo() . ' al lordo dell\'acconto.'];
            case MetodoAccontoIva::analitico:
                $base = 0.0;
                $calcolo = new CalcoloLiquidazioneIva($this->dataMapper);
                $regime = new RegimeIva($this->dataMapper);
                foreach ((new LiquidazioneGruppo($this->dataMapper))->getSocieta($gruppo, $anno) as ['soggetto' => $societa]) {
                    $dal = $regime->getPeriodicita($societa, $anno) === PeriodicitaIva::trimestrale ? new SismaDate($anno . '-10-01') : new SismaDate($anno . '-12-01');
                    $base += $calcolo->calcola($societa, $dal, new SismaDate($anno . '-12-20'))->getIvaPeriodo();
                }
                $precedente = $liquidazioni->getPerPeriodo($gruppo, $anno, $ultimo - 1);
                $base = round($base - ($precedente?->getCreditoRiportato() ?? 0.0) + ($precedente?->debitoRinviato ?? 0.0), 2);
                return [max(0.0, $base), 'IVA delle operazioni delle società del gruppo fino al 20/12/' . $anno . ', con il credito del gruppo del periodo precedente.'];
            default:
                return [null, 'Indicare il dovuto presunto del gruppo per l\'ultimo periodo dell\'anno.'];
        }
    }

    /**
     * @throws \DomainException se l'azienda non liquida l'IVA nell'anno
     */
    public function elabora(Soggetto $soggetto, int $anno, MetodoAccontoIva $metodo, float $base, ?string $note = null, ?GruppoIva $gruppo = null): AccontoIva
    {
        $periodicita = $gruppo === null ? (new RegimeIva($this->dataMapper))->getPeriodicita($soggetto, $anno) : (new LiquidazioneGruppo($this->dataMapper))->getPeriodicita($gruppo, $anno);
        $primaTransazione = $this->dataMapper->startTransaction();
        $acconto = (new AccontoIvaModel($this->dataMapper))->getPerAnno($soggetto, $anno, $gruppo) ?? new AccontoIva($this->dataMapper);
        $acconto->studio = $soggetto->studio;
        $acconto->soggetto = $soggetto;
        $acconto->gruppoIva = $gruppo;
        $acconto->anno = $anno;
        $acconto->periodicita = $periodicita;
        $acconto->metodo = $metodo;
        $acconto->base = round(max(0.0, $base), 2);
        $acconto->importo = $metodo->getImporto($base);
        $acconto->note = $note;
        $acconto->dataElaborazione = new SismaDateTime();
        $this->dataMapper->save($acconto);
        $versamenti = [];
        if ($acconto->isDovuto()) {
            $versamento = new Versamento($this->dataMapper);
            $versamento->scadenza = PeriodiIva::getScadenzaAcconto($anno);
            $versamento->sezione = SezioneF24::erario;
            $codice = PeriodiIva::getCodiceTributoAcconto($periodicita);
            $versamento->codiceTributo = (new CodiceTributoModel($this->dataMapper))->getByCodice(SezioneCodiceTributo::erario, $codice)
                    ?? throw new \InvalidArgumentException('Codice tributo ' . $codice . ' assente nelle tabelle di Deleghe F24.');
            $versamento->annoRiferimento = $anno;
            $versamento->acconto = true;
            $versamento->importoDebito = $acconto->importo;
            $versamento->descrizione = 'Acconto IVA ' . ($gruppo === null ? '' : 'di gruppo ') . $anno;
            $versamenti[] = $versamento;
        }
        $esito = (new ArchivioVersamenti($this->dataMapper))->registra($soggetto, AccontoIva::ORIGINE, $acconto->getRiferimentoVersamento(), $versamenti);
        $acconto->differenze = count($esito->differenze) > 0 ? implode("\n", $esito->differenze) : null;
        $this->dataMapper->save($acconto);
        $this->dataMapper->commitTransaction($primaTransazione);
        return $acconto;
    }

    /**
     * @return string[] differenze con i versamenti in deleghe non modificabili
     */
    public function elimina(AccontoIva $acconto): array
    {
        $primaTransazione = $this->dataMapper->startTransaction();
        $esito = (new ArchivioVersamenti($this->dataMapper))->registra($acconto->soggetto, AccontoIva::ORIGINE, $acconto->getRiferimentoVersamento(), []);
        $this->dataMapper->delete($acconto);
        $this->dataMapper->commitTransaction($primaTransazione);
        return $esito->differenze;
    }
}
