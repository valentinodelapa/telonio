<?php

namespace Dichiarativi\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Models\OpzioneTassazioneModel;
use Contabilita\Application\Models\PartecipanteGruppoIvaModel;
use Contabilita\Application\Services\EsoneroAgricolo;
use Contabilita\Application\Services\RegimeIva;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Modelli di dichiarazione che riguardano il contribuente nell'anno
 * d'imposta: IVA annuale per i titolari di partita IVA in un regime con le
 * liquidazioni IVA (dati fiscali dell'Anagrafica), Redditi PF per le
 * persone fisiche, Redditi SP per le società di persone e le associazioni
 * professionali, Redditi SC per le società di capitali (tipo del soggetto
 * nell'Anagrafica), modello IVA TR per il credito trimestrale, Certificazione
 * Unica e 770 per i sostituti d'imposta con ritenute operate nell'anno.
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
        $modelli[ModelloDichiarativo::redditiSp->value] = $soggetto->tipoSoggetto === TipoSoggetto::societaPersone ? null : 'Il soggetto non è una società di persone o un\'associazione professionale.';
        $modelli[ModelloDichiarativo::redditiSc->value] = $soggetto->tipoSoggetto === TipoSoggetto::societaCapitali ? null : 'Il soggetto non è una società di capitali.';
        // IRAP: società di persone e di capitali (le persone fisiche non sono soggetti passivi dal 2022, L. 234/2021), non nel regime forfetario
        $regime = (new \Anagrafica\Application\Models\DatiFiscaliModel($this->dataMapper))->getVigenteAl($soggetto, new \SismaFramework\Orm\CustomTypes\SismaDate($anno . '-12-31'))?->regimeFiscale;
        $modelli[ModelloDichiarativo::irap->value] = match (true) {
            in_array($soggetto->tipoSoggetto, [TipoSoggetto::societaPersone, TipoSoggetto::societaCapitali], true) === false => 'Il soggetto non è una società: le persone fisiche non sono soggette all\'IRAP dal 2022 (L. 234/2021); per gli enti il modello non è ancora gestito.',
            $regime === \Anagrafica\Application\Enumerations\RegimeFiscale::forfetario => 'Regime forfetario: nessuna IRAP.',
            default => null,
        };
        $opzione = $soggetto->tipoSoggetto === TipoSoggetto::societaCapitali ? (new OpzioneTassazioneModel($this->dataMapper))->getVigente($soggetto, $anno) : null;
        $modelli[ModelloDichiarativo::consolidato->value] = $opzione?->isConsolidante() ? null : 'Il soggetto non è la consolidante di un consolidato nazionale nell\'anno (opzioni dei dati fiscali dell\'Anagrafica).';
        // modello IVA TR: chi liquida l'IVA, salvo le controllate del gruppo IVA (lo presenta la controllante)
        $modelli[ModelloDichiarativo::ivaTr->value] = $esonerato ? 'Agricoltore esonerato: nessuna liquidazione periodica.' : $modelli[ModelloDichiarativo::iva->value]
                ?? ((new PartecipanteGruppoIvaModel($this->dataMapper))->getPerControllata($soggetto, $anno) === null ? null
                    : 'Società del gruppo IVA: il credito trimestrale lo chiede la controllante nel suo modello IVA TR.');
        // Certificazione Unica e 770: sostituto d'imposta con ritenute operate nell'anno (o indicato nei dati fiscali)
        $sostituto = (count((new \Contabilita\Application\Services\RitenuteOperate($this->dataMapper))->getPerMese($soggetto, $anno)) > 0)
                || ((new \Anagrafica\Application\Models\DatiFiscaliModel($this->dataMapper))->getVigenteAl($soggetto, new \SismaFramework\Orm\CustomTypes\SismaDate($anno . '-12-31'))?->sostitutoImposta === true);
        foreach ([ModelloDichiarativo::certificazioneUnica, ModelloDichiarativo::modello770] as $modello) {
            $modelli[$modello->value] = $sostituto ? null : "Nessuna ritenuta d'acconto operata nell'anno (Contabilità) e soggetto non indicato come sostituto d'imposta nei dati fiscali.";
        }
        return $modelli;
    }

    public function isDovuto(Soggetto $soggetto, ModelloDichiarativo $modello, int $anno): bool
    {
        return $this->getModelli($soggetto, $anno)[$modello->value] === null;
    }
}
