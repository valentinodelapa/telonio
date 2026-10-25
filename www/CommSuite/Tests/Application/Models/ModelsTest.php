<?php

namespace CommSuite\Tests\Application\Models;

use CommSuite\Application\Entities\Password;
use CommSuite\Application\Entities\Studio;
use CommSuite\Application\Enumerations\RuoloUtente;
use CommSuite\Application\Models\PasswordModel;
use CommSuite\Application\Models\StudioModel;
use CommSuite\Application\Models\UtenteModel;
use CommSuite\Tests\SuiteTestCase;
use SismaFramework\Core\HelperClasses\Encryptor;

class ModelsTest extends SuiteTestCase
{

    public function testStudioModelGetElencoEEsisteCodiceFiscale(): void
    {
        $studioModel = new StudioModel($this->dataMapper);
        $studio1 = $this->creaStudio(1, 'Studio Alfa', '01234567897');
        $studio2 = $this->creaStudio(2, 'Studio Beta', '01234567897');

        $this->testAdapter->accodaSelect([$studio1, $studio2]);
        $elenco = $studioModel->getElenco();
        $this->assertCount(2, $elenco);

        // Nessun risultato -> false
        $this->testAdapter->accodaSelect([]);
        $this->assertFalse($studioModel->esisteCodiceFiscale('01234567897'));

        // Trovato senza escluso -> true
        $this->testAdapter->accodaSelect([$studio1]);
        $this->assertTrue($studioModel->esisteCodiceFiscale('01234567897'));

        // Trovato ma coincide con l'entità esclusa -> false
        $this->testAdapter->accodaSelect([$studio1]);
        $this->assertFalse($studioModel->esisteCodiceFiscale('01234567897', $studio1));

        // Trovato con entità esclusa nuova (senza id) -> true
        $studioNuovo = new Studio($this->dataMapper);
        $this->testAdapter->accodaSelect([$studio1]);
        $this->assertTrue($studioModel->esisteCodiceFiscale('01234567897', $studioNuovo));

        // Trovato un altro studio diverso dall'escluso -> true
        $this->testAdapter->accodaSelect([$studio1, $studio2]);
        $this->assertTrue($studioModel->esisteCodiceFiscale('01234567897', $studio1));
    }

    public function testUtenteModelGetValidAuthenticableInterfaceByIdentifier(): void
    {
        $utenteModel = new UtenteModel($this->dataMapper);
        $studioAttivo = $this->creaStudio(1, 'Studio Attivo', '01234567897', true);
        $studioDisattivato = $this->creaStudio(2, 'Studio Disattivato', '01234567897', false);

        $utenteStudioAttivo = $this->creaUtente($studioAttivo, RuoloUtente::amministratore, 1, 'mario');
        $utenteStudioDisattivato = $this->creaUtente($studioDisattivato, RuoloUtente::amministratore, 2, 'mario');

        // Studio attivo -> restituisce l'utente
        $this->testAdapter->accodaSelect([$utenteStudioAttivo]);
        $this->assertSame($utenteStudioAttivo, $utenteModel->getValidAuthenticableInterfaceByIdentifier('mario'));

        // Studio disattivato -> restituisce null
        $this->testAdapter->accodaSelect([$utenteStudioDisattivato]);
        $this->assertNull($utenteModel->getValidAuthenticableInterfaceByIdentifier('mario'));
    }

    public function testUtenteModelGetElencoEsisteUsernameEContaAmministratoriAttivi(): void
    {
        $utenteModel = new UtenteModel($this->dataMapper);
        $studio = $this->creaStudio(1);
        $admin1 = $this->creaUtente($studio, RuoloUtente::amministratore, 1, 'admin1');
        $admin2 = $this->creaUtente($studio, RuoloUtente::amministratore, 2, 'admin2');

        $this->testAdapter->accodaSelect([$admin1, $admin2]);
        $this->assertCount(2, $utenteModel->getElenco($studio));

        // esisteUsername
        $this->testAdapter->accodaSelect([]);
        $this->assertFalse($utenteModel->esisteUsername('admin1'));

        $this->testAdapter->accodaSelect([$admin1]);
        $this->assertTrue($utenteModel->esisteUsername('admin1'));

        $this->testAdapter->accodaSelect([$admin1]);
        $this->assertFalse($utenteModel->esisteUsername('admin1', $admin1));

        $this->testAdapter->accodaSelect([$admin2]);
        $this->assertTrue($utenteModel->esisteUsername('admin1', $admin1));

        // contaAmministratoriAttivi
        $this->testAdapter->accodaSelect([$admin1, $admin2]);
        $this->assertSame(2, $utenteModel->contaAmministratoriAttivi($studio));

        $this->testAdapter->accodaSelect([$admin1, $admin2]);
        $this->assertSame(1, $utenteModel->contaAmministratoriAttivi($studio, $admin1));

        $this->testAdapter->accodaSelect([$admin1]);
        $this->assertSame(0, $utenteModel->contaAmministratoriAttivi($studio, $admin1));
    }

    public function testPasswordModelSalvaGetEVerificaPassword(): void
    {
        $passwordModel = new PasswordModel($this->dataMapper);
        $utente = $this->creaUtente();

        $passwordSalvata = $passwordModel->salvaPassword($utente, 'SegretoSicuro123');
        $this->assertInstanceOf(Password::class, $passwordSalvata);
        $this->assertSame($utente, $passwordSalvata->utente);
        $this->assertTrue(Encryptor::verifyBlowfishHash('SegretoSicuro123', $passwordSalvata->password));

        $this->testAdapter->accodaSelect([$passwordSalvata]);
        $this->assertSame($passwordSalvata, $passwordModel->getPasswordByAuthenticableInterface($utente));

        $this->testAdapter->accodaSelect([$passwordSalvata]);
        $this->assertTrue($passwordModel->verificaPassword($utente, 'SegretoSicuro123'));

        $this->testAdapter->accodaSelect([$passwordSalvata]);
        $this->assertFalse($passwordModel->verificaPassword($utente, 'PasswordErrata'));

        $this->testAdapter->accodaSelect([]);
        $this->assertFalse($passwordModel->verificaPassword($utente, 'SegretoSicuro123'));
    }
}
