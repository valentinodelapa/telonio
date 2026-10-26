<?php

namespace Telonio\Application\Models;

use Telonio\Application\Entities\Password;
use Telonio\Application\Entities\Utente;
use SismaFramework\Core\HelperClasses\Encryptor;
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
        $passwordCollection = $this->getByUtente($authenticableIntherface, null, ['dataCreazione' => 'DESC', 'id' => 'DESC'], 0, 1);
        return $passwordCollection[0] ?? null;
    }

    public function salvaPassword(Utente $utente, string $passwordInChiaro): Password
    {
        $password = new Password($this->dataMapper);
        $password->utente = $utente;
        $password->password = Encryptor::getBlowfishHash($passwordInChiaro);
        $this->dataMapper->save($password);
        return $password;
    }

    public function verificaPassword(Utente $utente, string $passwordInChiaro): bool
    {
        $password = $this->getPasswordByAuthenticableInterface($utente);
        return ($password !== null) && Encryptor::verifyBlowfishHash($passwordInChiaro, $password->password);
    }
}
