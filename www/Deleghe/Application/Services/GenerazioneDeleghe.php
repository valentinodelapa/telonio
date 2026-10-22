<?php

namespace Deleghe\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Models\ContoCorrenteModel;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\ModelloF24;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Models\DelegaModel;
use Deleghe\Application\Models\VersamentoModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Generazione delle deleghe dall'archivio del contribuente: i versamenti da
 * versare con la data indicata vanno nella delega in bozza di quella data
 * (creata se manca), separati per modello (ELIDE e accise hanno un modello
 * proprio, gli altri vanno nell'ordinario) e per secondo codice fiscale
 * richiesto (Versamento::$codiceFiscaleCoobbligato, es. il defunto per
 * l'imposta di successione versata dall'erede); a richiesta si compensano
 * con i crediti disponibili.
 */
class GenerazioneDeleghe
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return Delega[] deleghe che hanno ricevuto i versamenti
     */
    public function genera(Soggetto $soggetto, SismaDate $scadenza, bool $compensa): array
    {
        $gruppi = [];
        foreach ((new VersamentoModel($this->dataMapper))->getDaVersare($soggetto, $scadenza) as $versamento) {
            $gruppi[self::getModello($versamento->sezione)->value . '|' . ($versamento->codiceFiscaleCoobbligato ?? '') . '|' . ($versamento->codiceIdentificativo?->value ?? '')][] = $versamento;
        }
        $deleghe = [];
        $primaTransazione = $this->dataMapper->startTransaction();
        foreach ($gruppi as $chiave => $versamenti) {
            $modello = ModelloF24::from(explode('|', $chiave)[0]);
            $delega = $this->trovaBozza($soggetto, $scadenza, $modello, $versamenti) ?? $this->creaDelega($soggetto, $scadenza, $modello, $versamenti[0]);
            $this->aggiungi($delega, $versamenti);
            if ($compensa) {
                (new Compensazioni($this->dataMapper))->compensaAutomaticamente($delega);
            }
            $deleghe[] = $delega;
        }
        $this->dataMapper->commitTransaction($primaTransazione);
        return $deleghe;
    }

    /**
     * Inserisce nella delega versamenti da versare con la stessa data; quelli
     * con un secondo codice fiscale diverso da quello della delega restano
     * da versare (vedi isCompatibile).
     *
     * @param Versamento[] $versamenti
     */
    public function aggiungi(Delega $delega, array $versamenti): void
    {
        $versamentoModel = new VersamentoModel($this->dataMapper);
        foreach ($versamenti as $versamento) {
            if (($versamento->isDaVersare() === false) || ($versamento->soggetto->id !== $delega->soggetto->id) || ($this->isCompatibile($delega, $versamento) === false)) {
                continue;
            }
            $versamento->delega = $delega;
            $versamento->scadenza = $delega->scadenza;
            $versamento->ordine = $versamentoModel->getProssimoOrdine($delega, $versamento->sezione);
            $this->dataMapper->save($versamento);
        }
        $delega->dataModifica = new SismaDateTime();
        $this->aggiornaDetrazioneImu($delega);
        $this->dataMapper->save($delega);
    }

    /**
     * Riporta nella delega la detrazione per l'abitazione principale dei
     * righi IMU generati (Versamento::$detrazioneImu). Senza righi con
     * detrazione resta quella indicata a mano, tranne con $azzeraSeAssente
     * (un rigo con detrazione è appena uscito dalla delega). Non salva la
     * delega.
     */
    public function aggiornaDetrazioneImu(Delega $delega, bool $azzeraSeAssente = false): void
    {
        $detrazione = null;
        foreach ((new VersamentoModel($this->dataMapper))->getByDelega($delega) as $versamento) {
            if ($versamento->detrazioneImu !== null) {
                $detrazione = round(($detrazione ?? 0) + $versamento->detrazioneImu, 2);
            }
        }
        if (($detrazione !== null) || $azzeraSeAssente) {
            $delega->detrazioneImu = $detrazione;
        }
    }

    /**
     * Un versamento con secondo codice fiscale richiesto va solo nelle
     * deleghe con quel secondo soggetto; uno senza non va nelle deleghe che
     * contengono versamenti con secondo soggetto richiesto (il secondo codice
     * fiscale vale per tutta la delega). Le altre deleghe, anche con un
     * secondo codice fiscale indicato a mano (es. il genitore), ricevono i
     * versamenti come prima.
     */
    public function isCompatibile(Delega $delega, Versamento $versamento): bool
    {
        if ($versamento->hasSecondoSoggettoRichiesto()) {
            return ($delega->codiceFiscaleCoobbligato === $versamento->codiceFiscaleCoobbligato) && ($delega->codiceIdentificativo === $versamento->codiceIdentificativo);
        }
        foreach ((new VersamentoModel($this->dataMapper))->getByDelega($delega) as $contenuto) {
            if ($contenuto->hasSecondoSoggettoRichiesto()) {
                return false;
            }
        }
        return true;
    }

    public static function getModello(SezioneF24 $sezione): ModelloF24
    {
        return match ($sezione) {
            SezioneF24::elide => ModelloF24::elide,
            SezioneF24::accise => ModelloF24::accise,
            default => ModelloF24::ordinario,
        };
    }

    /**
     * Delega in bozza della data con un modello che ammette tutte le sezioni
     * dei versamenti (per l'ordinario anche il semplificato).
     *
     * @param Versamento[] $versamenti
     */
    private function trovaBozza(Soggetto $soggetto, SismaDate $scadenza, ModelloF24 $modello, array $versamenti): ?Delega
    {
        $compatibili = $modello === ModelloF24::ordinario ? [ModelloF24::ordinario, ModelloF24::semplificato] : [$modello];
        foreach ((new DelegaModel($this->dataMapper))->getPerScadenza($soggetto, $scadenza) as $delega) {
            if ($delega->isModificabile() && in_array($delega->modello, $compatibili, true)
                    && (count(array_filter($versamenti, fn(Versamento $versamento) => ($delega->modello->ammetteSezione($versamento->sezione) === false) || ($this->isCompatibile($delega, $versamento) === false))) === 0)) {
                return $delega;
            }
        }
        return null;
    }

    private function creaDelega(Soggetto $soggetto, SismaDate $scadenza, ModelloF24 $modello, Versamento $primo): Delega
    {
        $delega = new Delega($this->dataMapper);
        $delega->studio = $soggetto->studio;
        $delega->soggetto = $soggetto;
        $delega->scadenza = $scadenza;
        $delega->modello = $modello;
        $delega->codiceFiscaleCoobbligato = $primo->codiceFiscaleCoobbligato;
        $delega->codiceIdentificativo = $primo->codiceIdentificativo;
        $delega->contoAddebito = (new ContoCorrenteModel($this->dataMapper))->getPredefinito($soggetto);
        $this->dataMapper->save($delega);
        return $delega;
    }
}
