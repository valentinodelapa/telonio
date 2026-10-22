<?php

namespace CommSuite\Application\Fixtures;

use CommSuite\Application\Entities\Password;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Core\HelperClasses\Encryptor;

/**
 * Password "admin" dell'utente demo: solo per lo sviluppo.
 */
class PasswordFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(UtenteFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $password = new Password($this->dataMapper);
        $password->utente = $this->getEntityByFixtureName(UtenteFixture::class);
        $password->password = Encryptor::getBlowfishHash('admin');
        $this->addEntity($password);
    }
}
