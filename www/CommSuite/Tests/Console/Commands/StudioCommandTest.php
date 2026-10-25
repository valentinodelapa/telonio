<?php

namespace CommSuite\Tests\Console\Commands;

use CommSuite\Application\Enumerations\RuoloUtente;
use CommSuite\Console\Commands\StudioCommand;
use CommSuite\Tests\SuiteTestCase;

class StudioCommandTest extends SuiteTestCase
{

    /**
     * @return array{esito: bool, output: string}
     */
    private function eseguiComando(array $arguments = [], array $options = []): array
    {
        $comando = new StudioCommand($this->dataMapper);
        $comando->setArguments($arguments);
        $comando->setOptions($options);
        ob_start();
        try {
            $esito = $comando->run();
            $output = (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return ['esito' => $esito, 'output' => $output];
    }

    public function testCheckCompatibilityEHelp(): void
    {
        $comando = new StudioCommand($this->dataMapper);
        $this->assertTrue($comando->checkCompatibility('studio'));
        $this->assertFalse($comando->checkCompatibility('altro'));

        $risultato = $this->eseguiComando([], ['help' => '']);
        $this->assertTrue($risultato['esito']);
        $this->assertStringContainsString('sisma studio crea', $risultato['output']);
    }

    public function testElencoVuotoEConStudi(): void
    {
        $this->testAdapter->accodaSelect([]);
        $vuoto = $this->eseguiComando();
        $this->assertTrue($vuoto['esito']);
        $this->assertStringContainsString('Nessuno studio registrato', $vuoto['output']);

        $studio = $this->creaStudio(1, 'Studio Rossi', '01234567897', true);
        $utente = $this->creaUtente($studio, RuoloUtente::amministratore, 10, 'mrossi');
        $this->testAdapter->accodaSelect([$studio]);
        $this->testAdapter->accodaSelect([$utente]);

        $conDati = $this->eseguiComando(['0' => 'elenco']);
        $this->assertTrue($conDati['esito']);
        $this->assertStringContainsString('[1] Studio Rossi (CF 01234567897, P.IVA 01234567897) - attivo', $conDati['output']);
        $this->assertStringContainsString('mrossi (Mario Rossi <mario.rossi@studio.it>) [amministratore, attivo]', $conDati['output']);
    }

    public function testCreaStudioConSuccesso(): void
    {
        $this->testAdapter->accodaSelect([]); // esisteCodiceFiscale
        $this->testAdapter->accodaSelect([]); // esisteUsername

        $risultato = $this->eseguiComando(['0' => 'crea'], [
            'denominazione' => 'Studio Associato Test',
            'cf' => '01234567897',
            'piva' => 'IT01234567897',
            'username' => 'admin.test',
            'cognome' => 'Rossi',
            'nome' => 'Mario',
            'email' => 'mario.rossi@studio.it',
            'password' => 'PasswordSicura123',
        ]);

        $this->assertTrue($risultato['esito']);
        $this->assertStringContainsString('Studio "Studio Associato Test"', $risultato['output']);
        $this->assertStringContainsString('creato con amministratore "admin.test"', $risultato['output']);
    }

    public function testAmministratoreCreazioneEReimpostazione(): void
    {
        $studio = $this->creaStudio(5, 'Studio Verdi', '01234567897');

        // Creazione nuovo amministratore per studio cercato tramite ID
        $this->testAdapter->accodaSelect([$studio]); // getEntityById
        $this->testAdapter->accodaSelect([]); // getByUsername -> non esiste

        $crea = $this->eseguiComando(['0' => 'amministratore'], [
            'studio' => '5',
            'username' => 'nuovo.admin',
            'cognome' => 'Verdi',
            'nome' => 'Luca',
            'email' => 'luca.verdi@studio.it',
            'password' => 'PasswordSicura123',
        ]);
        $this->assertTrue($crea['esito']);
        $this->assertStringContainsString('Amministratore "nuovo.admin" creato per lo studio "Studio Verdi"', $crea['output']);

        // Reimpostazione utente già appartenente allo studio (cercato tramite CF)
        $operatoreEsistente = $this->creaUtente($studio, RuoloUtente::operatore, 12, 'op.verdi', false);
        $studioCfAlfa = $this->creaStudio(6, 'Studio Bianchi', 'RSSMRA80A01H501U');
        $operatoreEsistente->studio = $studioCfAlfa;
        $this->testAdapter->accodaSelect([$studioCfAlfa]); // getByCodiceFiscale
        $this->testAdapter->accodaSelect([$operatoreEsistente]); // getByUsername

        $reset = $this->eseguiComando(['0' => 'amministratore'], [
            'studio' => 'RSSMRA80A01H501U',
            'username' => 'op.verdi',
            'password' => 'NuovaPassword123',
        ]);
        $this->assertTrue($reset['esito']);
        $this->assertTrue($operatoreEsistente->attivo);
        $this->assertSame(RuoloUtente::amministratore, $operatoreEsistente->ruolo);
        $this->assertStringContainsString('impostato come amministratore attivo', $reset['output']);
    }

    public function testAttivaEDisattivaStudio(): void
    {
        $studio = $this->creaStudio(3, 'Studio Alfa', '01234567897', true);

        $this->testAdapter->accodaSelect([$studio]);
        $disattiva = $this->eseguiComando(['0' => 'disattiva'], ['studio' => '3']);
        $this->assertTrue($disattiva['esito']);
        $this->assertFalse($studio->attivo);
        $this->assertStringContainsString('disattivato', $disattiva['output']);

        $this->testAdapter->accodaSelect([$studio]);
        $attiva = $this->eseguiComando(['0' => 'attiva'], ['studio' => '3']);
        $this->assertTrue($attiva['esito']);
        $this->assertTrue($studio->attivo);
        $this->assertStringContainsString('attivato', $attiva['output']);
    }

    public function testGestioneErroriDiValidazione(): void
    {
        // Azione sconosciuta
        $azioneErrata = $this->eseguiComando(['0' => 'inesistente']);
        $this->assertFalse($azioneErrata['esito']);
        $this->assertStringContainsString('Azione non riconosciuta', $azioneErrata['output']);

        // CF non valido in crea
        $cfErrato = $this->eseguiComando(['0' => 'crea'], [
            'denominazione' => 'Studio Test',
            'cf' => 'NONVALIDO',
        ]);
        $this->assertFalse($cfErrato['esito']);
        $this->assertStringContainsString('Codice fiscale dello studio non valido', $cfErrato['output']);

        // Studio mancante in attiva
        $studioMancante = $this->eseguiComando(['0' => 'attiva']);
        $this->assertFalse($studioMancante['esito']);
        $this->assertStringContainsString('Specificare lo studio con --studio=ID|CF', $studioMancante['output']);

        // Username appartenente a un altro studio in amministratore
        $studio1 = $this->creaStudio(1, 'Studio 1');
        $studio2 = $this->creaStudio(2, 'Studio 2');
        $utenteAltroStudio = $this->creaUtente($studio2, RuoloUtente::operatore, 9, 'condiviso');
        $this->testAdapter->accodaSelect([$studio1]);
        $this->testAdapter->accodaSelect([$utenteAltroStudio]);

        $conflitto = $this->eseguiComando(['0' => 'amministratore'], [
            'studio' => '1',
            'username' => 'condiviso',
        ]);
        $this->assertFalse($conflitto['esito']);
        $this->assertStringContainsString('appartiene già a un altro studio', $conflitto['output']);
    }
}
