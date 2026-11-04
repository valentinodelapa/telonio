<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Fixtures\SoggettoNeriFixture;
use Anagrafica\Application\Fixtures\SoggettoRossiFixture;
use Contabilita\Application\Entities\EsercizioContabile;
use Contabilita\Application\Entities\GruppoIva;
use Contabilita\Application\Entities\PartecipanteGruppoIva;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\MetodoAccontoIva;
use Contabilita\Application\Enumerations\StatoComunicazioneLipe;
use Contabilita\Application\Services\ElaborazioneAccontoIva;
use Contabilita\Application\Services\ElaborazioneLiquidazione;
use Contabilita\Application\Services\Lipe\PreparazioneLipe;
use Contabilita\Application\Services\LiquidazioneGruppo;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use Deleghe\Application\Fixtures\IntermediarioStudioFixture;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Fixtures\ProfessionistaMariniFixture;
use Dichiarativi\Application\HelperClasses\AziendaDemo;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\Iva\PropostaIva;
use Dichiarativi\Application\Services\Iva\VersamentiIva;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Application\Fixtures\StudioFixture;

/**
 * Gruppo IVA demo (art. 73, c. 3) dal 2025: Verde Holding S.p.A.
 * (controllante, mensile, a credito per i costi di struttura), Verde
 * Distribuzione S.r.l. (mensile, a debito) e Verde Servizi S.r.l.
 * (trimestrale, saldi trasferiti nel mese di fine trimestre, il terzo in
 * una liquidazione di gruppo separata), possedute
 * al 100% dal 2020; Verde Logistica, Verde Energia, Verde Immobiliare e
 * Verde Trasporti entrano nel gruppo dal 2026 (quadro VG su due quadri). Liquidazioni delle società trasferite al gruppo,
 * liquidazioni e acconto del gruppo con i versamenti della controllante,
 * comunicazioni del gruppo dei primi tre trimestri inviate, dichiarazioni
 * IVA delle controllate (quadro VK, VX7/VX8) e della controllante con il
 * prospetto IVA 26 PR. Modello IVA TR di gruppo del primo trimestre (Verde
 * Servizi con i macchinari, TE51 con il visto), compensato in F24 (VW22);
 * credito annuale del gruppo con la quota di rimborso di Verde Servizi
 * (VS campi 9-10, VK25, VY4).
 */
class DichiarazioneIvaGruppoVerde2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class)
                ->addDependency(SoggettoRossiFixture::class)
                ->addDependency(SoggettoNeriFixture::class)
                ->addDependency(IntermediarioStudioFixture::class)
                ->addDependency(ProfessionistaMariniFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $demo = new AziendaDemo($this->dataMapper);
        $studio = $this->getEntityByFixtureName(StudioFixture::class);
        $holding = $demo->creaSocieta($studio, 'C045', '09222333008', 'Verde Holding S.p.A.', NaturaGiuridica::societaPerAzioni, '70.10.00', PeriodicitaIva::mensile, 'F205', 'Corso Venezia 10', '20121');
        $distribuzione = $demo->creaSocieta($studio, 'C046', '09333444009', 'Verde Distribuzione S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata, '46.64.99', PeriodicitaIva::mensile, 'F205', 'Via Mecenate 76', '20138');
        $servizi = $demo->creaSocieta($studio, 'C047', '09444555008', 'Verde Servizi S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata, '81.21.00', PeriodicitaIva::trimestrale, 'F205', 'Via Ripamonti 88', '20141');
        $logistica = $demo->creaSocieta($studio, 'C048', '09555666008', 'Verde Logistica S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata, '52.26.02', PeriodicitaIva::mensile, 'F205', 'Via Toffetti 12', '20139');
        $demo->legame($holding, $this->getEntityByFixtureName(SoggettoRossiFixture::class), RuoloLegame::amministratoreUnico);
        foreach ([$distribuzione, $servizi, $logistica] as $controllata) {
            $demo->legame($controllata, $this->getEntityByFixtureName(SoggettoNeriFixture::class), RuoloLegame::amministratoreUnico);
            $demo->legame($controllata, $holding, RuoloLegame::socio, '2020-01-01', 100.0);
        }
        $gruppo = new GruppoIva($this->dataMapper);
        $gruppo->studio = $studio;
        $gruppo->controllante = $holding;
        $gruppo->annoInizio = 2025;
        $gruppo->note = 'Opzione comunicata con la dichiarazione IVA 2025';
        $this->dataMapper->save($gruppo);
        // altre controllate che entrano dal 2026: con Verde Logistica sono quattro, il quadro VG prosegue nel quadro n. 2
        $nuove = [];
        foreach ([['C049', '09666777009', 'Verde Energia S.r.l.'], ['C050', '09777888000', 'Verde Immobiliare S.r.l.'], ['C051', '09888999001', 'Verde Trasporti S.r.l.']] as [$codice, $partitaIva, $denominazione]) {
            $nuova = $demo->creaSocieta($studio, $codice, $partitaIva, $denominazione, NaturaGiuridica::societaResponsabilitaLimitata, '70.10.00', PeriodicitaIva::mensile, 'F205', 'Corso Venezia 10', '20121');
            $demo->legame($nuova, $holding, RuoloLegame::socio, '2020-01-01', 100.0);
            $nuove[] = [$nuova, 2026];
        }
        foreach (array_merge([[$distribuzione, 2025], [$servizi, 2025], [$logistica, 2026]], $nuove) as [$controllata, $ingresso]) {
            $partecipante = new PartecipanteGruppoIva($this->dataMapper);
            $partecipante->gruppoIva = $gruppo;
            $partecipante->soggetto = $controllata;
            $partecipante->annoIngresso = $ingresso;
            $partecipante->dataPossesso = new SismaDate('2020-01-01');
            $partecipante->percentualePossesso = 100.0;
            $this->dataMapper->save($partecipante);
        }
        $vendita = CausaleContabile::fatturaVendita;
        $acquisto = CausaleContabile::fatturaAcquisto;
        // holding: servizi alle controllate e consulenze, a credito
        $esercizioHolding = $demo->apriContabilita($holding, 2025);
        $consulente = $demo->controparte($holding, 'Studio Legale Associato Brera', '08111222000', false);
        foreach (['03', '06', '09', '12'] as $indice => $mese) {
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizioHolding, $demo->controparte($holding, 'Verde Distribuzione S.r.l. ' . ($indice + 1), '09333444009', true), $vendita,
                    '2025-' . $mese . '-20', (string) ($indice + 1), ['22' => 5000.0]);
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizioHolding, $consulente, $acquisto, '2025-' . $mese . '-10', 'SL/' . ($indice + 1), ['22' => 8000.0]);
        }
        // distribuzione: vendite a debito ogni mese
        $esercizioDistribuzione = $demo->apriContabilita($distribuzione, 2025);
        $clienti = $demo->controparte($distribuzione, 'Rivenditori Associati S.r.l.', '08222333001', true);
        $fornitore = $demo->controparte($distribuzione, 'Costruttori Macchine S.p.A.', '08333444002', false);
        for ($mese = 1; $mese <= 12; $mese++) {
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizioDistribuzione, $clienti, $vendita, sprintf('2025-%02d-25', $mese), (string) $mese, ['22' => 20000.0]);
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizioDistribuzione, $fornitore, $acquisto, sprintf('2025-%02d-12', $mese), 'CM/' . $mese, ['22' => 14000.0]);
        }
        // servizi (trimestrale): credito nel primo trimestre per l'acquisto dei macchinari
        $esercizioServizi = $demo->apriContabilita($servizi, 2025);
        $condomini = $demo->controparte($servizi, 'Gestioni Condominiali Milano S.r.l.', '08444555003', true);
        $attrezzature = $demo->controparte($servizi, 'Attrezzature Pulizia S.r.l.', '08555666004', false, '02.03.0001');
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizioServizi, $attrezzature, $acquisto, '2025-02-14', 'AP/1', ['22' => 60000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizioServizi, $attrezzature, $acquisto, '2025-12-05', 'AP/2', ['22' => 150000.0]);
        foreach (['03', '06', '09', '12'] as $indice => $mese) {
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizioServizi, $condomini, $vendita, '2025-' . $mese . '-15', (string) ($indice + 1), ['22' => 15000.0]);
        }
        // liquidazioni delle società (trasferite al gruppo) e acconto del gruppo
        $elaborazione = new ElaborazioneLiquidazione($this->dataMapper);
        for ($mese = 1; $mese <= 12; $mese++) {
            $elaborazione->elabora($holding, 2025, $mese, 0.0, false);
            $elaborazione->elabora($distribuzione, 2025, $mese, 0.0, false);
        }
        for ($trimestre = 1; $trimestre <= 4; $trimestre++) {
            $elaborazione->elabora($servizi, 2025, $trimestre, 0.0, false);
        }
        (new ElaborazioneAccontoIva($this->dataMapper))->elabora($holding, 2025, MetodoAccontoIva::previsionale, 3000.0, 'Dovuto presunto del gruppo per dicembre', $gruppo);
        $liquidazioneGruppo = new LiquidazioneGruppo($this->dataMapper);
        for ($mese = 1; $mese <= 12; $mese++) {
            // settembre senza anticipare il terzo trimestre di Verde Servizi, liquidato a parte dal gruppo
            $liquidazioneGruppo->elabora($gruppo, 2025, $mese, 0.0, $mese !== 9);
            if ($mese === 9) {
                $liquidazioneGruppo->elaboraTrimestre($gruppo, 2025, 3);
            }
            // credito del primo trimestre del gruppo con il modello IVA TR: non si riporta ad aprile
            if ($mese === 3) {
                $tr = $demo->presentaTr($holding, 2025, 1, ['TE051001' => 6600.0], '2025-04-28', $this->getEntityByFixtureName(ProfessionistaMariniFixture::class));
                $demo->compensaCreditoTr($tr, 6600.0, '2025-05-16');
            }
        }
        $lipe = new PreparazioneLipe($this->dataMapper);
        for ($trimestre = 1; $trimestre <= 3; $trimestre++) {
            $comunicazione = $lipe->preparaGruppo($gruppo, 2025, $trimestre);
            $comunicazione->stato = StatoComunicazioneLipe::inviata;
            $comunicazione->dataInvio = new SismaDate(sprintf('2025-%02d-28', $trimestre * 3 + 2));
            $this->dataMapper->save($comunicazione);
        }
        // dichiarazioni: prima le controllate, poi la controllante con il prospetto IVA 26 PR
        $dichiarazioni = [];
        foreach ([$distribuzione, $servizi, $holding] as $societa) {
            $dichiarazioni[$societa->id] = $dichiarazione = $this->dichiara($societa);
        }
        // quota di rimborso di Verde Servizi (esonerata dalla garanzia, concordato biennale) nel prospetto della controllante, che torna nel suo VK25
        $dichiarazioni[$servizi->id]->concordatoBiennale = true;
        $this->dataMapper->save($dichiarazioni[$servizi->id]);
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        for ($riga = 1; $riga <= 12; $riga++) {
            if ($quadri->get(sprintf('VS%03d001', $riga), 1) === $servizi->partitaIva) {
                $quadri->imposta(sprintf('VS%03d010', $riga), 10000, 1);
            }
        }
        (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri);
        (new PropostaIva($this->dataMapper))->proponi($dichiarazioni[$servizi->id]);
        $this->addEntity($dichiarazione);
    }

    private function dichiara(Soggetto $societa): Dichiarazione
    {
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $societa->studio;
        $dichiarazione->soggetto = $societa;
        $dichiarazione->modello = ModelloDichiarativo::iva;
        $dichiarazione->anno = 2025;
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        $this->dataMapper->save($dichiarazione);
        (new PropostaIva($this->dataMapper))->proponi($dichiarazione);
        (new VersamentiIva($this->dataMapper))->registraSaldo($dichiarazione, new QuadriDichiarazione($dichiarazione, $this->dataMapper));
        return $dichiarazione;
    }
}
