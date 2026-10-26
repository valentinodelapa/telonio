<?php

namespace GestioneStudio\Application\Services;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Anagrafica\Application\Models\LegameModel;
use GestioneStudio\Application\Enumerations\CondizioneProroga;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Verifica se il soggetto ha i requisiti di una proroga condizionata, con
 * i dati fiscali dell'Anagrafica in vigore alla data indicata (di norma la
 * fine del periodo d'imposta di riferimento).
 *
 * Per i soggetti ISA: applica gli ISA o è in regime forfetario o di
 * vantaggio; oppure partecipa, come socio o collaboratore dell'impresa
 * familiare, a una società di persone, a una società di capitali in
 * trasparenza o a un'impresa familiare con questi requisiti (artt. 5, 115 e
 * 116 TUIR).
 */
class RequisitiProroga
{

    private const RUOLI_PARTECIPAZIONE = [RuoloLegame::socio, RuoloLegame::socioAccomandatario, RuoloLegame::socioAccomandante, RuoloLegame::collaboratoreFamiliare];

    /** @var array<string, bool> */
    private array $esiti = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public function isSoddisfatta(CondizioneProroga $condizione, Soggetto $soggetto, SismaDate $data): bool
    {
        if ($condizione === CondizioneProroga::tutti) {
            return true;
        }
        $chiave = $condizione->value . '/' . $soggetto->id . '/' . $data->format('Y-m-d');
        return $this->esiti[$chiave] ??= $this->isSoggettoIsa($soggetto, $data) || $this->isPartecipanteIsa($soggetto, $data);
    }

    private function isSoggettoIsa(Soggetto $soggetto, SismaDate $data): bool
    {
        $dati = $this->getDatiFiscali($soggetto, $data);
        return ($dati !== null) && ($dati->soggettoIsa || ($dati->regimeFiscale?->isEsclusoDagliIsa() ?? false));
    }

    private function isPartecipanteIsa(Soggetto $soggetto, SismaDate $data): bool
    {
        foreach ((new LegameModel($this->dataMapper))->getIncarichi($soggetto) as $legame) {
            if ((in_array($legame->ruolo, self::RUOLI_PARTECIPAZIONE, true) === false) || ($legame->isVigenteAl($data) === false)) {
                continue;
            }
            $partecipata = $legame->soggetto;
            if ($this->isTrasparente($partecipata, $legame->ruolo, $data) && $this->isSoggettoIsa($partecipata, $data)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Il reddito della partecipata è imputato ai soci o ai collaboratori.
     */
    private function isTrasparente(Soggetto $partecipata, RuoloLegame $ruolo, SismaDate $data): bool
    {
        if ($ruolo === RuoloLegame::collaboratoreFamiliare) {
            return $partecipata->isPersonaFisica();
        }
        return match ($partecipata->tipoSoggetto) {
            TipoSoggetto::societaPersone => true,
            TipoSoggetto::societaCapitali => $this->getDatiFiscali($partecipata, $data)?->trasparenzaFiscale ?? false,
            default => false,
        };
    }

    private function getDatiFiscali(Soggetto $soggetto, SismaDate $data): ?DatiFiscali
    {
        return (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($soggetto, $data);
    }
}
