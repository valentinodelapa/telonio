<?php

namespace GestioneStudio\Application\Services;

use CommSuite\Application\Entities\Studio;
use CommSuite\Application\Entities\Utente;
use CommSuite\Application\Enumerations\ModuloSuite;
use CommSuite\Application\Services\RaccoltaScadenze;
use CommSuite\Application\Services\Scadenza;
use GestioneStudio\Application\Entities\LavorazioneScadenza;
use GestioneStudio\Application\Enumerations\FiltroStato;
use GestioneStudio\Application\Enumerations\StatoLavorazione;
use GestioneStudio\Application\Models\LavorazioneScadenzaModel;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Scadenzario dello studio: le scadenze di tutti i moduli disponibili
 * (RaccoltaScadenze) con lo stato di lavorazione registrato dallo studio.
 */
class Scadenzario
{

    public const RESPONSABILE_TUTTI = 0;
    public const RESPONSABILE_NESSUNO = -1;

    private RaccoltaScadenze $raccolta;

    public function __construct(private DataMapper $dataMapper = new DataMapper(), ?RaccoltaScadenze $raccolta = null)
    {
        $this->raccolta = $raccolta ?? new RaccoltaScadenze($dataMapper);
    }

    /**
     * @param int $responsabile id dell'utente, RESPONSABILE_TUTTI o RESPONSABILE_NESSUNO
     * @return VoceScadenzario[] per data
     */
    public function getVoci(Studio $studio, SismaDate $dal, SismaDate $al, ?BaseEntity $soggetto = null, ?ModuloSuite $modulo = null,
            FiltroStato $filtro = FiltroStato::tutte, int $responsabile = self::RESPONSABILE_TUTTI): array
    {
        $scadenze = $this->raccolta->raccogli($studio, $dal, $al, $soggetto, $modulo);
        $riferimenti = [];
        foreach ($scadenze as $scadenza) {
            $riferimenti[$scadenza->modulo->value][] = $scadenza->riferimento;
        }
        $lavorazioni = (new LavorazioneScadenzaModel($this->dataMapper))->getPerRiferimenti($studio, $riferimenti);
        $voci = [];
        foreach ($scadenze as $scadenza) {
            $voce = new VoceScadenzario($scadenza, $lavorazioni[$scadenza->getChiave()] ?? null);
            if ($filtro->isIncluso($voce->stato) && self::isDelResponsabile($voce, $responsabile)) {
                $voci[] = $voce;
            }
        }
        return $voci;
    }

    /**
     * Scadenza del modulo con il riferimento indicato, cercata nel periodo.
     */
    public function trova(Studio $studio, ModuloSuite $modulo, string $riferimento, SismaDate $dal, SismaDate $al, ?BaseEntity $soggetto = null): ?VoceScadenzario
    {
        foreach ($this->getVoci($studio, $dal, $al, $soggetto, $modulo) as $voce) {
            if ($voce->scadenza->riferimento === $riferimento) {
                return $voce;
            }
        }
        return null;
    }

    /**
     * Nuova lavorazione della scadenza, con stato, responsabile e note da
     * impostare prima di registrarla.
     */
    public function preparaLavorazione(Studio $studio, VoceScadenzario $voce): LavorazioneScadenza
    {
        if ($voce->lavorazione !== null) {
            return $voce->lavorazione;
        }
        $lavorazione = new LavorazioneScadenza($this->dataMapper);
        $lavorazione->studio = $studio;
        $lavorazione->responsabile = $voce->responsabile;
        return $lavorazione;
    }

    /**
     * Registra la lavorazione con lo stato, il responsabile e le note
     * impostati: collega la scadenza e, quando lo stato passa a completata o
     * non dovuta, la data di completamento e l'utente.
     */
    public function registra(Scadenza $scadenza, LavorazioneScadenza $lavorazione, ?StatoLavorazione $statoPrecedente, Utente $utente): LavorazioneScadenza
    {
        if ($scadenza->completata) {
            throw new \LogicException('Scadenza completata dal modulo ' . $scadenza->modulo->getLabel() . ': lo stato si gestisce nel modulo.');
        }
        $lavorazione->modulo = $scadenza->modulo->value;
        $lavorazione->riferimento = $scadenza->riferimento;
        $lavorazione->soggetto = $scadenza->soggetto;
        $lavorazione->dataScadenza = $scadenza->data;
        $lavorazione->descrizione = mb_substr($scadenza->descrizione, 0, 255);
        if ($lavorazione->stato->isAperta()) {
            $lavorazione->dataCompletamento = null;
            $lavorazione->utenteCompletamento = null;
        } elseif (($statoPrecedente !== $lavorazione->stato) || ($lavorazione->dataCompletamento === null)) {
            $lavorazione->dataCompletamento = new SismaDate();
            $lavorazione->utenteCompletamento = $utente;
        }
        $lavorazione->dataModifica = new SismaDateTime();
        $this->dataMapper->save($lavorazione);
        return $lavorazione;
    }

    private static function isDelResponsabile(VoceScadenzario $voce, int $responsabile): bool
    {
        return match ($responsabile) {
            self::RESPONSABILE_TUTTI => true,
            self::RESPONSABILE_NESSUNO => $voce->responsabile === null,
            default => $voce->responsabile?->id === $responsabile,
        };
    }
}
