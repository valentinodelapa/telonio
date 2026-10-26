<?php

namespace GestioneStudio\Tests;

use CommSuite\Application\Entities\Studio;
use CommSuite\Application\Enumerations\ModuloSuite;
use CommSuite\Application\Interfaces\FonteScadenze;
use CommSuite\Application\Services\RaccoltaScadenze;
use CommSuite\Application\Services\Scadenza;
use CommSuite\Tests\TestResultSet;
use GestioneStudio\Application\Entities\LavorazioneScadenza;
use GestioneStudio\Application\Enumerations\FiltroStato;
use GestioneStudio\Application\Enumerations\StatoLavorazione;
use GestioneStudio\Application\Services\CalendarioAdempimenti;
use GestioneStudio\Application\Services\RiepilogoScrivania;
use GestioneStudio\Application\Services\ScadenzeModulo;
use GestioneStudio\Application\Services\Scadenzario;
use GestioneStudio\Application\Services\VoceScadenzario;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

class ServicesTest extends GestioneStudioTestCase
{

    /**
     * Risponde alle query di termini e proroghe con le entità indicate.
     */
    private function impostaCalendario(array $termini, array $proroghe = [], array $assegnazioni = [], array $lavorazioni = []): void
    {
        $this->testAdapter->impostaSelectCallback(function (string $cmd, array $bind) use ($termini, $proroghe, $assegnazioni, $lavorazioni): TestResultSet {
            if (str_contains($cmd, 'FROM `termine_adempimento`')) {
                return new TestResultSet(array_filter($termini, fn($termine) => $termine->adempimento->id === $bind[0]));
            }
            if (str_contains($cmd, 'FROM `proroga_adempimento`')) {
                $delloStudio = str_contains($cmd, '`studio_id` = ?');
                return new TestResultSet(array_filter($proroghe, fn($proroga) => ($proroga->termineAdempimento->id === $bind[0]) && ($proroga->anno === $bind[1])
                                        && (($proroga->studio !== null) === $delloStudio)));
            }
            if (str_contains($cmd, 'FROM `assegnazione_adempimento`')) {
                return new TestResultSet($assegnazioni);
            }
            if (str_contains($cmd, 'FROM `lavorazione_scadenza`')) {
                return new TestResultSet($lavorazioni);
            }
            return new TestResultSet([]);
        });
    }

    public function testTerminiEffettiviConSlittamentoAgostoEProroghe(): void
    {
        $iva = $this->creaAdempimento(2, 'IVA_TRIMESTRALE');
        $trimestri = [$this->creaTermine($iva, 1, '1° trimestre', 5, 16), $this->creaTermine($iva, 2, '2° trimestre', 8, 16), $this->creaTermine($iva, 3, '3° trimestre', 11, 16)];
        $redditi = $this->creaAdempimento(10, 'REDDITI_PF');
        $termineRedditi = $this->creaTermine($redditi, 1, 'anno', 10, 31, 1);
        $proroghe = [
            $this->creaProroga($termineRedditi, 2025, '2026-11-30'),
            $this->creaProroga($termineRedditi, 2024, '2025-11-15'),
            $this->creaProroga($termineRedditi, 2024, '2025-12-15', true),
        ];
        $this->impostaCalendario([...$trimestri, $termineRedditi], $proroghe);
        $calendario = new CalendarioAdempimenti($this->studioPredefinito, $this->dataMapper);

        // 2027: 16/05 domenica -> 17/05; 16/08 -> 20/08 (venerdì); 16/11 martedì
        $date = array_map(fn($effettivo) => $effettivo->data->format('Y-m-d'), $calendario->getTermini($iva, new SismaDate('2027-01-01'), new SismaDate('2027-12-31')));
        $this->assertSame(['2027-05-17', '2027-08-20', '2027-11-16'], $date);

        // proroga del calendario fiscale per l'anno 2025, della quale si tiene traccia
        $anno2025 = $calendario->getTerminiAnno($redditi, 2025)[0];
        $this->assertSame('2026-11-30', $anno2025->data->format('Y-m-d'));
        $this->assertSame('2026-10-31', $anno2025->ordinario->format('Y-m-d'));
        $this->assertSame('D.L. di prova', $anno2025->proroga->provvedimento);
        // per il 2024 prevale la proroga dello studio
        $this->assertSame('2025-12-15', $calendario->getTerminiAnno($redditi, 2024)[0]->data->format('Y-m-d'));
        // senza proroghe: 31/10/2027 domenica, 01/11 festivo -> 02/11/2027
        $this->assertSame('2027-11-02', $calendario->getTerminiAnno($redditi, 2026)[0]->data->format('Y-m-d'));

        // nel periodo cadono solo i termini con data effettiva compresa
        $nelPeriodo = $calendario->getTermini($redditi, new SismaDate('2026-11-01'), new SismaDate('2026-12-31'));
        $this->assertCount(1, $nelPeriodo);
        $this->assertSame(2025, $nelPeriodo[0]->anno);
    }

    public function testTerminiSenzaSlittamentoNeProrogaDiAgosto(): void
    {
        $interno = $this->creaAdempimento(1000, 'INTERNO', true, false, false);
        $this->impostaCalendario([$this->creaTermine($interno, 1, 'anno', 8, 16)]);
        $calendario = new CalendarioAdempimenti($this->studioPredefinito, $this->dataMapper);
        // 16/08/2026 è domenica: resta tale
        $this->assertSame('2026-08-16', $calendario->getTerminiAnno($interno, 2026)[0]->data->format('Y-m-d'));
        $this->assertSame([], $calendario->getTermini($this->creaAdempimento(11, 'SENZA_TERMINI'), new SismaDate('2026-01-01'), new SismaDate('2026-12-31')));
    }

    public function testScadenzeDegliAdempimentiNellaValiditaDellAssegnazione(): void
    {
        $iva = $this->creaAdempimento(1, 'IVA_MENSILE');
        $termini = [];
        foreach (['gennaio', 'febbraio', 'marzo', 'aprile'] as $indice => $mese) {
            $termini[] = $this->creaTermine($iva, $indice + 1, $mese, $indice + 2, 16);
        }
        $soggetto = $this->creaSoggetto();
        // dovuto fino al 31/03/2026: la liquidazione di marzo (16/04) è esclusa
        $assegnazione = $this->creaAssegnazione($soggetto, $iva, '2026-01-01', '2026-03-31');
        $this->impostaCalendario($termini, [], [$assegnazione]);

        $scadenze = (new ScadenzeModulo($this->dataMapper))->getScadenze($this->studioPredefinito, new SismaDate('2026-01-01'), new SismaDate('2026-06-30'));
        $this->assertSame(['2026-02-16', '2026-03-16'], array_map(fn(Scadenza $scadenza) => $scadenza->data->format('Y-m-d'), $scadenze));
        $this->assertSame(ModuloSuite::gestioneStudio, $scadenze[0]->modulo);
        $this->assertSame('T101/2026/S50', $scadenze[0]->riferimento);
        $this->assertSame('Adempimento IVA_MENSILE - gennaio 2026', $scadenze[0]->descrizione);
        $this->assertSame($this->utenteCorrente, $scadenze[0]->responsabile);
        $this->assertSame($soggetto, $scadenze[0]->soggetto);

        // adempimento disattivato: nessuna scadenza
        $iva->attivo = false;
        $this->assertSame([], (new ScadenzeModulo($this->dataMapper))->getScadenze($this->studioPredefinito, new SismaDate('2026-01-01'), new SismaDate('2026-06-30')));
    }

    private function creaFonte(array $scadenze): FonteScadenze
    {
        return new class($scadenze) implements FonteScadenze {

            public function __construct(private DataMapper|array $scadenze)
            {

            }

            public function getScadenze(Studio $studio, SismaDate $dal, SismaDate $al, ?BaseEntity $soggetto = null): array
            {
                return $this->scadenze;
            }
        };
    }

    public function testScadenzarioConLavorazioniFiltriEResponsabili(): void
    {
        $soggetto = $this->creaSoggetto();
        $operatore = $this->creaUtente($this->studioPredefinito, id: 2, username: 'operatore');
        $delega = new Scadenza(ModuloSuite::deleghe, 'S50/2026-11-16', new SismaDate('2026-11-16'), 'Versamenti F24', $soggetto, 120.5);
        $trasmessa = new Scadenza(ModuloSuite::deleghe, 'S50/2026-10-16', new SismaDate('2026-10-16'), 'Versamenti F24', $soggetto, 80.0, completata: true);
        $adempimento = new Scadenza(ModuloSuite::gestioneStudio, 'T1001/2025/S50', new SismaDate('2026-11-02'), 'Redditi', $soggetto, responsabile: $this->utenteCorrente);
        $lavorazione = new LavorazioneScadenza($this->dataMapper);
        $lavorazione->id = 1;
        $lavorazione->studio = $this->studioPredefinito;
        $lavorazione->modulo = 'GestioneStudio';
        $lavorazione->riferimento = 'T1001/2025/S50';
        $lavorazione->stato = StatoLavorazione::inLavorazione;
        $lavorazione->responsabile = $operatore;
        $this->impostaCalendario([], [], [], [$lavorazione]);
        $raccolta = new RaccoltaScadenze($this->dataMapper, ['Deleghe' => $this->creaFonte([$delega, $trasmessa]), 'GestioneStudio' => $this->creaFonte([$adempimento])]);
        $scadenzario = new Scadenzario($this->dataMapper, $raccolta);
        $dal = new SismaDate('2026-10-01');
        $al = new SismaDate('2026-12-31');

        $voci = $scadenzario->getVoci($this->studioPredefinito, $dal, $al);
        $this->assertSame(['2026-10-16', '2026-11-02', '2026-11-16'], array_map(fn(VoceScadenzario $voce) => $voce->scadenza->data->format('Y-m-d'), $voci));
        $this->assertSame(StatoLavorazione::completata, $voci[0]->stato);
        $this->assertTrue($voci[0]->isCompletataDalModulo());
        $this->assertSame(StatoLavorazione::inLavorazione, $voci[1]->stato);
        // il responsabile della lavorazione prevale su quello dell'assegnazione
        $this->assertSame($operatore, $voci[1]->responsabile);
        $this->assertSame(StatoLavorazione::daFare, $voci[2]->stato);
        $this->assertTrue($voci[2]->isScaduta(new SismaDate('2026-11-17')));
        $this->assertFalse($voci[0]->isScaduta(new SismaDate('2026-11-17')));

        $this->assertCount(2, $scadenzario->getVoci($this->studioPredefinito, $dal, $al, filtro: FiltroStato::aperte));
        $this->assertCount(1, $scadenzario->getVoci($this->studioPredefinito, $dal, $al, filtro: FiltroStato::chiuse));
        $this->assertCount(1, $scadenzario->getVoci($this->studioPredefinito, $dal, $al, responsabile: 2));
        $this->assertCount(2, $scadenzario->getVoci($this->studioPredefinito, $dal, $al, responsabile: Scadenzario::RESPONSABILE_NESSUNO));
        $this->assertCount(1, $scadenzario->getVoci($this->studioPredefinito, $dal, $al, modulo: ModuloSuite::gestioneStudio));
        $this->assertSame($voci[1]->lavorazione, $scadenzario->trova($this->studioPredefinito, ModuloSuite::gestioneStudio, 'T1001/2025/S50', $dal, $al)->lavorazione);
        $this->assertNull($scadenzario->trova($this->studioPredefinito, ModuloSuite::deleghe, 'inesistente', $dal, $al));
    }

    public function testRegistraLaLavorazioneConDataEUtenteDiCompletamento(): void
    {
        $this->impostaCalendario([]);
        $scadenzario = new Scadenzario($this->dataMapper, new RaccoltaScadenze($this->dataMapper, []));
        $scadenza = new Scadenza(ModuloSuite::gestioneStudio, 'T1001/2025/S50', new SismaDate('2026-11-02'), str_repeat('x', 300), $this->creaSoggetto(), responsabile: $this->utenteCorrente);
        $voce = new VoceScadenzario($scadenza);

        $lavorazione = $scadenzario->preparaLavorazione($this->studioPredefinito, $voce);
        $this->assertSame($this->utenteCorrente, $lavorazione->responsabile);
        $lavorazione->stato = StatoLavorazione::completata;
        $scadenzario->registra($scadenza, $lavorazione, null, $this->utenteCorrente);
        $this->assertSame('GestioneStudio', $lavorazione->modulo);
        $this->assertSame('T1001/2025/S50', $lavorazione->riferimento);
        $this->assertSame(255, mb_strlen($lavorazione->descrizione));
        $this->assertSame((new SismaDate())->format('Y-m-d'), $lavorazione->dataCompletamento->format('Y-m-d'));
        $this->assertSame($this->utenteCorrente, $lavorazione->utenteCompletamento);

        // riaperta: la data di completamento si azzera
        $lavorazione->stato = StatoLavorazione::daFare;
        $scadenzario->registra($scadenza, $lavorazione, StatoLavorazione::completata, $this->utenteCorrente);
        $this->assertNull($lavorazione->dataCompletamento);
        $this->assertNull($lavorazione->utenteCompletamento);
        $this->assertSame($lavorazione, $scadenzario->preparaLavorazione($this->studioPredefinito, new VoceScadenzario($scadenza, $lavorazione)));

        // la scadenza completata nel modulo non si lavora dallo scadenzario
        $this->expectException(\LogicException::class);
        $scadenzario->registra(new Scadenza(ModuloSuite::deleghe, 'S1/2026-01-16', new SismaDate('2026-01-16'), 'F24', completata: true), $lavorazione, null, $this->utenteCorrente);
    }

    public function testRiepilogoScrivania(): void
    {
        $iva = $this->creaAdempimento(1, 'IVA_MENSILE');
        $oggi = new \DateTimeImmutable();
        $termini = [
            $this->creaTermine($iva, 1, 'scaduto', (int) $oggi->modify('-40 days')->format('n'), (int) $oggi->modify('-40 days')->format('j')),
            $this->creaTermine($iva, 2, 'prossimo', (int) $oggi->modify('+20 days')->format('n'), (int) $oggi->modify('+20 days')->format('j')),
        ];
        // dovuto dagli ultimi 60 giorni: i termini degli anni precedenti non contano
        $this->impostaCalendario($termini, [], [$this->creaAssegnazione($this->creaSoggetto(), $iva, $oggi->modify('-60 days')->format('Y-m-d'))]);
        $indicatori = (new RiepilogoScrivania($this->dataMapper))->getIndicatori($this->studioPredefinito);
        // un termine scaduto da 40 giorni e uno fra 20 giorni
        $this->assertSame(1, $indicatori['Scadute']);
        $this->assertSame(1, $indicatori['Entro 30 giorni']);
        $this->assertSame(0, $indicatori['Entro 7 giorni']);
        $this->assertSame(['Scadute', 'Entro 7 giorni', 'Entro 30 giorni'], array_keys($indicatori));
    }
}
