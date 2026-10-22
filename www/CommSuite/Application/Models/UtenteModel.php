<?php

namespace CommSuite\Application\Models;

use CommSuite\Application\Entities\Studio;
use CommSuite\Application\Entities\Utente;
use CommSuite\Application\Enumerations\RuoloUtente;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;
use SismaFramework\Security\Interfaces\Entities\AuthenticableInterface;
use SismaFramework\Security\Interfaces\Models\AuthenticableModelInterface;

class UtenteModel extends DependentModel implements AuthenticableModelInterface
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Utente::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    #[\Override]
    public function getValidAuthenticableInterfaceByIdentifier(string $identifier): ?AuthenticableInterface
    {
        foreach ($this->getByUsernameAndAttivo($identifier, true) as $utente) {
            if ($utente->studio->attivo) {
                return $utente;
            }
        }
        return null;
    }

    public function getElenco(Studio $studio): SismaCollection
    {
        return $this->getByStudio($studio, null, ['cognome' => 'ASC', 'nome' => 'ASC']);
    }

    public function esisteUsername(string $username, ?Utente $escluso = null): bool
    {
        foreach ($this->getByUsername($username) as $utente) {
            if (($escluso === null) || (isset($escluso->id) === false) || ($utente->id !== $escluso->id)) {
                return true;
            }
        }
        return false;
    }

    public function contaAmministratoriAttivi(Studio $studio, ?Utente $escluso = null): int
    {
        $totale = 0;
        foreach ($this->getByStudioAndRuoloAndAttivo($studio, RuoloUtente::amministratore, true) as $utente) {
            if (($escluso === null) || (isset($escluso->id) === false) || ($utente->id !== $escluso->id)) {
                $totale++;
            }
        }
        return $totale;
    }
}
