<?php

namespace Anagrafica\Tests\Services;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Services\SincronizzazioneSoggetto;
use CommSuite\Tests\SuiteTestCase;
use SismaFramework\Orm\CustomTypes\SismaDate;

class SincronizzazioneSoggettoTest extends SuiteTestCase
{

    private SincronizzazioneSoggetto $servizio;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->servizio = new SincronizzazioneSoggetto($this->dataMapper);
    }

    public function testAggiornaPersonaFisicaConDatiFiscaliVigenti(): void
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 1;
        $soggetto->studio = $this->creaStudio();
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->cognome = 'Rossi';
        $soggetto->nome = 'Mario';
        $soggetto->nominativo = 'VECCHIO';
        $soggetto->codiceFiscale = 'RSSMRA80A01H501U';

        $datiFiscali = new DatiFiscali($this->dataMapper);
        $datiFiscali->id = 10;
        $datiFiscali->soggetto = $soggetto;
        $datiFiscali->validoDal = new SismaDate('2020-01-01');
        $datiFiscali->partitaIva = '01234567897';
        $datiFiscali->tipoAttivita = TipoAttivita::impresaIndividuale;

        $this->testAdapter->accodaSelect([$datiFiscali]);

        $this->servizio->aggiorna($soggetto, true);

        $this->assertSame('ROSSI MARIO', $soggetto->nominativo);
        $this->assertSame('01234567897', $soggetto->partitaIva);
        $this->assertSame(TipoAttivita::impresaIndividuale, $soggetto->tipoAttivita);
        $this->assertNotNull($soggetto->dataModifica);
    }

    public function testAggiornaSocietaRipiegaSuStoricoSeNessunVigenteOggi(): void
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 2;
        $soggetto->studio = $this->creaStudio();
        $soggetto->tipoSoggetto = TipoSoggetto::societaCapitali;
        $soggetto->nominativo = 'Alfa S.r.l.';
        $soggetto->codiceFiscale = '01234567897';

        $datiFiscali = new DatiFiscali($this->dataMapper);
        $datiFiscali->id = 20;
        $datiFiscali->soggetto = $soggetto;
        $datiFiscali->validoDal = new SismaDate('2020-01-01');
        $datiFiscali->validoAl = new SismaDate('2023-12-31');
        $datiFiscali->denominazione = 'Alfa Nuova S.r.l.';
        $datiFiscali->naturaGiuridica = NaturaGiuridica::societaResponsabilitaLimitata;
        $datiFiscali->partitaIva = '01234567897';

        $this->testAdapter->accodaSelect([]);
        $this->testAdapter->accodaSelect([$datiFiscali]);

        $this->servizio->aggiorna($soggetto, false);

        $this->assertSame('Alfa Nuova S.r.l.', $soggetto->nominativo);
        $this->assertSame('01234567897', $soggetto->partitaIva);
        $this->assertNull($soggetto->dataModifica);
    }
}
