<?php

namespace Anagrafica\Tests\Services;

use Anagrafica\Application\Controllers\ContrattoLocazioneController;
use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\ContrattoLocazione;
use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\PertinenzaLocata;
use Anagrafica\Application\Entities\VariazioneCanone;
use Anagrafica\Application\Enumerations\CausaleClassamento;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TipoContrattoLocazione;
use Anagrafica\Application\Forms\ContrattoLocazioneForm;
use Anagrafica\Application\Forms\VariazioneCanoneForm;
use Anagrafica\Application\Services\CanoniLocazione;
use Telonio\Application\Enumerations\RuoloUtente;
use Telonio\Tests\SuiteTestCase;
use Telonio\Tests\TestResultSet;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Contratti di locazione: canone del periodo (giorni, variazioni,
 * locazioni brevi), ripartizione tra immobile e pertinenze, controlli del
 * form e pagine del controller.
 */
class ContrattoLocazioneTest extends SuiteTestCase
{

    private Immobile $immobile;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $comune = new Comune($this->dataMapper);
        $comune->id = 1;
        $comune->codiceCatastale = 'H501';
        $comune->denominazione = 'Roma';
        $comune->siglaProvincia = 'RM';
        $this->immobile = $this->creaImmobile(10, TipoCatasto::fabbricati, $comune);
    }

    private function creaImmobile(int $id, TipoCatasto $tipo, Comune $comune): Immobile
    {
        $immobile = new Immobile($this->dataMapper);
        $immobile->id = $id;
        $immobile->studio = $this->creaStudio(1);
        $immobile->tipoCatasto = $tipo;
        $immobile->comune = $comune;
        $immobile->foglio = '1';
        $immobile->particella = (string) $id;
        return $immobile;
    }

    private function creaContratto(TipoContrattoLocazione $tipo, string $inizio, ?string $fine, float $canone): ContrattoLocazione
    {
        $contratto = new ContrattoLocazione($this->dataMapper);
        $contratto->id = 50;
        $contratto->immobile = $this->immobile;
        $contratto->tipo = $tipo;
        $contratto->dataInizio = new SismaDate($inizio);
        $contratto->dataFine = $fine === null ? null : new SismaDate($fine);
        $contratto->canoneAnnuo = $canone;
        return $contratto;
    }

    public function testCanoneInProporzioneAiGiorniConVariazione(): void
    {
        $contratto = $this->creaContratto(TipoContrattoLocazione::canoneLibero, '2024-03-01', null, 7300.0);
        $variazione = new VariazioneCanone($this->dataMapper);
        $variazione->contrattoLocazione = $contratto;
        $variazione->validoDal = new SismaDate('2025-07-01');
        $variazione->canoneAnnuo = 9125.0;
        $this->testAdapter->accodaSelect(new TestResultSet([$variazione]));
        // 181 giorni a 7.300 (20 al giorno) e 184 a 9.125 (25 al giorno)
        $canone = (new CanoniLocazione($this->dataMapper))->getCanonePeriodo($contratto, new SismaDate('2025-01-01'), new SismaDate('2025-12-31'));
        $this->assertSame(round(181 * 20 + 184 * 25, 2), $canone);
    }

    public function testCanoneContrattoIniziatoNellAnno(): void
    {
        $contratto = $this->creaContratto(TipoContrattoLocazione::canoneConcordato, '2025-10-01', null, 3650.0);
        $this->testAdapter->accodaSelect(new TestResultSet([]));
        // dal 1/10 al 31/12: 92 giorni a 10 euro
        $this->assertSame(920.0, (new CanoniLocazione($this->dataMapper))->getCanonePeriodo($contratto, new SismaDate('2025-01-01'), new SismaDate('2025-12-31')));
        $this->assertSame(10, $contratto->getAliquotaCedolare());
    }

    public function testLocazioneBreveACavalloDellAnno(): void
    {
        // esempio delle istruzioni del quadro RB: 980 euro per 14 giorni, 8 nel 2025
        $contratto = $this->creaContratto(TipoContrattoLocazione::locazioneBreve, '2025-12-24', '2026-01-06', 980.0);
        $contratto->cedolareSecca = true;
        $this->assertSame(560.0, (new CanoniLocazione($this->dataMapper))->getCanonePeriodo($contratto, new SismaDate('2025-01-01'), new SismaDate('2025-12-31')));
        $this->assertSame(26, $contratto->getAliquotaCedolare());
        $contratto->cedolareRidotta = true;
        $this->assertSame(21, $contratto->getAliquotaCedolare());
    }

    public function testCedolareRevocata(): void
    {
        $contratto = $this->creaContratto(TipoContrattoLocazione::canoneLibero, '2022-01-01', null, 6000.0);
        $contratto->cedolareSecca = true;
        $contratto->cedolareRevocataDal = new SismaDate('2025-01-01');
        $this->assertTrue($contratto->isCedolareAl(new SismaDate('2024-12-31')));
        $this->assertFalse($contratto->isCedolareAl(new SismaDate('2025-01-01')));
        $this->assertTrue($contratto->isInCorsoTra(new SismaDate('2025-01-01'), new SismaDate('2025-12-31')));
    }

    public function testRipartizioneInProporzioneAlleRendite(): void
    {
        // esempio delle istruzioni: rendite 450 e 50, canone 10.000 -> 9.000 e 1.000
        $contratto = $this->creaContratto(TipoContrattoLocazione::canoneLibero, '2025-01-01', null, 10000.0);
        $box = $this->creaImmobile(11, TipoCatasto::fabbricati, $this->immobile->comune);
        $pertinenza = new PertinenzaLocata($this->dataMapper);
        $pertinenza->contrattoLocazione = $contratto;
        $pertinenza->immobile = $box;
        $this->testAdapter->accodaSelect(
            new TestResultSet([$pertinenza]),
            new TestResultSet([$this->creaDatiCatastali($this->immobile, 450.0)]),
            new TestResultSet([$this->creaDatiCatastali($box, 50.0)]),
        );
        $quote = (new CanoniLocazione($this->dataMapper))->getRipartizione($contratto, new SismaDate('2025-06-30'));
        $this->assertEqualsWithDelta(0.9, $quote[10], 0.0001);
        $this->assertEqualsWithDelta(0.1, $quote[11], 0.0001);

        // locazione breve: tutto all'abitazione
        $contratto->tipo = TipoContrattoLocazione::locazioneBreve;
        $this->testAdapter->accodaSelect(new TestResultSet([$pertinenza]));
        $this->assertSame([10 => 1.0, 11 => 0.0], (new CanoniLocazione($this->dataMapper))->getRipartizione($contratto, new SismaDate('2025-06-30')));
    }

    private function creaDatiCatastali(Immobile $immobile, float $rendita): DatiCatastali
    {
        $dati = new DatiCatastali($this->dataMapper);
        $dati->immobile = $immobile;
        $dati->validoDal = new SismaDate('2000-01-01');
        $dati->causale = CausaleClassamento::costituzione;
        $dati->rendita = $rendita;
        return $dati;
    }

    public function testControlliDelForm(): void
    {
        $contratto = new ContrattoLocazione($this->dataMapper);
        $contratto->immobile = $this->immobile;
        $form = new ContrattoLocazioneForm($contratto, $this->dataMapper);
        $form->handleRequest($this->creaRequest([
            'tipo' => TipoContrattoLocazione::affittoLibero->value,
            'dataInizio' => '2025-01-01',
            'dataFine' => '2024-12-31',
            'canoneAnnuo' => '1.200,50',
            'cedolareSecca' => '1',
            'cedolareRidotta' => '1',
            'codiceIdentificativo' => 'tz12345678901234',
            'dataRegistrazione' => '2025-01-10',
            'cin' => 'IT058091C2ABC',
        ]));
        $this->assertFalse($form->isValid());
        $errori = $form->getFilterErrors();
        foreach (['tipo', 'dataFine', 'cedolareSecca', 'cedolareRidotta', 'codiceIdentificativo', 'cin'] as $campo) {
            $this->assertNotEmpty($errori->{$campo . 'CustomMessage'} ?? null, $campo);
        }

        $form = new ContrattoLocazioneForm($contratto, $this->dataMapper);
        $form->handleRequest($this->creaRequest([
            'tipo' => TipoContrattoLocazione::canoneConcordato->value,
            'dataInizio' => '2025-01-01',
            'canoneAnnuo' => '1.200,50',
            'cedolareSecca' => '1',
            'emergenzaCalamita' => '1',
            'codiceIdentificativo' => 'tz12345678901234',
        ]));
        $this->assertTrue($form->isValid());
        $salvato = $form->resolveEntity();
        $this->assertSame(1200.5, $salvato->canoneAnnuo);
        $this->assertSame('TZ12345678901234', $salvato->codiceIdentificativo);
        $this->assertTrue($salvato->cedolareSecca);
        $this->assertFalse($salvato->veneziaCentro);
    }

    public function testVariazioneDopoLInizio(): void
    {
        $variazione = new VariazioneCanone($this->dataMapper);
        $variazione->contrattoLocazione = $this->creaContratto(TipoContrattoLocazione::canoneLibero, '2025-01-01', null, 6000.0);
        $form = new VariazioneCanoneForm($variazione, $this->dataMapper);
        $form->handleRequest($this->creaRequest(['validoDal' => '2025-01-01', 'canoneAnnuo' => '6100']));
        $this->assertFalse($form->isValid());
        $form = new VariazioneCanoneForm($variazione, $this->dataMapper);
        $form->handleRequest($this->creaRequest(['validoDal' => '2026-01-01', 'canoneAnnuo' => '6.150,00', 'rinegoziazione' => '1']));
        $this->assertTrue($form->isValid());
        $this->assertSame(6150.0, $form->resolveEntity()->canoneAnnuo);
    }

    public function testPagineDelController(): void
    {
        $studio = $this->creaStudio(1);
        $this->utenteCorrente = $this->creaUtente($studio, RuoloUtente::amministratore, 10);
        $this->autenticaUtente($this->utenteCorrente);
        $cattura = $this->catturaRenderERouter();
        $this->testAdapter->accodaSelect(new TestResultSet([$this->utenteCorrente]));
        $controller = new ContrattoLocazioneController($this->dataMapper);

        $controller->create($this->creaRequest([], [], [], 'GET'), $this->immobile);
        $this->assertSame('contrattoLocazione/form', $cattura->ultimaVista);
        $this->assertSame(TipoContrattoLocazione::canoneLibero, $cattura->ultimeVars['formEntity']->tipo);
        $this->assertNotContains(TipoContrattoLocazione::affittoLibero, $cattura->ultimeVars['tipi']);

        $contratto = $this->creaContratto(TipoContrattoLocazione::canoneLibero, '2025-01-01', null, 6000.0);
        $this->testAdapter->accodaSelect(new TestResultSet([]), new TestResultSet([]));
        $controller->show($contratto);
        $this->assertSame('contrattoLocazione/show', $cattura->ultimaVista);

        $controller->variazione($this->creaRequest([], [], [], 'GET'), $contratto);
        $this->assertSame('contrattoLocazione/variazione', $cattura->ultimaVista);
        $controller->pertinenza($this->creaRequest([], [], [], 'GET'), $contratto);
        $this->assertSame('contrattoLocazione/pertinenza', $cattura->ultimaVista);

        $this->testAdapter->impostaSelectCallback(fn() => TestResultSet::conteggio(0));
        $controller->delete($this->creaRequest(), $contratto);
        $this->testAdapter->impostaSelectCallback(null);
        $this->assertSame('immobile/show/immobile/10', $cattura->ultimoRedirect);
    }
}
