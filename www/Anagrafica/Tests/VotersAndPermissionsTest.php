<?php

namespace Anagrafica\Tests;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Permissions\ImmobilePermission;
use Anagrafica\Application\Permissions\SoggettoPermission;
use Anagrafica\Application\Voters\ImmobileVoter;
use Anagrafica\Application\Voters\SoggettoVoter;
use Telonio\Application\Enumerations\RuoloUtente;
use Telonio\Tests\SuiteTestCase;
use SismaFramework\Core\Exceptions\AccessDeniedException;
use SismaFramework\Security\Enumerations\AccessControlEntry;

class VotersAndPermissionsTest extends SuiteTestCase
{

    public function testSoggettoVoterEPermission(): void
    {
        $studio1 = $this->creaStudio(1);
        $studio2 = $this->creaStudio(2);
        $utente1 = $this->creaUtente($studio1, RuoloUtente::amministratore, 10);
        $utente2 = $this->creaUtente($studio2, RuoloUtente::amministratore, 20);

        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 100;
        $soggetto->studio = $studio1;

        $this->assertTrue(SoggettoVoter::isAllowed($soggetto, AccessControlEntry::allow, $utente1));
        $this->assertFalse(SoggettoVoter::isAllowed($soggetto, AccessControlEntry::allow, $utente2));
        $this->assertFalse(SoggettoVoter::isAllowed($soggetto, AccessControlEntry::allow, null));

        SoggettoPermission::isAllowed($soggetto, AccessControlEntry::allow, $utente1);

        $this->expectException(AccessDeniedException::class);
        SoggettoPermission::isAllowed($soggetto, AccessControlEntry::allow, $utente2);
    }

    public function testImmobileVoterEPermission(): void
    {
        $studio1 = $this->creaStudio(1);
        $studio2 = $this->creaStudio(2);
        $utente1 = $this->creaUtente($studio1, RuoloUtente::amministratore, 10);
        $utente2 = $this->creaUtente($studio2, RuoloUtente::amministratore, 20);

        $immobile = new Immobile($this->dataMapper);
        $immobile->id = 200;
        $immobile->studio = $studio1;

        $this->assertTrue(ImmobileVoter::isAllowed($immobile, AccessControlEntry::allow, $utente1));
        $this->assertFalse(ImmobileVoter::isAllowed($immobile, AccessControlEntry::allow, $utente2));
        $this->assertFalse(ImmobileVoter::isAllowed($immobile, AccessControlEntry::allow, null));

        ImmobilePermission::isAllowed($immobile, AccessControlEntry::allow, $utente1);

        $this->expectException(AccessDeniedException::class);
        ImmobilePermission::isAllowed($immobile, AccessControlEntry::allow, $utente2);
    }
}
