<?php

namespace Deleghe\Application\Services;

use Telonio\Application\Entities\Studio;
use Telonio\Application\Enumerations\ModuloSuite;
use Telonio\Application\Interfaces\FonteScadenze;
use Telonio\Application\Services\Scadenza;
use Deleghe\Application\Entities\Ravvedimento;
use Deleghe\Application\Entities\SanzioneCivile;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Models\RavvedimentoModel;
use Deleghe\Application\Models\VersamentoModel;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Scadenze del modulo per lo scadenzario dello studio: i versamenti
 * dell'archivio del contribuente raggruppati per data di scadenza, compresi
 * quelli registrati dagli altri moduli (IMU, successioni, ...).
 *
 * La scadenza è completata quando tutti i versamenti sono in deleghe
 * trasmesse, consegnate al cliente (che le paga da sé) o quietanzate,
 * oppure, se omessi, regolarizzati con un ravvedimento.
 */
class ScadenzeModulo implements FonteScadenze
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    #[\Override]
    public function getScadenze(Studio $studio, SismaDate $dal, SismaDate $al, ?BaseEntity $soggetto = null): array
    {
        $gruppi = [];
        foreach ((new VersamentoModel($this->dataMapper))->getPerPeriodo($studio, $dal, $al, $soggetto) as $versamento) {
            $gruppi[$versamento->soggetto->id . '/' . $versamento->scadenza->format('Y-m-d')][] = $versamento;
        }
        $ravvedimentoModel = new RavvedimentoModel($this->dataMapper);
        $scadenze = [];
        foreach ($gruppi as $chiave => $versamenti) {
            $primo = $versamenti[0];
            $saldo = 0.0;
            $eseguiti = 0;
            $stati = [];
            foreach ($versamenti as $versamento) {
                $saldo += $versamento->importoDebito - $versamento->importoCredito;
                $stato = $versamento->delega?->stato;
                $stati[$stato?->getLabel() ?? 'da inserire in delega'] = ($stati[$stato?->getLabel() ?? 'da inserire in delega'] ?? 0) + 1;
                if (in_array($stato, [StatoDelega::trasmessa, StatoDelega::consegnata, StatoDelega::quietanzata], true) || (($stato === null) && ($ravvedimentoModel->countByVersamentoOmesso($versamento) > 0))) {
                    $eseguiti++;
                }
            }
            $scadenze[] = new Scadenza(
                    modulo: ModuloSuite::deleghe,
                    riferimento: 'S' . $chiave,
                    data: $primo->scadenza,
                    descrizione: 'Versamenti F24' . self::getOrigini($versamenti),
                    soggetto: $primo->soggetto,
                    importo: round($saldo, 2),
                    url: '/contribuente/show/soggetto/' . $primo->soggetto->id,
                    completata: $eseguiti === count($versamenti),
                    dettaglio: implode(', ', array_map(fn(string $stato, int $numero) => $numero . ' ' . $stato, array_keys($stati), $stati)),
            );
        }
        return $scadenze;
    }

    /**
     * @param Versamento[] $versamenti
     */
    private static function getOrigini(array $versamenti): string
    {
        $origini = array_unique(array_filter(array_map(fn(Versamento $versamento) => $versamento->origine, $versamenti)));
        return count($origini) === 0 ? '' : ' (' . implode(', ', array_map(fn(string $origine) => match ($origine) {
                    Ravvedimento::ORIGINE => 'ravvedimento',
                    SanzioneCivile::ORIGINE => 'sanzioni civili',
                    default => ModuloSuite::tryFrom($origine)?->getLabel() ?? $origine,
                }, $origini)) . ')';
    }
}
