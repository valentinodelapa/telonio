<?php

namespace Successioni\Application\Services;

use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Entities\DevoluzioneSuccessione;
use Successioni\Application\Enumerations\Agevolazione;
use Successioni\Application\Enumerations\CodiceDiritto;
use Successioni\Application\Enumerations\GradoParentela;
use Successioni\Application\Enumerations\QuadroBene;
use Successioni\Application\Enumerations\TempisticaPagamento;
use Successioni\Application\Enumerations\TipoBeneficiario;
use Successioni\Application\Enumerations\TipoCespite;
use Successioni\Application\Enumerations\TipoDichiarazioneSuccessione;
use Successioni\Application\Enumerations\TipologiaTerreno;

/**
 * Liquidazione delle imposte della dichiarazione (quadro EF), secondo le
 * regole di controllo delle specifiche tecniche SUC13:
 * - imposta ipotecaria 2% e catastale 1% sul valore delle quote immobiliari
 *   (minimo 200 euro ciascuna), fisse per agevolazioni G, M e prima casa
 *   (200 euro per immobile), non dovute per C, D, E, H;
 * - tassa ipotecaria (120 euro, 65 senza volture) e imposta di bollo (85
 *   euro) per circoscrizione, bollo e tributi speciali per la copia
 *   conforme;
 * - per le successioni aperte dal 1° gennaio 2025 l'imposta di successione
 *   autoliquidata per beneficiario (QuotaImposta).
 *
 * Non sono considerate le devoluzioni allo Stato (grado 36) e agli enti
 * (37-39), esenti.
 */
class Liquidazione
{

    public const IMPOSTA_FISSA = 200;
    public const MINIMO_PROPORZIONALE = 200;
    public const ALIQUOTA_IPOTECARIA = 0.02;
    public const ALIQUOTA_CATASTALE = 0.01;
    public const TASSA_IPOTECARIA = 120;
    public const TASSA_IPOTECARIA_SENZA_VOLTURE = 65;
    public const BOLLO_CIRCOSCRIZIONE = 85;
    public const BOLLO_COPIA_CONFORME = 32;
    public const TRIBUTI_SPECIALI_COPIA_CONFORME = 16.0;
    public const PRESUNZIONE_DENARO = 0.10;
    public const SOGLIA_IMPOSTA_MINIMA = 10;
    public const INIZIO_TRUST_DISABILI = '2017-01-01';
    public const COMUNI_TAVOLARI_SENZA_CATASTALE = ['G831A', 'E800', 'L468', 'G406'];

    public function liquida(DatiDichiarazione $dati): RisultatoLiquidazione
    {
        $risultato = new RisultatoLiquidazione();
        $dichiarazione = $dati->dichiarazione;
        $this->riepiloga($dati, $risultato);
        if ($dichiarazione->tipoDichiarazione->hasQuadri()) {
            $this->liquidaIpocatastali($dati, $risultato);
            $this->liquidaTasseCircoscrizioni($dati, $risultato);
        }
        if ($dichiarazione->copiaConforme) {
            $risultato->bolloCopiaConforme = self::BOLLO_COPIA_CONFORME;
            $risultato->bollo += self::BOLLO_COPIA_CONFORME;
            $risultato->tributiSpeciali = self::TRIBUTI_SPECIALI_COPIA_CONFORME;
        }
        $risultato->autoliquidazione = $dichiarazione->isAutoliquidazione() && $dichiarazione->tipoDichiarazione->hasQuadri();
        if ($risultato->autoliquidazione) {
            $this->liquidaSuccessione($dati, $risultato);
        }
        $risultato->totaleDaVersare = round($risultato->ipotecariaDaVersare + $risultato->catastaleDaVersare + $risultato->tassaIpotecaria + $risultato->bollo
                + $risultato->tributiSpeciali + $this->getSuccessioneConDichiarazione($dati, $risultato), 2);
        if ($dichiarazione->tipoDichiarazione->hasQuadri()) {
            (new Ravvedimento())->calcola($dati, $risultato);
        }
        return $risultato;
    }

    /**
     * Imposta di successione versata con la dichiarazione (addebito): tutta
     * o l'acconto se rateizzata, con pagamento contestuale.
     */
    public function getSuccessioneConDichiarazione(DatiDichiarazione $dati, RisultatoLiquidazione $risultato): int
    {
        $dichiarazione = $dati->dichiarazione;
        if (($risultato->successioneDaVersare === 0) || ($dichiarazione->tempisticaPagamento !== TempisticaPagamento::contestuale)) {
            return 0;
        }
        return $dichiarazione->numeroRate !== null ? (int) $dichiarazione->acconto : $risultato->successioneDaVersare;
    }

    /**
     * Quadro EE: valori dei beni per gruppo di quadri e passività, esclusi
     * gli immobili aziendali (il loro valore è nell'azienda del quadro EN) e
     * i beni con l'agevolazione A (beni culturali).
     */
    private function riepiloga(DatiDichiarazione $dati, RisultatoLiquidazione $risultato): void
    {
        foreach ($dati->getBeni() as $bene) {
            $beniCulturali = in_array(Agevolazione::beniCulturali, array_map(fn(DevoluzioneSuccessione $devoluzione) => $devoluzione->agevolazione, $dati->getDevoluzioni($bene)), true);
            if ($bene->isAziendale() || $beniCulturali) {
                continue;
            }
            $risultato->riepilogo[$bene->quadro->getVoceRiepilogo()] += $bene->getValoreTotale();
        }
        $risultato->riepilogo['attivo'] = $risultato->riepilogo['immobili'] + $risultato->riepilogo['aziende'] + $risultato->riepilogo['titoli'] + $risultato->riepilogo['aereiNavi'] + $risultato->riepilogo['altriBeni'];
        foreach ($dati->passivita as $passivita) {
            $risultato->riepilogo['passivo'] += $passivita->valore;
        }
        $risultato->riepilogo['netto'] = $risultato->riepilogo['attivo'] - $risultato->riepilogo['passivo'];
    }

    /**
     * Sezioni I e II: imposte ipotecaria e catastale.
     */
    private function liquidaIpocatastali(DatiDichiarazione $dati, RisultatoLiquidazione $risultato): void
    {
        $proporzionale = 0.0;
        $valoreG = $valoreM = $valorePrimaCasa = 0.0;
        $principali = $contigui = 0;
        $soloTerreniNonEdificabili = true;
        $valoriFiscali = 0.0;
        $presenti = false;
        $proporzionaleCatastale = 0.0;
        foreach ([QuadroBene::terreni, QuadroBene::fabbricati, QuadroBene::terreniTavolare, QuadroBene::fabbricatiTavolare] as $quadro) {
            foreach ($dati->getBeniQuadro($quadro) as $bene) {
                $devoluzioni = array_filter($dati->getDevoluzioni($bene), fn(DevoluzioneSuccessione $devoluzione) => self::isImponibileIpocatastale($devoluzione));
                if ((count($devoluzioni) === 0) || ($bene->codiceDiritto === CodiceDiritto::oneriReali) || $bene->isImmobileEstero()) {
                    continue;
                }
                $presenti = true;
                if (count(array_filter($devoluzioni, fn(DevoluzioneSuccessione $devoluzione) => $devoluzione->beneficiarioSuccessione->isTrust())) > 0) {
                    // EF3bis/EF10bis: con la sola dotazione del trust le imposte sono fisse
                    $soloTerreniNonEdificabili = false;
                    $risultato->trustValore += (int) round(array_sum(array_map(fn(DevoluzioneSuccessione $devoluzione) => (float) $devoluzione->valoreQuota, $devoluzioni)));
                    continue;
                }
                $agevolazioni = array_map(fn(DevoluzioneSuccessione $devoluzione) => $devoluzione->agevolazione, $devoluzioni);
                $primaCasa = self::isPrimaCasa($bene, $agevolazioni);
                $agevolato = count(array_filter($agevolazioni, fn(?Agevolazione $agevolazione) => $agevolazione !== null)) > 0;
                if ($quadro->isFabbricato() || ($bene->tipologiaTerreno !== TipologiaTerreno::nonEdificabile) || $agevolato) {
                    $soloTerreniNonEdificabili = false;
                }
                if ($quadro->isTerreno()) {
                    $valoriFiscali += (float) $dati->getCatasto($bene)?->redditoDominicale * $bene->getQuota() * 112.5;
                }
                if ($primaCasa !== null) {
                    $valorePrimaCasa += array_sum(array_map(fn(DevoluzioneSuccessione $devoluzione) => (float) $devoluzione->valoreQuota, $devoluzioni));
                    $principali += $primaCasa === Agevolazione::primaCasaContiguo ? 0 : ($primaCasa === Agevolazione::pertinenzaPrimaCasa ? 0 : 1);
                    $contigui += $primaCasa === Agevolazione::primaCasaContiguo ? 1 : 0;
                    continue;
                }
                if (in_array(Agevolazione::fondiMontani, $agevolazioni, true)) {
                    $valoreM += array_sum(array_map(fn(DevoluzioneSuccessione $devoluzione) => (float) $devoluzione->valoreQuota, $devoluzioni));
                    continue;
                }
                foreach ($devoluzioni as $devoluzione) {
                    if ($devoluzione->agevolazione === Agevolazione::giovaniAgricoltori) {
                        $valoreG += (float) $devoluzione->valoreQuota;
                    } elseif (Agevolazione::isIpocatastaleProporzionale($devoluzione->agevolazione)) {
                        $proporzionale += (float) $devoluzione->valoreQuota;
                        if (self::isSoggettoCatastale($bene) === true) {
                            $proporzionaleCatastale += (float) $devoluzione->valoreQuota;
                        }
                    }
                }
            }
        }
        if ($presenti === false) {
            return;
        }
        $risultato->ipotecariaImponibile = (int) round($proporzionale);
        $risultato->catastaleImponibile = (int) round($proporzionaleCatastale);
        $risultato->ipotecariaProporzionale = (int) round($proporzionale * self::ALIQUOTA_IPOTECARIA);
        $risultato->catastaleProporzionale = (int) round($proporzionaleCatastale * self::ALIQUOTA_CATASTALE);
        if ($soloTerreniNonEdificabili && ($proporzionale > 0)) {
            $risultato->ipotecariaProporzionale = max(self::MINIMO_PROPORZIONALE, $risultato->ipotecariaProporzionale);
            $risultato->catastaleProporzionale = max(self::MINIMO_PROPORZIONALE, $risultato->catastaleProporzionale);
            if (($valoriFiscali > 0) && ($valoriFiscali < $risultato->ipotecariaProporzionale + $risultato->catastaleProporzionale)) {
                $risultato->ipotecariaProporzionale = (int) round($valoriFiscali * 2 / 3);
                $risultato->catastaleProporzionale = (int) round($valoriFiscali / 3);
                $risultato->avviso('Soli terreni non edificabili: imposte ipotecaria e catastale ridotte ai valori fiscali (reddito dominicale x 112,5).');
            }
        }
        $risultato->agevolazioneGValore = (int) round($valoreG);
        $risultato->agevolazioneGImposta = $valoreG > 0 ? self::IMPOSTA_FISSA : 0;
        $risultato->agevolazioneMValore = (int) round($valoreM);
        $risultato->agevolazioneMImposta = $valoreM > 0 ? self::IMPOSTA_FISSA : 0;
        $risultato->primaCasaNumero = $principali > 0 ? $principali : min(1, $contigui);
        $risultato->primaCasaValore = (int) round($valorePrimaCasa);
        $risultato->primaCasaImposta = $risultato->primaCasaNumero * self::IMPOSTA_FISSA;
        $risultato->trustIpotecaria = $risultato->trustCatastale = $risultato->trustValore > 0 ? self::IMPOSTA_FISSA : 0;
        $risultato->ipotecariaDovuta = $risultato->ipotecariaProporzionale + $risultato->agevolazioneGImposta + $risultato->agevolazioneMImposta + $risultato->trustIpotecaria + $risultato->primaCasaImposta;
        if (($proporzionale > 0) && ($soloTerreniNonEdificabili === false)) {
            $risultato->ipotecariaDovuta = max(self::MINIMO_PROPORZIONALE, $risultato->ipotecariaDovuta);
        }
        $risultato->catastaleFissa = $risultato->primaCasaImposta;
        $risultato->catastaleDovuta = $risultato->catastaleProporzionale + $risultato->catastaleFissa + $risultato->trustCatastale;
        if (($proporzionale > 0) && ($soloTerreniNonEdificabili === false)) {
            $risultato->catastaleDovuta = max(self::MINIMO_PROPORZIONALE, $risultato->catastaleDovuta);
        }
        $dichiarazione = $dati->dichiarazione;
        $risultato->ipotecariaVersata = (int) $dichiarazione->ipotecariaVersata;
        $risultato->catastaleVersata = (int) $dichiarazione->catastaleVersata;
        // credito del quadro EH, sezione III: prima sull'ipotecaria, il resto sulla catastale
        $credito = (int) $dichiarazione->creditoImporto;
        $risultato->ipotecariaCredito = (int) ($dichiarazione->creditoIpotecaria ?? min($credito, max(0, $risultato->ipotecariaDovuta - $risultato->ipotecariaVersata)));
        $risultato->ipotecariaDaVersare = max(0, $risultato->ipotecariaDovuta - $risultato->ipotecariaVersata - $risultato->ipotecariaCredito);
        $residuo = $risultato->ipotecariaDaVersare === 0 ? max(0, $credito - $risultato->ipotecariaCredito) : 0;
        $risultato->catastaleCredito = (int) ($dichiarazione->creditoCatastale ?? min($residuo, max(0, $risultato->catastaleDovuta - $risultato->catastaleVersata)));
        $risultato->catastaleDaVersare = max(0, $risultato->catastaleDovuta - $risultato->catastaleVersata - $risultato->catastaleCredito);
    }

    /**
     * Agevolazione prima casa del bene (estesa a tutti i beneficiari della
     * sua devoluzione), anche tramite il diritto di abitazione del
     * coniuge.
     *
     * @param array<?Agevolazione> $agevolazioni
     */
    private static function isPrimaCasa(BeneSuccessione $bene, array $agevolazioni): ?Agevolazione
    {
        foreach ([Agevolazione::primaCasa, Agevolazione::pertinenzaSenzaPrincipale, Agevolazione::primaCasaContiguo, Agevolazione::pertinenzaPrimaCasa] as $tipo) {
            if (in_array($tipo, $agevolazioni, true)) {
                return $tipo === Agevolazione::pertinenzaSenzaPrincipale ? Agevolazione::primaCasa : $tipo;
            }
        }
        return $bene->dirittoAbitazione?->getAgevolazione();
    }

    /**
     * I terreni del catasto tavolare di Trento e Bolzano (e dei comuni con
     * il libro fondiario indicati nelle specifiche) non scontano l'imposta
     * catastale proporzionale. "G831A" è la sezione censuaria A del comune
     * G831 (Pontebba).
     */
    private static function isSoggettoCatastale(BeneSuccessione $bene): bool
    {
        if ($bene->quadro !== QuadroBene::terreniTavolare) {
            return true;
        }
        $comune = $bene->immobile?->comune;
        $codice = $comune?->codiceCatastale;
        return (in_array($comune?->siglaProvincia, ['TN', 'BZ'], true) === false) && (in_array($codice, self::COMUNI_TAVOLARI_SENZA_CATASTALE, true) === false)
                && (in_array($codice . mb_strtoupper($bene->immobile?->sezione ?? ''), self::COMUNI_TAVOLARI_SENZA_CATASTALE, true) === false);
    }

    /**
     * Trust i cui righi del quadro EA sono tutti a favore di persone con
     * disabilità grave.
     */
    public static function isTrustDisabili(DatiDichiarazione $dati): bool
    {
        $righi = array_filter($dati->beneficiari, fn(BeneficiarioSuccessione $beneficiario) => $beneficiario->isTrust());
        return (count($righi) > 0) && (count(array_filter($righi, fn(BeneficiarioSuccessione $beneficiario) => $beneficiario->disabile === false)) === 0);
    }

    private static function isImponibileIpocatastale(DevoluzioneSuccessione $devoluzione): bool
    {
        return $devoluzione->beneficiarioSuccessione->gradoParentela->isEnte() === false;
    }

    /**
     * Sezioni III e IV: circoscrizioni immobiliari dei beni volturati.
     */
    private function liquidaTasseCircoscrizioni(DatiDichiarazione $dati, RisultatoLiquidazione $risultato): void
    {
        $tassa = $bollo = [];
        foreach ([QuadroBene::terreni, QuadroBene::fabbricati] as $quadro) {
            foreach ($dati->getBeniQuadro($quadro) as $bene) {
                if (($bene->immobile === null) || ($bene->codiceDiritto === CodiceDiritto::oneriReali)) {
                    continue;
                }
                $comune = $bene->immobile->comune->codiceCatastale;
                foreach ($dati->getDevoluzioni($bene) as $devoluzione) {
                    $grado = $devoluzione->beneficiarioSuccessione->gradoParentela;
                    if (($devoluzione->agevolazione === Agevolazione::inagibiliSisma) || ($devoluzione->codiceDiritto === CodiceDiritto::oneriReali)) {
                        continue;
                    }
                    if ($grado !== GradoParentela::stato) {
                        $tassa[$comune] = true;
                    }
                    if (($grado !== GradoParentela::stato) && ($grado !== GradoParentela::entiTerritorialiTerzoSettore) && (($devoluzione->agevolazione === null) || ($devoluzione->agevolazione->isEsenteBollo() === false))) {
                        $bollo[$comune] = true;
                    }
                }
            }
        }
        if ($dati->dichiarazione->tipoDichiarazione === TipoDichiarazioneSuccessione::sostitutivaSenzaTrascrizione) {
            return;
        }
        $indicate = $dati->dichiarazione->numeroCircoscrizioni;
        $risultato->circoscrizioniTassa = count($tassa) > 0 ? ($indicate ?? count($tassa)) : 0;
        $risultato->circoscrizioniBollo = count($bollo) === 0 ? 0 : ($indicate === null ? count($bollo) : min($indicate, count($bollo)));
        $senzaVolture = $dati->dichiarazione->senzaVolture || ($dati->dichiarazione->codiceCarica?->isSenzaVoltureAutomatiche() ?? false);
        $risultato->tassaIpotecaria = $risultato->circoscrizioniTassa * ($senzaVolture ? self::TASSA_IPOTECARIA_SENZA_VOLTURE : self::TASSA_IPOTECARIA);
        if (self::isTrustDisabili($dati) && ($dati->dichiarazione->getDataApertura()->format('Y-m-d') >= self::INIZIO_TRUST_DISABILI)) {
            // trust a favore di persone con disabilità grave (art. 6 L. 112/2016): niente bollo
            $risultato->circoscrizioniBollo = 0;
            $risultato->bollo = 0;
        }
        $risultato->bollo = $risultato->circoscrizioniBollo * self::BOLLO_CIRCOSCRIZIONE;
        if (($indicate === null) && (count($tassa) > 1)) {
            $risultato->avviso('Circoscrizioni immobiliari contate per comune (' . count($tassa) . '): se più comuni appartengono alla stessa conservatoria indicare il numero nel frontespizio.');
        }
    }

    /**
     * Sezione V-bis: imposta di successione autoliquidata, per beneficiario.
     */
    private function liquidaSuccessione(DatiDichiarazione $dati, RisultatoLiquidazione $risultato): void
    {
        $dichiarazione = $dati->dichiarazione;
        $trust = count(array_filter($dati->beneficiari, fn(BeneficiarioSuccessione $beneficiario) => $beneficiario->isTrust())) > 0;
        if ($trust && (($dichiarazione->pagamentoAnticipatoTrust === false) || (self::isTrustDisabili($dati) && ($dichiarazione->getDataApertura()->format('Y-m-d') >= self::INIZIO_TRUST_DISABILI)))) {
            // trust: imposta all'attribuzione ai beneficiari finali, o esente se tutti con disabilità grave
            $risultato->trustDifferito = $dichiarazione->pagamentoAnticipatoTrust === false;
            $risultato->avviso($risultato->trustDifferito ? 'Trust senza pagamento anticipato: l\'imposta di successione è dovuta all\'attribuzione dei beni ai beneficiari finali.' : 'Trust a favore di persone con disabilità grave: imposta di successione non dovuta (art. 6 L. 112/2016).');
            return;
        }
        $beniInventariati = false;
        foreach ($dati->getBeniQuadro(QuadroBene::altriBeni) as $bene) {
            $beniInventariati = $beniInventariati || ($bene->tipoCespite === TipoCespite::beniInventariati);
        }
        $quote = [];
        foreach ($dati->beneficiari as $beneficiario) {
            $quote[$beneficiario->id] = new QuotaImposta($beneficiario);
        }
        $devolutePerBeneficiario = [];
        foreach ($dati->getBeni() as $bene) {
            foreach ($dati->getDevoluzioni($bene) as $devoluzione) {
                $quota = $quote[$devoluzione->beneficiarioSuccessione->id] ?? null;
                if ($quota === null) {
                    continue;
                }
                if ($bene->isAziendale()) {
                    continue;
                }
                $devolutePerBeneficiario[$devoluzione->beneficiarioSuccessione->id][] = $devoluzione;
                if ($bene->tipoCespite === TipoCespite::denaroGioielliMobilia) {
                    $quota->quotaDenaro += (float) $devoluzione->valoreQuota;
                } elseif ($trust || ($devoluzione->agevolazione === null) || ($devoluzione->agevolazione->isEsenteSuccessione() === false)) {
                    // con il pagamento anticipato del trustee non si applicano esenzioni e riduzioni
                    $quota->quotaEreditaria += (float) $devoluzione->valoreQuota;
                }
            }
        }
        foreach ($dati->passivita as $passivita) {
            foreach ($dati->getRipartizioni($passivita) as $ripartizione) {
                if (isset($quote[$ripartizione->beneficiarioSuccessione->id])) {
                    $quote[$ripartizione->beneficiarioSuccessione->id]->quotaPassivo += (float) $ripartizione->valoreQuota;
                }
            }
        }
        $franchigieUsate = [];
        $totale = 0.0;
        foreach ($quote as $id => $quota) {
            $beneficiario = $quota->beneficiario;
            $quota->attivoNetto = max(0.0, $quota->quotaEreditaria + $quota->quotaDenaro - $quota->quotaPassivo);
            $quota->aliquota = $beneficiario->gradoParentela->getAliquota();
            if (($quota->aliquota === 0.0) || ($quota->attivoNetto === 0.0)) {
                continue;
            }
            // franchigia per beneficiario: nel trust quello finale (o il rigo, se non individuato)
            $chiaveSoggetto = $beneficiario->isTrust() ? ($beneficiario->beneficiarioFinale !== null ? 's' . $beneficiario->beneficiarioFinale->id : 'r' . $beneficiario->id) : 's' . $beneficiario->soggetto->id;
            $franchigiaResidua = $beneficiario->gradoParentela->getFranchigia($beneficiario->disabile) - ($franchigieUsate[$chiaveSoggetto] ?? 0.0);
            $quota->franchigia = min(max(0.0, $franchigiaResidua), $quota->attivoNetto);
            $franchigieUsate[$chiaveSoggetto] = ($franchigieUsate[$chiaveSoggetto] ?? 0.0) + $quota->franchigia;
            $quota->quotaNetta = $quota->attivoNetto - $quota->franchigia;
            if (($beneficiario->tipoBeneficiario !== TipoBeneficiario::legatario) && ($beniInventariati === false)) {
                $quota->presunzione = max(0.0, self::PRESUNZIONE_DENARO * ($quota->quotaNetta - $quota->quotaDenaro) - $quota->quotaDenaro);
            }
            $quota->imponibile = $quota->quotaNetta + $quota->presunzione;
            $quota->impostaLorda = $quota->imponibile * $quota->aliquota / 100;
            if (($quota->quotaEreditaria > 0) && ($trust === false)) {
                $this->riduci($quota, $devolutePerBeneficiario[$id] ?? []);
            }
            $quota->impostaNetta = max(0.0, round($quota->impostaLorda - $quota->riduzioni - $quota->detrazioneEstero, 2));
            $totale += $quota->impostaNetta;
        }
        $risultato->quote = $quote;
        $risultato->successioneImposta = (int) round($totale);
        $risultato->successioneVersata = (int) $dichiarazione->successioneVersata;
        $risultato->successioneCredito = (int) $dichiarazione->creditoSuccessione;
        $daVersare = $risultato->successioneImposta - $risultato->successioneVersata - $risultato->successioneCredito;
        $risultato->successioneDaVersare = $daVersare <= self::SOGLIA_IMPOSTA_MINIMA ? 0 : $daVersare;
    }

    /**
     * Riduzioni (agevolazioni L, Q, R, F e art. 25, c. 1) e detrazione
     * dell'imposta pagata all'estero, in proporzione alla quota del bene:
     * r = valore quota x ISL / QE.
     *
     * @param DevoluzioneSuccessione[] $devoluzioni
     */
    private function riduci(QuotaImposta $quota, array $devoluzioni): void
    {
        $rapporto = $quota->impostaLorda / $quota->quotaEreditaria;
        foreach ($devoluzioni as $devoluzione) {
            $agevolazione = $devoluzione->agevolazione;
            $valoreQuota = (float) $devoluzione->valoreQuota;
            if (($agevolazione !== null) && ($agevolazione->isEsenteSuccessione() || ($devoluzione->beneSuccessione->tipoCespite === TipoCespite::denaroGioielliMobilia))) {
                continue;
            }
            if (($agevolazione !== null) && ($agevolazione->getRiduzione() > 0)) {
                $base = $agevolazione->hasValoreMassimoRiduzione() ? min($valoreQuota, Agevolazione::VALORE_MASSIMO_RIDUZIONE) : $valoreQuota;
                $quota->riduzioni += $base * $rapporto * $agevolazione->getRiduzione();
            }
            $bene = $devoluzione->beneSuccessione;
            if (($devoluzione->riduzione !== null) && ($bene->valorePrecedente !== null) && ($bene->getValoreTotale() > 0)) {
                $quota->riduzioni += ($bene->valorePrecedente * $valoreQuota / $bene->getValoreTotale()) * $rapporto * $devoluzione->riduzione->getRiduzione();
            }
            if ($bene->beneEstero && ($bene->impostaEstero !== null)) {
                $quotaEstera = $valoreQuota * $rapporto;
                $impostaEstera = $bene->impostaEstero * $devoluzione->getQuota();
                $quota->detrazioneEstero += min($quotaEstera, $impostaEstera);
            }
        }
    }
}
