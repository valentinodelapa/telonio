<?php

namespace CommSuite\Application\Models;

use CommSuite\Application\Entities\Password;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;
use SismaFramework\Security\Interfaces\Entities\AuthenticableInterface;
use SismaFramework\Security\Interfaces\Entities\PasswordInterface;
use SismaFramework\Security\Interfaces\Models\PasswordModelInterface;

class PasswordModel extends DependentModel implements PasswordModelInterface
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Password::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    #[\Override]
    public function getPasswordByAuthenticableInterface(AuthenticableInterface $authenticableIntherface): ?PasswordInterface
    {
        $passwordCollection = $this->getByUtente($authenticableIntherface, null, ['dataCreazione' => 'DESC'], 0, 1);
        return $passwordCollection[0] ?? null;
    }
}
