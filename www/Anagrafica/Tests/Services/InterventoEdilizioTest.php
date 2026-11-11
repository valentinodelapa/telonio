<?php

namespace Anagrafica\Tests\Services;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\InterventoEdilizio;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TipoDetrazioneEdilizia as Tipo;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Forms\InterventoEdilizioForm;
use Anagrafica\Application\Services\TabellaDetrazioniEdilizie as Tabella;
use Telonio\Tests\SuiteTestCase;
use Telonio\Tests\TestResultSet;

/**
 * Archivio degli interventi edilizi: percentuali, rateazioni e numero della
 * rata dalle tabelle delle specifiche tecniche del modello Redditi PF 2026
 * (par. 13.1, 14 e 15) e controlli del form.
 */
class InterventoEdilizioTest extends SuiteTestCase
{

    public function testPercentualiDalleTabelle(): void
    {
        // recupero edilizio: 50% fino al 2024; dal 2025 36%, 50% con la casella 3 (abitazione principale)
        $this->assertSame(50.0, Tabella::percentuale(Tipo::ristrutturazione, 2019, null, null));
        $this->assertSame(36.0, Tabella::percentuale(Tipo::ristrutturazione, 2019, 12, null));
        $this->assertSame(65.0, Tabella::percentuale(Tipo::ristrutturazione, 2016, 4, null));
        $this->assertSame(36.0, Tabella::percentuale(Tipo::ristrutturazione, 2025, null, null));
        $this->assertSame(50.0, Tabella::percentuale(Tipo::ristrutturazione, 2025, null, 3));
        // superbonus: 110% con il codice 1; 90% nel 2023 e 70% nel 2024 con il codice 2; 65% nel 2025
        $this->assertSame(110.0, Tabella::percentuale(Tipo::ristrutturazione, 2022, 5, 1));
        $this->assertSame(70.0, Tabella::percentuale(Tipo::ristrutturazione, 2022, 6, null));
        $this->assertSame(90.0, Tabella::percentuale(Tipo::ristrutturazione, 2023, 16, 2));
        $this->assertSame(50.0, Tabella::percentuale(Tipo::ristrutturazione, 2023, 17, null));
        $this->assertSame(70.0, Tabella::percentuale(Tipo::ristrutturazione, 2024, 20, 2));
        $this->assertSame(65.0, Tabella::percentuale(Tipo::ristrutturazione, 2025, 18, 2));
        $this->assertSame(60.0, Tabella::percentuale(Tipo::ristrutturazione, 2022, 15, null));
        $this->assertSame(75.0, Tabella::percentuale(Tipo::ristrutturazione, 2025, 21, null));
        // combinazioni non ammesse: tipologia 16 del 2024 senza casella, codice 3 prima del 2025, tipologia 23 prima del 2025
        $this->assertNull(Tabella::percentuale(Tipo::ristrutturazione, 2024, 16, null));
        $this->assertNull(Tabella::percentuale(Tipo::ristrutturazione, 2024, null, 3));
        $this->assertNull(Tabella::percentuale(Tipo::ristrutturazione, 2024, 23, null));

        // risparmio energetico (tabella del rigo RP65)
        $this->assertSame(65.0, Tabella::percentuale(Tipo::risparmioEnergetico, 2017, 5, null));
        $this->assertSame(50.0, Tabella::percentuale(Tipo::risparmioEnergetico, 2019, 5, null));
        $this->assertSame(85.0, Tabella::percentuale(Tipo::risparmioEnergetico, 2020, 11, null));
        $this->assertSame(110.0, Tabella::percentuale(Tipo::risparmioEnergetico, 2021, 30, null));
        $this->assertSame(90.0, Tabella::percentuale(Tipo::risparmioEnergetico, 2023, 2, 2));
        $this->assertSame(36.0, Tabella::percentuale(Tipo::risparmioEnergetico, 2025, 1, null));
        $this->assertSame(50.0, Tabella::percentuale(Tipo::risparmioEnergetico, 2025, 8, 3));
        $this->assertNull(Tabella::percentuale(Tipo::risparmioEnergetico, 2025, 31, null));

        // colonnine (par. 14.3 e 14.4) e bonus mobili
        $this->assertSame(50.0, Tabella::percentuale(Tipo::colonnine, 2020, 2, null));
        $this->assertSame(110.0, Tabella::percentuale(Tipo::colonnine, 2022, 3, null));
        $this->assertSame(70.0, Tabella::percentuale(Tipo::colonnine, 2024, 4, 3));
        $this->assertSame(65.0, Tabella::percentuale(Tipo::colonnine, 2025, 5, 4));
        $this->assertSame(50.0, Tabella::percentuale(Tipo::arredo, 2025, null, null));
    }

    public function testRateazioniENumeroDellaRata(): void
    {
        $this->assertSame([10], Tabella::rateazioni(Tipo::ristrutturazione, 2020, null, null));
        $this->assertSame([5], Tabella::rateazioni(Tipo::ristrutturazione, 2021, 18, null));
        $this->assertSame([4, 10], Tabella::rateazioni(Tipo::ristrutturazione, 2022, 5, 1));
        $this->assertSame([5], Tabella::rateazioni(Tipo::ristrutturazione, 2022, 5, null));
        $this->assertSame([4], Tabella::rateazioni(Tipo::ristrutturazione, 2023, 16, 1));
        $this->assertSame([10], Tabella::rateazioni(Tipo::ristrutturazione, 2023, 16, 1, true));
        $this->assertSame([10], Tabella::rateazioni(Tipo::ristrutturazione, 2024, 6, 1));
        $this->assertSame([5], Tabella::rateazioni(Tipo::risparmioEnergetico, 2021, 2, 1));
        $this->assertSame([10], Tabella::rateazioni(Tipo::risparmioEnergetico, 2021, 2, null));
        $this->assertSame([5], Tabella::rateazioni(Tipo::colonnine, 2021, 3, null));

        // numero della rata nel 2025 (tabella "Coerenza tra il numero di rata e l'anno")
        $this->assertSame(10, Tabella::numeroRata(Tipo::ristrutturazione, 2016, null, null, 10, 2025));
        $this->assertSame(1, Tabella::numeroRata(Tipo::ristrutturazione, 2025, null, 3, 10, 2025));
        $this->assertSame(4, Tabella::numeroRata(Tipo::ristrutturazione, 2022, null, null, 10, 2025));
        // spese 2022 del superbonus in 10 rate dal 2023: terza rata
        $this->assertSame(3, Tabella::numeroRata(Tipo::ristrutturazione, 2022, 5, 1, 10, 2025));
        $this->assertSame(4, Tabella::numeroRata(Tipo::ristrutturazione, 2022, 5, 1, 4, 2025));
        $this->assertSame(3, Tabella::numeroRata(Tipo::ristrutturazione, 2022, 19, null, 10, 2025));
        $this->assertSame(3, Tabella::numeroRata(Tipo::colonnine, 2022, 3, null, 10, 2025));
        // rate finite e spese future
        $this->assertNull(Tabella::numeroRata(Tipo::ristrutturazione, 2021, 18, null, 5, 2026));
        $this->assertNull(Tabella::numeroRata(Tipo::arredo, 2026, null, null, 10, 2025));

        $intervento = new InterventoEdilizio($this->dataMapper);
        $intervento->tipo = Tipo::ristrutturazione;
        $intervento->anno = 2025;
        $intervento->spesa = 30005.0;
        $intervento->rateazione = 10;
        $this->assertSame(3001, $intervento->getImportoRata());
        $this->assertSame(1, $intervento->getNumeroRata(2025));
    }

    public function testLimitiDiSpesa(): void
    {
        $this->assertSame(96000.0, Tabella::limiteSpesa(Tipo::ristrutturazione, 2025, null));
        $this->assertSame(144000.0, Tabella::limiteSpesa(Tipo::ristrutturazione, 2022, 6, true));
        $this->assertSame(48000.0, Tabella::limiteSpesa(Tipo::ristrutturazione, 2023, 16));
        $this->assertSame(5000.0, Tabella::limiteSpesa(Tipo::arredo, 2025, null));
        $this->assertSame(16000.0, Tabella::limiteSpesa(Tipo::arredo, 2021, null));
        $this->assertNull(Tabella::limiteSpesa(Tipo::risparmioEnergetico, 2025, 1));
    }

    private function creaIntervento(bool $conSoggetto = true): InterventoEdilizio
    {
        $studio = $this->creaStudio(1);
        $immobile = new Immobile($this->dataMapper);
        $immobile->id = 5;
        $immobile->studio = $studio;
        $immobile->tipoCatasto = TipoCatasto::fabbricati;
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 1;
        $soggetto->studio = $studio;
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $intervento = new InterventoEdilizio($this->dataMapper);
        $intervento->immobile = $immobile;
        if ($conSoggetto) {
            $intervento->soggetto = $soggetto;
        }
        return $intervento;
    }

    public function testForm(): void
    {
        // percentuale e rate proposte dalla tabella: abitazione principale 2025 al 50% in 10 rate
        $form = new InterventoEdilizioForm($this->creaIntervento(), $this->dataMapper);
        $form->handleRequest($this->creaRequest(['tipo' => 'RE', 'anno' => '2025', 'codicePercentuale' => '3', 'spesa' => '30.000,00']));
        $this->assertTrue($form->isValid());
        $intervento = $form->resolveEntity();
        $this->assertSame(50.0, $intervento->percentualeDetrazione);
        $this->assertSame(10, $intervento->rateazione);
        $this->assertSame(30000.0, $intervento->spesa);

        // superbonus 2022 in 10 rate: ammesso
        $form = new InterventoEdilizioForm($this->creaIntervento(), $this->dataMapper);
        $form->handleRequest($this->creaRequest(['tipo' => 'RE', 'anno' => '2022', 'tipologia' => '5', 'codicePercentuale' => '1', 'codiceFiscaleCondominio' => '80012345676', 'spesa' => '50000', 'rateazione' => '10']));
        $this->assertTrue($form->isValid());

        // tipologia non ammessa per l'anno, percentuale diversa dalla tabella, rate non ammesse, sisma senza superbonus
        $form = new InterventoEdilizioForm($this->creaIntervento(), $this->dataMapper);
        $form->handleRequest($this->creaRequest(['tipo' => 'RE', 'anno' => '2025', 'tipologia' => '15', 'spesa' => '1000', 'percentualeDetrazione' => '50', 'rateazione' => '5', 'maggiorazioneSisma' => '1']));
        $this->assertFalse($form->isValid());
        $errori = $form->getFilterErrors();
        $this->assertNotEmpty($errori->tipologiaCustomMessage);
        $this->assertNotEmpty($errori->rateazioneCustomMessage);
        $this->assertNotEmpty($errori->maggiorazioneSismaCustomMessage);

        $form = new InterventoEdilizioForm($this->creaIntervento(), $this->dataMapper);
        $form->handleRequest($this->creaRequest(['tipo' => 'RE', 'anno' => '2025', 'spesa' => '1000', 'percentualeDetrazione' => '50']));
        $this->assertFalse($form->isValid());
        $this->assertNotEmpty($form->getFilterErrors()->percentualeDetrazioneCustomMessage);

        // condominio obbligatorio (tipologia 19), bonus mobili oltre il limite, casella "Percentuale" obbligatoria (2025, tipo 30)
        $form = new InterventoEdilizioForm($this->creaIntervento(), $this->dataMapper);
        $form->handleRequest($this->creaRequest(['tipo' => 'RE', 'anno' => '2025', 'tipologia' => '19', 'spesa' => '1000']));
        $this->assertFalse($form->isValid());
        $this->assertNotEmpty($form->getFilterErrors()->codiceFiscaleCondominioCustomMessage);
        $form = new InterventoEdilizioForm($this->creaIntervento(), $this->dataMapper);
        $form->handleRequest($this->creaRequest(['tipo' => 'AR', 'anno' => '2025', 'spesa' => '6000']));
        $this->assertFalse($form->isValid());
        $this->assertNotEmpty($form->getFilterErrors()->spesaCustomMessage);
        $form = new InterventoEdilizioForm($this->creaIntervento(), $this->dataMapper);
        $form->handleRequest($this->creaRequest(['tipo' => 'EN', 'anno' => '2025', 'tipologia' => '30', 'spesa' => '20000']));
        $this->assertFalse($form->isValid());
        $this->assertNotEmpty($form->getFilterErrors()->codicePercentualeCustomMessage);

        // il beneficiario scelto dev'essere una persona fisica dello studio
        $societa = new Soggetto($this->dataMapper);
        $societa->id = 9;
        $societa->studio = $this->creaStudio(1);
        $societa->tipoSoggetto = TipoSoggetto::societaCapitali;
        $intervento = $this->creaIntervento(false);
        $this->testAdapter->accodaSelect(new TestResultSet([$societa]));
        $form = new InterventoEdilizioForm($intervento, $this->dataMapper);
        $form->handleRequest($this->creaRequest(['soggetto' => '9', 'tipo' => 'AR', 'anno' => '2025', 'spesa' => '2000']));
        $this->assertFalse($form->isValid());
        $this->assertNotEmpty($form->getFilterErrors()->soggettoCustomMessage);
    }
}
