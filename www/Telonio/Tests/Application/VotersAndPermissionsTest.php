<?php

namespace Telonio\Tests\Application;

use Telonio\Application\Enumerations\RuoloUtente;
use Telonio\Application\Permissions\StudioPermission;
use Telonio\Application\Permissions\UtentePermission;
use Telonio\Application\Voters\StudioVoter;
use Telonio\Application\Voters\UtenteVoter;
use Telonio\Tests\SuiteTestCase;
use SismaFramework\Core\Exceptions\AccessDeniedException;
use SismaFramework\Security\Enumerations\AccessControlEntry;

class VotersAndPermissionsTest extends SuiteTestCase
{

    public function testStudioVoterEPermission(): void
    {
        $studioA = $this->creaStudio(1, 'Studio A');
        $studioB = $this->creaStudio(2, 'Studio B');
        $adminA = $this->creaUtente($studioA, RuoloUtente::amministratore, 1, 'adminA');
        $operatoreA = $this->creaUtente($studioA, RuoloUtente::operatore, 2, 'opA');

        // Amministratore stesso studio -> consentito
        $this->assertTrue(StudioVoter::isAllowed($studioA, AccessControlEntry::allow, $adminA));
        StudioPermission::isAllowed($studioA, AccessControlEntry::allow, $adminA);

        // Amministratore altro studio -> negato
        $this->assertFalse(StudioVoter::isAllowed($studioB, AccessControlEntry::allow, $adminA));

        // Operatore stesso studio -> negato
        $this->assertFalse(StudioVoter::isAllowed($studioA, AccessControlEntry::allow, $operatoreA));

        // Soggetto o authenticable non valido -> negato
        $this->assertFalse(StudioVoter::isAllowed($operatoreA, AccessControlEntry::allow, $adminA));
        $this->assertFalse(StudioVoter::isAllowed($studioA, AccessControlEntry::allow, null));
    }

    public function testStudioPermissionLanciaAccessDeniedExceptionSeNegato(): void
    {
        $studio = $this->creaStudio(1);
        $operatore = $this->creaUtente($studio, RuoloUtente::operatore, 2, 'op');

        $this->expectException(AccessDeniedException::class);
        StudioPermission::isAllowed($studio, AccessControlEntry::allow, $operatore);
    }

    public function testUtenteVoterEPermission(): void
    {
        $studioA = $this->creaStudio(1, 'Studio A');
        $studioB = $this->creaStudio(2, 'Studio B');
        $adminA = $this->creaUtente($studioA, RuoloUtente::amministratore, 1, 'adminA');
        $operatoreA = $this->creaUtente($studioA, RuoloUtente::operatore, 2, 'opA');
        $utenteB = $this->creaUtente($studioB, RuoloUtente::operatore, 3, 'opB');

        // Amministratore stesso studio -> consentito
        $this->assertTrue(UtenteVoter::isAllowed($operatoreA, AccessControlEntry::allow, $adminA));
        UtentePermission::isAllowed($operatoreA, AccessControlEntry::allow, $adminA);

        // Amministratore altro studio -> negato
        $this->assertFalse(UtenteVoter::isAllowed($utenteB, AccessControlEntry::allow, $adminA));

        // Operatore stesso studio -> negato
        $this->assertFalse(UtenteVoter::isAllowed($operatoreA, AccessControlEntry::allow, $operatoreA));

        // Soggetto o authenticable non valido -> negato
        $this->assertFalse(UtenteVoter::isAllowed($studioA, AccessControlEntry::allow, $adminA));
        $this->assertFalse(UtenteVoter::isAllowed($operatoreA, AccessControlEntry::allow, null));
    }

    public function testUtentePermissionLanciaAccessDeniedExceptionSeNegato(): void
    {
        $studioA = $this->creaStudio(1);
        $studioB = $this->creaStudio(2);
        $adminA = $this->creaUtente($studioA, RuoloUtente::amministratore, 1, 'adminA');
        $utenteB = $this->creaUtente($studioB, RuoloUtente::operatore, 2, 'opB');

        $this->expectException(AccessDeniedException::class);
        UtentePermission::isAllowed($utenteB, AccessControlEntry::allow, $adminA);
    }
}
