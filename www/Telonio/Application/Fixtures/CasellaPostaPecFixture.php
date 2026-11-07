<?php

namespace Telonio\Application\Fixtures;

use Telonio\Application\Entities\CasellaPosta;
use Telonio\Application\Enumerations\CifraturaSmtp;
use Telonio\Application\Enumerations\TipoCasellaPosta;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Casella PEC dello studio demo sul mock SMTP Mailpit dello sviluppo
 * (http://mail.telonio.localhost). L'e-mail ordinaria non è configurata e
 * usa i parametri MAIL_* del .env, che in sviluppo puntano anch'essi a
 * Mailpit: così si provano entrambe le strade.
 */
class CasellaPostaPecFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $casella = new CasellaPosta($this->dataMapper);
        $casella->studio = $this->getEntityByFixtureName(StudioFixture::class);
        $casella->tipoCasella = TipoCasellaPosta::pec;
        $casella->host = 'mailpit';
        $casella->porta = 1025;
        $casella->cifratura = CifraturaSmtp::nessuna;
        $casella->indirizzo = 'studio.demo@pec.telonio.localhost';
        $casella->nomeMittente = 'Studio Demo Associati (PEC)';
        $this->addEntity($casella);
    }
}
