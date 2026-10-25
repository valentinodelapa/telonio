<?php

namespace Anagrafica\Tests\Services;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Services\StoricoService;
use CommSuite\Tests\SuiteTestCase;
use SismaFramework\Orm\CustomTypes\SismaDate;

class StoricoServiceTest extends SuiteTestCase
{

    private StoricoService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StoricoService($this->dataMapper);
    }

    private function creaVersione(string $validoDal, ?string $validoAl = null, ?int $id = null): DatiCatastali
    {
        $versione = new DatiCatastali($this->dataMapper);
        if ($id !== null) {
            $versione->id = $id;
        }
        $versione->validoDal = new SismaDate($validoDal);
        $versione->validoAl = $validoAl !== null ? new SismaDate($validoAl) : null;
        return $versione;
    }

    public function testVerificaValidaSenzaAltreVersioni(): void
    {
        $versione = $this->creaVersione('2024-01-01', '2024-12-31');
        $this->assertNull($this->service->verifica($versione, []));
    }

    public function testVerificaRifiutaFineValiditaPrecedenteADecorrenza(): void
    {
        $versione = $this->creaVersione('2024-06-01', '2024-05-31');
        $errore = $this->service->verifica($versione, []);
        $this->assertSame('La fine della validità non può precedere la decorrenza.', $errore);
    }

    public function testVerificaRifiutaStessaDecorrenzaDiAltraVersione(): void
    {
        $esistente = $this->creaVersione('2024-01-01', null, 1);
        $nuova = $this->creaVersione('2024-01-01');
        $errore = $this->service->verifica($nuova, [$esistente]);
        $this->assertSame('Esiste già una versione con decorrenza 01/01/2024: modificala invece di crearne una nuova.', $errore);
    }

    public function testVerificaConsenteModificaStessaVersioneConStessaDecorrenza(): void
    {
        $esistente = $this->creaVersione('2024-01-01', null, 1);
        $this->assertNull($this->service->verifica($esistente, [$esistente]));
    }

    public function testVerificaRifiutaSovrapposizioneConVersioneSuccessiva(): void
    {
        $successiva = $this->creaVersione('2024-06-01', null, 2);
        $retroattiva = $this->creaVersione('2024-01-01', '2024-06-01');
        $errore = $this->service->verifica($retroattiva, [$successiva]);
        $this->assertSame('La validità si sovrappone alla versione successiva, in vigore dal 01/06/2024.', $errore);
    }

    public function testRegistraChiudeVersionePrecedenteAperta(): void
    {
        $precedente = $this->creaVersione('2020-01-01', null, 1);
        $nuova = $this->creaVersione('2024-03-15');

        $this->service->registra($nuova, [$precedente]);

        $this->assertNotNull($precedente->validoAl);
        $this->assertSame('2024-03-14', $precedente->validoAl->format('Y-m-d'));
        $this->assertNull($nuova->validoAl);
    }

    public function testRegistraInserimentoRetroattivoAdeguaFineValiditaEPrecedente(): void
    {
        $prima = $this->creaVersione('2020-01-01', '2023-12-31', 1);
        $terza = $this->creaVersione('2024-01-01', null, 2);
        $intermedia = $this->creaVersione('2022-07-01');

        $this->service->registra($intermedia, [$prima, $terza]);

        $this->assertSame('2022-06-30', $prima->validoAl->format('Y-m-d'));
        $this->assertNotNull($intermedia->validoAl);
        $this->assertSame('2023-12-31', $intermedia->validoAl->format('Y-m-d'));
        $this->assertNull($terza->validoAl);
    }

    public function testRegistraRettificaDecorrenzaMantieneContiguitaPrecedente(): void
    {
        $precedente = $this->creaVersione('2020-01-01', '2023-12-31', 1);
        $corrente = $this->creaVersione('2024-02-01', null, 2);
        $decorrenzaOriginale = new SismaDate('2024-01-01');

        $this->service->registra($corrente, [$precedente, $corrente], $decorrenzaOriginale);

        $this->assertSame('2024-01-31', $precedente->validoAl->format('Y-m-d'));
    }

    public function testRegistraNonModificaPrecedenteChiusaNonContigua(): void
    {
        $precedente = $this->creaVersione('2020-01-01', '2021-12-31', 1);
        $nuova = $this->creaVersione('2024-01-01', null, 2);

        $this->service->registra($nuova, [$precedente]);

        $this->assertSame('2021-12-31', $precedente->validoAl->format('Y-m-d'));
    }

    public function testAnnullaEstendePrecedenteContigua(): void
    {
        $precedente = $this->creaVersione('2020-01-01', '2023-12-31', 1);
        $daEliminare = $this->creaVersione('2024-01-01', null, 2);

        $this->service->annulla($daEliminare, [$precedente, $daEliminare]);

        $this->assertNull($precedente->validoAl);
    }

    public function testAnnullaNonModificaPrecedenteNonContigua(): void
    {
        $precedente = $this->creaVersione('2020-01-01', '2022-12-31', 1);
        $daEliminare = $this->creaVersione('2024-01-01', '2024-12-31', 2);

        $this->service->annulla($daEliminare, [$precedente, $daEliminare]);

        $this->assertSame('2022-12-31', $precedente->validoAl->format('Y-m-d'));
    }
}
