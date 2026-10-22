<?php

namespace Successioni\Application\Services;

use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Models\DatiCatastaliModel;
use Anagrafica\Application\Models\PossessoModel;
use SismaFramework\Orm\HelperClasses\DataMapper;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Entities\DevoluzioneSuccessione;
use Successioni\Application\Entities\DichiarazioneSuccessione;
use Successioni\Application\Enumerations\CodiceDiritto;
use Successioni\Application\Enumerations\QuadroBene;
use Successioni\Application\Enumerations\TipologiaTerreno;
use Successioni\Application\Models\BeneficiarioSuccessioneModel;
use Successioni\Application\Models\BeneSuccessioneModel;
use Successioni\Application\Models\DevoluzioneSuccessioneModel;

/**
 * Proposte dai dati dell'Anagrafica:
 * - attivo immobiliare: i possessi del defunto in vigore alla data del
 *   decesso diventano beni dei quadri EB (terreni) ed EC (fabbricati), con
 *   diritto, quota e valore catastale; usufrutto, uso e abitazione si
 *   estinguono con la morte e non entrano nell'attivo;
 * - devoluzione per legge: le quote della successione legittima
 *   (QuoteLegittime) applicate a tutti i beni, con il diritto del defunto.
 */
class PropostaAttivo
{

    /** @var string[] */
    private array $avvisi = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return int beni inseriti (gli immobili già presenti con lo stesso
     *         diritto non si ripetono)
     */
    public function importaImmobili(DichiarazioneSuccessione $dichiarazione): int
    {
        $this->avvisi = [];
        $dataDecesso = $dichiarazione->getDataApertura();
        $beneModel = new BeneSuccessioneModel($this->dataMapper);
        $presenti = [];
        foreach ($beneModel->getElenco($dichiarazione) as $bene) {
            if ($bene->immobile !== null) {
                $presenti[$bene->immobile->id . '|' . $bene->codiceDiritto->value] = true;
            }
        }
        $datiCatastaliModel = new DatiCatastaliModel($this->dataMapper);
        $inseriti = 0;
        foreach ((new PossessoModel($this->dataMapper))->getStoricoSoggetto($dichiarazione->defunto) as $possesso) {
            if ($possesso->isVigenteAl($dataDecesso) === false) {
                continue;
            }
            $immobile = $possesso->immobile;
            $codiceDiritto = CodiceDiritto::daTitoloPossesso($possesso->titolo);
            if ($codiceDiritto === null) {
                $this->avvisi[] = sprintf('%s: %s del defunto non entra nell\'attivo (si estingue con la morte o non è un diritto reale).', $immobile->getIdentificativo(), mb_strtolower($possesso->titolo->getLabel()));
                continue;
            }
            if (isset($presenti[$immobile->id . '|' . $codiceDiritto->value])) {
                continue;
            }
            $terreno = $immobile->tipoCatasto === TipoCatasto::terreni;
            $quota = Frazione::daPercentuale($possesso->quota);
            $bene = new BeneSuccessione($this->dataMapper);
            $bene->dichiarazioneSuccessione = $dichiarazione;
            $bene->quadro = QuadroBene::perImmobile($terreno, $immobile->isTavolare());
            $bene->ordine = $beneModel->getProssimoOrdine($dichiarazione, $bene->quadro);
            $bene->immobile = $immobile;
            $bene->codiceDiritto = $codiceDiritto;
            $bene->quotaNumeratore = (float) $quota->numeratore;
            $bene->quotaDenominatore = $quota->denominatore;
            if ($terreno) {
                $bene->natura = 'T';
                $bene->tipologiaTerreno = TipologiaTerreno::nonEdificabile;
            }
            $catasto = $datiCatastaliModel->getVigenteAl($immobile, $dataDecesso);
            $bene->valore = $catasto === null ? null : ValoreCatastale::getValoreQuota($catasto, $terreno, $quota->toFloat());
            if ($bene->valore === null) {
                $this->avvisi[] = $immobile->getIdentificativo() . ': dati catastali assenti alla data del decesso, valore da indicare.';
            }
            $this->dataMapper->save($bene);
            $presenti[$immobile->id . '|' . $codiceDiritto->value] = true;
            $inseriti++;
        }
        return $inseriti;
    }

    /**
     * Devoluzione per legge di tutti i beni: sostituisce le devoluzioni
     * esistenti.
     *
     * @return bool false se le quote vanno indicate a mano (vedi getAvvisi)
     */
    public function proponiDevoluzioni(DichiarazioneSuccessione $dichiarazione): bool
    {
        $this->avvisi = [];
        $beneficiari = iterator_to_array((new BeneficiarioSuccessioneModel($this->dataMapper))->getElenco($dichiarazione), false);
        $quoteLegittime = new QuoteLegittime();
        $quote = $quoteLegittime->calcola($beneficiari);
        $this->avvisi[] = (string) $quoteLegittime->getRegola();
        if ($quote === null) {
            return false;
        }
        $perId = [];
        foreach ($beneficiari as $beneficiario) {
            $perId[$beneficiario->id] = $beneficiario;
        }
        $devoluzioneModel = new DevoluzioneSuccessioneModel($this->dataMapper);
        $primaTransazione = $this->dataMapper->startTransaction();
        foreach ((new BeneSuccessioneModel($this->dataMapper))->getElenco($dichiarazione) as $bene) {
            foreach ($devoluzioneModel->getElenco($bene) as $esistente) {
                $this->dataMapper->delete($esistente);
            }
            $valori = self::ripartisciValore($bene->getValoreTotale(), $quote);
            foreach ($quote as $id => $quota) {
                $devoluzione = new DevoluzioneSuccessione($this->dataMapper);
                $devoluzione->beneSuccessione = $bene;
                $devoluzione->beneficiarioSuccessione = $perId[$id];
                $devoluzione->quotaNumeratore = $quota->numeratore;
                $devoluzione->quotaDenominatore = $quota->denominatore;
                $devoluzione->codiceDiritto = $bene->codiceDiritto;
                $devoluzione->valoreQuota = $valori[$id];
                $this->dataMapper->save($devoluzione);
            }
        }
        $this->dataMapper->commitTransaction($primaTransazione);
        return true;
    }

    /**
     * Valore del bene ripartito per quote, in centesimi esatti: la somma
     * coincide con il valore del bene.
     *
     * @param array<int, Frazione> $quote
     * @return array<int, float>
     */
    public static function ripartisciValore(int $valore, array $quote): array
    {
        $centesimi = $valore * 100;
        $parti = [];
        $assegnati = 0;
        $ultimo = array_key_last($quote);
        foreach ($quote as $id => $quota) {
            $parti[$id] = $id === $ultimo ? $centesimi - $assegnati : (int) round($centesimi * $quota->toFloat());
            $assegnati += $parti[$id];
        }
        return array_map(fn(int $parte) => $parte / 100, $parti);
    }

    /**
     * @return string[]
     */
    public function getAvvisi(): array
    {
        return $this->avvisi;
    }
}
