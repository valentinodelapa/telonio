<?php

namespace GestioneStudio\Tests;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Telonio\Tests\SuiteTestCase;
use GestioneStudio\Application\Entities\Adempimento;
use GestioneStudio\Application\Entities\AssegnazioneAdempimento;
use GestioneStudio\Application\Entities\ProrogaAdempimento;
use GestioneStudio\Application\Entities\TermineAdempimento;
use GestioneStudio\Application\Enumerations\AmbitoAdempimento;
use GestioneStudio\Application\Enumerations\CategoriaAdempimento;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Entità di prova del modulo.
 */
abstract class GestioneStudioTestCase extends SuiteTestCase
{

    protected function creaAdempimento(int $id = 10, string $codice = 'REDDITI_PF', bool $delloStudio = false, bool $prorogaAgosto = true, bool $slittamento = true): Adempimento
    {
        $adempimento = new Adempimento($this->dataMapper);
        $adempimento->id = $id;
        $adempimento->studio = $delloStudio ? $this->studioPredefinito : null;
        $adempimento->codice = $codice;
        $adempimento->denominazione = 'Adempimento ' . $codice;
        $adempimento->categoria = CategoriaAdempimento::dichiarazione;
        $adempimento->ambito = AmbitoAdempimento::personeFisiche;
        $adempimento->fonte = 'art. 2, c. 2, D.P.R. 322/1998';
        $adempimento->prorogaAgosto = $prorogaAgosto;
        $adempimento->slittamentoFestivi = $slittamento;
        return $adempimento;
    }

    protected function creaTermine(Adempimento $adempimento, int $ordine, string $periodo, int $mese, int $giorno, int $anniSuccessivi = 0): TermineAdempimento
    {
        $termine = new TermineAdempimento($this->dataMapper);
        $termine->id = $adempimento->id * 100 + $ordine;
        $termine->adempimento = $adempimento;
        $termine->ordine = $ordine;
        $termine->periodo = $periodo;
        $termine->mese = $mese;
        $termine->giorno = $giorno;
        $termine->anniSuccessivi = $anniSuccessivi;
        return $termine;
    }

    protected function creaProroga(TermineAdempimento $termine, int $anno, string $data, bool $delloStudio = false): ProrogaAdempimento
    {
        $proroga = new ProrogaAdempimento($this->dataMapper);
        $proroga->id = $termine->id + $anno;
        $proroga->studio = $delloStudio ? $this->studioPredefinito : null;
        $proroga->termineAdempimento = $termine;
        $proroga->anno = $anno;
        $proroga->data = new SismaDate($data);
        $proroga->provvedimento = $delloStudio ? 'Proroga dello studio' : 'D.L. di prova';
        return $proroga;
    }

    protected function creaSoggetto(int $id = 50, TipoSoggetto $tipo = TipoSoggetto::personaFisica, ?string $partitaIva = null): Soggetto
    {
        $soggetto = $this->creaSoggettoFisico($id, $this->studioPredefinito, 'RSSMRA80A01H501U', 'ROSSI', 'MARIO');
        $soggetto->tipoSoggetto = $tipo;
        $soggetto->partitaIva = $partitaIva;
        return $soggetto;
    }

    protected function creaAssegnazione(Soggetto $soggetto, Adempimento $adempimento, string $dal, ?string $al = null, int $id = 70): AssegnazioneAdempimento
    {
        $assegnazione = new AssegnazioneAdempimento($this->dataMapper);
        $assegnazione->id = $id;
        $assegnazione->studio = $this->studioPredefinito;
        $assegnazione->soggetto = $soggetto;
        $assegnazione->adempimento = $adempimento;
        $assegnazione->responsabile = $this->utenteCorrente;
        $assegnazione->validoDal = new SismaDate($dal);
        $assegnazione->validoAl = $al === null ? null : new SismaDate($al);
        return $assegnazione;
    }
}
