<?php

namespace CommSuite\Application\Models;

use CommSuite\Application\Entities\Utente;
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
}
