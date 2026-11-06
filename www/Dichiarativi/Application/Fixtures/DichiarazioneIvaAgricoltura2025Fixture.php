<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeDetrazioneIva;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Fixtures\SoggettoRossiFixture;
use Anagrafica\Application\Models\CodiceAtecoModel;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Anagrafica\Application\Models\NazioneModel;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\QualificaOperazione;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Services\ElaborazioneLiquidazione;
use Contabilita\Application\Services\Lipe\PreparazioneLipe;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use Deleghe\Application\Fixtures\IntermediarioStudioFixture;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Entities\RettificaDetrazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\HelperClasses\AziendaDemo;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\Iva\PropostaIva;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Application\Fixtures\StudioFixture;

/**
 * Regimi speciali agricoli demo per il 2025: Tenuta Colli Berici S.r.l. con
 * una contabilità separata per regime (art. 34 con cessioni a percentuale di
 * compensazione, conferimento alla cooperativa, esportazione, operazioni
 * diverse e connessa occasionale; agriturismo, attività connesse, enoturismo
 * e oleoturismo con la detrazione del 50%), Cantina Export S.r.l. (IVA teorica
 * delle esportazioni: modello IVA TR con il TC5 e causale 7), Rosa Fiore
 * (esonerata nei limiti: dichiarazione non dovuta) e Gino Orti (esonerato
 * oltre il limite di un terzo: dichiarazione senza VH), Mario Campi
 * (rinuncia all'esonero e opzione per i modi ordinari dal 2025: VO3, caselle
 * 1 e 3). Tenuta Colli Berici revoca dal 2025 l'opzione dell'agriturismo (VO32,
 * casella 2) e ha un acquisto per le connesse occasionali (VF18).
 */
class DichiarazioneIvaAgricoltura2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class)
                ->addDependency(SoggettoRossiFixture::class)
                ->addDependency(IntermediarioStudioFixture::class);
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
        // Tenuta Colli Berici: un'attività e un registro per ogni regime
        $tenuta = $demo->creaSocieta($studio, 'C059', '09990008006', 'Tenuta Colli Berici S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata, '01.21.00', PeriodicitaIva::trimestrale, 'L840', 'Via dei Colli 5', '36100');
        $demo->legame($tenuta, $rossi, RuoloLegame::amministratoreUnico);
        $datiTenuta = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($tenuta, new SismaDate('2024-12-31'));
        $datiTenuta->opzioniOrdinarie = RegimeDetrazioneIva::agriturismo->value;
        $this->dataMapper->save($datiTenuta);
        $demo->variaDatiFiscali($tenuta, '2025-01-01', ['opzioniOrdinarie' => null]);
        $esercizio = $demo->apriContabilita($tenuta, 2025);
        $agricola = $demo->attivita($tenuta, 1, 'Viticoltura', '01.21.00', null, 2025);
        $agricola->regimeDetrazioneIva = RegimeDetrazioneIva::agricolo;
        $this->dataMapper->save($agricola);
        $venditeAgricole = $demo->assegnaRegistro($tenuta, TipoRegistroIva::vendite, '', $agricola);
        $demo->assegnaRegistro($tenuta, TipoRegistroIva::acquisti, '', $agricola);
        $cliente = $demo->controparte($tenuta, 'Enoteca Vicentina S.r.l.', '07666777001', true);
        $estero = $demo->controparte($tenuta, 'Weinhandel Bauer GmbH', null, true);
        $cooperativa = $demo->controparte($tenuta, 'Cantina Sociale dei Berici Soc. Coop.', '07777888002', true);
        $fornitore = $demo->controparte($tenuta, 'Consorzio Agrario di Vicenza', '07888999003', false);
        // uve da un agricoltore esonerato: la Tenuta emette l'autofattura con la percentuale di compensazione
        $esonerato = $demo->controparte($tenuta, 'Fiore Rosa', '09990010002', false);
        $esonerato->codiceFiscale = 'FRORSO70A41L840L';
        $esonerato->indirizzo = 'Via dei Campi 2';
        $esonerato->cap = '36100';
        $esonerato->comune = 'Vicenza';
        $esonerato->provincia = 'VI';
        $esonerato->agricoltoreEsonerato = true;
        $this->dataMapper->save($esonerato);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $esonerato, $acquisto, '2025-09-05', 'AF/1', ['4' => 800.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $cliente, $vendita, '2025-03-10', '1', ['22' => 30000.0], ['percentualeCompensazione' => 12.3]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $cliente, $vendita, '2025-05-14', '2', ['10' => 10000.0], ['percentualeCompensazione' => 8.8]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $cooperativa, $vendita, '2025-10-02', '3', ['C8.8' => 5000.0], ['qualifica' => QualificaOperazione::conferimentoAgricolo]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $estero, $vendita, '2025-06-20', '4', ['N3.1' => 8000.0], ['percentualeCompensazione' => 12.3]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $cliente, $vendita, '2025-07-08', '5', ['22' => 4000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $cliente, $vendita, '2025-09-11', '6', ['22' => 2000.0], ['qualifica' => QualificaOperazione::connessaOccasionale]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $fornitore, $acquisto, '2025-02-18', 'CA/1', ['22' => 10000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $fornitore, $acquisto, '2025-06-05', 'CA/2', ['22' => 1000.0], ['operazioneDiversa' => true]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $fornitore, $acquisto, '2025-09-02', 'CA/3', ['22' => 500.0], ['qualifica' => QualificaOperazione::connessaOccasionale]);
        foreach ([[2, 'Agriturismo', '55.20.51', RegimeDetrazioneIva::agriturismo, 'AT', '10', 12000.0], [3, 'Lavorazioni conto terzi con le attrezzature agricole', '01.61.10', RegimeDetrazioneIva::attivitaConnesse, 'CN', '22', 6000.0],
    [4, 'Enoturismo', '56.11.11', RegimeDetrazioneIva::enoturismo, 'EN', '22', 3000.0], [5, 'Oleoturismo', '56.11.11', RegimeDetrazioneIva::oleoturismo, 'OL', '22', 1000.0]] as [$numero, $descrizione, $ateco, $regime, $sezionale, $codice, $importo]) {
            $attivita = $demo->attivita($tenuta, $numero, $descrizione, $ateco, null, 2025);
            $attivita->regimeDetrazioneIva = $regime;
            $this->dataMapper->save($attivita);
            $registro = $demo->sezionale($tenuta, TipoRegistroIva::vendite, $sezionale, $descrizione, null);
            $registro->attivitaIva = $attivita;
            $this->dataMapper->save($registro);
            RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $cliente, $vendita, '2025-08-20', $sezionale . '/1', [$codice => $importo], ['registro' => $registro]);
        }
        $this->elaboraAnno($elaborazione, $tenuta);
        // comunicazione delle liquidazioni con l'imposta dei regimi speciali (VP4 e VP5)
        (new PreparazioneLipe($this->dataMapper))->prepara($tenuta, 2025, 3);
        $this->dichiara($tenuta);
        // Cantina Export: esportazioni di vino, IVA teorica oltre l'imposta delle vendite
        $cantina = $demo->creaSocieta($studio, 'C060', '09990009004', 'Cantina Export S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata, '01.21.00', PeriodicitaIva::trimestrale, 'L840', 'Via Marosticana 40', '36100');
        $demo->legame($cantina, $rossi, RuoloLegame::amministratoreUnico);
        $datiCantina = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($cantina, new SismaDate('2025-01-01'));
        $datiCantina->regimeDetrazioneIva = RegimeDetrazioneIva::agricolo;
        $this->dataMapper->save($datiCantina);
        $esercizio = $demo->apriContabilita($cantina, 2025);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $demo->controparte($cantina, 'Vinum Import LLC', null, true), $vendita, '2025-02-25', '1', ['N3.1' => 50000.0], ['percentualeCompensazione' => 12.3]);
        $this->elaboraAnno($elaborazione, $cantina);
        $demo->presentaTr($cantina, 2025, 1, ['TD006001' => 2000.0], '2025-04-28');
        $this->dichiara($cantina, 3000);
        // esonerati: nei limiti non presentano la dichiarazione, oltre un terzo sì
        $fiore = $this->creaAgricoltore($demo, $studio, 'C061', 'FRORSO70A41L840L', 'Fiore', 'Rosa', Sesso::femmina, '1970-01-01', '09990010002');
        RegistrazioneDocumentoIva::registra($this->dataMapper, $demo->apriContabilita($fiore, 2025), $demo->controparte($fiore, 'Mercato Ortofrutticolo di Vicenza', '07999000004', true), $vendita, '2025-07-15', '1', ['4' => 6000.0], ['percentualeCompensazione' => 4.0]);
        $orti = $this->creaAgricoltore($demo, $studio, 'C062', 'RTOGNI65B02L840C', 'Orti', 'Gino', Sesso::maschio, '1965-02-02', '09990011000');
        $esercizio = $demo->apriContabilita($orti, 2025);
        $mercato = $demo->controparte($orti, 'Mercato Ortofrutticolo di Vicenza', '07999000004', true);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $mercato, $vendita, '2025-05-15', '1', ['4' => 4000.0], ['percentualeCompensazione' => 4.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $mercato, $vendita, '2025-09-20', '2', ['22' => 3000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $demo->controparte($orti, 'Attrezzature Agricole S.r.l.', '07000111005', false), $acquisto, '2025-04-02', 'AA/1', ['22' => 1000.0], ['operazioneDiversa' => true]);
        $this->dichiara($orti);
        // Mario Campi: già esonerato, dal 2025 rinuncia e applica l'IVA nei modi ordinari
        $campi = $this->creaAgricoltore($demo, $studio, 'C064', 'CMPMRA72C03L840C', 'Campi', 'Mario', Sesso::maschio, '1972-03-03', '09990013006');
        $esercizioBene = $demo->apriContabilita($campi, 2024);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizioBene, $demo->controparte($campi, 'Macchine Agricole Vicentine S.r.l.', '07000222004', false, '02.02.0002'), $acquisto, '2024-05-20', 'MA/1', ['22' => 5000.0]);
        // coefficiente oltre il 25%: resta nella proposta del prospetto D ma fuori dal totale
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizioBene, $demo->controparte($campi, 'Macchine Agricole Vicentine S.r.l.', '07000222004', false, '02.02.0002'), $acquisto, '2024-06-12', 'MA/2', ['22' => 1000.0], ['coefficienteElevato' => true]);
        $demo->variaDatiFiscali($campi, '2025-01-01', ['regimeDetrazioneIva' => null, 'opzioniOrdinarie' => RegimeDetrazioneIva::agricolo->value, 'rinunciaEsonero' => true]);
        $esercizio = $demo->apriEsercizio($campi, 2025);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $demo->controparte($campi, 'Mercato Ortofrutticolo di Vicenza', '07999000004', true), $vendita, '2025-06-10', '1', ['4' => 5000.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $demo->controparte($campi, 'Attrezzature Agricole S.r.l.', '07000111005', false), $acquisto, '2025-03-12', 'AA/1', ['22' => 1000.0]);
        // connessa occasionale nei modi ordinari: VF62 e VF18
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $demo->controparte($campi, 'Azienda Vicina S.S.', '07000333000', true), $vendita, '2025-10-05', '2', ['22' => 1000.0], ['qualifica' => QualificaOperazione::connessaOccasionale]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $demo->controparte($campi, 'Attrezzature Agricole S.r.l.', '07000111005', false), $acquisto, '2025-10-01', 'AA/2', ['22' => 400.0], ['qualifica' => QualificaOperazione::connessaOccasionale]);
        $this->elaboraAnno($elaborazione, $campi);
        // prospetto D per il passaggio dal regime speciale: il bene del 2024 per quattro quinti e le rimanenze
        $dichiarazioneCampi = $this->dichiara($campi);
        $rettifica = new RettificaDetrazione($this->dataMapper);
        $rettifica->dichiarazione = $dichiarazioneCampi;
        $rettifica->rigo = 3;
        $rettifica->importo = 940.0;
        $rettifica->nota = 'Trattore del 2024: 880 (4/5 dell\'imposta); rimanenze di concimi: 60';
        $this->dataMapper->save($rettifica);
        (new PropostaIva($this->dataMapper))->proponi($dichiarazioneCampi);
        // Le Querce: agriturismo nei modi ordinari per opzione su un'attività separata
        $querce = $demo->creaSocieta($studio, 'C065', '09990014004', 'Le Querce Agricola S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata, '01.21.00', PeriodicitaIva::trimestrale, 'L840', 'Via delle Querce 3', '36100');
        $demo->legame($querce, $rossi, RuoloLegame::amministratoreUnico);
        $esercizio = $demo->apriContabilita($querce, 2025);
        $coltivazione = $demo->attivita($querce, 1, 'Viticoltura', '01.21.00', null, 2025);
        $coltivazione->regimeDetrazioneIva = RegimeDetrazioneIva::agricolo;
        $this->dataMapper->save($coltivazione);
        $demo->assegnaRegistro($querce, TipoRegistroIva::vendite, '', $coltivazione);
        $demo->assegnaRegistro($querce, TipoRegistroIva::acquisti, '', $coltivazione);
        $ospitalita = $demo->attivita($querce, 2, 'Agriturismo', '55.20.51', null, 2025);
        $ospitalita->opzioneOrdinaria = RegimeDetrazioneIva::agriturismo;
        $ospitalita->opzioneDal = 2025;
        $this->dataMapper->save($ospitalita);
        $registroOspitalita = $demo->sezionale($querce, TipoRegistroIva::vendite, 'AT', 'Agriturismo', null);
        $registroOspitalita->attivitaIva = $ospitalita;
        $this->dataMapper->save($registroOspitalita);
        $ospiti = $demo->controparte($querce, 'Viaggi Berici S.r.l.', '07000444007', true);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $ospiti, $vendita, '2025-06-12', '1', ['22' => 3000.0], ['percentualeCompensazione' => 12.3]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $ospiti, $vendita, '2025-07-12', 'AT/1', ['10' => 4000.0], ['registro' => $registroOspitalita]);
        $this->elaboraAnno($elaborazione, $querce);
        $this->dichiara($querce);
        $this->addEntity($dichiarazioneCampi);
    }

    private function elaboraAnno(ElaborazioneLiquidazione $elaborazione, Soggetto $soggetto): void
    {
        for ($trimestre = 1; $trimestre <= 4; $trimestre++) {
            $elaborazione->elabora($soggetto, 2025, $trimestre, 0.0, false);
        }
    }

    private function dichiara(Soggetto $soggetto, int $rimborso = 0): Dichiarazione
    {
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $soggetto->studio;
        $dichiarazione->soggetto = $soggetto;
        $dichiarazione->modello = ModelloDichiarativo::iva;
        $dichiarazione->anno = 2025;
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        $this->dataMapper->save($dichiarazione);
        if ($rimborso === 0) {
            (new PropostaIva($this->dataMapper))->proponi($dichiarazione);
            return $dichiarazione;
        }
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $quadri->imposta('VX004001', $rimborso);
        (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri);
        return $dichiarazione;
    }

    private function creaAgricoltore(AziendaDemo $demo, $studio, string $codice, string $codiceFiscale, string $cognome, string $nome, Sesso $sesso, string $nascita, string $partitaIva): Soggetto
    {
        $soggetto = $demo->persona($studio, $codice, $codiceFiscale, $cognome, $nome, $sesso, $nascita, 'L840');
        $soggetto->partitaIva = $partitaIva;
        $soggetto->clienteStudio = true;
        $this->dataMapper->save($soggetto);
        $dati = new DatiFiscali($this->dataMapper);
        $dati->soggetto = $soggetto;
        $dati->validoDal = new SismaDate('2015-01-01');
        $dati->denominazione = $cognome . ' ' . $nome;
        $dati->tipoAttivita = TipoAttivita::impresaIndividuale;
        $dati->partitaIva = $partitaIva;
        $dati->regimeFiscale = RegimeFiscale::ordinario;
        $dati->regimeDetrazioneIva = RegimeDetrazioneIva::agricoloEsonerato;
        $dati->periodicitaIva = PeriodicitaIva::trimestrale;
        $dati->codiceAteco = (new CodiceAtecoModel($this->dataMapper))->getByCodice(ClassificazioneAteco::ateco2025, '01.13.11');
        $this->dataMapper->save($dati);
        $sede = new Indirizzo($this->dataMapper);
        $sede->soggetto = $soggetto;
        $sede->tipoIndirizzo = TipoIndirizzo::residenza;
        $sede->validoDal = new SismaDate('2015-01-01');
        $sede->indirizzo = 'Strada delle Vigne 1';
        $sede->cap = '36100';
        $sede->comune = (new ComuneModel($this->dataMapper))->getByCodice('L840');
        $sede->nazione = (new NazioneModel($this->dataMapper))->getItalia();
        $this->dataMapper->save($sede);
        return $soggetto;
    }
}
