<?php

namespace Anagrafica\Tests;

use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use PHPUnit\Framework\TestCase;

class RegimeFiscaleTest extends TestCase
{

    public function testRegimiAmmessiPerTipoDiSoggetto(): void
    {
        $this->assertSame([RegimeFiscale::ordinario, RegimeFiscale::semplificato, RegimeFiscale::forfetario, RegimeFiscale::vantaggio],
                RegimeFiscale::perSoggetto(TipoSoggetto::personaFisica, TipoAttivita::lavoroAutonomo));
        // persona fisica senza attività: nessun regime
        $this->assertSame([], RegimeFiscale::perSoggetto(TipoSoggetto::personaFisica));
        $this->assertSame([RegimeFiscale::ordinario], RegimeFiscale::perSoggetto(TipoSoggetto::societaCapitali));
        $this->assertSame([RegimeFiscale::ordinario, RegimeFiscale::semplificato], RegimeFiscale::perSoggetto(TipoSoggetto::societaPersone));
        $this->assertSame([RegimeFiscale::ordinario, RegimeFiscale::semplificato], RegimeFiscale::perSoggetto(TipoSoggetto::ente));
        $this->assertSame([RegimeFiscale::ordinario, RegimeFiscale::semplificato, RegimeFiscale::forfetarioEnc, RegimeFiscale::associazioniSportive],
                RegimeFiscale::perSoggetto(TipoSoggetto::ente, null, true));
    }

    public function testComportamentoDeiRegimi(): void
    {
        $this->assertFalse(RegimeFiscale::forfetario->isLiquidazioneIva());
        $this->assertFalse(RegimeFiscale::vantaggio->isLiquidazioneIva());
        $this->assertTrue(RegimeFiscale::semplificato->isLiquidazioneIva());
        $this->assertTrue(RegimeFiscale::forfetario->isEsclusoDagliIsa());
        $this->assertFalse(RegimeFiscale::ordinario->isEsclusoDagliIsa());
        $this->assertSame('2015-01-01', RegimeFiscale::forfetario->getDecorrenzaMinima());
        $this->assertNull(RegimeFiscale::ordinario->getDecorrenzaMinima());
        $this->assertSame(PeriodicitaIva::trimestrale, RegimeFiscale::associazioniSportive->getPeriodicitaObbligata());
        $this->assertNull(RegimeFiscale::ordinario->getPeriodicitaObbligata());
        foreach ([...RegimeFiscale::cases(), ...PeriodicitaIva::cases()] as $caso) {
            $this->assertNotSame('', $caso->getLabel());
        }
    }
}
