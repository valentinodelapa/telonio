<?php

namespace Deleghe\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\PianoRateazione;
use Deleghe\Application\Entities\RateazioneSecondoAcconto;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Models\RateazioneSecondoAccontoModel;
use Deleghe\Application\Models\RavvedimentoModel;
use Deleghe\Application\Models\SanzioneCivileModel;
use Deleghe\Application\Models\VersamentoModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Piani di rateazione: le rate calcolate da CalcoloRateazione sono
 * registrate nell'archivio tramite ArchivioVersamenti (origine
 * "Rateazione"), i versamenti originari collegati al piano escono dai
 * versamenti da versare.
 *
 * Il piano si modifica o si elimina finché nessuna rata è in una delega non
 * più in bozza o regolarizzata con un ravvedimento; una rata non pagata si
 * regolarizza con il ravvedimento della singola rata.
 *
 * Il secondo acconto si rateizza (o si differisce in unica soluzione) solo
 * per i periodi d'imposta di una norma straordinaria
 * (RateazioneSecondoAcconto): tutti i versamenti del piano devono rientrare
 * nella stessa norma.
 */
class RateazioneService
{

    private RateazioneSecondoAccontoModel $normeSecondoAcconto;

    public function __construct(private DataMapper $dataMapper = new DataMapper(), private ?CalcoloRateazione $calcolo = null)
    {
        $this->calcolo ??= new CalcoloRateazione($dataMapper);
        $this->normeSecondoAcconto = new RateazioneSecondoAccontoModel($dataMapper);
    }

    /**
     * Se il versamento si può rateizzare: saldo e primo acconto (art. 20
     * D.Lgs. 241/1997) o secondo acconto di un periodo con norma
     * straordinaria.
     */
    public function isRateizzabile(Versamento $versamento): bool
    {
        return CalcoloRateazione::isRateizzabile($versamento) || ($this->getNormaSecondoAcconto($versamento) !== null);
    }

    public function getNormaSecondoAcconto(Versamento $versamento): ?RateazioneSecondoAcconto
    {
        return $this->normeSecondoAcconto->getPerVersamento($versamento);
    }

    /**
     * Norma straordinaria del secondo acconto dei versamenti del piano, o
     * null per il pagamento rateale ordinario.
     *
     * @param Versamento[] $originali
     * @throws \InvalidArgumentException se secondo acconto e altri versamenti sono mescolati
     */
    public function getNormaPiano(array $originali): ?RateazioneSecondoAcconto
    {
        $norme = [];
        foreach ($originali as $originale) {
            $norma = CalcoloRateazione::isRateizzabile($originale) ? null : $this->getNormaSecondoAcconto($originale);
            $norme[$norma?->periodoImposta ?? 0] = $norma;
        }
        if (count($norme) > 1) {
            throw new \InvalidArgumentException('Il secondo acconto differito dalla norma straordinaria si rateizza separatamente dagli altri versamenti.');
        }
        return reset($norme) ?: null;
    }

    /**
     * @param Versamento[] $originali
     * @param \DateTimeInterface[] $date
     * @throws \InvalidArgumentException se versamenti o date non sono validi
     */
    public function crea(Soggetto $soggetto, array $originali, array $date, ?string $descrizione = null): PianoRateazione
    {
        $this->verificaOriginali($soggetto, $originali);
        $scadenza = $originali[array_key_first($originali)]->scadenza;
        $this->verificaDate($scadenza, $date, $this->getNormaPiano($originali));
        $primaTransazione = $this->dataMapper->startTransaction();
        $piano = new PianoRateazione($this->dataMapper);
        $piano->studio = $soggetto->studio;
        $piano->soggetto = $soggetto;
        $piano->scadenza = $scadenza;
        $piano->descrizione = $descrizione;
        $piano->setDate($date);
        $this->dataMapper->save($piano);
        foreach ($originali as $originale) {
            $originale->pianoRateazione = $piano;
            $this->dataMapper->save($originale);
        }
        $this->registraRate($piano, $originali);
        $this->dataMapper->commitTransaction($primaTransazione);
        return $piano;
    }

    /**
     * Ricalcola le rate con nuove date (stessi versamenti originari).
     *
     * @param \DateTimeInterface[] $date
     */
    public function aggiorna(PianoRateazione $piano, array $date, ?string $descrizione = null): void
    {
        $this->verificaModificabile($piano);
        $this->verificaDate($piano->scadenza, $date, $this->getNormaPiano($this->getOriginali($piano)));
        $primaTransazione = $this->dataMapper->startTransaction();
        $piano->setDate($date);
        $piano->descrizione = $descrizione;
        $this->dataMapper->save($piano);
        $this->registraRate($piano, $this->getOriginali($piano));
        $this->dataMapper->commitTransaction($primaTransazione);
    }

    /**
     * Elimina il piano e le sue rate: i versamenti originari tornano da
     * versare.
     */
    public function elimina(PianoRateazione $piano): void
    {
        $this->verificaModificabile($piano);
        $primaTransazione = $this->dataMapper->startTransaction();
        (new ArchivioVersamenti($this->dataMapper))->registra($piano->soggetto, PianoRateazione::ORIGINE, $piano->getRiferimentoOrigine(), []);
        foreach ($this->getOriginali($piano) as $originale) {
            $originale->pianoRateazione = null;
            $this->dataMapper->save($originale);
        }
        $this->dataMapper->delete($piano);
        $this->dataMapper->commitTransaction($primaTransazione);
    }

    /**
     * @return Versamento[]
     */
    public function getOriginali(PianoRateazione $piano): array
    {
        return iterator_to_array((new VersamentoModel($this->dataMapper))->getByPianoRateazione($piano, null, ['sezione' => 'ASC', 'id' => 'ASC']), false);
    }

    /**
     * @return Versamento[] rate (quote e interessi) registrate dal piano
     */
    public function getRate(PianoRateazione $piano): array
    {
        return iterator_to_array((new VersamentoModel($this->dataMapper))->getGenerati($piano->soggetto, PianoRateazione::ORIGINE, $piano->getRiferimentoOrigine()), false);
    }

    /**
     * Motivo per cui il piano non si può più modificare, o null.
     */
    public function getMotivoBlocco(PianoRateazione $piano): ?string
    {
        $ravvedimentoModel = new RavvedimentoModel($this->dataMapper);
        $sanzioneCivileModel = new SanzioneCivileModel($this->dataMapper);
        foreach ($this->getRate($piano) as $rata) {
            if ($rata->isModificabile() === false) {
                return 'la rata del ' . $rata->scadenza->format('d/m/Y') . ' è in una delega ' . mb_strtolower($rata->delega->stato->getLabel());
            }
            if ($ravvedimentoModel->countByVersamentoOmesso($rata) > 0) {
                return 'la rata del ' . $rata->scadenza->format('d/m/Y') . ' è regolarizzata con un ravvedimento';
            }
            if ($sanzioneCivileModel->countByVersamentoTardivo($rata) > 0) {
                return '"' . $rata->descrizione . '" è versata in ritardo con le sanzioni civili';
            }
        }
        return null;
    }

    /**
     * Rigo degli interessi della stessa rata a cui appartiene una quota
     * (stessa sezione, ente e anno; per l'INPS stessa posizione), da versare
     * ($delega null) o già nella delega indicata: con il ravvedimento o il
     * versamento tardivo della quota si versano nella stessa delega (gli
     * interessi di dilazione non sono soggetti a sanzione).
     *
     * @return Versamento[]
     */
    public function getInteressiRata(Versamento $quota, ?Delega $delega): array
    {
        $numero = CalcoloRateazione::getNumeroRata($quota);
        if (($quota->origine !== PianoRateazione::ORIGINE) || CalcoloRateazione::isInteresse($quota) || ($numero === null)) {
            return [];
        }
        $interessi = [];
        foreach ((new VersamentoModel($this->dataMapper))->getGenerati($quota->soggetto, PianoRateazione::ORIGINE, $quota->riferimentoOrigine) as $rigo) {
            if (CalcoloRateazione::isInteresse($rigo) && (CalcoloRateazione::getNumeroRata($rigo) === $numero) && ($rigo->delega?->id === $delega?->id)
                    && (CalcoloRateazione::getChiaveInteressi($rigo) === CalcoloRateazione::getChiaveInteressi($quota))) {
                $interessi[] = $rigo;
            }
        }
        return $interessi;
    }

    private function registraRate(PianoRateazione $piano, array $originali): void
    {
        $rate = $this->calcolo->calcola($originali, $piano->getDate());
        (new ArchivioVersamenti($this->dataMapper))->registra($piano->soggetto, PianoRateazione::ORIGINE, $piano->getRiferimentoOrigine(), $rate);
    }

    private function verificaOriginali(Soggetto $soggetto, array $originali): void
    {
        if (count($originali) === 0) {
            throw new \InvalidArgumentException('Scegliere almeno un versamento da rateizzare.');
        }
        $scadenze = [];
        foreach ($originali as $originale) {
            if (($originale->soggetto->id !== $soggetto->id) || ($originale->isDaVersare() === false) || ($originale->pianoRateazione !== null)) {
                throw new \InvalidArgumentException('Si rateizzano solo versamenti da versare del contribuente, non già rateizzati.');
            }
            if ($this->isRateizzabile($originale) === false) {
                throw new \InvalidArgumentException('Il versamento ' . $originale->getCodice() . ' non è rateizzabile (art. 20 D.Lgs. 241/1997: saldo e primo acconto; secondo acconto solo con una norma straordinaria).');
            }
            $scadenze[$originale->scadenza->format('Y-m-d')] = true;
        }
        if (count($scadenze) > 1) {
            throw new \InvalidArgumentException('I versamenti rateizzati insieme devono avere la stessa scadenza.');
        }
    }

    private function verificaDate(\DateTimeInterface $scadenza, array $date, ?RateazioneSecondoAcconto $norma): void
    {
        $errori = CalcoloRateazione::verificaDate($scadenza, array_values($date), $norma);
        if (count($errori) > 0) {
            throw new \InvalidArgumentException(implode(' ', $errori));
        }
    }

    private function verificaModificabile(PianoRateazione $piano): void
    {
        $motivo = $this->getMotivoBlocco($piano);
        if ($motivo !== null) {
            throw new \InvalidArgumentException('Il piano non si può più modificare: ' . $motivo . '.');
        }
    }
}
