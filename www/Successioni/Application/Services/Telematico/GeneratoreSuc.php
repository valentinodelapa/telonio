<?php

namespace Successioni\Application\Services\Telematico;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Entities\DevoluzioneSuccessione;
use Successioni\Application\Entities\PassivitaSuccessione;
use Successioni\Application\Entities\UfficioRegistrazione;
use Successioni\Application\Enumerations\Agevolazione;
use Successioni\Application\Enumerations\CaricaDichiarante;
use Successioni\Application\Enumerations\QuadroBene;
use Successioni\Application\Enumerations\StatoCivileDefunto;
use Successioni\Application\Enumerations\TipoAllegato;
use Successioni\Application\Enumerations\TipoBeneficiario;
use Successioni\Application\Enumerations\TipoDichiarazioneSuccessione;
use Successioni\Application\Enumerations\TipoUnita;
use Successioni\Application\Models\ComuneCatastaleTavolareModel;
use Successioni\Application\Services\DatiDichiarazione;
use Successioni\Application\Services\Liquidazione;
use Successioni\Application\Services\RisultatoLiquidazione;
use Successioni\Application\Services\TabelleRiferimento\SpecificheSuc;

/**
 * File telematico della dichiarazione di successione: fornitura XML
 * "SUC13" secondo le specifiche tecniche dell'Agenzia delle Entrate
 * (aggiornamento del 2 luglio 2025, schema fornituraSUC13_v1.xsd), una
 * dichiarazione per file, con gli allegati in base64.
 *
 * L'ordine degli elementi segue lo schema; righi e moduli dei quadri sono
 * numerati come nel modello: tre beneficiari per modulo del quadro EA, due
 * terreni o fabbricati, tre titoli, quattro rendite e tre passività per
 * modulo; oltre tre beneficiari di un bene la devoluzione continua nel
 * rigo successivo (casella "continuazione").
 *
 * Il file è validato sullo schema prima dello scaricamento; va poi
 * controllato con il software dell'Agenzia e trasmesso con Entratel o
 * Fisconline.
 */
class GeneratoreSuc
{

    public const NS_TELEMATICO = 'www.agenziaentrate.gov.it:specificheTecniche:telent:v1';
    public const NS_SUC = 'urn:www.agenziaentrate.gov.it:specificheTecniche:sco:suc:v1';
    public const NS_REG = 'urn:www.agenziaentrate.gov.it:specificheTecniche:sco:reg:v2';
    public const NS_SC = 'urn:www.agenziaentrate.gov.it:specificheTecniche:sco:common:v3';
    public const CODICE_FORNITURA = 'SUC13';
    public const DEVOLUZIONI_PER_RIGO = 3;
    public const GRAFFATI_PER_RIGO = 4;

    private \DOMDocument $documento;
    private DatiDichiarazione $dati;

    /** @var array<int, int> posizione (da 1) del beneficiario nel quadro EA, per id */
    private array $posizioni = [];

    public function genera(DatiDichiarazione $dati, RisultatoLiquidazione $risultato): string
    {
        $this->dati = $dati;
        $this->posizioni = [];
        foreach ($dati->beneficiari as $indice => $beneficiario) {
            $this->posizioni[$beneficiario->id] = $indice + 1;
        }
        $dichiarazione = $dati->dichiarazione;
        $this->documento = new \DOMDocument('1.0', 'UTF-8');
        $this->documento->formatOutput = true;
        $messaggio = $this->documento->createElementNS(self::NS_TELEMATICO, 'tm:Messaggio');
        $messaggio->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:suc', self::NS_SUC);
        $messaggio->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:reg', self::NS_REG);
        $messaggio->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sc', self::NS_SC);
        $this->documento->appendChild($messaggio);
        $intestazione = $this->documento->createElementNS(self::NS_TELEMATICO, 'tm:Intestazione');
        $messaggio->appendChild($intestazione);
        $intestazione->appendChild($this->documento->createElementNS(self::NS_TELEMATICO, 'tm:CodiceFiscaleFornitore', $this->getFornitore()->codiceFiscale));
        $contenuto = $this->documento->createElementNS(self::NS_TELEMATICO, 'tm:Contenuto');
        $contenuto->setAttribute('CodiceFornitura', self::CODICE_FORNITURA);
        $messaggio->appendChild($contenuto);
        $fornitura = $this->documento->createElementNS(self::NS_SUC, 'suc:Fornitura');
        $contenuto->appendChild($fornitura);
        $intestazioneFornitura = $this->el($fornitura, 'Intestazione');
        $this->el($intestazioneFornitura, 'TipoFornitore', $this->getTipoFornitore());
        $documento = $this->el($fornitura, 'Dichiarazione');
        $documento->setAttribute('identificativo', '00001');
        $this->frontespizio($documento, $risultato);
        if ($dichiarazione->tipoDichiarazione->hasQuadri()) {
            $this->quadroEA($documento);
            $this->quadroBeni($documento, QuadroBene::terreni);
            $this->quadroBeni($documento, QuadroBene::fabbricati);
            $this->quadroED($documento);
            $this->quadroEE($documento, $risultato);
            if ($risultato->hasQuadroEF()) {
                $this->quadroEF($documento, $risultato);
            }
        }
        $this->quadroEG($documento);
        if ($this->hasQuadroEH()) {
            $this->quadroEH($documento, $risultato);
        }
        if ($dichiarazione->tipoDichiarazione->hasQuadri()) {
            $this->quadroEI($documento);
            foreach ([QuadroBene::terreniTavolare, QuadroBene::fabbricatiTavolare, QuadroBene::aziende, QuadroBene::titoli, QuadroBene::aeromobili, QuadroBene::navi, QuadroBene::altriBeni] as $quadro) {
                $this->quadroBeni($documento, $quadro);
            }
        }
        return $this->documento->saveXML();
    }

    /**
     * Validazione sullo schema dell'Agenzia (XSD del modulo).
     *
     * @return string[] errori di validazione
     */
    public static function valida(string $xml): array
    {
        $precedente = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $documento = new \DOMDocument();
        $documento->loadXML($xml);
        $documento->schemaValidate(SpecificheSuc::getCartellaXsd() . '/' . SpecificheSuc::XSD_FORNITURA);
        $errori = array_map(fn(\LibXMLError $errore) => 'Riga ' . $errore->line . ': ' . trim($errore->message), libxml_get_errors());
        libxml_clear_errors();
        libxml_use_internal_errors($precedente);
        return $errori;
    }

    public static function getNomeFile(DatiDichiarazione $dati): string
    {
        return 'SUC13_' . $dati->dichiarazione->defunto->codiceFiscale . '_' . $dati->dichiarazione->id . '.xml';
    }

    private function getFornitore(): Soggetto
    {
        return $this->dati->dichiarazione->intermediario?->soggetto ?? $this->dati->dichiarazione->presentatore;
    }

    private function getTipoFornitore(): string
    {
        return $this->dati->dichiarazione->intermediario === null ? '1' : '10';
    }

    // ---------------------------------------------------------------
    // Frontespizio
    // ---------------------------------------------------------------

    private function frontespizio(\DOMElement $documento, RisultatoLiquidazione $risultato): void
    {
        $dichiarazione = $this->dati->dichiarazione;
        $defunto = $dichiarazione->defunto;
        $frontespizio = $this->el($documento, 'Frontespizio');
        $this->el($frontespizio, 'CodiceFiscaleDefunto', $defunto->codiceFiscale);
        $tipo = $this->el($frontespizio, 'TipoDichiarazione');
        if ($dichiarazione->tipoDichiarazione === TipoDichiarazioneSuccessione::prima) {
            $this->el($tipo, 'PrimaDichiarazione', '1');
        } else {
            $this->el($tipo, 'DichiarazioneSostitutiva', $dichiarazione->tipoDichiarazione->value);
            $estremi = $this->el($tipo, 'EstremiPrimaDichiarazione');
            $this->el($estremi, 'Anno', (string) $dichiarazione->annoPrima);
            $this->el($estremi, 'Volume', $dichiarazione->volumePrima);
            $this->el($estremi, 'Numero', $dichiarazione->numeroPrima);
        }
        $devoluzione = $this->el($tipo, 'Devoluzione');
        $this->flag($devoluzione, 'DevoluzionePerLegge', $dichiarazione->devoluzioneLegge);
        if ($dichiarazione->devoluzioneTestamento) {
            $this->el($devoluzione, 'DevoluzionePerTestamento', '1');
            $testamento = $this->el($devoluzione, 'Testamento');
            if ($dichiarazione->testamentoEstero === false) {
                $pubblicazione = $this->el($testamento, 'Pubblicazione');
                $this->el($pubblicazione, 'PubblicoUfficiale', $this->testo($dichiarazione->testamentoPubblicoUfficiale, 50));
                $this->el($pubblicazione, 'DataPubblicazioneTestamento', $this->data($dichiarazione->testamentoDataPubblicazione));
                $this->registrazione($this->el($testamento, 'Registrazione'), $dichiarazione->testamentoUfficio, $dichiarazione->testamentoSerie, $dichiarazione->testamentoNumero, $dichiarazione->testamentoSottonumero, $dichiarazione->testamentoDataRegistrazione);
            } else {
                $this->el($testamento, 'TestamentoEstero', '1');
            }
        }
        $this->flag($devoluzione, 'LeggeEstera', $dichiarazione->leggeEstera);
        $this->flag($tipo, 'EventiEccezionali', $dichiarazione->eventiEccezionali);
        $conteggi = ['NumeroEredi' => TipoBeneficiario::erede, 'NumeroChiamati' => TipoBeneficiario::chiamato, 'NumeroLegatari' => TipoBeneficiario::legatario];
        if ($dichiarazione->tipoDichiarazione->hasQuadri() && (count($this->dati->beneficiari) > 0)) {
            $beneficiari = $this->el($frontespizio, 'Beneficiari');
            foreach ($conteggi as $elemento => $tipoBeneficiario) {
                $numero = count(array_filter($this->dati->beneficiari, fn(BeneficiarioSuccessione $beneficiario) => $beneficiario->tipoBeneficiario === $tipoBeneficiario));
                $this->numero($beneficiari, $elemento, $numero);
            }
            $this->flag($beneficiari, 'AccettazioneConBeneficioInventario', $dichiarazione->beneficioInventario);
        }
        $datiDefunto = $this->el($frontespizio, 'DatiDefunto');
        $this->anagrafici($this->el($datiDefunto, 'DatiAnagrafici'), $defunto);
        $ulteriori = $this->el($datiDefunto, 'UlterioriDati');
        $this->el($ulteriori, 'DataDecesso', $this->data($defunto->dataDecesso));
        $this->el($ulteriori, 'StatoCivile', $dichiarazione->statoCivile?->value);
        if ($dichiarazione->residenzaEstera !== null) {
            $this->el($ulteriori, 'ResidenzaEstera', $dichiarazione->residenzaEstera->value);
        }
        $presentatore = $this->el($frontespizio, 'Presentatore');
        $this->el($presentatore, 'CodiceFiscale', $dichiarazione->presentatore->codiceFiscale);
        $this->el($presentatore, 'CodiceCarica', $dichiarazione->codiceCarica->value);
        if ($dichiarazione->decorrenzaTermine !== null) {
            $this->el($presentatore, 'DecorrenzaTerminePresentazione', $this->data($dichiarazione->decorrenzaTermine));
        }
        $this->anagrafici($this->el($presentatore, 'DatiAnagrafici'), $dichiarazione->presentatore);
        if ($dichiarazione->telefono !== null) {
            $this->el($presentatore, 'Telefono', preg_replace('/\D/', '', $dichiarazione->telefono));
        }
        if ($dichiarazione->email !== null) {
            $this->el($presentatore, 'Email', $dichiarazione->email);
        }
        if ($dichiarazione->rappresentato !== null) {
            $this->el($presentatore, 'CodiceFiscaleRappresentato', $dichiarazione->rappresentato->codiceFiscale);
        }
        $firma = $this->el($frontespizio, 'FirmaModello');
        foreach ($this->getQuadriCompilati($risultato) as $quadro) {
            $this->el($firma, 'Casella' . $quadro, '1');
        }
        $this->el($firma, 'FirmaDichiarante', '1');
        $senzaVolture = $dichiarazione->senzaVolture || ($dichiarazione->codiceCarica?->isSenzaVoltureAutomatiche() ?? false);
        if ($senzaVolture || $dichiarazione->copiaConforme) {
            $casi = $this->el($frontespizio, 'CasiParticolari');
            $this->flag($casi, 'CasiParticolari', $senzaVolture);
            $this->flag($casi, 'CopiaConforme', $dichiarazione->copiaConforme);
            $this->el($casi, 'FirmaDichiarante', '1');
        }
        if (($dichiarazione->intermediario !== null) && ($dichiarazione->intermediario->soggetto->codiceFiscale !== $dichiarazione->presentatore->codiceFiscale)) {
            $impegno = $this->el($frontespizio, 'ImpegnoATrasmettere');
            $this->el($impegno, 'CFintermediario', $dichiarazione->intermediario->soggetto->codiceFiscale, self::NS_REG);
            $this->el($impegno, 'ImpegnoATrasmettere', $dichiarazione->impegno?->value, self::NS_REG);
            $this->el($impegno, 'DataImpegno', $this->data($dichiarazione->dataImpegno), self::NS_REG);
            $this->el($impegno, 'FirmaIntermediario', '1', self::NS_REG);
        }
        if (($risultato->getImportoDaVersare() > 0) && ($dichiarazione->contoAddebito !== null)) {
            $versamento = $this->el($frontespizio, 'Versamento');
            $this->el($versamento, 'IBAN', $dichiarazione->contoAddebito->iban, self::NS_REG);
            $this->el($versamento, 'CodiceFiscaleTitolareCC', $dichiarazione->contoAddebito->soggetto->codiceFiscale, self::NS_REG);
        }
        if ($dichiarazione->tipoDichiarazione->hasQuadri() && $risultato->hasQuadroEF()) {
            $this->el($frontespizio, 'ImportoDaVersare', $this->importo($risultato->getImportoDaVersare()));
        }
    }

    /**
     * @return string[] quadri presenti nel file (caselle della firma)
     */
    private function getQuadriCompilati(RisultatoLiquidazione $risultato): array
    {
        $dati = $this->dati;
        $quadri = [];
        if ($dati->dichiarazione->tipoDichiarazione->hasQuadri()) {
            $quadri = array_keys(array_filter([
                'EA' => count($dati->beneficiari) > 0,
                'EB' => count($dati->getBeniQuadro(QuadroBene::terreni)) > 0,
                'EC' => count($dati->getBeniQuadro(QuadroBene::fabbricati)) > 0,
                'ED' => count($dati->passivita) > 0,
                'EE' => true,
                'EF' => $risultato->hasQuadroEF(),
            ]));
        }
        if (count($dati->allegati) > 0) {
            $quadri[] = 'EG';
        }
        if ($this->hasQuadroEH()) {
            $quadri[] = 'EH';
        }
        if ($dati->dichiarazione->tipoDichiarazione->hasQuadri()) {
            if ($this->hasQuadroEI()) {
                $quadri[] = 'EI';
            }
            foreach ([QuadroBene::terreniTavolare, QuadroBene::fabbricatiTavolare, QuadroBene::aziende, QuadroBene::titoli, QuadroBene::aeromobili, QuadroBene::navi, QuadroBene::altriBeni] as $quadro) {
                if (count($dati->getBeniQuadro($quadro)) > 0) {
                    $quadri[] = $quadro->value;
                }
            }
        }
        return $quadri;
    }

    // ---------------------------------------------------------------
    // Quadro EA
    // ---------------------------------------------------------------

    private function quadroEA(\DOMElement $documento): void
    {
        if (count($this->dati->beneficiari) === 0) {
            return;
        }
        $quadro = $this->el($documento, 'QuadroEA');
        foreach (array_chunk($this->dati->beneficiari, BeneficiarioSuccessione::RIGHI_PER_MODULO) as $gruppo) {
            $modulo = $this->el($quadro, 'Modulo');
            foreach ($gruppo as $beneficiario) {
                $soggetto = $this->el($modulo, 'Soggetto');
                $this->el($soggetto, 'CodiceFiscale', $beneficiario->soggetto->codiceFiscale);
                $this->el($soggetto, 'TipoSoggetto', $beneficiario->tipoBeneficiario->value);
                $this->flag($soggetto, 'Rinuncia', $beneficiario->rinuncia);
                $this->el($soggetto, 'GradoParentela', $beneficiario->gradoParentela->value);
                $this->flag($soggetto, 'PortatoreHandicap', $beneficiario->disabile);
                if ($beneficiario->soggetto->isPersonaFisica()) {
                    $this->anagrafici($this->el($soggetto, 'DatiAnagrafici'), $beneficiario->soggetto);
                } else {
                    $this->el($soggetto, 'Denominazione', $this->testo($beneficiario->soggetto->nominativo, 150));
                }
                if ($beneficiario->rappresentato !== null) {
                    $rappresentazione = $this->el($soggetto, 'SuccessionePerRappresentazione');
                    $this->el($rappresentazione, 'CodiceFiscaleSoggettoRappresentato', $beneficiario->rappresentato->codiceFiscale);
                    $this->el($rappresentazione, 'GradoParentela', $beneficiario->gradoParentelaRappresentato?->value);
                }
                if ($beneficiario->isTrust() && (($beneficiario->codiceFiscaleTrust !== null) || ($beneficiario->beneficiarioFinale !== null))) {
                    $trust = $this->el($soggetto, 'Trust');
                    if ($beneficiario->codiceFiscaleTrust !== null) {
                        $this->el($trust, 'CodiceFiscaleTrust', $beneficiario->codiceFiscaleTrust);
                    }
                    if ($beneficiario->beneficiarioFinale !== null) {
                        $this->el($trust, 'CodiceFiscaleBeneficiario', $beneficiario->beneficiarioFinale->codiceFiscale);
                    }
                }
            }
        }
    }

    // ---------------------------------------------------------------
    // Quadri dei beni (EB, EC, EL, EM, EN, EO, EP, EQ, ER)
    // ---------------------------------------------------------------

    private function quadroBeni(\DOMElement $documento, QuadroBene $quadroBene): void
    {
        $beni = $this->dati->getBeniQuadro($quadroBene);
        if (count($beni) === 0) {
            return;
        }
        $righi = [];
        foreach ($beni as $bene) {
            // le devoluzioni e i graffati che non stanno nel rigo continuano nei righi successivi
            $gruppi = array_chunk($this->dati->getDevoluzioni($bene), self::DEVOLUZIONI_PER_RIGO) ?: [[]];
            $graffati = array_chunk($this->dati->getGraffati($bene), self::GRAFFATI_PER_RIGO);
            $numero = max(count($gruppi), count($graffati));
            for ($indice = 0; $indice < $numero; $indice++) {
                $righi[] = ['bene' => $bene, 'devoluzioni' => $gruppi[$indice] ?? [], 'continuazione' => $indice < count($gruppi) - 1, 'primo' => $indice === 0,
                    'graffati' => $graffati[$indice] ?? [], 'continuazioneGraffati' => $indice < count($graffati) - 1];
            }
        }
        $quadro = $this->el($documento, 'Quadro' . $quadroBene->value);
        $elementoRigo = match ($quadroBene) {
            QuadroBene::terreni => 'Terreni',
            QuadroBene::fabbricati => 'Fabbricati',
            QuadroBene::terreniTavolare => 'TerreniTavolare',
            QuadroBene::fabbricatiTavolare => 'FabbricatiTavolare',
            QuadroBene::aziende => 'Aziende',
            QuadroBene::titoli => 'Titoli',
            QuadroBene::aeromobili => 'Aeromobili',
            QuadroBene::navi => 'Navi',
            QuadroBene::altriBeni => 'Rendite',
        };
        foreach (array_chunk($righi, $quadroBene->getRighiPerModulo()) as $gruppo) {
            $modulo = $this->el($quadro, 'Modulo');
            foreach ($gruppo as $rigo) {
                $elemento = $this->el($modulo, $elementoRigo);
                match ($quadroBene) {
                    QuadroBene::terreni => $this->terreno($elemento, $rigo['bene'], $rigo['primo']),
                    QuadroBene::fabbricati => $this->fabbricato($elemento, $rigo['bene'], $rigo['primo']),
                    QuadroBene::terreniTavolare => $this->terrenoTavolare($elemento, $rigo['bene'], $rigo['primo']),
                    QuadroBene::fabbricatiTavolare => $this->fabbricatoTavolare($elemento, $rigo['bene'], $rigo['primo']),
                    QuadroBene::aziende => $this->azienda($elemento, $rigo['bene'], $rigo['primo']),
                    QuadroBene::titoli => $this->titolo($elemento, $rigo['bene'], $rigo['primo']),
                    QuadroBene::aeromobili => $this->aeromobile($elemento, $rigo['bene'], $rigo['primo']),
                    QuadroBene::navi => $this->nave($elemento, $rigo['bene'], $rigo['primo']),
                    QuadroBene::altriBeni => $this->rendita($elemento, $rigo['bene'], $rigo['primo']),
                };
                $this->graffati($elemento, $rigo['graffati'], $rigo['continuazioneGraffati'], $quadroBene === QuadroBene::fabbricatiTavolare);
                $this->devoluzione($elemento, 'Devoluzione' . $quadroBene->value, $rigo['devoluzioni'], $rigo['continuazione']);
            }
        }
    }

    private function terreno(\DOMElement $rigo, BeneSuccessione $bene, bool $primo): void
    {
        if ($primo === false) {
            return;
        }
        $catasto = $this->dati->getCatasto($bene);
        $this->luogo($rigo, $bene);
        if ($bene->isImmobileEstero()) {
            $this->datiEstero($rigo, $bene);
            return;
        }
        $dati = $this->el($rigo, 'DatiTerreni');
        $this->datiCatastali($dati, $bene);
        $terreno = $this->el($dati, 'DatiTerreni', null, self::NS_REG);
        $metri = (int) round((float) $catasto?->superficie);
        if ($metri > 0) {
            $superficie = $this->el($terreno, 'Superficie', null, self::NS_REG);
            $this->numero($superficie, 'SuperficieEttari', intdiv($metri, 10000), self::NS_REG);
            $this->numero($superficie, 'SuperficieAre', intdiv($metri % 10000, 100), self::NS_REG);
            $this->numero($superficie, 'SuperficieCentiare', $metri % 100, self::NS_REG);
        }
        $this->el($terreno, 'Natura', $bene->natura ?? 'T', self::NS_REG);
        if ($catasto?->redditoDominicale !== null) {
            $this->el($terreno, 'RedditoDominicale', $this->importo($catasto->redditoDominicale), self::NS_REG);
        }
        $this->possesso($rigo, $bene);
        $this->el($rigo, 'CodiceDiritto_P', $bene->codiceDiritto->value);
        $this->flag($rigo, 'BeneAziendale', $bene->isAziendale());
        $this->flag($rigo, 'DiscordanzaDatiIntestatario', $bene->discordanzaIntestatario);
        $this->flag($rigo, 'PassaggiSenzaAttiLegali', $bene->passaggiSenzaAtti);
        $this->numero($rigo, 'ImpostaVersataEstero', (int) $bene->impostaEstero);
        $this->el($rigo, 'TipologiaTerreno', $bene->tipologiaTerreno?->value);
        $this->numero($rigo, 'Valore', (int) $bene->valore);
        $this->numero($rigo, 'ValorePrecSucc', (int) $bene->valorePrecedente);
    }

    private function fabbricato(\DOMElement $rigo, BeneSuccessione $bene, bool $primo): void
    {
        if ($primo === false) {
            return;
        }
        $catasto = $this->dati->getCatasto($bene);
        $this->luogo($rigo, $bene);
        if ($bene->isImmobileEstero()) {
            $this->datiEstero($rigo, $bene);
            return;
        }
        $dati = $this->el($rigo, 'DatiFabbricati');
        $this->datiCatastali($dati, $bene);
        $fabbricato = $this->el($dati, 'DatiFabbricati', null, self::NS_REG);
        if ($bene->immobile->sezione !== null) {
            $this->el($fabbricato, 'SezioneUrbana', $this->testo($bene->immobile->sezione, 3), self::NS_REG);
        }
        if ($catasto?->categoria !== null) {
            $this->el($fabbricato, 'CategoriaCatastale', str_replace('/', '', $catasto->categoria->value), self::NS_REG);
            if ($catasto->classe !== null) {
                $this->el($fabbricato, 'Classe', $this->testo($catasto->classe, 2), self::NS_REG);
            }
            if (($catasto->consistenza !== null) && in_array($catasto->categoria->getGruppo(), ['A', 'B', 'C'], true)) {
                $this->el($fabbricato, 'Consistenza', $this->importo($catasto->consistenza), self::NS_REG);
            }
            if (($catasto->rendita !== null) && $catasto->categoria->haRendita()) {
                $this->el($fabbricato, 'RenditaCatastale', $this->importo($catasto->rendita), self::NS_REG);
            }
        }
        $this->possesso($rigo, $bene);
        $this->el($rigo, 'CodiceDiritto_P', $bene->codiceDiritto->value);
        $this->flag($rigo, 'BeneAziendale', $bene->isAziendale());
        $this->numero($rigo, 'ImpostaVersataEstero', (int) $bene->impostaEstero);
        $this->numero($rigo, 'Valore', (int) $bene->valore);
        $this->numero($rigo, 'ValorePrecSucc', (int) $bene->valorePrecedente);
        $this->flag($rigo, 'DiscordanzaDatiIntestatario', $bene->discordanzaIntestatario);
        $this->flag($rigo, 'PassaggiSenzaAttiLegali', $bene->passaggiSenzaAtti);
        if ($bene->dirittoAbitazione !== null) {
            $this->el($rigo, 'DirittoAbitazione', $bene->dirittoAbitazione->value);
        }
    }

    private function terrenoTavolare(\DOMElement $rigo, BeneSuccessione $bene, bool $primo): void
    {
        if ($primo === false) {
            return;
        }
        $catasto = $this->dati->getCatasto($bene);
        $this->luogoTavolare($rigo, $bene);
        $dati = $this->el($rigo, 'DatiTerreniTavolare');
        $this->datiCatastaliTavolare($dati, $bene);
        $terreno = $this->el($dati, 'DatiTerreni', null, self::NS_REG);
        $metri = (int) round((float) $catasto?->superficie);
        if ($metri > 0) {
            $superficie = $this->el($terreno, 'Superficie', null, self::NS_REG);
            $this->numero($superficie, 'SuperficieEttari', intdiv($metri, 10000), self::NS_REG);
            $this->numero($superficie, 'SuperficieAre', intdiv($metri % 10000, 100), self::NS_REG);
            $this->numero($superficie, 'SuperficieCentiare', $metri % 100, self::NS_REG);
        }
        $this->el($terreno, 'Natura', $bene->natura ?? 'T', self::NS_REG);
        if ($catasto?->redditoDominicale !== null) {
            $this->el($terreno, 'RedditoDominicale', $this->importo($catasto->redditoDominicale), self::NS_REG);
        }
        $this->el($terreno, 'PartitaTavolare', $this->testo($bene->immobile->partitaTavolare, 7), self::NS_REG);
        if ($bene->immobile->corpoTavolare !== null) {
            $this->el($terreno, 'CorpoTavolare', $this->testo($bene->immobile->corpoTavolare, 3), self::NS_REG);
        }
        $this->possesso($rigo, $bene);
        $this->el($rigo, 'CodiceDiritto_P', $bene->codiceDiritto->value);
        $this->flag($rigo, 'BeneAziendale', $bene->isAziendale());
        $this->el($rigo, 'TipologiaTerreno', $bene->tipologiaTerreno?->value);
        $this->numero($rigo, 'Valore', (int) $bene->valore);
        $this->numero($rigo, 'ValorePrecSucc', (int) $bene->valorePrecedente);
    }

    private function fabbricatoTavolare(\DOMElement $rigo, BeneSuccessione $bene, bool $primo): void
    {
        if ($primo === false) {
            return;
        }
        $catasto = $this->dati->getCatasto($bene);
        $this->luogoTavolare($rigo, $bene);
        $dati = $this->el($rigo, 'DatiFabbricatiTavolare');
        $this->datiCatastaliTavolare($dati, $bene);
        $fabbricato = $this->el($dati, 'DatiFabbricati', null, self::NS_REG);
        if ($bene->immobile->sezione !== null) {
            $this->el($fabbricato, 'SezioneUrbana', $this->testo($bene->immobile->sezione, 3), self::NS_REG);
        }
        if ($catasto?->categoria !== null) {
            $this->el($fabbricato, 'CategoriaCatastale', str_replace('/', '', $catasto->categoria->value), self::NS_REG);
            if ($catasto->classe !== null) {
                $this->el($fabbricato, 'Classe', $this->testo($catasto->classe, 2), self::NS_REG);
            }
            if (($catasto->consistenza !== null) && in_array($catasto->categoria->getGruppo(), ['A', 'B', 'C'], true)) {
                $this->el($fabbricato, 'Consistenza', $this->importo($catasto->consistenza), self::NS_REG);
            }
            if (($catasto->rendita !== null) && $catasto->categoria->haRendita()) {
                $this->el($fabbricato, 'RenditaCatastale', $this->importo($catasto->rendita), self::NS_REG);
            }
        }
        if ($bene->immobile->partitaTavolare !== null) {
            $this->el($fabbricato, 'PartitaTavolare', $this->testo($bene->immobile->partitaTavolare, 7), self::NS_REG);
        }
        if ($bene->immobile->corpoTavolare !== null) {
            $this->el($fabbricato, 'CorpoTavolare', $this->testo($bene->immobile->corpoTavolare, 3), self::NS_REG);
        }
        if ($bene->immobile->porzioneMateriale !== null) {
            $this->el($fabbricato, 'PorzioneMateriale', $this->testo($bene->immobile->porzioneMateriale, 12), self::NS_REG);
        }
        $this->possesso($rigo, $bene);
        $this->el($rigo, 'CodiceDiritto_P', $bene->codiceDiritto->value);
        $this->flag($rigo, 'BeneAziendale', $bene->isAziendale());
        $this->numero($rigo, 'Valore', (int) $bene->valore);
        $this->numero($rigo, 'ValorePrecSucc', (int) $bene->valorePrecedente);
        if ($bene->dirittoAbitazione !== null) {
            $this->el($rigo, 'DirittoAbitazione', $bene->dirittoAbitazione->value);
        }
    }

    /**
     * Luogo dell'immobile tavolare: per Trento e Bolzano con il comune
     * catastale, il cui codice si ricava dal nome indicato in Anagrafica.
     */
    private function luogoTavolare(\DOMElement $rigo, BeneSuccessione $bene): void
    {
        $immobile = $bene->immobile;
        $luogo = $this->el($rigo, 'LuogoTavolare');
        $this->el($luogo, 'ComuneAmministrativo', $this->testo($immobile->comune->denominazione, 50), self::NS_REG);
        $this->el($luogo, 'CodiceComune', $immobile->comune->codiceCatastale, self::NS_REG);
        $indirizzo = trim(($immobile->indirizzo ?? '') . ($immobile->numeroCivico !== null ? ' ' . $immobile->numeroCivico : ''));
        if ($indirizzo !== '') {
            $this->el($luogo, 'Indirizzo', $this->testo($indirizzo, 80), self::NS_REG);
        }
        $this->el($luogo, 'CodiceComuneAmministrativo', $immobile->comune->codiceCatastale, self::NS_REG);
        $this->el($luogo, 'Provincia', $immobile->comune->siglaProvincia, self::NS_REG);
        $comuneCatastale = in_array($immobile->comune->siglaProvincia, ['TN', 'BZ'], true) ? (new ComuneCatastaleTavolareModel())->trovaPerNome($immobile->comuneCatastale) : null;
        if ($comuneCatastale !== null) {
            $this->el($luogo, 'ComuneCatastale', $this->testo($comuneCatastale->denominazione, 50), self::NS_REG);
            $this->el($luogo, 'CodiceComuneCatastale', $comuneCatastale->codice, self::NS_REG);
        }
    }

    /**
     * Foglio (per i fabbricati), particella con l'eventuale denominatore
     * ("123/4") e subalterno.
     */
    private function datiCatastaliTavolare(\DOMElement $padre, BeneSuccessione $bene): void
    {
        $catastali = $this->el($padre, 'DatiCatastali', null, self::NS_REG);
        if ($bene->immobile->foglio !== '') {
            $this->el($catastali, 'Foglio', $this->testo($bene->immobile->foglio, 4), self::NS_REG);
        }
        [$particella, $denominatore] = array_pad(explode('/', $bene->immobile->particella, 2), 2, null);
        $this->el($catastali, 'Particella', $this->testo($particella, 5), self::NS_REG);
        if (($denominatore ?? '') !== '') {
            $this->el($catastali, 'Particella_Denominatore', $this->testo($denominatore, 5), self::NS_REG);
        }
        if ($bene->immobile->subalterno !== null) {
            $this->el($catastali, 'Subalterno', $this->testo($bene->immobile->subalterno, 4), self::NS_REG);
        }
    }

    private function azienda(\DOMElement $rigo, BeneSuccessione $bene, bool $primo): void
    {
        if ($primo === false) {
            return;
        }
        $descrizione = $this->el($rigo, 'Descrizione');
        $this->el($descrizione, 'Denominazione', $this->testo($bene->descrizione, 150));
        if (($bene->codiceFiscaleEmittente !== null) && ($bene->beneEstero === false)) {
            $this->el($descrizione, 'CodiceFiscale', $bene->codiceFiscaleEmittente);
        }
        if ($bene->dataInventario !== null) {
            $inventario = $this->el($rigo, 'Inventario');
            $this->el($inventario, 'Inventario', '1');
            $this->el($inventario, 'DataRedazioneInventario', $this->data($bene->dataInventario));
        }
        $this->el($rigo, 'CodiceDiritto_P', $bene->codiceDiritto->value);
        $this->numero($rigo, 'Valore', (int) $bene->valore);
        $this->numero($rigo, 'ValorePrecSucc', (int) $bene->valorePrecedente);
        $this->beniEsteri($rigo, $bene);
        $immobili = $this->dati->getImmobiliAziendali($bene);
        foreach (array_slice($immobili, 0, 9) as $immobile) {
            [$rigoBene, $moduloBene] = $this->getPosizioneBene($immobile);
            $elemento = $this->el($rigo, 'ImmobiliAziendali');
            $this->el($elemento, 'Quadro', $immobile->quadro->value);
            $identificativo = $this->el($elemento, 'Identificativo');
            $this->el($identificativo, 'Rigo', (string) $rigoBene);
            $this->el($identificativo, 'Modulo', (string) $moduloBene);
        }
    }

    private function aeromobile(\DOMElement $rigo, BeneSuccessione $bene, bool $primo): void
    {
        if ($primo === false) {
            return;
        }
        $tipo = $this->el($rigo, 'Tipo');
        $this->el($tipo, 'MarcaModello', $this->testo($bene->marcaModello, 45));
        $this->el($tipo, 'AnnoCostruzione', (string) $bene->annoCostruzione);
        $this->el($tipo, 'Nazionalita', $this->testo($bene->nazionalita, 20));
        $this->el($tipo, 'AnnoImmatricolazione', (string) $bene->annoIscrizione);
        $this->el($tipo, 'NumeroImmatricolazione', $bene->numeroIscrizione);
        $this->possesso($rigo, $bene);
        $this->el($rigo, 'CodiceDiritto_P', $bene->codiceDiritto->value);
        $this->beniEsteri($rigo, $bene);
        $this->numero($rigo, 'Valore', (int) $bene->valore);
        $this->numero($rigo, 'ValorePrecSucc', (int) $bene->valorePrecedente);
    }

    private function nave(\DOMElement $rigo, BeneSuccessione $bene, bool $primo): void
    {
        if ($primo === false) {
            return;
        }
        $tipo = $this->el($rigo, 'Tipo');
        $this->el($tipo, 'TipoUnita', $bene->tipoUnita?->value);
        $this->el($tipo, 'Nazionalita', $this->testo($bene->nazionalita, 20));
        $this->el($tipo, 'SiglaUfficioIscrizione', $this->testo($bene->ufficioIscrizione, 6));
        $this->el($tipo, 'AnnoIscrizione', (string) $bene->annoIscrizione);
        $this->el($tipo, 'NumeroIscrizione', $bene->numeroIscrizione);
        $this->el($tipo, 'AnnoCostruzione', (string) $bene->annoCostruzione);
        $this->el($this->el($tipo, 'Dimensione'), $bene->tipoUnita === TipoUnita::vela ? 'Lunghezza' : 'Stazza', (string) $bene->dimensione);
        $this->possesso($rigo, $bene);
        $this->el($rigo, 'CodiceDiritto_P', $bene->codiceDiritto->value);
        $this->beniEsteri($rigo, $bene);
        $this->numero($rigo, 'Valore', (int) $bene->valore);
        $this->numero($rigo, 'ValorePrecSucc', (int) $bene->valorePrecedente);
    }

    private function titolo(\DOMElement $rigo, BeneSuccessione $bene, bool $primo): void
    {
        if ($primo === false) {
            return;
        }
        $this->el($rigo, 'TipoTitolo', $bene->tipoTitolo?->value);
        if (($bene->codiceFiscaleEmittente !== null) && ($bene->beneEstero === false)) {
            $this->el($rigo, 'CodiceFiscale', $bene->codiceFiscaleEmittente);
        }
        $this->numero($rigo, 'Quantita', (int) $bene->quantita);
        if ($bene->codiceTitolo !== null) {
            $this->el($rigo, 'CodiceTitolo', $this->testo($bene->codiceTitolo, 12));
        }
        $this->el($rigo, 'Descrizione', $this->testo($bene->descrizione, 250));
        $this->el($rigo, 'TipoDocumento', ($bene->tipoDocumento ?? \Successioni\Application\Enumerations\TipoDocumentoTitolo::altro)->value);
        $this->el($rigo, 'DataDocumento', $this->data($bene->dataDocumento ?? $this->dati->dichiarazione->getDataApertura()));
        $possesso = $this->el($rigo, 'Possesso');
        $this->el($possesso, 'PossessoNumeratore', $this->importo($bene->quotaNumeratore));
        $this->el($possesso, 'PossessoDenominatore', (string) $bene->quotaDenominatore);
        $this->numero($possesso, 'Valore', (int) $bene->valore);
        $this->numero($possesso, 'ValoreEsente', (int) $bene->valoreEsente);
        $this->numero($possesso, 'ValorePrecSucc', (int) $bene->valorePrecedente);
        $this->el($rigo, 'CodiceDiritto_C', $bene->codiceDiritto->value);
        $this->beniEsteri($rigo, $bene);
    }

    private function rendita(\DOMElement $rigo, BeneSuccessione $bene, bool $primo): void
    {
        if ($primo === false) {
            return;
        }
        $this->el($rigo, 'TipoCespite', $bene->tipoCespite?->value);
        $this->el($rigo, 'Descrizione', $this->testo($bene->descrizione, 250));
        $this->possesso($rigo, $bene);
        $this->el($rigo, 'CodiceDiritto_P', $bene->codiceDiritto->value);
        $this->beniEsteri($rigo, $bene);
        $this->numero($rigo, 'Valore', (int) $bene->valore);
        $this->numero($rigo, 'ValorePrecSucc', (int) $bene->valorePrecedente);
    }

    private function beniEsteri(\DOMElement $rigo, BeneSuccessione $bene): void
    {
        if ($bene->beneEstero) {
            $esteri = $this->el($rigo, 'BeniEsteri');
            $this->el($esteri, 'BeneSitoEstero', '1');
            $this->numero($esteri, 'ImpostaVersataEstero', (int) $bene->impostaEstero);
        }
    }

    /**
     * Righi dei quadri EB ed EC per gli immobili all'estero: senza dati
     * catastali, nell'ordine dello schema.
     */
    private function datiEstero(\DOMElement $rigo, BeneSuccessione $bene): void
    {
        $this->possesso($rigo, $bene);
        $this->el($rigo, 'CodiceDiritto_P', $bene->codiceDiritto->value);
        $this->flag($rigo, 'BeneAziendale', $bene->isAziendale());
        $this->numero($rigo, 'ImpostaVersataEstero', (int) $bene->impostaEstero);
        if ($bene->quadro->isTerreno()) {
            $this->el($rigo, 'TipologiaTerreno', $bene->tipologiaTerreno?->value);
        }
        $this->numero($rigo, 'Valore', (int) $bene->valore);
        $this->numero($rigo, 'ValorePrecSucc', (int) $bene->valorePrecedente);
        if ($bene->quadro->isFabbricato() && ($bene->dirittoAbitazione !== null)) {
            $this->el($rigo, 'DirittoAbitazione', $bene->dirittoAbitazione->value);
        }
    }

    private function luogo(\DOMElement $rigo, BeneSuccessione $bene): void
    {
        if ($bene->isImmobileEstero()) {
            $luogo = $this->el($rigo, 'Luogo');
            $this->el($luogo, 'Provincia', 'EE', self::NS_REG);
            $estero = $this->el($luogo, 'Estero', null, self::NS_REG);
            $this->el($estero, 'CodiceStatoEstero', $bene->codiceStatoEstero, self::NS_SC);
            if ($bene->localitaEstero !== null) {
                $this->el($estero, 'LocalitaEstero', $this->testo($bene->localitaEstero, 50), self::NS_SC);
            }
            $this->el($estero, 'IndirizzoEstero', $this->testo($bene->indirizzoEstero, 80), self::NS_SC);
            $this->el($estero, 'StatoEstero', $this->testo($bene->statoEstero?->denominazione, 34), self::NS_REG);
            return;
        }
        $immobile = $bene->immobile;
        $luogo = $this->el($rigo, 'Luogo');
        $this->el($luogo, 'Provincia', $immobile->comune->siglaProvincia, self::NS_REG);
        $italia = $this->el($luogo, 'Italia', null, self::NS_REG);
        $this->el($italia, 'ComuneAmministrativo', $this->testo($immobile->comune->denominazione, 50), self::NS_REG);
        $this->el($italia, 'CodiceComune', $immobile->comune->codiceCatastale, self::NS_REG);
        $indirizzo = trim(($immobile->indirizzo ?? '') . ($immobile->numeroCivico !== null ? ' ' . $immobile->numeroCivico : ''));
        if ($indirizzo !== '') {
            $this->el($italia, 'Indirizzo', $this->testo($indirizzo, 80), self::NS_REG);
        }
        $this->el($italia, 'CodiceComuneAmministrativo', $immobile->comune->codiceCatastale, self::NS_REG);
    }

    private function datiCatastali(\DOMElement $padre, BeneSuccessione $bene, string $spazio = self::NS_REG): void
    {
        $catastali = $this->el($padre, 'DatiCatastali', null, $spazio);
        $this->el($catastali, 'Foglio', $this->testo($bene->immobile->foglio, 4), self::NS_REG);
        $this->el($catastali, 'Particella', $this->testo($bene->immobile->particella, 5), self::NS_REG);
        if ($bene->immobile->subalterno !== null) {
            $this->el($catastali, 'Subalterno', $this->testo($bene->immobile->subalterno, 4), self::NS_REG);
        }
    }

    private function possesso(\DOMElement $rigo, BeneSuccessione $bene): void
    {
        $possesso = $this->el($rigo, 'Possesso');
        $this->el($possesso, 'PossessoNumeratore', $this->importo($bene->quotaNumeratore));
        $this->el($possesso, 'PossessoDenominatore', (string) $bene->quotaDenominatore);
    }

    /**
     * Immobili graffati del rigo (quadri EC, EM): fino a quattro, gli altri
     * nel rigo successivo con la casella di continuazione.
     *
     * @param \Anagrafica\Application\Entities\IdentificativoGraffato[] $graffati
     */
    private function graffati(\DOMElement $rigo, array $graffati, bool $continuazione, bool $tavolare): void
    {
        if (count($graffati) === 0) {
            return;
        }
        $sezione = $this->el($rigo, 'Graffati');
        foreach ($graffati as $graffato) {
            $catastali = $this->el($this->el($sezione, 'ImmobiliGraffati'), $tavolare ? 'DatiCatastaliTavolare' : 'DatiCatastali');
            $this->el($catastali, 'Foglio', $this->testo($graffato->foglio, 4), self::NS_REG);
            [$particella, $denominatore] = $tavolare ? array_pad(explode('/', $graffato->particella, 2), 2, null) : [$graffato->particella, null];
            $this->el($catastali, 'Particella', $this->testo($particella, 5), self::NS_REG);
            if (($denominatore ?? '') !== '') {
                $this->el($catastali, 'Particella_Denominatore', $this->testo($denominatore, 5), self::NS_REG);
            }
            if ($graffato->subalterno !== null) {
                $this->el($catastali, 'Subalterno', $this->testo($graffato->subalterno, 4), self::NS_REG);
            }
            if ($graffato->sezione !== null) {
                $this->el($catastali, 'SezioneUrbana', $this->testo($graffato->sezione, 3));
            }
        }
        $this->flag($sezione, 'ContinuazioneGraffati', $continuazione);
    }

    /**
     * Sezione devoluzione di un rigo.
     *
     * @param DevoluzioneSuccessione[] $devoluzioni
     */
    private function devoluzione(\DOMElement $rigo, string $nome, array $devoluzioni, bool $continuazione): void
    {
        if (count($devoluzioni) === 0) {
            return;
        }
        $sezione = $this->el($rigo, $nome);
        foreach ($devoluzioni as $devoluzione) {
            $elemento = $this->el($sezione, 'Devoluzione');
            $this->identificazione($elemento, $devoluzione->beneficiarioSuccessione);
            $quota = $this->el($elemento, 'Quota');
            $elementi = $this->el($quota, 'QuotaElementi');
            $this->el($elementi, 'QuotaNumeratore', (string) $devoluzione->quotaNumeratore);
            $this->el($elementi, 'QuotaDenominatore', (string) $devoluzione->quotaDenominatore);
            if ($devoluzione->valoreQuota !== null) {
                $this->el($quota, 'QuotaValore', $this->importo($devoluzione->valoreQuota));
            }
            $this->el($elemento, 'CodiceDiritto_Rip', $devoluzione->codiceDiritto->value);
            if ($devoluzione->agevolazione !== null) {
                $this->el($elemento, 'Agevolazioni', $devoluzione->agevolazione->value);
            }
            if ($devoluzione->riduzione !== null) {
                $this->el($elemento, 'Riduzioni', $devoluzione->riduzione->value);
            }
        }
        $this->flag($sezione, 'Continuazione', $continuazione);
    }

    private function identificazione(\DOMElement $padre, BeneficiarioSuccessione $beneficiario): void
    {
        $posizione = $this->posizioni[$beneficiario->id];
        $identificazione = $this->el($padre, 'IdentificazioneSoggetto');
        $this->el($identificazione, 'Rigo', (string) (($posizione - 1) % BeneficiarioSuccessione::RIGHI_PER_MODULO + 1));
        $this->el($identificazione, 'Modulo', (string) (intdiv($posizione - 1, BeneficiarioSuccessione::RIGHI_PER_MODULO) + 1));
    }

    // ---------------------------------------------------------------
    // Quadri ED, EE, EF
    // ---------------------------------------------------------------

    private function quadroED(\DOMElement $documento): void
    {
        if (count($this->dati->passivita) === 0) {
            return;
        }
        $righi = [];
        foreach ($this->dati->passivita as $passivita) {
            $gruppi = array_chunk($this->dati->getRipartizioni($passivita), self::DEVOLUZIONI_PER_RIGO) ?: [[]];
            foreach ($gruppi as $indice => $ripartizioni) {
                $righi[] = ['passivita' => $passivita, 'ripartizioni' => $ripartizioni, 'continuazione' => $indice < count($gruppi) - 1, 'primo' => $indice === 0];
            }
        }
        $quadro = $this->el($documento, 'QuadroED');
        foreach (array_chunk($righi, 3) as $gruppo) {
            $modulo = $this->el($quadro, 'Modulo');
            foreach ($gruppo as $rigo) {
                $elemento = $this->el($modulo, 'Passivita');
                if ($rigo['primo']) {
                    $this->passivita($elemento, $rigo['passivita']);
                }
                if (count($rigo['ripartizioni']) > 0) {
                    $sezione = $this->el($elemento, 'RipartizioneED');
                    foreach ($rigo['ripartizioni'] as $ripartizione) {
                        $voce = $this->el($sezione, 'Ripartizione');
                        $this->identificazione($voce, $ripartizione->beneficiarioSuccessione);
                        $quota = $this->el($voce, 'Quota');
                        $elementi = $this->el($quota, 'QuotaElementi');
                        $this->el($elementi, 'QuotaNumeratore', (string) $ripartizione->quotaNumeratore);
                        $this->el($elementi, 'QuotaDenominatore', (string) $ripartizione->quotaDenominatore);
                        if ($ripartizione->valoreQuota !== null) {
                            $this->el($quota, 'QuotaValore', $this->importo($ripartizione->valoreQuota));
                        }
                    }
                    $this->flag($sezione, 'Continuazione', $rigo['continuazione']);
                }
            }
        }
    }

    private function passivita(\DOMElement $rigo, PassivitaSuccessione $passivita): void
    {
        $this->el($rigo, 'Tipo', $passivita->tipoPassivita->value);
        $this->el($rigo, 'Descrizione', $this->testo($passivita->descrizione, 250));
        $this->el($rigo, 'EstremiDocumento', $this->testo($passivita->estremiDocumento, 50));
        $this->el($rigo, 'DataDocumento', $this->data($passivita->dataDocumento));
        $possesso = $this->el($rigo, 'Possesso');
        if ($passivita->tipoPassivita->hasQuota()) {
            $this->numero($possesso, 'PossessoNumeratore', (int) $passivita->quotaNumeratore);
            $this->numero($possesso, 'PossessoDenominatore', (int) $passivita->quotaDenominatore);
        }
        $this->el($possesso, 'Valore', (string) $passivita->valore);
        if ($passivita->beneSuccessione !== null) {
            $bene = $passivita->beneSuccessione;
            [$rigoBene, $moduloBene] = $this->getPosizioneBene($bene);
            $cespite = $this->el($rigo, 'ProgressivoCespite');
            $this->el($cespite, 'Quadro', $bene->quadro->value);
            $this->el($cespite, 'Rigo', (string) $rigoBene);
            $this->el($cespite, 'Mod', (string) $moduloBene);
        }
    }

    /**
     * Rigo e modulo del bene nel suo quadro, contando i righi di
     * continuazione delle devoluzioni.
     *
     * @return array{0: int, 1: int}
     */
    private function getPosizioneBene(BeneSuccessione $bene): array
    {
        $progressivo = 0;
        foreach ($this->dati->getBeniQuadro($bene->quadro) as $altro) {
            if ($altro->id === $bene->id) {
                break;
            }
            $progressivo += max(1, (int) ceil(count($this->dati->getDevoluzioni($altro)) / self::DEVOLUZIONI_PER_RIGO));
        }
        $perModulo = $bene->quadro->getRighiPerModulo();
        return [$progressivo % $perModulo + 1, intdiv($progressivo, $perModulo) + 1];
    }

    private function quadroEE(\DOMElement $documento, RisultatoLiquidazione $risultato): void
    {
        $riepilogo = $risultato->riepilogo;
        $quadro = $this->el($documento, 'QuadroEE');
        $this->numero($quadro, 'TotaleValoreImmobili', $riepilogo['immobili']);
        $this->numero($quadro, 'TotaleValoreAziende', $riepilogo['aziende']);
        $this->numero($quadro, 'TotaleValoreTitoli', $riepilogo['titoli']);
        $this->numero($quadro, 'TotaleValoreAereiNavi', $riepilogo['aereiNavi']);
        $this->numero($quadro, 'TotaleValoreAltriBeni', $riepilogo['altriBeni']);
        $this->numero($quadro, 'TotaleAttivo', $riepilogo['attivo']);
        $this->numero($quadro, 'TotalePassivo', $riepilogo['passivo']);
        if ($riepilogo['netto'] !== 0) {
            $this->el($quadro, 'TotaleValoreAsseEreditarioNetto', (string) $riepilogo['netto']);
        }
    }

    private function quadroEF(\DOMElement $documento, RisultatoLiquidazione $risultato): void
    {
        $dichiarazione = $this->dati->dichiarazione;
        $quadro = $this->el($documento, 'QuadroEF');
        if ($risultato->ipotecariaDovuta > 0) {
            $sezione = $this->el($quadro, 'SezioneI_ImpostaIpotecaria');
            $this->coppia($sezione, 'ImpostaProporzionale', $risultato->ipotecariaImponibile, $risultato->ipotecariaProporzionale);
            $this->coppia($sezione, 'AgevolazioneG', $risultato->agevolazioneGValore, $risultato->agevolazioneGImposta);
            $this->coppia($sezione, 'AgevolazioneM', $risultato->agevolazioneMValore, $risultato->agevolazioneMImposta);
            if ($risultato->trustValore > 0) {
                $trust = $this->el($sezione, 'TrustDisab');
                $this->el($trust, 'TrustDisab_Valore', (string) $risultato->trustValore);
                $this->numero($trust, 'TrustDisab_Imposta', $risultato->trustIpotecaria);
            }
            if ($risultato->primaCasaNumero > 0) {
                $primaCasa = $this->el($sezione, 'PrimaCasa');
                $this->el($primaCasa, 'PrimaCasa_Numero', (string) $risultato->primaCasaNumero);
                $this->el($primaCasa, 'AgevolazionePX_Valore', (string) max(1, $risultato->primaCasaValore));
                $this->el($primaCasa, 'PrimaCasa_Imposta', (string) $risultato->primaCasaImposta);
            }
            $this->numero($sezione, 'ImpostaIpotecariaDovuta', $risultato->ipotecariaDovuta);
            $this->numero($sezione, 'ImpostaIpotecariaVersata', $risultato->ipotecariaVersata);
            $this->numero($sezione, 'CreditoImposta', $risultato->ipotecariaCredito);
            $this->numero($sezione, 'ImpostaIpotecariaDaVersare', $risultato->ipotecariaDaVersare);
        }
        if ($risultato->catastaleDovuta > 0) {
            $sezione = $this->el($quadro, 'SezioneII_ImpostaCatastale');
            $this->coppia($sezione, 'ImpostaProporzionale', $risultato->catastaleImponibile, $risultato->catastaleProporzionale);
            $this->numero($sezione, 'ImpostaCatastaleFissa', $risultato->catastaleFissa);
            if ($risultato->trustValore > 0) {
                $trust = $this->el($sezione, 'ImpostaCatastaleTrustDisab');
                $this->el($trust, 'ImpostaCatastaleTrustDisab_Valore', (string) $risultato->trustValore);
                $this->numero($trust, 'ImpostaCatastaleTrustDisab_Imposta', $risultato->trustCatastale);
            }
            $this->numero($sezione, 'ImpostaCatastaleDovuta', $risultato->catastaleDovuta);
            $this->numero($sezione, 'ImpostaCatastaleVersata', $risultato->catastaleVersata);
            $this->numero($sezione, 'CreditoImposta', $risultato->catastaleCredito);
            $this->numero($sezione, 'ImpostaCatastaleDaVersare', $risultato->catastaleDaVersare);
        }
        if ($risultato->tassaIpotecaria > 0) {
            $sezione = $this->el($quadro, 'SezioneIII_TassaIpotecaria');
            $this->el($sezione, 'Circoscrizioni_Numero', (string) $risultato->circoscrizioniTassa);
            $this->el($sezione, 'Circoscrizioni_Imposta', (string) $risultato->tassaIpotecaria);
        }
        if ($risultato->bollo > 0) {
            $sezione = $this->el($quadro, 'SezioneIV_ImpostaBollo');
            $this->numero($sezione, 'ImpostaBollo_CopiaConforme', $risultato->bolloCopiaConforme);
            $this->numero($sezione, 'Circoscrizioni_Numero', $risultato->circoscrizioniBollo);
            $this->el($sezione, 'Circoscrizioni_Imposta', (string) $risultato->bollo);
        }
        if ($risultato->tributiSpeciali > 0) {
            $copia = $this->el($this->el($quadro, 'SezioneV_TributiSpeciali'), 'CopiaConforme');
            $this->el($copia, 'CopiaConforme_Importo', $this->importo($risultato->tributiSpeciali));
        }
        $trustee = $dichiarazione->codiceCarica === CaricaDichiarante::trustee;
        if ($risultato->autoliquidazione || ($trustee && $dichiarazione->pagamentoAnticipatoTrust)) {
            $sezione = $this->el($quadro, 'SezioneVBis_ImpostaSuccessione');
            if ($risultato->autoliquidazione && ($risultato->successioneImposta <= 0)) {
                $this->el($sezione, 'ImpostaNonDovuta', '1');
            } elseif ($risultato->successioneImposta > 0) {
                $calcolata = $this->el($sezione, 'ImpostaCalcolata');
                $this->el($calcolata, 'Imposta', (string) $risultato->successioneImposta);
                $this->numero($calcolata, 'ImpostaVersata', $risultato->successioneVersata);
                $this->numero($calcolata, 'CreditoImposta', $risultato->successioneCredito);
                if ($risultato->successioneDaVersare > 0) {
                    $this->el($calcolata, 'ImpostaDaVersare', (string) $risultato->successioneDaVersare);
                    $this->el($calcolata, 'TempisticaPagamento', $dichiarazione->tempisticaPagamento?->value);
                    if ($dichiarazione->numeroRate !== null) {
                        $this->el($calcolata, 'PagamentoRateale', (string) $dichiarazione->numeroRate);
                        $this->el($calcolata, 'Acconto', (string) $dichiarazione->acconto);
                    }
                }
            }
            if ($trustee && $dichiarazione->pagamentoAnticipatoTrust) {
                $this->el($sezione, 'PagamentoAnticipatoTrust', '1');
            }
        }
        $this->el($quadro, 'TotaleDaVersare', $this->importo($risultato->totaleDaVersare));
        if (($risultato->getTotaleSanzioni() + $risultato->getTotaleInteressi()) > 0) {
            $sezione = $this->el($quadro, 'SezioneVI_SanzioniInteressi');
            foreach (['ipotecaria' => 'ImpostaIpotecaria', 'catastale' => 'ImpostaCatastale', 'tassaIpotecaria' => 'TassaIpotecaria', 'bollo' => 'ImpostaBollo', 'successione' => 'ImpostaSuccessione'] as $voce => $elemento) {
                $sanzioni = $risultato->sanzioni[$voce] ?? 0.0;
                $interessi = $risultato->interessi[$voce] ?? 0.0;
                if ($sanzioni + $interessi > 0) {
                    $rigo = $this->el($sezione, $elemento);
                    $this->el($rigo, $elemento . '_Sanzioni', $this->importo($sanzioni));
                    $this->el($rigo, $elemento . '_Interessi', $this->importo($interessi));
                }
            }
            $totale = $this->el($sezione, 'TotaleDaVersare');
            $this->el($totale, 'TotaleDaVersare_Sanzioni', $this->importo($risultato->getTotaleSanzioni()));
            $this->el($totale, 'TotaleDaVersare_Interessi', $this->importo($risultato->getTotaleInteressi()));
        }
    }

    private function coppia(\DOMElement $sezione, string $nome, int $valore, int $imposta): void
    {
        if ($valore <= 0) {
            return;
        }
        $elemento = $this->el($sezione, $nome);
        $this->el($elemento, $nome . '_' . ($nome === 'ImpostaProporzionale' ? 'Imponibile' : 'Valore'), (string) $valore);
        $this->numero($elemento, $nome . '_Imposta', $imposta);
    }

    // ---------------------------------------------------------------
    // Quadro EG (allegati)
    // ---------------------------------------------------------------

    private function quadroEG(\DOMElement $documento): void
    {
        if (count($this->dati->allegati) === 0) {
            return;
        }
        $quadro = $this->el($documento, 'QuadroEG');
        foreach (TipoAllegato::cases() as $tipo) {
            $allegati = array_values(array_filter($this->dati->allegati, fn($allegato) => $allegato->tipoAllegato === $tipo));
            if (count($allegati) === 0) {
                continue;
            }
            $sezione = $this->el($quadro, $tipo->getElemento());
            $this->el($sezione, $tipo->getElemento() . 'Num', (string) count($allegati));
            foreach ($allegati as $allegato) {
                $file = $this->el($sezione, $tipo->getElemento() . 'All');
                $this->el($file, 'FileType', $allegato->tipoMime, self::NS_REG);
                $this->el($file, 'FileName', $this->testo($allegato->nomeFile, 100), self::NS_REG);
                if ($allegato->descrizione !== null) {
                    $this->el($file, 'FileDescription', $this->testo($allegato->descrizione, 100), self::NS_REG);
                }
                $this->el($file, 'ImageData', $allegato->contenutoAllegato->contenuto, self::NS_REG);
            }
        }
    }

    // ---------------------------------------------------------------
    // Quadro EH (dichiarazioni sostitutive)
    // ---------------------------------------------------------------

    private function hasQuadroEH(): bool
    {
        $dichiarazione = $this->dati->dichiarazione;
        return $dichiarazione->tipoDichiarazione->hasQuadri()
                && (in_array($dichiarazione->codiceCarica, [CaricaDichiarante::curatoreEreditaGiacente, CaricaDichiarante::amministratoreEredita, CaricaDichiarante::esecutoreTestamentario], true) === false);
    }

    private function quadroEH(\DOMElement $documento, RisultatoLiquidazione $risultato): void
    {
        $dichiarazione = $this->dati->dichiarazione;
        $defunto = $dichiarazione->defunto;
        $eredi = array_values(array_filter($this->dati->beneficiari, fn(BeneficiarioSuccessione $beneficiario) => in_array($beneficiario->tipoBeneficiario,
                                [TipoBeneficiario::erede, TipoBeneficiario::legatario, TipoBeneficiario::chiamato, TipoBeneficiario::coniugeRinunciatario], true)));
        $disabili = array_values(array_filter($this->dati->beneficiari, fn(BeneficiarioSuccessione $beneficiario) => $beneficiario->disabile));
        $rinunce = array_values(array_filter($this->dati->beneficiari, fn(BeneficiarioSuccessione $beneficiario) => $beneficiario->rinuncia));
        $quadro = $this->el($documento, 'QuadroEH');
        $primo = $this->el($quadro, 'PrimoModulo');
        $sezione = $this->el($primo, 'SezioneI_DichSost');
        $presentatore = $this->el($sezione, 'Presentatore');
        $this->anagrafici($presentatore, $dichiarazione->presentatore);
        $this->el($presentatore, 'CodiceFiscale', $dichiarazione->presentatore->codiceFiscale);
        $this->el($presentatore, 'CodiceCarica', $dichiarazione->codiceCarica->value);
        if ($dichiarazione->rappresentato !== null) {
            $this->el($presentatore, 'CodiceFiscaleRappresentato', $dichiarazione->rappresentato->codiceFiscale);
        }
        $datiDefunto = $this->el($sezione, 'DatiDefunto');
        $this->anagrafici($this->el($datiDefunto, 'DatiAnagrafici'), $defunto);
        $decesso = $this->el($datiDefunto, 'Decesso');
        $this->el($decesso, 'FlagDeceduto', '1');
        $this->el($decesso, 'DataDecesso', $this->data($defunto->dataDecesso));
        [$comune, $provincia] = $this->getLuogoDecesso();
        $this->el($decesso, 'Comune', $this->testo($comune, 50));
        $this->el($decesso, 'Provincia', $provincia);
        if (count($this->dati->beneficiari) > 0) {
            $presente = $this->dati->getBeneficiarioDichiarante() !== null;
            $this->el($this->el($sezione, 'Dichiarante'), $presente ? 'PresenzaDichiarante' : 'FlagAssenzaDichiarante', '1');
        }
        foreach (array_slice($eredi, 0, 3) as $erede) {
            $this->erede($sezione, $erede);
        }
        $testamento = $this->el($sezione, 'Testamento');
        if ($dichiarazione->devoluzioneTestamento) {
            $presenza = $this->el($testamento, 'PresenzaTestamento');
            $this->el($presenza, 'FlagTestamento', '1');
            if ($dichiarazione->testamentoEstero === false) {
                $this->registrazione($this->el($presenza, 'RegistrazioneTestamento'), $dichiarazione->testamentoUfficio, $dichiarazione->testamentoSerie, $dichiarazione->testamentoNumero, $dichiarazione->testamentoSottonumero, $dichiarazione->testamentoDataRegistrazione);
            }
        } else {
            $this->el($testamento, 'FlagAssenzaTestamento', '1');
        }
        $interdetti = $this->el($sezione, 'Interdetti');
        if (count($disabili) > 0) {
            $this->interdetto($interdetti, $disabili[0], true);
        } else {
            $this->el($interdetti, 'FlagAssenzaInterdetti', '1');
        }
        $rinuncia = $this->el($sezione, 'Rinuncia');
        if (count($rinunce) > 0) {
            $this->rinuncia($rinuncia, $rinunce[0], true);
        } else {
            $this->el($rinuncia, 'FlagAssenzaRinuncia', '1');
        }
        if ($dichiarazione->statoCivile === StatoCivileDefunto::separato) {
            $this->el($this->el($sezione, 'Separazione'), $dichiarazione->separazioneColpa ? 'FlagSeparazione' : 'FlagAssenzaSeparazione', '1');
        }
        if ($dichiarazione->statoCivile === StatoCivileDefunto::unioneCivile) {
            $this->el($sezione, 'FlagNoScioglimentoUnioneCivile', '1');
        }
        $aziende = array_values(array_filter($this->dati->getBeniQuadro(QuadroBene::aziende), fn(BeneSuccessione $bene) => $bene->dataDeposito !== null));
        if (count($aziende) > 0) {
            $this->depositoAzienda($sezione, $aziende[0], true);
        }
        $tipiAllegati = array_map(fn($allegato) => $allegato->tipoAllegato, $this->dati->allegati);
        $this->flag($sezione, 'FlagProspetto', in_array(TipoAllegato::inventario, $tipiAllegati, true));
        $this->flag($sezione, 'FlagPassivita', in_array(TipoAllegato::documentiPassivita, $tipiAllegati, true));
        $navi = $this->dati->getBeniQuadro(QuadroBene::navi);
        $aeromobili = $this->dati->getBeniQuadro(QuadroBene::aeromobili);
        if (count($navi) > 0) {
            $this->iscrizioneNave($sezione, $navi[0], true);
        }
        if (count($aeromobili) > 0) {
            $this->iscrizioneAeromobile($sezione, $aeromobili[0], true);
        }
        $this->el($sezione, 'FirmaDichiarante', '1');
        $this->primaCasa($primo);
        $this->creditoImposta($primo, $risultato);
        $this->altreAgevolazioni($primo);
        $ulteriori = max((int) ceil(max(0, count($eredi) - 3) / 3), count($disabili) - 1, count($rinunce) - 1, count($aziende) - 1, count($navi) - 1, count($aeromobili) - 1);
        for ($indice = 1; $indice <= $ulteriori; $indice++) {
            $sezioneModulo = $this->el($this->el($quadro, 'Modulo'), 'SezioneI_DichSost');
            foreach (array_slice($eredi, $indice * 3, 3) as $erede) {
                $this->erede($sezioneModulo, $erede);
            }
            if (isset($disabili[$indice])) {
                $this->interdetto($sezioneModulo, $disabili[$indice], false);
            }
            if (isset($rinunce[$indice])) {
                $this->rinuncia($sezioneModulo, $rinunce[$indice], false);
            }
            if (isset($aziende[$indice])) {
                $this->depositoAzienda($sezioneModulo, $aziende[$indice], false);
            }
            if (isset($navi[$indice])) {
                $this->iscrizioneNave($sezioneModulo, $navi[$indice], false);
            }
            if (isset($aeromobili[$indice])) {
                $this->iscrizioneAeromobile($sezioneModulo, $aeromobili[$indice], false);
            }
        }
    }

    private function depositoAzienda(\DOMElement $sezione, BeneSuccessione $azienda, bool $primoModulo): void
    {
        $elemento = $this->el($sezione, 'Aziende');
        if ($primoModulo) {
            $this->el($elemento, 'FlagAzienda', '1');
        }
        $this->el($elemento, 'DataDeposito', $this->data($azienda->dataDeposito));
        $this->el($elemento, 'CameraCommercio', $this->testo($azienda->cameraCommercio, 35));
    }

    private function iscrizioneNave(\DOMElement $sezione, BeneSuccessione $nave, bool $primoModulo): void
    {
        $elemento = $this->el($sezione, 'Navi');
        if ($primoModulo) {
            $this->el($elemento, 'FlagNavi', '1');
        }
        $this->el($elemento, 'SiglaUfficioIscrizione', $this->testo($nave->ufficioIscrizione, 6));
        $this->el($elemento, 'AnnoIscrizione', (string) $nave->annoIscrizione);
        $this->el($elemento, 'NumeroIscrizione', $nave->numeroIscrizione);
    }

    private function iscrizioneAeromobile(\DOMElement $sezione, BeneSuccessione $aeromobile, bool $primoModulo): void
    {
        $elemento = $this->el($sezione, 'Aeromobili');
        if ($primoModulo) {
            $this->el($elemento, 'FlagAeromobili', '1');
        }
        $this->el($elemento, 'Nazionalita', $this->testo($aeromobile->nazionalita, 20));
        $this->el($elemento, 'AnnoImmatricolazione', (string) $aeromobile->annoIscrizione);
        $this->el($elemento, 'NumeroImmatricolazione', $aeromobile->numeroIscrizione);
    }

    // ---------------------------------------------------------------
    // Quadro EI (dichiarazioni sostitutive per le volture)
    // ---------------------------------------------------------------

    private function hasQuadroEI(): bool
    {
        foreach ([QuadroBene::terreni, QuadroBene::fabbricati] as $quadro) {
            foreach ($this->dati->getBeniQuadro($quadro) as $bene) {
                if ($bene->discordanzaIntestatario || $bene->passaggiSenzaAtti) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Passaggi senza atti legali (sezione I) e, per ogni immobile con i dati
     * dell'intestatario discordanti, gli estremi degli atti di provenienza
     * (sezione II, tre trascrizioni e tre registrazioni per modulo; le altre
     * nei moduli successivi con la casella di continuazione).
     */
    private function quadroEI(\DOMElement $documento): void
    {
        if ($this->hasQuadroEI() === false) {
            return;
        }
        $dichiarazione = $this->dati->dichiarazione;
        $passaggi = false;
        $sezioni = [];
        foreach ([QuadroBene::terreni, QuadroBene::fabbricati] as $quadro) {
            foreach ($this->dati->getBeniQuadro($quadro) as $bene) {
                $passaggi = $passaggi || $bene->passaggiSenzaAtti;
                if ($bene->discordanzaIntestatario) {
                    $estremi = $this->dati->getEstremi($bene);
                    $trascrizioni = array_chunk(array_values(array_filter($estremi, fn($estremo) => $estremo->isTrascrizione())), 3);
                    $registrazioni = array_chunk(array_values(array_filter($estremi, fn($estremo) => $estremo->isTrascrizione() === false)), 3);
                    $parti = max(1, count($trascrizioni), count($registrazioni));
                    for ($indice = 0; $indice < $parti; $indice++) {
                        $sezioni[] = ['bene' => $bene, 'primo' => $indice === 0, 'trascrizioni' => $trascrizioni[$indice] ?? [], 'registrazioni' => $registrazioni[$indice] ?? [], 'continuazione' => $indice < $parti - 1];
                    }
                }
            }
        }
        $quadro = $this->el($documento, 'QuadroEI_new');
        $primo = $this->el($quadro, 'PrimoModulo');
        $presentatore = $this->el($primo, 'Presentatore');
        $this->anagrafici($presentatore, $dichiarazione->presentatore);
        $this->el($presentatore, 'CodiceFiscale', $dichiarazione->presentatore->codiceFiscale);
        $this->el($presentatore, 'CodiceCarica', $dichiarazione->codiceCarica->value);
        if ($dichiarazione->rappresentato !== null) {
            $this->el($presentatore, 'CodiceFiscaleRappresentato', $dichiarazione->rappresentato->codiceFiscale);
        }
        if ($passaggi) {
            $attiLegali = $this->el($primo, 'SezioneI_AttiLegali');
            $this->el($attiLegali, 'FlagAttiLegali', '1');
            $this->el($attiLegali, 'FirmaDichiarante', '1');
        }
        foreach ($sezioni as $indice => $voce) {
            $sezione = $this->el($indice === 0 ? $primo : $this->el($quadro, 'Modulo'), 'SezioneII_DatiIntestatario');
            if ($voce['primo']) {
                $immobile = $voce['bene']->immobile;
                $elemento = $this->el($sezione, 'Immobile');
                $this->el($elemento, 'TipoCatasto', $voce['bene']->quadro === QuadroBene::terreni ? 'T' : 'U');
                $luogo = $this->el($elemento, 'Luogo');
                $this->el($luogo, 'Provincia', $immobile->comune->siglaProvincia);
                $this->el($luogo, 'ComuneAmministrativo', $this->testo($immobile->comune->denominazione, 50));
                $this->el($luogo, 'CodiceComune', $immobile->comune->codiceCatastale);
                $this->el($luogo, 'CodiceComuneAmministrativo', $immobile->comune->codiceCatastale);
                $this->datiCatastali($elemento, $voce['bene'], self::NS_SUC);
                if (($voce['bene']->quadro === QuadroBene::fabbricati) && ($immobile->sezione !== null)) {
                    $this->el($elemento, 'SezioneUrbana', $this->testo($immobile->sezione, 3));
                }
            }
            foreach ($voce['trascrizioni'] as $estremo) {
                $trascrizione = $this->el($sezione, 'EstremiTrascrizione');
                $this->el($trascrizione, 'UfficioTrascrizione', $estremo->ufficioTrascrizione?->codice);
                $this->el($trascrizione, 'RegistroParticolare', ltrim($estremo->numero, '0'));
                $this->el($trascrizione, 'Anno', (string) $estremo->anno);
            }
            foreach ($voce['registrazioni'] as $estremo) {
                $registrazione = $this->el($sezione, 'EstremiRegistrazione');
                $this->el($registrazione, 'UfficioTerritoriale', $estremo->ufficioRegistrazione?->codice);
                $this->el($registrazione, 'Anno', (string) $estremo->anno);
                $this->el($registrazione, 'Volume', ltrim((string) $estremo->volume, '0'));
                $this->el($registrazione, 'Numero', ltrim($estremo->numero, '0'));
            }
            $this->flag($sezione, 'Continuazione', $voce['continuazione']);
            if ($indice === 0) {
                $this->el($sezione, 'FirmaDichiarante', '1');
            }
        }
    }

    /**
     * Comune e provincia del decesso (stato estero e "EE" se all'estero).
     *
     * @return array{0: string, 1: string}
     */
    private function getLuogoDecesso(): array
    {
        $dichiarazione = $this->dati->dichiarazione;
        if ($dichiarazione->comuneDecesso !== null) {
            return [$dichiarazione->comuneDecesso->denominazione, (string) $dichiarazione->comuneDecesso->siglaProvincia];
        }
        return [(string) $dichiarazione->statoDecesso, 'EE'];
    }

    private function erede(\DOMElement $sezione, BeneficiarioSuccessione $erede): void
    {
        $elemento = $this->el($sezione, 'Eredi');
        $this->el($elemento, 'CodiceFiscale', $erede->soggetto->codiceFiscale);
        $this->el($elemento, 'GradoParentela', $erede->gradoParentela->value);
        if ($erede->soggetto->isPersonaFisica()) {
            $this->anagrafici($this->el($elemento, 'DatiAnagrafici'), $erede->soggetto);
        } else {
            $this->el($elemento, 'Denominazione', $this->testo($erede->soggetto->nominativo, 150));
        }
    }

    private function interdetto(\DOMElement $padre, BeneficiarioSuccessione $beneficiario, bool $primoModulo): void
    {
        $presenza = $this->el($padre, 'PresenzaInterdetti');
        if ($primoModulo) {
            $this->el($presenza, 'FlagInterdetti', '1');
        }
        $this->identificazione($presenza, $beneficiario);
        $this->el($presenza, 'Certificatore', $this->testo($beneficiario->certificatoreDisabilita, 35));
    }

    private function rinuncia(\DOMElement $padre, BeneficiarioSuccessione $beneficiario, bool $primoModulo): void
    {
        $presenza = $this->el($padre, 'PresenzaRinuncia');
        if ($primoModulo) {
            $this->el($presenza, 'FlagRinuncia', '1');
        }
        $this->identificazione($presenza, $beneficiario);
        $this->registrazione($this->el($presenza, 'RegistrazioneRinuncia'), $beneficiario->rinunciaUfficio, $beneficiario->rinunciaSerie, $beneficiario->rinunciaNumero, $beneficiario->rinunciaSottonumero, $beneficiario->rinunciaData);
    }

    /**
     * Sezione II: dichiarazioni del dichiarante per l'agevolazione prima
     * casa (gli altri beneficiari le allegano nel quadro EG).
     */
    private function primaCasa(\DOMElement $modulo): void
    {
        $dichiarante = $this->dati->getBeneficiarioDichiarante();
        if ($dichiarante === null) {
            return;
        }
        $immobili = ['P' => [], 'Z' => [], 'X' => []];
        foreach (array_merge($this->dati->getBeniQuadro(QuadroBene::fabbricati), $this->dati->getBeniQuadro(QuadroBene::fabbricatiTavolare)) as $bene) {
            foreach ($this->dati->getDevoluzioni($bene) as $devoluzione) {
                if ($devoluzione->beneficiarioSuccessione->id !== $dichiarante->id) {
                    continue;
                }
                $agevolazione = $devoluzione->agevolazione ?? ($dichiarante->gradoParentela === \Successioni\Application\Enumerations\GradoParentela::coniuge ? $bene->dirittoAbitazione?->getAgevolazione() : null);
                $chiave = match ($agevolazione) {
                    Agevolazione::primaCasa => 'P',
                    Agevolazione::primaCasaContiguo => 'Z',
                    Agevolazione::pertinenzaPrimaCasa, Agevolazione::pertinenzaSenzaPrincipale => 'X',
                    default => null,
                };
                if ($chiave !== null) {
                    $immobili[$chiave][$bene->id] = $bene;
                }
            }
        }
        if (count($immobili['P']) + count($immobili['Z']) + count($immobili['X']) === 0) {
            return;
        }
        $dichiarazione = $this->dati->dichiarazione;
        $sezione = $this->el($modulo, 'SezioneII_AgevPrimaCasa');
        foreach (['P' => 'ImmobilePrincipale', 'Z' => 'ImmobileContiguo'] as $chiave => $elemento) {
            if (count($immobili[$chiave]) > 0) {
                $this->immobilePrimaCasa($this->el($sezione, $elemento), reset($immobili[$chiave]));
            }
        }
        foreach (array_slice($immobili['X'], 0, 3) as $bene) {
            $this->immobilePrimaCasa($this->el($sezione, 'Pertinenza'), $bene);
        }
        $opzioni = $this->el($sezione, 'Opzioni');
        $flagOpzione = ['R' => 'FlagComuneResidenza', 'A' => 'FlagComuneAttivita', 'C' => 'FlagCambioResidenza', 'E' => 'FlagTrasferitoEstero', 'F' => 'FlagForzeArmate'][$dichiarazione->primaCasaOpzione ?? 'R'];
        $this->el($opzioni, $flagOpzione, '1');
        if ($flagOpzione === 'FlagTrasferitoEstero') {
            $this->flag($sezione, 'FlagTrasferimentoPrima', $dichiarazione->primaCasaTrasferimentoPrima);
        }
        $soloContigui = (count($immobili['P']) === 0) && (count($immobili['Z']) > 0);
        if ($soloContigui === false) {
            $this->flag($sezione, 'FlagNessunaAltraCasaComune', $dichiarazione->primaCasaNessunaAltraComune);
            $this->el($this->el($sezione, 'AltraCasa'), ($dichiarazione->primaCasaAltraCasa ?? 'N') === 'V' ? 'FlagVenditaAltraCasa' : 'FlagNessunaAltraCasa', '1');
        }
        $this->flag($sezione, 'FlagContigui', count($immobili['Z']) > 0);
        $this->el($sezione, 'FirmaDichiarante', '1');
    }

    private function immobilePrimaCasa(\DOMElement $elemento, BeneSuccessione $bene): void
    {
        $immobile = $bene->immobile;
        $luogo = $this->el($elemento, 'Luogo');
        $this->el($luogo, 'Provincia', $immobile->comune->siglaProvincia);
        $this->el($luogo, 'Comune', $this->testo($immobile->comune->denominazione, 50));
        $this->el($luogo, 'CodiceComune', $immobile->comune->codiceCatastale);
        $indirizzo = trim(($immobile->indirizzo ?? '') . ($immobile->numeroCivico !== null ? ' ' . $immobile->numeroCivico : ''));
        if ($indirizzo !== '') {
            $this->el($luogo, 'Indirizzo', $this->testo($indirizzo, 80));
        }
        if ($immobile->sezione !== null) {
            $this->el($elemento, 'SezioneUrbana', $this->testo($immobile->sezione, 3));
        }
        $this->datiCatastali($elemento, $bene, self::NS_SUC);
    }

    /**
     * Sezione III: credito d'imposta per il riacquisto della prima casa
     * (presente solo se il credito è usato nel rigo EF7).
     */
    private function creditoImposta(\DOMElement $modulo, RisultatoLiquidazione $risultato): void
    {
        $dichiarazione = $this->dati->dichiarazione;
        if (($risultato->ipotecariaCredito <= 0) || ($dichiarazione->creditoTipo === null)) {
            return;
        }
        $sezione = $this->el($modulo, 'SezioneIII_CreditoImposta');
        $this->el($sezione, 'TipologiaImposta', $dichiarazione->creditoTipo->value);
        $this->numero($sezione, 'Imposta', (int) $dichiarazione->creditoImporto);
        $this->registrazione($this->el($sezione, 'RegistrazioneAcquisto'), $dichiarazione->creditoAcquistoUfficio, $dichiarazione->creditoAcquistoSerie, $dichiarazione->creditoAcquistoNumero, $dichiarazione->creditoAcquistoSottonumero, $dichiarazione->creditoAcquistoData);
        if ($dichiarazione->creditoVenditaData !== null) {
            $this->registrazione($this->el($sezione, 'RegistrazioneVendita'), $dichiarazione->creditoVenditaUfficio, $dichiarazione->creditoVenditaSerie, $dichiarazione->creditoVenditaNumero, $dichiarazione->creditoVenditaSottonumero, $dichiarazione->creditoVenditaData);
        }
        $this->el($sezione, 'FirmaDichiarante', '1');
    }

    /**
     * Sezione IV: esenzioni e riduzioni richieste dal dichiarante.
     */
    private function altreAgevolazioni(\DOMElement $modulo): void
    {
        $dichiarante = $this->dati->getBeneficiarioDichiarante();
        if ($dichiarante === null) {
            return;
        }
        $codici = [];
        $riduzione = false;
        foreach ($this->dati->getBeni() as $bene) {
            foreach ($this->dati->getDevoluzioni($bene) as $devoluzione) {
                if ($devoluzione->beneficiarioSuccessione->id === $dichiarante->id) {
                    $codici[] = $devoluzione->agevolazione?->value;
                    $riduzione = $riduzione || ($devoluzione->riduzione !== null);
                }
            }
        }
        $esenzioni = array_intersect(['A', 'L'], $codici);
        $agevolazioni = array_intersect(['C', 'D', 'E', 'Q'], $codici);
        if ((count($esenzioni) === 0) && (count($agevolazioni) === 0) && ($riduzione === false)) {
            return;
        }
        $dichiarazione = $this->dati->dichiarazione;
        $sezione = $this->el($modulo, 'SezioneIV_AltreAgevRiduz');
        if (count($esenzioni) > 0) {
            $esenzione = $this->el($sezione, 'EsenzioneImpostaSuccessione');
            foreach ($esenzioni as $codice) {
                $tipo = $this->el($esenzione, 'AgevolazTipo' . $codice);
                $this->el($tipo, 'Flag' . $codice, '1');
                $this->el($tipo, 'NumProtocollo', $this->testo($dichiarazione->vincoloProtocollo, 30));
                $this->el($tipo, 'Data', $this->data($dichiarazione->vincoloData));
            }
            $this->el($esenzione, 'FirmaDichiarante', '1');
        }
        if (count($agevolazioni) > 0) {
            $elemento = $this->el($sezione, 'Agevolazioni');
            foreach ($agevolazioni as $codice) {
                $this->el($elemento, 'Flag' . $codice, '1');
            }
            $this->el($elemento, 'FirmaDichiarante', '1');
        }
        if ($riduzione) {
            $elemento = $this->el($sezione, 'Riduzioni');
            if ($dichiarazione->precedenteUfficio !== null) {
                $this->el($elemento, 'UfficioTerritoriale', $dichiarazione->precedenteUfficio);
                $estremi = $this->el($elemento, 'EstremiDichiarazionePrecedente');
                $this->el($estremi, 'Anno', (string) $dichiarazione->precedenteAnno);
                $this->el($estremi, 'Volume', $dichiarazione->precedenteVolume);
                $this->el($estremi, 'Numero', $dichiarazione->precedenteNumero);
            }
            $this->el($elemento, 'FirmaDichiarante', '1');
        }
    }

    // ---------------------------------------------------------------
    // Elementi comuni
    // ---------------------------------------------------------------

    private function anagrafici(\DOMElement $padre, Soggetto $soggetto): void
    {
        $this->el($padre, 'Cognome', $this->testo($soggetto->cognome, 80), self::NS_REG);
        $this->el($padre, 'Nome', $this->testo($soggetto->nome, 80), self::NS_REG);
        $this->el($padre, 'Sesso', $soggetto->sesso?->value, self::NS_REG);
        $this->el($padre, 'DataNascita', $this->data($soggetto->dataNascita), self::NS_REG);
        [$luogo, $provincia] = self::getLuogoNascita($soggetto);
        $this->el($padre, 'ComuneNascita', $this->testo($luogo, 50), self::NS_REG);
        $this->el($padre, 'ProvinciaNascita', $provincia, self::NS_REG);
    }

    /**
     * @return array{0: string, 1: string} comune (o stato estero) e sigla
     *         della provincia ("EE" per i nati all'estero)
     */
    public static function getLuogoNascita(Soggetto $soggetto): array
    {
        if ($soggetto->comuneNascita instanceof Comune) {
            return [$soggetto->comuneNascita->denominazione, (string) $soggetto->comuneNascita->siglaProvincia];
        }
        return [(string) $soggetto->nazioneNascita?->denominazione, 'EE'];
    }

    private function registrazione(\DOMElement $padre, ?UfficioRegistrazione $ufficio, ?string $serie, ?string $numero, ?string $sottonumero, ?SismaDate $data): void
    {
        $this->el($padre, 'UfficioDiRegistrazione', $ufficio?->codice, self::NS_REG);
        $this->el($padre, 'Serie', $serie, self::NS_REG);
        $this->el($padre, 'NumeroRegistrazione', $numero, self::NS_REG);
        if ($sottonumero !== null) {
            $this->el($padre, 'SottonumeroRegistrazione', $sottonumero, self::NS_REG);
        }
        $this->el($padre, 'DataRegistrazione', $this->data($data), self::NS_REG);
    }

    private function el(\DOMElement $padre, string $nome, ?string $valore = null, string $spazio = self::NS_SUC): \DOMElement
    {
        $prefisso = match ($spazio) {
            self::NS_REG => 'reg',
            self::NS_SC => 'sc',
            default => 'suc',
        };
        $elemento = $this->documento->createElementNS($spazio, $prefisso . ':' . $nome);
        if ($valore !== null) {
            $elemento->appendChild($this->documento->createTextNode($valore));
        }
        $padre->appendChild($elemento);
        return $elemento;
    }

    private function flag(\DOMElement $padre, string $nome, bool $valore): void
    {
        if ($valore) {
            $this->el($padre, $nome, '1');
        }
    }

    /**
     * Numero naturale positivo: gli zeri non si indicano.
     */
    private function numero(\DOMElement $padre, string $nome, int $valore, string $spazio = self::NS_SUC): void
    {
        if ($valore > 0) {
            $this->el($padre, $nome, (string) $valore, $spazio);
        }
    }

    private function importo(float $valore): string
    {
        return number_format($valore, 2, ',', '');
    }

    private function data(?\DateTimeInterface $data): ?string
    {
        return $data?->format('dmY');
    }

    /**
     * Testo nel formato delle specifiche (DatoAN): maiuscole, accentate
     * maiuscole ammesse (À È É Ì Ò Ù Ü), altri caratteri traslitterati.
     */
    public static function testo(?string $testo, int $lunghezza): string
    {
        $testo = mb_strtoupper(trim((string) $testo));
        $testo = strtr($testo, ['Á' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Ã' => 'A', 'Ê' => 'E', 'Ë' => 'E', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ó' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O',
            'Ú' => 'U', 'Û' => 'U', 'Ç' => 'C', 'Ñ' => 'N', 'Š' => 'S', 'Ž' => 'Z', 'Č' => 'C', 'Ć' => 'C', 'ß' => 'SS', '’' => "'", '‘' => "'", '“' => '"', '”' => '"', '–' => '-', '—' => '-']);
        $testo = preg_replace('/[^0-9A-Z&\'\-.,\s\/°^()ÀÈÉÌÒÙÜ"\\\\|+]/u', ' ', $testo);
        $testo = preg_replace('/\s+/', ' ', $testo);
        $testo = preg_replace('/^[^0-9A-Z\-"]+/u', '', $testo);
        return mb_substr(trim($testo), 0, $lunghezza);
    }
}
