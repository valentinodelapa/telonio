<?php

namespace Contabilita\Application\Models;

use Contabilita\Application\Entities\CodiceIva;
use Contabilita\Application\Enumerations\NaturaIva;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;
use Telonio\Application\Entities\Studio;

class CodiceIvaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return CodiceIva::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Codici visibili allo studio: quelli della suite e quelli dello
     * studio, in ordine di codice.
     *
     * @return CodiceIva[]
     */
    public function getElenco(Studio $studio, bool $soloAttivi = true): array
    {
        $elenco = [];
        foreach ([null, $studio] as $titolare) {
            $codici = $soloAttivi ? $this->getByStudioAndAttivo($titolare, true, null, ['id' => 'ASC']) : $this->getByStudio($titolare, null, ['id' => 'ASC']);
            array_push($elenco, ...iterator_to_array($codici, false));
        }
        return $elenco;
    }

    /**
     * Codice dello studio o, in mancanza, della suite.
     */
    public function getPerCodice(Studio $studio, string $codice): ?CodiceIva
    {
        return $this->getByStudioAndCodice($studio, $codice)[0] ?? $this->getByStudioAndCodice(null, $codice)[0] ?? null;
    }

    /**
     * Primo codice attivo con aliquota, natura e inversione contabile
     * indicate (es. per le righe delle fatture elettroniche), preferendo
     * la detraibilità piena.
     */
    public function trova(Studio $studio, float $aliquota, ?NaturaIva $natura, bool $inversioneContabile = false): ?CodiceIva
    {
        $trovato = null;
        foreach ($this->getElenco($studio) as $codice) {
            if ((abs($codice->aliquota - $aliquota) < 0.001) && ($codice->natura === $natura) && ($codice->inversioneContabile === $inversioneContabile) && ($codice->ventilazione === false)) {
                if ($codice->detraibilita >= 100) {
                    return $codice;
                }
                $trovato ??= $codice;
            }
        }
        return $trovato;
    }

    public function esisteCodice(Studio $studio, string $codice, ?CodiceIva $escluso = null): bool
    {
        foreach ([null, $studio] as $titolare) {
            foreach ($this->getByStudioAndCodice($titolare, $codice) as $esistente) {
                if (($escluso === null) || (isset($escluso->id) === false) || ($esistente->id !== $escluso->id)) {
                    return true;
                }
            }
        }
        return false;
    }
}
