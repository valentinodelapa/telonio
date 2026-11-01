<?php

namespace Contabilita\Tests;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Contabilita\Application\Entities\CodiceIva;
use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Entities\EsercizioContabile;
use Contabilita\Application\Entities\PianoConti;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RegistroIva;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Enumerations\LivelloConto;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Enumerations\NaturaIva;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Tests\SuiteTestCase;
use Telonio\Tests\TestResultSet;

/**
 * Entità di prova del modulo: azienda con piano, conti con funzione,
 * codici IVA, registri ed esercizio 2026.
 */
abstract class ContabilitaTestCase extends SuiteTestCase
{

    protected Soggetto $azienda;
    protected PianoConti $piano;
    protected EsercizioContabile $esercizio;

    /** @var array<string, Conto> per funzione o codice */
    protected array $conti = [];

    /** @var array<string, CodiceIva> per codice */
    protected array $codiciIva = [];

    /** @var array<string, RegistroIva> per tipo */
    protected array $registri = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->azienda = $this->creaSoggettoFisico(1, $this->studioPredefinito, '01234567897', 'Alfa', '');
        $this->azienda->tipoSoggetto = TipoSoggetto::societaCapitali;
        $this->azienda->nominativo = 'Alfa Consulting S.r.l.';
        $this->azienda->partitaIva = '01234567897';
        $this->piano = new PianoConti($this->dataMapper);
        $this->piano->id = 1000;
        $this->piano->studio = $this->studioPredefinito;
        $this->piano->soggetto = $this->azienda;
        $this->piano->denominazione = 'Piano di Alfa';
        $id = 100000;
        foreach ([['06.01', 'Crediti verso clienti', NaturaConto::attivita, FunzioneConto::clienti, LivelloConto::conto],
            ['13.01', 'Debiti verso fornitori', NaturaConto::passivita, FunzioneConto::fornitori, LivelloConto::conto],
            ['06.03.0001', 'Erario c/IVA', NaturaConto::attivita, FunzioneConto::erarioIva, LivelloConto::sottoconto],
            ['06.03.0002', 'IVA su acquisti', NaturaConto::attivita, FunzioneConto::ivaAcquisti, LivelloConto::sottoconto],
            ['06.03.0003', 'Erario c/acconto IVA', NaturaConto::attivita, FunzioneConto::accontoIva, LivelloConto::sottoconto],
            ['13.04.0001', 'IVA su vendite', NaturaConto::passivita, FunzioneConto::ivaVendite, LivelloConto::sottoconto],
            ['07.01.0001', 'Banca c/c', NaturaConto::attivita, FunzioneConto::banca, LivelloConto::sottoconto],
            ['07.02.0001', 'Cassa', NaturaConto::attivita, FunzioneConto::cassa, LivelloConto::sottoconto],
            ['20.01.0001', 'Ricavi', NaturaConto::ricavo, FunzioneConto::ricavi, LivelloConto::sottoconto],
            ['21.01.0001', 'Acquisti', NaturaConto::costo, FunzioneConto::costi, LivelloConto::sottoconto],
            ['22.01.0001', 'Consulenze', NaturaConto::costo, null, LivelloConto::sottoconto],
            ['06.01.0001', 'Cliente Beta', NaturaConto::attivita, null, LivelloConto::sottoconto],
            ['13.01.0001', 'Fornitore Gamma', NaturaConto::passivita, null, LivelloConto::sottoconto]] as [$codice, $descrizione, $natura, $funzione, $livello]) {
            $conto = new Conto($this->dataMapper);
            $conto->id = $id++;
            $conto->pianoConti = $this->piano;
            $conto->codice = $codice;
            $conto->descrizione = $descrizione;
            $conto->natura = $natura;
            $conto->funzione = $funzione;
            $conto->livello = $livello;
            $this->conti[$funzione?->value ?? $codice] = $conto;
        }
        foreach ([[1, '22', 22.0, null, 100.0, false], [2, '10', 10.0, null, 100.0, false], [5, 'A22', 22.0, null, 40.0, false],
            [29, 'N4', 0.0, NaturaIva::esenti, 100.0, false], [60, 'RC22', 22.0, null, 100.0, true]] as [$codiceId, $codice, $aliquota, $natura, $detraibilita, $inversione]) {
            $codiceIva = new CodiceIva($this->dataMapper);
            $codiceIva->id = $codiceId;
            $codiceIva->codice = $codice;
            $codiceIva->descrizione = 'Codice ' . $codice;
            $codiceIva->aliquota = $aliquota;
            $codiceIva->natura = $natura;
            $codiceIva->detraibilita = $detraibilita;
            $codiceIva->inversioneContabile = $inversione;
            $this->codiciIva[$codice] = $codiceIva;
        }
        foreach (TipoRegistroIva::cases() as $indice => $tipo) {
            $registro = new RegistroIva($this->dataMapper);
            $registro->id = $indice + 1;
            $registro->studio = $this->studioPredefinito;
            $registro->soggetto = $this->azienda;
            $registro->tipo = $tipo;
            $registro->descrizione = $tipo->getLabel();
            $this->registri[$tipo->value] = $registro;
        }
        $this->esercizio = new EsercizioContabile($this->dataMapper);
        $this->esercizio->id = 1;
        $this->esercizio->studio = $this->studioPredefinito;
        $this->esercizio->soggetto = $this->azienda;
        $this->esercizio->anno = 2026;
        $this->esercizio->dataInizio = new SismaDate('2026-01-01');
        $this->esercizio->dataFine = new SismaDate('2026-12-31');
    }

    /**
     * Risposte del database fittizio: i conti del piano filtrati per
     * funzione, le altre tabelle vuote (o da $tabelle).
     */
    protected function rispondiConti(array $tabelle = []): void
    {
        $this->testAdapter->impostaSelectCallback(function (string $cmd, array $bind) use ($tabelle): TestResultSet {
            if (str_contains($cmd, 'COUNT(')) {
                return TestResultSet::conteggio(0);
            }
            if (str_contains($cmd, 'FROM `conto`') && str_contains($cmd, 'funzione')) {
                foreach ($bind as $valore) {
                    $funzione = $valore instanceof FunzioneConto ? $valore : FunzioneConto::tryFrom((string) (is_scalar($valore) ? $valore : ''));
                    if (($funzione !== null) && isset($this->conti[$funzione->value])) {
                        return new TestResultSet([$this->conti[$funzione->value]]);
                    }
                }
                return new TestResultSet([]);
            }
            if (str_contains($cmd, 'FROM `codice_iva`') && str_contains($cmd, '`codice`')) {
                return new TestResultSet(array_values(array_filter($this->codiciIva, fn($codice) => in_array($codice->codice, $bind, true))));
            }
            if (preg_match('/FROM `([a-z_]+)`/', $cmd, $tabella) === 1) {
                return new TestResultSet($tabelle[$tabella[1]] ?? []);
            }
            return new TestResultSet([]);
        });
    }

    protected function creaControparte(int $id, string $denominazione, ?Conto $contoCliente, ?Conto $contoFornitore): Controparte
    {
        $controparte = new Controparte($this->dataMapper);
        $controparte->id = $id;
        $controparte->studio = $this->studioPredefinito;
        $controparte->soggetto = $this->azienda;
        $controparte->denominazione = $denominazione;
        $controparte->cliente = $contoCliente !== null;
        $controparte->fornitore = $contoFornitore !== null;
        $controparte->contoCliente = $contoCliente;
        $controparte->contoFornitore = $contoFornitore;
        return $controparte;
    }

    protected function creaRegistrazione(CausaleContabile $causale, ?Controparte $controparte = null, string $data = '2026-02-10'): RegistrazioneContabile
    {
        $registrazione = new RegistrazioneContabile($this->dataMapper);
        $registrazione->studio = $this->studioPredefinito;
        $registrazione->soggetto = $this->azienda;
        $registrazione->esercizioContabile = $this->esercizio;
        $registrazione->causale = $causale;
        $registrazione->dataRegistrazione = new SismaDate($data);
        $registrazione->controparte = $controparte;
        if ($causale->isIva()) {
            $registrazione->registroIva = $this->registri[$causale->getTipoRegistroIva()->value];
            $registrazione->dataCompetenzaIva = new SismaDate($data);
            $registrazione->dataDocumento = new SismaDate($data);
            $registrazione->numeroDocumento = '1';
        }
        return $registrazione;
    }

    /**
     * Riga IVA con il segno della causale.
     */
    protected function creaRigaIva(string $codice, float $imponibile, int $segno = 1, ?float $imposta = null, ?RegistrazioneContabile $registrazione = null): RigaIva
    {
        $riga = new RigaIva($this->dataMapper);
        $riga->codiceIva = $this->codiciIva[$codice];
        $riga->imponibile = $imponibile * $segno;
        $riga->imposta = ($imposta ?? $this->codiciIva[$codice]->calcolaImposta($imponibile)) * $segno;
        $riga->detraibilita = $this->codiciIva[$codice]->detraibilita;
        if ($registrazione !== null) {
            $riga->registrazioneContabile = $registrazione;
            $riga->registroIva = $registrazione->registroIva;
            $riga->soggetto = $this->azienda;
            $riga->dataCompetenzaIva = $registrazione->dataCompetenzaIva;
        }
        return $riga;
    }
}
