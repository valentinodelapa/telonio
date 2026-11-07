<?php

namespace Dichiarativi\Application\Fixtures;

use Contabilita\Application\Enumerations\CodiceCaricaIva;
use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Fixtures\SoggettoRossiFixture;
use Anagrafica\Application\Models\CodiceAtecoModel;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Anagrafica\Application\Models\NazioneModel;
use Contabilita\Application\Entities\GruppoIva;
use Contabilita\Application\Entities\PartecipanteGruppoIva;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\QualificaOperazione;
use Contabilita\Application\Services\ElaborazioneLiquidazione;
use Contabilita\Application\Services\LiquidazioneGruppo;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use Deleghe\Application\Entities\Credito;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Fixtures\IntermediarioStudioFixture;
use Deleghe\Application\Models\CodiceTributoModel;
use Dichiarativi\Application\Entities\AttoAccertamento;
use Dichiarativi\Application\Entities\GaranziaRimborso;
use Dichiarativi\Application\Enumerations\TipoGaranzia;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Entities\ValoreDichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Enumerations\StatoDichiarazione;
use Dichiarativi\Application\HelperClasses\AziendaDemo;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\Iva\PropostaIva;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Application\Fixtures\StudioFixture;

/**
 * Presupposti del rimborso demo per il 2025, trimestrali: Adria Logistica
 * S.r.l. (prestazioni verso soggetti non stabiliti oltre il 50%: TD5 con la
 * qualifica delle righe IVA e causale 5), Cinema Odeon S.r.l. (proiettore
 * nel primo trimestre: TD3, causale 4 ed erogazione prioritaria 7 dal codice
 * ATECO), Alberto Conti (persona fisica che passa al regime forfetario dal
 * 2026: causale 11), Nordic Trade S.r.l. (non residente con il rappresentante
 * fiscale: TD4 e causale 6; attività iniziata da meno di due anni: rimborso
 * oltre 30.000 euro con la garanzia dovuta nonostante visto e attestazione,
 * anche per un accertamento oltre le soglie in archivio accanto a uno sotto),
 * Beta Partecipata S.r.l. (entra in un Gruppo IVA dal 2026: causale 13 e
 * resto del credito trasferito) e il gruppo Delta dal 2023: Delta Servizi
 * S.r.l. (entrata nel 2024, credito precedente non trasferibile compensato
 * in parte in F24: VL9, VL10 e causale 10), Delta Commerciale S.r.l. a debito
 * e Delta Holding S.r.l. con il credito di gruppo del 2024 (VW25), in parte
 * compensato in F24 (VW24) e in parte con i debiti delle società (VS30), e la
 * minore eccedenza del triennio dai prospetti precedenti (VY4 e quadro VZ).
 */
class DichiarazioneIvaRimborsi2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class)
                ->addDependency(SoggettoRossiFixture::class)
                ->addDependency(IntermediarioStudioFixture::class)
                ->addDependency(ProfessionistaMariniFixture::class)
                ->addDependency(IntermediarioMariniFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $demo = new AziendaDemo($this->dataMapper);
        $studio = $this->getEntityByFixtureName(StudioFixture::class);
        $rossi = $this->getEntityByFixtureName(SoggettoRossiFixture::class);
        $vendita = CausaleContabile::fatturaVendita;
        $acquisto = CausaleContabile::fatturaAcquisto;
        $elaborazione = new ElaborazioneLiquidazione($this->dataMapper);
        // Adria Logistica: trasporti per committenti non stabiliti
        $adria = $demo->creaSocieta($studio, 'C052', '09990001001', 'Adria Logistica S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata, '52.26.02', PeriodicitaIva::trimestrale, 'L736', 'Via della Pila 12', '30175');
        $demo->legame($adria, $rossi, RuoloLegame::amministratoreUnico);
        $esercizio = $demo->apriContabilita($adria, 2025);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $demo->controparte($adria, 'Spedizioni Kraus GmbH', 'DE987654321', true), $vendita, '2025-05-20', '1', ['N2.1' => 60000.0],
                ['qualifica' => QualificaOperazione::prestazioneNonStabiliti]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $demo->controparte($adria, 'Vetrerie Murano S.r.l.', '08666770709', true), $vendita, '2025-06-10', '2', ['22' => 10000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $demo->controparte($adria, 'Carburanti Veneti S.p.A.', '08777880801', false), $acquisto, '2025-04-15', 'CV/1', ['22' => 40000.0]);
        $this->elaboraAnno($elaborazione, $adria);
        $tr = $demo->presentaTr($adria, 2025, 2, ['TD007001' => 3000.0], '2025-07-28');
        $demo->compensaCreditoTr($tr, 3000.0, '2025-08-20');
        $this->dichiara($adria, 3600);
        // Cinema Odeon: proiezione cinematografica e nuovo proiettore
        $odeon = $demo->creaSocieta($studio, 'C053', '09990000201', 'Cinema Odeon S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata, '59.14.00', PeriodicitaIva::trimestrale, 'A944', 'Via Indipendenza 1', '40121');
        $demo->legame($odeon, $rossi, RuoloLegame::amministratoreUnico);
        $esercizio = $demo->apriContabilita($odeon, 2025);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $demo->controparte($odeon, 'Distribuzione Film S.r.l.', '08888990903', true), $vendita, '2025-03-20', '1', ['10' => 5000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $demo->controparte($odeon, 'Proiettori Digitali S.r.l.', '08999000998', false, '02.03.0001'), $acquisto, '2025-02-10', 'PD/1', ['22' => 50000.0]);
        $this->elaboraAnno($elaborazione, $odeon);
        $demo->presentaTr($odeon, 2025, 1, ['TD006001' => 5000.0], '2025-04-28');
        $this->dichiara($odeon, 5000);
        // Alberto Conti: ultimo anno nel regime ordinario
        $conti = $this->creaProfessionista($demo, $studio);
        $esercizio = $demo->apriContabilita($conti, 2025);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $demo->controparte($conti, 'Costruzioni Tiberine S.r.l.', '07111220203', true), $vendita, '2025-06-16', '1', ['22' => 5000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $demo->controparte($conti, 'Strumenti Topografici S.r.l.', '07222330305', false), $acquisto, '2025-03-11', 'ST/1', ['22' => 20000.0]);
        $this->elaboraAnno($elaborazione, $conti);
        $this->dichiara($conti, 1500);
        // Nordic Trade: non residente con il rappresentante fiscale, attività dal settembre 2024
        $nordic = $demo->creaSocieta($studio, 'C055', '09990009996', 'Nordic Trade S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata, '46.64.99', PeriodicitaIva::trimestrale, 'H294', 'Via Pisacane 3', '47921');
        $demo->legame($nordic, $rossi, RuoloLegame::amministratoreUnico);
        $datiNordic = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($nordic, new SismaDate('2025-01-01'));
        $datiNordic->nonResidenteIva = true;
        $datiNordic->dataInizioAttivita = new SismaDate('2024-09-01');
        $this->dataMapper->save($datiNordic);
        $esercizio = $demo->apriContabilita($nordic, 2025);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $demo->controparte($nordic, 'Hotel Riviera S.r.l.', '07333440407', true), $vendita, '2025-05-12', '1', ['22' => 10000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $demo->controparte($nordic, 'Forniture Adriatiche S.r.l.', '07444550508', false), $acquisto, '2025-02-20', 'FA/1', ['22' => 200000.0]);
        $this->elaboraAnno($elaborazione, $nordic);
        $trNordic = $demo->presentaTr($nordic, 2025, 1, ['TD007001' => 3000.0], '2025-04-28', $this->getEntityByFixtureName(ProfessionistaMariniFixture::class), $this->getEntityByFixtureName(IntermediarioMariniFixture::class));
        // non residente: sottoscrive il rappresentante fiscale (codice carica 6)
        $trNordic->codiceCarica = CodiceCaricaIva::rappresentanteFiscale;
        $this->dataMapper->save($trNordic);
        $this->atto($nordic, 2022, '2025-06-10', 'TK3AB0001', 100000.0, 104000.0);
        $this->atto($nordic, 2023, '2025-11-20', 'TK3AB0002', 100000.0, 125000.0);
        $this->garanzia($nordic, 2024, TipoGaranzia::fideiussioneBancaria, 'Banca Adriatica S.p.A.', 12000.0, '2025-02-10', '2025-03-20', '2026-03-02');
        $garanziaDocumenti = $this->garanzia($nordic, 2025, TipoGaranzia::polizzaAssicurativa, 'Assicurazioni del Nord S.p.A.', 35000.0, '2026-06-10', '2026-07-15', null);
        // documenti consegnati 28 giorni dopo la richiesta: decadenza differita di 13 giorni
        $garanziaDocumenti->dataRichiestaDocumenti = new SismaDate('2026-05-04');
        $garanziaDocumenti->dataConsegnaDocumenti = new SismaDate('2026-06-01');
        $this->dataMapper->save($garanziaDocumenti);
        $annuale = $this->dichiara($nordic, 35000, function (Dichiarazione $dichiarazione): void {
            $dichiarazione->attestazionePatrimoniale = true;
            $dichiarazione->verificaAccertamenti = true;
            $dichiarazione->codiceCarica = CodiceCaricaIva::rappresentanteFiscale;
            $dichiarazione->vistoProfessionista = $this->getEntityByFixtureName(ProfessionistaMariniFixture::class);
            $dichiarazione->intermediario = $this->getEntityByFixtureName(IntermediarioMariniFixture::class);
        });
        // Beta Partecipata: dal 2026 nel Gruppo IVA, il credito resta chiesto in parte a rimborso
        $beta = $demo->creaSocieta($studio, 'C056', '09990000508', 'Beta Partecipata S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata, '46.64.99', PeriodicitaIva::trimestrale, 'F205', 'Via Tortona 8', '20144');
        $demo->legame($beta, $rossi, RuoloLegame::amministratoreUnico);
        $demo->variaDatiFiscali($beta, '2026-01-01', ['gruppoIvaArt70bis' => true]);
        $esercizio = $demo->apriContabilita($beta, 2025);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $demo->controparte($beta, 'Forniture Lombarde S.r.l.', '07555660609', false), $acquisto, '2025-03-05', 'FL/1', ['22' => 30000.0]);
        $this->elaboraAnno($elaborazione, $beta);
        $this->dichiara($beta, 2000);
        // gruppo Delta dal 2023, Delta Servizi dal 2024
        $holding = $demo->creaSocieta($studio, 'C057', '09990000607', 'Delta Holding S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata, '70.10.00', PeriodicitaIva::mensile, 'F205', 'Via Solari 2', '20144');
        $servizi = $demo->creaSocieta($studio, 'C058', '09990000706', 'Delta Servizi S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata, '70.10.00', PeriodicitaIva::mensile, 'F205', 'Via Solari 2', '20144');
        $commerciale = $demo->creaSocieta($studio, 'C063', '09990010200', 'Delta Commerciale S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata, '46.64.99', PeriodicitaIva::mensile, 'F205', 'Via Solari 2', '20144');
        $demo->legame($holding, $rossi, RuoloLegame::amministratoreUnico);
        $gruppo = new GruppoIva($this->dataMapper);
        $gruppo->studio = $studio;
        $gruppo->controllante = $holding;
        $gruppo->annoInizio = 2023;
        $this->dataMapper->save($gruppo);
        foreach ([[$servizi, 2024], [$commerciale, 2023]] as [$controllata, $ingresso]) {
            $demo->legame($controllata, $rossi, RuoloLegame::amministratoreUnico);
            $demo->legame($controllata, $holding, RuoloLegame::socio, '2020-01-01', 100.0);
            $partecipante = new PartecipanteGruppoIva($this->dataMapper);
            $partecipante->gruppoIva = $gruppo;
            $partecipante->soggetto = $controllata;
            $partecipante->annoIngresso = $ingresso;
            $partecipante->dataPossesso = new SismaDate('2020-01-01');
            $partecipante->percentualePossesso = 100.0;
            $this->dataMapper->save($partecipante);
        }
        $demo->apriContabilita($holding, 2025);
        $demo->apriContabilita($servizi, 2025);
        $esercizio = $demo->apriContabilita($commerciale, 2025);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $demo->controparte($commerciale, 'Grossisti Lombardi S.r.l.', '07666770701', true), $vendita, '2025-05-22', '1', ['22' => 15000.0]);
        foreach ([$holding, $servizi, $commerciale] as $societa) {
            for ($mese = 1; $mese <= 12; $mese++) {
                $elaborazione->elabora($societa, 2025, $mese, 0.0, false);
            }
        }
        $liquidazioneGruppo = new LiquidazioneGruppo($this->dataMapper);
        for ($mese = 1; $mese <= 12; $mese++) {
            $liquidazioneGruppo->elabora($gruppo, 2025, $mese);
        }
        // Delta Servizi: credito precedente all'ingresso, non trasferibile al gruppo
        $this->dichiarazionePrecedente($servizi, 2023, ['VX005001' => 5000]);
        $this->dichiarazionePrecedente($servizi, 2024, ['VX005001' => 4000]);
        $demo->compensaCredito($this->credito($servizi, 4000.0, 'Credito IVA 2024 non trasferibile al gruppo'), 1000.0, '2025-03-17');
        $this->dichiara($servizi, 2500);
        $this->dichiara($commerciale, 0);
        // Delta Holding: credito di gruppo riportato dai prospetti del 2023 e del 2024, chiesto a rimborso per la minore eccedenza del triennio
        $garanziaGruppo = $this->garanzia($holding, 2024, TipoGaranzia::fideiussioneBancaria, 'Banca Adriatica S.p.A.', 8000.0, '2025-04-30', '2025-04-30', null);
        $garanziaGruppo->compensazioneGruppo = true;
        $this->dataMapper->save($garanziaGruppo);
        $this->dichiarazionePrecedente($holding, 2023, ['VY005001' => 6000]);
        $this->dichiarazionePrecedente($holding, 2024, ['VY005001' => 8000]);
        $demo->compensaCredito($this->credito($holding, 8000.0, 'Credito IVA di gruppo 2024'), 2000.0, '2025-02-17');
        $this->dichiara($holding, 0, null, ['VY004001' => 2000]);
        $this->addEntity($annuale);
    }

    private function garanzia(Soggetto $soggetto, int $anno, TipoGaranzia $tipo, string $garante, float $importo, string $rilascio, string $rimborso, ?string $svincolo): GaranziaRimborso
    {
        $garanzia = new GaranziaRimborso($this->dataMapper);
        $garanzia->studio = $soggetto->studio;
        $garanzia->soggetto = $soggetto;
        $garanzia->annoImposta = $anno;
        $garanzia->tipo = $tipo;
        $garanzia->garante = $garante;
        $garanzia->estremi = 'GR/' . $anno;
        $garanzia->importo = $importo;
        $garanzia->dataRilascio = new SismaDate($rilascio);
        $garanzia->dataRimborso = new SismaDate($rimborso);
        $garanzia->dataScadenza = $garanzia->getScadenzaProposta();
        $garanzia->dataSvincolo = $svincolo === null ? null : new SismaDate($svincolo);
        $this->dataMapper->save($garanzia);
        return $garanzia;
    }

    private function atto(Soggetto $soggetto, int $anno, string $notifica, string $estremi, float $dichiarato, float $accertato): void
    {
        $atto = new AttoAccertamento($this->dataMapper);
        $atto->studio = $soggetto->studio;
        $atto->soggetto = $soggetto;
        $atto->annoImposta = $anno;
        $atto->dataNotifica = new SismaDate($notifica);
        $atto->estremi = $estremi;
        $atto->importoDichiarato = $dichiarato;
        $atto->importoAccertato = $accertato;
        $this->dataMapper->save($atto);
    }

    private function credito(Soggetto $soggetto, float $importo, string $descrizione): Credito
    {
        $credito = new Credito($this->dataMapper);
        $credito->studio = $soggetto->studio;
        $credito->soggetto = $soggetto;
        $credito->sezione = SezioneF24::erario;
        $credito->codiceTributo = (new CodiceTributoModel($this->dataMapper))->getByCodice(SezioneCodiceTributo::erario, '6099');
        $credito->annoRiferimento = 2024;
        $credito->importo = $importo;
        $credito->utilizzabileDal = new SismaDate('2025-01-01');
        $credito->descrizione = $descrizione;
        $this->dataMapper->save($credito);
        return $credito;
    }

    /**
     * Dichiarazione presentata di un anno senza catalogo nella suite, con
     * il solo credito riportato (VX5, o VY5 del prospetto).
     *
     * @param array<string, int> $valori
     */
    private function dichiarazionePrecedente(Soggetto $soggetto, int $anno, array $valori): void
    {
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $soggetto->studio;
        $dichiarazione->soggetto = $soggetto;
        $dichiarazione->modello = ModelloDichiarativo::iva;
        $dichiarazione->anno = $anno;
        $dichiarazione->stato = StatoDichiarazione::presentata;
        $dichiarazione->dataPresentazione = new SismaDate(($anno + 1) . '-04-20');
        $this->dataMapper->save($dichiarazione);
        foreach ($valori as $codice => $importo) {
            $valore = new ValoreDichiarazione($this->dataMapper);
            $valore->dichiarazione = $dichiarazione;
            $valore->codice = $codice;
            $valore->valore = (string) $importo;
            $this->dataMapper->save($valore);
        }
    }

    private function elaboraAnno(ElaborazioneLiquidazione $elaborazione, Soggetto $soggetto): void
    {
        for ($trimestre = 1; $trimestre <= 4; $trimestre++) {
            $elaborazione->elabora($soggetto, 2025, $trimestre, 0.0, false);
        }
    }

    /**
     * @param array<string, int> $manuali altri valori inseriti a mano
     */
    private function dichiara(Soggetto $soggetto, int $rimborso, ?callable $frontespizio = null, array $manuali = []): Dichiarazione
    {
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $soggetto->studio;
        $dichiarazione->soggetto = $soggetto;
        $dichiarazione->modello = ModelloDichiarativo::iva;
        $dichiarazione->anno = 2025;
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        if ($frontespizio !== null) {
            $frontespizio($dichiarazione);
        }
        $this->dataMapper->save($dichiarazione);
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        foreach ($rimborso > 0 ? ['VX004001' => $rimborso] + $manuali : $manuali as $codice => $valore) {
            $quadri->imposta($codice, $valore);
        }
        (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri);
        return $dichiarazione;
    }

    private function creaProfessionista(AziendaDemo $demo, $studio): Soggetto
    {
        $conti = $demo->persona($studio, 'C054', 'CNTLRT80A01H501G', 'Conti', 'Alberto', Sesso::maschio, '1980-01-01', 'H501');
        $conti->partitaIva = '09990000300';
        $conti->clienteStudio = true;
        $this->dataMapper->save($conti);
        $ateco = (new CodiceAtecoModel($this->dataMapper))->getByCodice(ClassificazioneAteco::ateco2025, '71.12.10');
        foreach ([['2018-03-01', '2025-12-31', RegimeFiscale::ordinario], ['2026-01-01', null, RegimeFiscale::forfetario]] as [$dal, $al, $regime]) {
            $dati = new DatiFiscali($this->dataMapper);
            $dati->soggetto = $conti;
            $dati->validoDal = new SismaDate($dal);
            $dati->validoAl = $al === null ? null : new SismaDate($al);
            $dati->denominazione = 'Conti Alberto';
            $dati->partitaIva = $conti->partitaIva;
            $dati->regimeFiscale = $regime;
            $dati->periodicitaIva = PeriodicitaIva::trimestrale;
            $dati->codiceAteco = $ateco;
            $this->dataMapper->save($dati);
        }
        $sede = new Indirizzo($this->dataMapper);
        $sede->soggetto = $conti;
        $sede->tipoIndirizzo = TipoIndirizzo::sedeLegale;
        $sede->validoDal = new SismaDate('2018-03-01');
        $sede->indirizzo = 'Via Tiburtina 100';
        $sede->cap = '00185';
        $sede->comune = (new ComuneModel($this->dataMapper))->getByCodice('H501');
        $sede->nazione = (new NazioneModel($this->dataMapper))->getItalia();
        $this->dataMapper->save($sede);
        return $conti;
    }
}
