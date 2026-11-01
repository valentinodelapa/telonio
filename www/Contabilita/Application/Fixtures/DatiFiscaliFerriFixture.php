<?php

namespace Contabilita\Application\Fixtures;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Models\CodiceAtecoModel;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Ditta individuale demo "Alimentari Ferri": contabilità semplificata,
 * IVA trimestrale, ventilazione dei corrispettivi (art. 24, c. 3, D.P.R.
 * 633/1972; D.M. 24 febbraio 1973).
 */
class DatiFiscaliFerriFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoFerriFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiFiscali = new DatiFiscali($this->dataMapper);
        $datiFiscali->soggetto = $this->getEntityByFixtureName(SoggettoFerriFixture::class);
        $datiFiscali->validoDal = new SismaDate('2025-04-01');
        $datiFiscali->tipoAttivita = TipoAttivita::impresaIndividuale;
        $datiFiscali->denominazione = 'Alimentari Ferri';
        $datiFiscali->partitaIva = '06987651004';
        $datiFiscali->regimeFiscale = RegimeFiscale::semplificato;
        $datiFiscali->periodicitaIva = PeriodicitaIva::trimestrale;
        $datiFiscali->soggettoIsa = true;
        $datiFiscali->ventilazioneCorrispettivi = true;
        $datiFiscali->codiceAteco = (new CodiceAtecoModel($this->dataMapper))->getByCodice(ClassificazioneAteco::ateco2025, '47.11.02');
        $datiFiscali->descrizioneAttivita = 'Commercio al dettaglio di prodotti alimentari';
        $this->addEntity($datiFiscali);
    }
}
