<?php

namespace Successioni\Application\Services;

use Anagrafica\Application\Enumerations\TitoloPossesso;
use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Entities\DevoluzioneSuccessione;
use Successioni\Application\Enumerations\Agevolazione;
use Successioni\Application\Enumerations\CaricaDichiarante;
use Successioni\Application\Enumerations\CodiceDiritto;
use Successioni\Application\Enumerations\GradoParentela;
use Successioni\Application\Enumerations\QuadroBene;
use Successioni\Application\Enumerations\TipoAllegato;
use Successioni\Application\Enumerations\TipoBeneficiario;
use Successioni\Application\Enumerations\TipoCespite;
use Successioni\Application\Enumerations\TipoDichiarazioneSuccessione;
use Successioni\Application\Enumerations\TempisticaPagamento;
use Successioni\Application\Enumerations\TipoPassivita;
use Successioni\Application\Models\ComuneCatastaleTavolareModel;

/**
 * Controlli della dichiarazione prima della trasmissione, dalle regole
 * delle specifiche tecniche SUC13 (documentazione degli elementi XSD) che
 * dipendono dai dati inseriti. Il file va comunque controllato con il
 * software dell'Agenzia, che applica anche i controlli sull'Anagrafe
 * tributaria.
 */
class ControlloDichiarazione
{

    public const DATA_MINIMA_DECESSO = '2006-10-03';
    public const TOLLERANZA_PERCENTUALE = 0.01;
    public const TOLLERANZA_MASSIMA = 100.0;

    /** @var string[] */
    private array $errori = [];

    /**
     * @return string[] errori che impediscono la generazione del file
     */
    public function verifica(DatiDichiarazione $dati, RisultatoLiquidazione $risultato): array
    {
        $this->errori = [];
        $this->verificaFrontespizio($dati);
        if ($dati->dichiarazione->tipoDichiarazione->hasQuadri()) {
            $this->verificaBeneficiari($dati);
            $this->verificaBeni($dati);
            $this->verificaPassivita($dati);
            $this->verificaPagamento($dati, $risultato);
        }
        $this->verificaAllegati($dati, $risultato);
        return $this->errori;
    }

    private function errore(string $testo): void
    {
        $this->errori[] = $testo;
    }

    private function verificaFrontespizio(DatiDichiarazione $dati): void
    {
        $dichiarazione = $dati->dichiarazione;
        $defunto = $dichiarazione->defunto;
        if ($defunto->dataDecesso === null) {
            $this->errore('Indicare la data di decesso del defunto in Anagrafica.');
            return;
        }
        if ($defunto->dataDecesso->format('Y-m-d') < self::DATA_MINIMA_DECESSO) {
            $this->errore('Le successioni aperte prima del 3 ottobre 2006 non si presentano con questo modello.');
        }
        if (($defunto->cognome === null) || ($defunto->sesso === null) || ($defunto->dataNascita === null) || (($defunto->comuneNascita === null) && ($defunto->nazioneNascita === null))) {
            $this->errore('Completare in Anagrafica i dati anagrafici del defunto.');
        }
        if (($dichiarazione->comuneDecesso === null) && ($dichiarazione->statoDecesso === null)) {
            $this->errore('Indicare il comune (o lo stato estero) del decesso.');
        }
        if ($dichiarazione->statoCivile === null) {
            $this->errore('Indicare lo stato civile del defunto.');
        }
        if ($dichiarazione->tipoDichiarazione->isSostitutiva() && (($dichiarazione->annoPrima === null) || ($dichiarazione->volumePrima === null) || ($dichiarazione->numeroPrima === null))) {
            $this->errore('Indicare gli estremi della prima dichiarazione (anno, volume e numero).');
        }
        if (($dichiarazione->devoluzioneLegge === false) && ($dichiarazione->devoluzioneTestamento === false)) {
            $this->errore('Indicare la devoluzione per legge, per testamento o entrambe.');
        }
        if ($dichiarazione->devoluzioneTestamento && ($dichiarazione->testamentoEstero === false)) {
            if (($dichiarazione->testamentoPubblicoUfficiale === null) || ($dichiarazione->testamentoDataPubblicazione === null)) {
                $this->errore('Indicare il pubblico ufficiale e la data di pubblicazione del testamento.');
            }
            if (($dichiarazione->testamentoUfficio === null) || ($dichiarazione->testamentoSerie === null) || ($dichiarazione->testamentoNumero === null) || ($dichiarazione->testamentoDataRegistrazione === null)) {
                $this->errore('Indicare gli estremi di registrazione del testamento (ufficio, serie, numero e data).');
            }
            if (($dichiarazione->testamentoDataPubblicazione !== null) && ($dichiarazione->testamentoDataPubblicazione < $defunto->dataDecesso)) {
                $this->errore('La data di pubblicazione del testamento non può precedere il decesso.');
            }
        }
        $presentatore = $dichiarazione->presentatore;
        if (($presentatore === null) || ($dichiarazione->codiceCarica === null)) {
            $this->errore('Indicare chi presenta la dichiarazione e il codice di carica.');
        } else {
            if ($presentatore->id === $defunto->id) {
                $this->errore('Il dichiarante non può essere il defunto.');
            }
            if ($presentatore->isPersonaFisica() === false) {
                $this->errore('Il dichiarante deve essere una persona fisica.');
            } elseif (($presentatore->dataNascita !== null) && ((new \DateTimeImmutable($presentatore->dataNascita->format('Y-m-d')))->diff(new \DateTimeImmutable())->y < 18)) {
                $this->errore('Il dichiarante deve essere maggiorenne.');
            }
            if ($dichiarazione->codiceCarica->richiedeRappresentato() && ($dichiarazione->rappresentato === null)) {
                $this->errore('Indicare il soggetto rappresentato dal dichiarante.');
            }
            if ($dichiarazione->codiceCarica->isBeneficiario() && ($dati->getBeneficiarioDichiarante() === null)) {
                $this->errore('Il dichiarante (erede o legatario) deve essere tra i beneficiari del quadro EA.');
            }
            if (($dichiarazione->codiceCarica === CaricaDichiarante::esecutoreTestamentario) && ($dichiarazione->devoluzioneTestamento === false)) {
                $this->errore('L\'esecutore testamentario presuppone la devoluzione per testamento.');
            }
            $this->verificaCariche($dati);
        }
        if (($dichiarazione->telefono === null) && ($dichiarazione->email === null)) {
            $this->errore('Indicare il telefono o l\'indirizzo e-mail del dichiarante.');
        }
        if (($dichiarazione->intermediario !== null) && (($dichiarazione->impegno === null) || ($dichiarazione->dataImpegno === null))) {
            $this->errore('Indicare l\'impegno a trasmettere dell\'intermediario e la sua data.');
        }
    }

    /**
     * Eredità giacente (carica 5): nel quadro EA solo la curatela, con tipo
     * soggetto 6; trust testamentario (carica 9): righi di tipo 5 intestati
     * al trustee, uno per beneficiario finale. Per le eredità giacenti e
     * amministrate le agevolazioni sono limitate ad A, C, D, E, M, N, L.
     */
    private function verificaCariche(DatiDichiarazione $dati): void
    {
        $dichiarazione = $dati->dichiarazione;
        $carica = $dichiarazione->codiceCarica;
        $righiTrust = array_filter($dati->beneficiari, fn(BeneficiarioSuccessione $beneficiario) => $beneficiario->isTrust());
        if (($carica === CaricaDichiarante::curatoreEreditaGiacente) && ($dichiarazione->tipoDichiarazione === TipoDichiarazioneSuccessione::prima)
                && ((count($dati->beneficiari) !== 1) || ($dati->beneficiari[0]->tipoBeneficiario !== TipoBeneficiario::altro))) {
            $this->errore('Eredità giacente: nel quadro EA va indicata solo la curatela, con tipo soggetto 6 (altro).');
        }
        if (($carica === CaricaDichiarante::curatoreEreditaGiacente) && ($dati->getBeneficiarioDichiarante() !== null)) {
            $this->errore('Il curatore dell\'eredità giacente non deve essere nel quadro EA.');
        }
        if (in_array($carica, [CaricaDichiarante::curatoreEreditaGiacente, CaricaDichiarante::amministratoreEredita], true)) {
            foreach ($dati->getBeni() as $bene) {
                foreach ($dati->getDevoluzioni($bene) as $devoluzione) {
                    if (($devoluzione->agevolazione !== null) && (in_array($devoluzione->agevolazione->value, ['A', 'C', 'D', 'E', 'M', 'N', 'L'], true) === false)) {
                        $this->errore('Eredità giacente o amministrata: sono ammesse solo le agevolazioni A, C, D, E, M, N, L (' . $bene->getDescrizioneBreve() . ').');
                        break 2;
                    }
                }
            }
        }
        foreach ($dati->getBeni() as $bene) {
            foreach ($dati->getDevoluzioni($bene) as $devoluzione) {
                if ($devoluzione->beneficiarioSuccessione->isTrust() && (($devoluzione->agevolazione !== null) || ($devoluzione->riduzione !== null) || ($bene->dirittoAbitazione !== null))) {
                    $this->errore('Trust: sui beni conferiti nel trust non si indicano agevolazioni, riduzioni o diritto di abitazione (' . $bene->getDescrizioneBreve() . ').');
                    break 2;
                }
            }
        }
        if ((count($righiTrust) > 0) && ($carica !== CaricaDichiarante::trustee)) {
            $this->errore('Con un trust nel quadro EA il codice di carica è 9 (trustee).');
        }
        if ($carica === CaricaDichiarante::trustee) {
            if ($dichiarazione->devoluzioneTestamento === false) {
                $this->errore('Il trust si dichiara con questo modello solo se istituito per testamento.');
            }
            if ((count($righiTrust) === 0) || (count($righiTrust) !== count($dati->beneficiari))) {
                $this->errore('Trust: tutti i righi del quadro EA sono di tipo 5 (trust); se non tutti i beni sono conferiti nel trust si usa il modello 4 cartaceo.');
            }
            $finali = [];
            foreach ($righiTrust as $rigo) {
                if ($rigo->soggetto->id !== $dichiarazione->presentatore?->id) {
                    $this->errore('Trust: i righi del quadro EA sono intestati al trustee che presenta la dichiarazione.');
                    break;
                }
                if ($rigo->beneficiarioFinale !== null) {
                    if (isset($finali[$rigo->beneficiarioFinale->id])) {
                        $this->errore('Trust: lo stesso beneficiario finale è indicato su più righi.');
                    }
                    $finali[$rigo->beneficiarioFinale->id] = true;
                }
            }
        } elseif ($dichiarazione->pagamentoAnticipatoTrust) {
            $this->errore('Il pagamento anticipato si sceglie solo per il trust (carica 9).');
        }
    }

    private function verificaBeneficiari(DatiDichiarazione $dati): void
    {
        $dichiarazione = $dati->dichiarazione;
        if (count($dati->beneficiari) === 0) {
            $this->errore('Inserire gli eredi e i legatari (quadro EA).');
            return;
        }
        $soloLegge = $dichiarazione->devoluzioneLegge && ($dichiarazione->devoluzioneTestamento === false) && ($dichiarazione->leggeEstera === false);
        $coniugi = 0;
        foreach ($dati->beneficiari as $beneficiario) {
            $nome = $beneficiario->getRiferimento() . ' ' . $beneficiario->soggetto->nominativo;
            $grado = $beneficiario->gradoParentela;
            if ($beneficiario->soggetto->id === $dichiarazione->defunto->id) {
                $this->errore($nome . ': il defunto non può essere beneficiario.');
            }
            if ($grado === GradoParentela::coniuge) {
                $coniugi++;
                if (($dichiarazione->statoCivile !== null) && ($dichiarazione->statoCivile->ammetteConiuge() === false)) {
                    $this->errore($nome . ': il coniuge è ammesso solo con stato civile coniugato, separato o unito civilmente.');
                }
            }
            if (($beneficiario->tipoBeneficiario === TipoBeneficiario::legatario) && ($dichiarazione->devoluzioneTestamento === false) && ($dichiarazione->leggeEstera === false)) {
                $this->errore($nome . ': il legatario presuppone la devoluzione per testamento.');
            }
            if (($beneficiario->tipoBeneficiario === TipoBeneficiario::coniugeRinunciatario) && (($grado !== GradoParentela::coniuge) || ($beneficiario->rinuncia === false))) {
                $this->errore($nome . ': il coniuge rinunciatario ha grado 01 e la rinuncia indicata.');
            }
            if ($soloLegge && ($grado->isAmmessoPerLegge() === false)) {
                $this->errore($nome . ': nella successione per legge non sono ammessi affini, estranei ed enti diversi dallo Stato.');
            }
            if ($beneficiario->soggetto->isPersonaFisica() === false) {
                if (in_array($grado, [GradoParentela::estraneo, GradoParentela::stato, GradoParentela::entiTerritorialiTerzoSettore, GradoParentela::entiFondazioniArt3c1, GradoParentela::entiFondazioniArt3c2], true) === false) {
                    $this->errore($nome . ': per società ed enti il grado di parentela è 35 (estraneo) o 36-39 (Stato ed enti).');
                }
            } else {
                $this->verificaEta($beneficiario, $nome);
                if ($grado->isEnte()) {
                    $this->errore($nome . ': i gradi 36-39 sono riservati allo Stato e agli enti.');
                }
            }
            if ($beneficiario->disabile && ($beneficiario->certificatoreDisabilita === null)) {
                $this->errore($nome . ': indicare chi ha certificato la disabilità (quadro EH).');
            }
            if ($beneficiario->rinuncia && (($beneficiario->rinunciaUfficio === null) || ($beneficiario->rinunciaSerie === null) || ($beneficiario->rinunciaNumero === null) || ($beneficiario->rinunciaData === null))) {
                $this->errore($nome . ': indicare gli estremi di registrazione della rinuncia (quadro EH).');
            }
            if (($beneficiario->rappresentato !== null) && ($beneficiario->gradoParentelaRappresentato === null)) {
                $this->errore($nome . ': indicare il grado di parentela del soggetto rappresentato.');
            }
        }
        if ($coniugi > 1) {
            $this->errore('È ammesso un solo beneficiario con grado di parentela 01 (coniuge).');
        }
    }

    private function verificaEta($beneficiario, string $nome): void
    {
        $nascita = $beneficiario->soggetto->dataNascita;
        $nascitaDefunto = $beneficiario->dichiarazioneSuccessione->defunto->dataNascita;
        if (($nascita === null) || ($nascitaDefunto === null)) {
            return;
        }
        if ($beneficiario->gradoParentela->isDiscendente() && ($nascita < $nascitaDefunto)) {
            $this->errore($nome . ': un discendente non può essere più anziano del defunto.');
        }
        if ($beneficiario->gradoParentela->isAscendente() && ($nascita > $nascitaDefunto)) {
            $this->errore($nome . ': un ascendente deve essere più anziano del defunto.');
        }
    }

    private function verificaBeni(DatiDichiarazione $dati): void
    {
        $dichiarazione = $dati->dichiarazione;
        $soloLegge = $dichiarazione->devoluzioneLegge && ($dichiarazione->devoluzioneTestamento === false) && ($dichiarazione->leggeEstera === false);
        if (count($dati->getBeni()) === 0) {
            $this->errore('Inserire i beni dell\'attivo ereditario.');
        }
        $primaCasa = [];
        foreach ($dati->getBeni() as $bene) {
            $nome = $bene->quadro->value . ' n. ' . $dati->getNumero($bene) . ' (' . $bene->getDescrizioneBreve() . ')';
            if (in_array($bene->codiceDiritto, $bene->quadro->getDirittiDefunto(), true) === false) {
                $this->errore($nome . ': codice diritto del defunto non ammesso nel quadro.');
            }
            if ($bene->quadro->isImmobile()) {
                $this->verificaImmobile($dati, $bene, $nome);
            } else {
                $this->verificaBeneMobile($bene, $nome);
            }
            if ($bene->getValoreTotale() <= 0 && ($bene->tipoCespite !== TipoCespite::crediti) && (($dati->getCatasto($bene)?->categoria?->value ?? '') !== 'D/10')) {
                $this->errore($nome . ': indicare il valore.');
            }
            if (($bene->quadro === QuadroBene::titoli) && ($bene->tipoTitolo !== null) && ($bene->tipoTitolo->isEsente() !== ($bene->valoreEsente !== null))) {
                $this->errore($nome . ': per i titoli esenti si indica solo il valore esente, per gli altri solo il valore.');
            }
            $devoluzioni = $dati->getDevoluzioni($bene);
            if (count($devoluzioni) === 0) {
                $this->errore($nome . ': indicare la devoluzione ai beneficiari.');
                continue;
            }
            $this->verificaDevoluzioni($bene, $devoluzioni, $nome, $soloLegge, $primaCasa);
            $agevolazioniAziendali = array_filter($devoluzioni, fn(DevoluzioneSuccessione $devoluzione) => in_array($devoluzione->agevolazione, [Agevolazione::giovaniAgricoltori, Agevolazione::aziendeMontane], true));
            if ($bene->quadro->isImmobile() && (count($agevolazioniAziendali) > 0) && ($bene->isAziendale() === false)) {
                $this->errore($nome . ': le agevolazioni G e Q sugli immobili richiedono il bene aziendale (azienda del quadro EN).');
            }
            if ($bene->isAziendale() && (count(array_filter($devoluzioni, fn(DevoluzioneSuccessione $devoluzione) => ($devoluzione->agevolazione !== null) && (in_array($devoluzione->agevolazione, [Agevolazione::aziendeQuote, Agevolazione::giovaniAgricoltori, Agevolazione::aziendeMontane], true) === false))) > 0)) {
                $this->errore($nome . ': per i beni aziendali sono ammesse solo le agevolazioni D, G e Q.');
            }
        }
        foreach ($primaCasa as $id => $codici) {
            if (count(array_keys($codici, Agevolazione::primaCasa->value)) > 1) {
                $this->errore('L\'agevolazione prima casa (P) è ammessa una sola volta per beneficiario.');
            }
            if (in_array(Agevolazione::primaCasaContiguo->value, $codici, true) && (in_array(Agevolazione::primaCasa->value, $codici, true) === false)) {
                $this->errore('L\'agevolazione Z (immobile contiguo) richiede la prima casa (P) dello stesso beneficiario.');
            }
            if (in_array(Agevolazione::pertinenzaPrimaCasa->value, $codici, true) && (in_array(Agevolazione::primaCasa->value, $codici, true) === false)) {
                $this->errore('L\'agevolazione X (pertinenza) richiede la prima casa (P) dello stesso beneficiario; altrimenti usare Y.');
            }
            if (in_array(Agevolazione::pertinenzaSenzaPrincipale->value, $codici, true) && in_array(Agevolazione::primaCasa->value, $codici, true)) {
                $this->errore('L\'agevolazione Y non è ammessa insieme alla prima casa (P) dello stesso beneficiario: usare X.');
            }
        }
    }

    /**
     * Immobili all'estero (provincia "EE"): Stato, codice e indirizzo; niente
     * agevolazioni prima casa (P, Z, X, Y) né diritto di abitazione del
     * coniuge diverso da 4 e 8.
     */
    private function verificaImmobileEstero(DatiDichiarazione $dati, BeneSuccessione $bene, string $nome): void
    {
        if (($bene->statoEstero === null) || ($bene->codiceStatoEstero === null) || ($bene->indirizzoEstero === null)) {
            $this->errore($nome . ': per l\'immobile all\'estero indicare Stato, codice dello Stato e indirizzo.');
        }
        if ($bene->quadro->isTerreno() && ($bene->tipologiaTerreno === null)) {
            $this->errore($nome . ': indicare la tipologia del terreno.');
        }
        foreach ($dati->getDevoluzioni($bene) as $devoluzione) {
            if (in_array($devoluzione->agevolazione, [Agevolazione::primaCasa, Agevolazione::primaCasaContiguo, Agevolazione::pertinenzaPrimaCasa, Agevolazione::pertinenzaSenzaPrincipale], true)) {
                $this->errore($nome . ': le agevolazioni prima casa non si applicano agli immobili all\'estero.');
                break;
            }
        }
        if (($bene->dirittoAbitazione !== null) && (in_array($bene->dirittoAbitazione->value, ['4', '8'], true) === false)) {
            $this->errore($nome . ': per l\'immobile all\'estero il diritto di abitazione del coniuge può essere solo 4 o 8.');
        }
    }

    private function verificaImmobile(DatiDichiarazione $dati, BeneSuccessione $bene, string $nome): void
    {
        if ($bene->isImmobileEstero()) {
            $this->verificaImmobileEstero($dati, $bene, $nome);
            return;
        }
        if ($bene->immobile === null) {
            $this->errore($nome . ': collegare l\'immobile dell\'Anagrafica.');
            return;
        }
        $catasto = $dati->getCatasto($bene);
        if ($catasto === null) {
            $this->errore($nome . ': mancano in Anagrafica i dati catastali in vigore alla data del decesso.');
        } elseif ($bene->quadro->isFabbricato() && (($catasto->categoria === null) || (($catasto->rendita === null) && $catasto->categoria->haRendita()))) {
            $this->errore($nome . ': categoria e rendita catastale mancanti in Anagrafica.');
        }
        if ($bene->quadro->isTerreno() && ($bene->tipologiaTerreno === null)) {
            $this->errore($nome . ': indicare la tipologia del terreno.');
        }
        if ($bene->quadro->isTavolare() !== $bene->immobile->isTavolare()) {
            $this->errore($nome . ($bene->immobile->isTavolare() ? ': immobile del catasto tavolare, va nei quadri EL o EM (eliminalo e aggiungilo di nuovo dall\'Anagrafica).' : ': immobile del catasto ordinario, va nei quadri EB o EC.'));
        }
        if ($bene->quadro->isTavolare()) {
            if (($bene->quadro === QuadroBene::terreniTavolare) && ($bene->immobile->partitaTavolare === null)) {
                $this->errore($nome . ': indicare in Anagrafica la partita tavolare dell\'immobile.');
            }
            if (in_array($bene->immobile->comune->siglaProvincia, ['TN', 'BZ'], true) && ((new ComuneCatastaleTavolareModel())->trovaPerNome($bene->immobile->comuneCatastale) === null)) {
                $this->errore($nome . ': il comune catastale indicato in Anagrafica non è tra quelli di Trento e Bolzano delle specifiche.');
            }
        }
        if ($bene->discordanzaIntestatario && ($bene->quadro->isTavolare() === false) && (count($dati->getEstremi($bene)) === 0)) {
            $this->errore($nome . ': per la discordanza dei dati dell\'intestatario indicare gli estremi degli atti di provenienza (quadro EI).');
        }
        if ($bene->isAziendale() && ($bene->azienda->quadro !== QuadroBene::aziende)) {
            $this->errore($nome . ': il bene aziendale va collegato a un\'azienda del quadro EN.');
        }
        if (($bene->dirittoAbitazione !== null) && $bene->isAziendale()) {
            $this->errore($nome . ': il diritto di abitazione non è ammesso sui beni aziendali.');
        }
        if ($bene->dirittoAbitazione !== null) {
            $coniuge = array_filter($dati->beneficiari, fn($beneficiario) => $beneficiario->gradoParentela === GradoParentela::coniuge);
            if (count($coniuge) === 0) {
                $this->errore($nome . ': il diritto di abitazione richiede il coniuge tra i beneficiari.');
            }
            if (in_array($bene->codiceDiritto, [CodiceDiritto::proprieta, CodiceDiritto::proprietaSuperficiaria], true) === false) {
                $this->errore($nome . ': il diritto di abitazione richiede la proprietà del defunto.');
            }
        }
    }

    /**
     * Dati identificativi dei beni dei quadri EN, EO, EP, EQ, ER.
     */
    private function verificaBeneMobile(BeneSuccessione $bene, string $nome): void
    {
        $mancanti = match ($bene->quadro) {
            QuadroBene::aziende => ($bene->descrizione === null) || (($bene->codiceFiscaleEmittente === null) && ($bene->beneEstero === false)),
            QuadroBene::titoli => ($bene->descrizione === null) || ($bene->tipoTitolo === null),
            QuadroBene::aeromobili => in_array(null, [$bene->marcaModello, $bene->annoCostruzione, $bene->nazionalita, $bene->annoIscrizione, $bene->numeroIscrizione], true),
            QuadroBene::navi => in_array(null, [$bene->tipoUnita, $bene->nazionalita, $bene->ufficioIscrizione, $bene->annoIscrizione, $bene->numeroIscrizione, $bene->annoCostruzione, $bene->dimensione], true),
            default => ($bene->descrizione === null) || ($bene->tipoCespite === null),
        };
        if ($mancanti) {
            $this->errore($nome . ': completare i dati identificativi del bene.');
        }
        if (in_array($bene->quadro, [QuadroBene::aeromobili, QuadroBene::navi], true) && ($bene->annoCostruzione !== null) && ($bene->annoIscrizione !== null) && ($bene->annoIscrizione < $bene->annoCostruzione)) {
            $this->errore($nome . ': l\'anno di immatricolazione o iscrizione non può precedere quello di costruzione.');
        }
        if (($bene->quadro === QuadroBene::aziende) && ($bene->dataDeposito !== null) && ($bene->cameraCommercio === null)) {
            $this->errore($nome . ': indicare la camera di commercio del deposito.');
        }
    }

    /**
     * @param DevoluzioneSuccessione[] $devoluzioni
     */
    private function verificaDevoluzioni(BeneSuccessione $bene, array $devoluzioni, string $nome, bool $soloLegge, array &$primaCasa): void
    {
        $dominio = $godimento = 0.0;
        $soloEredi = true;
        foreach ($devoluzioni as $devoluzione) {
            $beneficiario = $devoluzione->beneficiarioSuccessione;
            $soloEredi = $soloEredi && ($beneficiario->tipoBeneficiario !== TipoBeneficiario::legatario);
            if ($beneficiario->isDestinatario() === false) {
                $this->errore($nome . ': ' . $beneficiario->soggetto->nominativo . ' ha rinunciato e non può ricevere beni.');
            }
            if (in_array($devoluzione->codiceDiritto, $bene->quadro->getDirittiDevoluzione(), true) === false) {
                $this->errore($nome . ': diritto ' . $devoluzione->codiceDiritto->value . ' non ammesso nella devoluzione.');
            }
            if (($devoluzione->agevolazione !== null) && (in_array($devoluzione->agevolazione, $bene->quadro->getAgevolazioni(), true) === false)) {
                $this->errore($nome . ': agevolazione ' . $devoluzione->agevolazione->value . ' non ammessa nel quadro.');
            }
            if (($devoluzione->agevolazione === Agevolazione::boschiMontani) && ($beneficiario->gradoParentela->isLineaRetta() === false)) {
                $this->errore($nome . ': l\'agevolazione N spetta solo al coniuge e ai parenti in linea retta.');
            }
            if (($devoluzione->agevolazione === Agevolazione::aziendeQuote) && (in_array($beneficiario->gradoParentela, [GradoParentela::coniuge, GradoParentela::figlio, GradoParentela::nipoteLineaRetta, GradoParentela::pronipoteLineaRetta, GradoParentela::figlioPronipote], true) === false)) {
                $this->errore($nome . ': l\'agevolazione D spetta solo al coniuge e ai discendenti.');
            }
            if (($devoluzione->agevolazione !== null) && $devoluzione->agevolazione->isPrimaCasa()) {
                $primaCasa[$beneficiario->id][] = $devoluzione->agevolazione->value;
            }
            if (($devoluzione->riduzione !== null) && ($bene->valorePrecedente === null)) {
                $this->errore($nome . ': la riduzione per successioni precedenti richiede il valore dei beni nella successione precedente.');
            }
            if ($soloLegge && $soloEredi && ($devoluzione->codiceDiritto !== $bene->codiceDiritto)) {
                $this->errore($nome . ': nella successione per legge il diritto devoluto coincide con quello del defunto.');
            }
            $quota = $devoluzione->getQuota();
            $titolo = $devoluzione->codiceDiritto->getTitoloPossesso();
            if (in_array($titolo, [TitoloPossesso::uso, TitoloPossesso::abitazione], true)) {
                continue;
            }
            if ($titolo === TitoloPossesso::usufrutto) {
                $godimento += $quota;
            } elseif ($titolo === TitoloPossesso::nudaProprieta) {
                $dominio += $quota;
            } else {
                $dominio += $quota;
                $godimento += $quota;
            }
        }
        $valori = array_sum(array_map(fn(DevoluzioneSuccessione $devoluzione) => (float) $devoluzione->valoreQuota, $devoluzioni));
        if (abs($dominio - 1) > 0.0001 || (($godimento > 0) && (abs($godimento - 1) > 0.0001))) {
            $this->errore($nome . ': la somma delle quote devolute deve essere pari all\'intero (1/1) per ogni diritto.');
        }
        $tolleranza = min(self::TOLLERANZA_MASSIMA, $bene->getValoreTotale() * self::TOLLERANZA_PERCENTUALE);
        if (($bene->getValoreTotale() > 0) && (abs($valori - $bene->getValoreTotale()) > $tolleranza)) {
            $this->errore(sprintf('%s: la somma dei valori delle quote (%s) deve corrispondere al valore del bene (%s).', $nome, number_format($valori, 2, ',', '.'), number_format($bene->getValoreTotale(), 0, ',', '.')));
        }
    }

    private function verificaPassivita(DatiDichiarazione $dati): void
    {
        foreach ($dati->passivita as $numero => $passivita) {
            $nome = 'Passività n. ' . ($numero + 1);
            if (($passivita->tipoPassivita === TipoPassivita::speseFunerarie) && ($passivita->valore > TipoPassivita::MASSIMO_SPESE_FUNERARIE)) {
                $this->errore($nome . ': le spese funerarie sono deducibili fino a 1.033 euro.');
            }
            if ($passivita->tipoPassivita->richiedeCespite() && ($passivita->beneSuccessione === null)) {
                $this->errore($nome . ': collegare il cespite cui si riferisce.');
            }
            if (($passivita->tipoPassivita === TipoPassivita::mutui) && ($passivita->beneSuccessione !== null) && ($passivita->beneSuccessione->quadro->isImmobile() === false)) {
                $this->errore($nome . ': il mutuo va collegato a un immobile.');
            }
            $ripartizioni = $dati->getRipartizioni($passivita);
            if (count($ripartizioni) === 0) {
                $this->errore($nome . ': indicare la ripartizione tra i beneficiari.');
                continue;
            }
            $quote = array_sum(array_map(fn($ripartizione) => $ripartizione->getQuota(), $ripartizioni));
            if (abs($quote - 1) > 0.0001) {
                $this->errore($nome . ': la somma delle quote ripartite deve essere pari all\'intero (1/1).');
            }
        }
    }

    private function verificaPagamento(DatiDichiarazione $dati, RisultatoLiquidazione $risultato): void
    {
        $dichiarazione = $dati->dichiarazione;
        if (($risultato->getImportoDaVersare() > 0) && ($dichiarazione->contoAddebito === null)) {
            $this->errore('Indicare il conto corrente di addebito delle imposte (IBAN del dichiarante o dell\'intermediario).');
        }
        if (($dichiarazione->contoAddebito !== null) && ($dichiarazione->contoAddebito->isItaliano() === false)) {
            $this->errore('Il conto di addebito deve essere un conto italiano.');
        }
        if ($dichiarazione->contoAddebito !== null) {
            $ammessi = array_filter([$dichiarazione->presentatore?->codiceFiscale, $dichiarazione->rappresentato?->codiceFiscale, $dichiarazione->intermediario?->soggetto->codiceFiscale]);
            if (in_array($dichiarazione->contoAddebito->soggetto->codiceFiscale, $ammessi, true) === false) {
                $this->errore('Il conto di addebito deve essere intestato al dichiarante, al rappresentato o all\'intermediario.');
            }
        }
        if ($risultato->successioneDaVersare === 0) {
            return;
        }
        if ($dichiarazione->tempisticaPagamento === null) {
            $this->errore('Indicare la tempistica di pagamento dell\'imposta di successione (rigo EF18ter).');
        }
        if ((Ravvedimento::giorni($dichiarazione->getScadenzaPagamento(), Ravvedimento::getDataPresentazione($dati)) > 0) && ($dichiarazione->tempisticaPagamento === TempisticaPagamento::nonContestuale)) {
            $this->errore('Dichiarazione presentata oltre i 90 giorni dal termine: l\'imposta di successione si paga con la dichiarazione (tempistica 2, istruzioni del rigo EF18ter).');
        }
        if ($dichiarazione->numeroRate !== null) {
            $acconto = (int) $dichiarazione->acconto;
            $residuo = $risultato->successioneDaVersare - $acconto;
            if (($acconto < ceil($risultato->successioneDaVersare * PianoVersamenti::ACCONTO_MINIMO)) || ($acconto > $risultato->successioneDaVersare)) {
                $this->errore('L\'acconto deve essere almeno il 20% dell\'imposta da versare e non superarla.');
            }
            if ($residuo < PianoVersamenti::MINIMO_RATEIZZABILE) {
                $this->errore('La rateazione non è ammessa per importi da rateizzare inferiori a 1.000 euro.');
            }
            $massimo = $residuo > PianoVersamenti::SOGLIA_DODICI_RATE ? 12 : 8;
            if (($dichiarazione->numeroRate < 1) || ($dichiarazione->numeroRate > $massimo)) {
                $this->errore(sprintf('Il numero di rate deve essere compreso tra 1 e %d.', $massimo));
            }
        }
    }

    private function verificaAllegati(DatiDichiarazione $dati, RisultatoLiquidazione $risultato): void
    {
        $dichiarazione = $dati->dichiarazione;
        $presenti = array_unique(array_map(fn($allegato) => $allegato->tipoAllegato->value, $dati->allegati));
        $richiesti = [];
        if ($dichiarazione->tipoDichiarazione->hasQuadri()) {
            $richiesti[TipoAllegato::alberoGenealogico->value] = 'il prospetto dell\'albero genealogico';
        }
        if ($dichiarazione->devoluzioneTestamento) {
            $richiesti[TipoAllegato::testamento->value] = 'la copia del testamento';
        }
        if (count($dati->passivita) > 0) {
            $richiesti[TipoAllegato::documentiPassivita->value] = 'i documenti delle passività';
        }
        if (in_array($dichiarazione->codiceCarica, [CaricaDichiarante::curatoreEreditaGiacente, CaricaDichiarante::amministratoreEredita, CaricaDichiarante::esecutoreTestamentario], true)) {
            $richiesti[TipoAllegato::dichiarazioniSostitutive->value] = 'le dichiarazioni sostitutive';
            $richiesti[TipoAllegato::documentiIdentita->value] = 'i documenti d\'identità';
        }
        foreach ($dati->getBeni() as $bene) {
            if ($bene->beneEstero && ($bene->impostaEstero !== null)) {
                $richiesti[TipoAllegato::certificazioneImpostaEstero->value] = 'la certificazione dell\'imposta versata all\'estero';
            }
            foreach ($dati->getDevoluzioni($bene) as $devoluzione) {
                if ($devoluzione->agevolazione === Agevolazione::inagibiliSisma) {
                    $richiesti[TipoAllegato::altro->value] = 'la documentazione degli immobili inagibili (allegato "altri documenti")';
                }
            }
        }
        if ($dichiarazione->eventiEccezionali) {
            $richiesti[TipoAllegato::altro->value] = 'la documentazione degli eventi eccezionali (allegato "altri documenti")';
        }
        if (in_array(TipoAllegato::primaCasa->value, $presenti, true)) {
            $richiesti[TipoAllegato::documentiIdentita->value] = 'i documenti d\'identità di chi rende le dichiarazioni';
        }
        foreach ($richiesti as $tipo => $descrizione) {
            if (in_array($tipo, $presenti, true) === false) {
                $this->errore('Allegare ' . $descrizione . ' (quadro EG).');
            }
        }
        if ($dichiarazione->tipoDichiarazione->hasQuadri() === false && count($dati->allegati) === 0) {
            $this->errore('La dichiarazione integrativa con sola documentazione richiede almeno un allegato.');
        }
    }
}
