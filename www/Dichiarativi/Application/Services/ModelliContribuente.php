<?php

namespace Dichiarativi\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Models\PartecipanteGruppoIvaModel;
use Contabilita\Application\Services\EsoneroAgricolo;
use Contabilita\Application\Services\RegimeIva;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Modelli di dichiarazione che riguardano il contribuente nell'anno
 * d'imposta: IVA annuale per i titolari di partita IVA in un regime con le
 * liquidazioni IVA (dati fiscali dell'Anagrafica), Redditi PF per le
 * persone fisiche, modello IVA TR per il credito trimestrale.
 */
class ModelliContribuente
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return array<string, string|null> valore del modello => null se
     *         dovuto, altrimenti il motivo per cui non lo è
     */
    public function getModelli(Soggetto $soggetto, int $anno): array
    {
        $modelli = [];
        $esonero = new EsoneroAgricolo($this->dataMapper);
        $esonerato = $esonero->isEsonerato($soggetto, $anno);
        try {
            (new RegimeIva($this->dataMapper))->getPeriodicita($soggetto, $anno);
            $modelli[ModelloDichiarativo::iva->value] = null;
        } catch (\DomainException $motivo) {
            // l'esonerato oltre i limiti presenta la dichiarazione, senza liquidazioni periodiche
            $modelli[ModelloDichiarativo::iva->value] = $esonerato && ($esonero->getMotivoDichiarazione($soggetto, $anno) !== null) ? null : $motivo->getMessage();
        }
        $modelli[ModelloDichiarativo::redditiPf->value] = $soggetto->isPersonaFisica() ? null : 'Il soggetto non è una persona fisica.';
        // modello IVA TR: chi liquida l'IVA, salvo le controllate del gruppo IVA (lo presenta la controllante)
        $modelli[ModelloDichiarativo::ivaTr->value] = $esonerato ? 'Agricoltore esonerato: nessuna liquidazione periodica.' : $modelli[ModelloDichiarativo::iva->value]
                ?? ((new PartecipanteGruppoIvaModel($this->dataMapper))->getPerControllata($soggetto, $anno) === null ? null
                    : 'Società del gruppo IVA: il credito trimestrale lo chiede la controllante nel suo modello IVA TR.');
        return $modelli;
    }

    public function isDovuto(Soggetto $soggetto, ModelloDichiarativo $modello, int $anno): bool
    {
        return $this->getModelli($soggetto, $anno)[$modello->value] === null;
    }
}
