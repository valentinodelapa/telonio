<?php

namespace CommSuite\Application\Fixtures;

use CommSuite\Application\Entities\Password;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Core\HelperClasses\Encryptor;

/**
 * Password "operatore" dell'operatore demo: solo per lo sviluppo.
 */
class PasswordOperatoreFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(UtenteOperatoreFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $password = new Password($this->dataMapper);
        $password->utente = $this->getEntityByFixtureName(UtenteOperatoreFixture::class);
        $password->password = Encryptor::getBlowfishHash('operatore');
        $this->addEntity($password);
    }
}
