<?php

namespace Imu\Application\Services;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Anagrafica\Application\Models\DatiCatastaliModel;
use Anagrafica\Application\Models\LegameModel;
use Anagrafica\Application\Models\PossessoModel;
use Deleghe\Application\Models\IntermediarioModel;
use Deleghe\Application\Models\VersamentoModel;
use Imu\Application\Entities\AgevolazioneImu;
use Imu\Application\Entities\CalcoloImu;
use Imu\Application\Entities\CaratteristicheImu;
use Imu\Application\Entities\DichiarazioneImu;
use Imu\Application\Entities\RigoDichiarazioneImu;
use Imu\Application\Enumerations\AssimilazioneAbitazione;
use Imu\Application\Enumerations\CaratteristicaDichiarata;
use Imu\Application\Enumerations\CodiceCarica;
use Imu\Application\Enumerations\EsenzioneImu;
use Imu\Application\Enumerations\ModelloDichiarazione;
use Imu\Application\Enumerations\StatoDichiarazione;
use Imu\Application\Models\AccontoVersatoImuModel;
use Imu\Application\Models\AgevolazioneImuModel;
use Imu\Application\Models\CalcoloImuModel;
use Imu\Application\Models\CaratteristicheImuModel;
use Imu\Application\Models\CostoContabileImuModel;
use Imu\Application\Models\DichiarazioneImuModel;
use Imu\Application\Models\RigoCalcoloImuModel;
use Imu\Application\Models\RigoDichiarazioneImuModel;
use Imu\Application\Models\UtilizzoMistoEncModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Proposta delle dichiarazioni IMU dell'anno dai dati dell'Anagrafica e del
 * modulo, secondo i "casi in cui deve essere presentata la dichiarazione"
 * delle istruzioni ministeriali (D.M. 24 aprile 2024) e il comma 768-bis:
 * - riduzioni: interesse storico (inizio e termine), inagibilità (solo
 *   termine), comodato ai parenti, pensionato estero;
 * - informazioni non conoscibili dal comune: aree fabbricabili (acquisto,
 *   cessione, variazione del valore, terreno divenuto area), locazione
 *   finanziaria, fabbricati D a valore contabile (costi incrementativi),
 *   terreni condotti da coltivatori diretti e IAP, inizio e fine di
 *   un'esenzione (immobili occupati abusivamente: codice 1), fabbricati
 *   merce, alloggi sociali e alloggi del personale delle Forze armate
 *   (equiparazione all'abitazione principale);
 * - gli acquisti e le cessioni degli altri immobili non si dichiarano (atti
 *   con modello unico informatico, dati catastali noti al comune).
 *
 * Per gli enti non commerciali la dichiarazione è annuale e comprende
 * tutti gli immobili: nel quadro A quelli imponibili, nel quadro B quelli
 * con un utilizzo per le attività del comma 759, lett. g) (UtilizzoMistoEnc).
 *
 * Una dichiarazione per comune; le bozze vengono riproposte, quelle
 * presentate restano (per correggerle si crea la sostitutiva).
 */
class PropostaDichiarazione
{

    private const RIDUZIONI = ['storicoArtistico' => '1', 'comodatoParenti' => '3', 'pensionatoEstero' => '4'];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return DichiarazioneImu[] dichiarazioni in bozza create o aggiornate
     */
    public function proponi(Soggetto $soggetto, int $anno): array
    {
        $enc = CalcoloImposta::isEnteNonCommerciale($soggetto, $anno, $this->dataMapper);
        $righiPerComune = [];
        foreach ($this->getPossessiPerImmobile($soggetto, $anno) as ['immobile' => $immobile, 'possessi' => $possessi]) {
            $righi = $this->getVicende($soggetto, $immobile, $possessi, $anno, $enc);
            if (count($righi) > 0) {
                $righiPerComune[$immobile->comune->codiceCatastale]['comune'] = $immobile->comune;
                $righiPerComune[$immobile->comune->codiceCatastale]['righi'][] = $righi;
            }
        }
        $dichiarazioneModel = new DichiarazioneImuModel($this->dataMapper);
        $proposte = [];
        $primaTransazione = $this->dataMapper->startTransaction();
        foreach ($righiPerComune as $codice => ['comune' => $comune, 'righi' => $perImmobile]) {
            $dichiarazione = $dichiarazioneModel->getPerComune($soggetto, $anno, $codice);
            if (($dichiarazione !== null) && ($dichiarazione->isModificabile() === false)) {
                continue;
            }
            $dichiarazione ??= $this->crea($soggetto, $anno, $codice, $comune->denominazione, $enc);
            $this->sostituisciRighi($dichiarazione, $perImmobile);
            if ($dichiarazione->isEnc()) {
                $this->determinaQuadroC($dichiarazione);
                $this->dataMapper->save($dichiarazione);
            }
            $proposte[] = $dichiarazione;
        }
        $this->dataMapper->commitTransaction($primaTransazione);
        return $proposte;
    }

    /**
     * Quadro C ENC dal calcolo dell'anno e dall'archivio: imposta dovuta per
     * il comune (al netto della detrazione), rate versate (versamenti della
     * procedura in deleghe confermate, trasmesse o quietanzate, più gli
     * acconti versati fuori procedura), eccedenza della dichiarazione
     * dell'anno precedente non chiesta a rimborso. Non salva.
     */
    public function determinaQuadroC(DichiarazioneImu $dichiarazione): void
    {
        $soggetto = $dichiarazione->soggetto;
        $comune = $dichiarazione->codiceComune;
        $imposta = 0.0;
        $calcolo = (new CalcoloImuModel($this->dataMapper))->getPerAnno($soggetto, $dichiarazione->anno);
        if ($calcolo !== null) {
            foreach ((new RigoCalcoloImuModel($this->dataMapper))->getRighi($calcolo) as $rigo) {
                if ($rigo->codiceComune === $comune) {
                    $imposta += $rigo->impostaAnnua - $rigo->detrazioneAnnua;
                }
            }
        }
        $versate = 0.0;
        foreach ((new VersamentoModel($this->dataMapper))->getGenerati($soggetto, CalcoloImu::ORIGINE, CalcoloImu::getRiferimento($dichiarazione->anno)) as $versamento) {
            if (($versamento->codiceEnte === $comune) && ($versamento->isModificabile() === false)) {
                $versate += $versamento->importoDebito;
            }
        }
        foreach ((new AccontoVersatoImuModel($this->dataMapper))->getPerAnno($soggetto, $dichiarazione->anno) as $acconto) {
            if ($acconto->codiceComune === $comune) {
                $versate += $acconto->importo;
            }
        }
        $precedente = (new DichiarazioneImuModel($this->dataMapper))->getPerComune($soggetto, $dichiarazione->anno - 1, $comune);
        $eccedenza = 0.0;
        if (($precedente !== null) && $precedente->isEnc()) {
            $eccedenza = max(0.0, -$precedente->getSaldoQuadroC() - (float) $precedente->creditoRimborso);
        }
        $dichiarazione->impostaDovuta = round($imposta);
        $dichiarazione->rateVersate = round($versate);
        $dichiarazione->eccedenzaPrecedente = round($eccedenza);
        $dichiarazione->eccedenzaCompensata ??= 0.0;
    }

    private function crea(Soggetto $soggetto, int $anno, string $codice, string $denominazione, bool $enc): DichiarazioneImu
    {
        $dichiarazione = new DichiarazioneImu($this->dataMapper);
        $dichiarazione->studio = $soggetto->studio;
        $dichiarazione->soggetto = $soggetto;
        $dichiarazione->anno = $anno;
        $dichiarazione->codiceComune = $codice;
        $dichiarazione->denominazioneComune = $denominazione;
        $dichiarazione->modello = $enc ? ModelloDichiarazione::entiNonCommerciali : ModelloDichiarazione::ordinario;
        $dichiarazione->stato = StatoDichiarazione::bozza;
        $dichiarazione->dataDichiarazione = new SismaDate();
        $rappresentante = $this->getRappresentante($soggetto, new SismaDate());
        if ($rappresentante !== null) {
            $dichiarazione->dichiarante = $rappresentante;
            $dichiarazione->codiceCarica = CodiceCarica::rappresentanteLegale;
        }
        $intermediario = (new IntermediarioModel($this->dataMapper))->getPredefinito($soggetto->studio);
        if ($intermediario !== null) {
            $dichiarazione->intermediario = $intermediario;
            $dichiarazione->dataImpegno = new SismaDate();
        }
        $this->dataMapper->save($dichiarazione);
        return $dichiarazione;
    }

    /**
     * Rappresentante legale (o amministratore unico) in carica per i
     * soggetti diversi dalle persone fisiche.
     */
    public function getRappresentante(Soggetto $soggetto, SismaDate $data): ?Soggetto
    {
        if ($soggetto->isPersonaFisica()) {
            return null;
        }
        foreach ([RuoloLegame::legaleRappresentante, RuoloLegame::amministratoreUnico, RuoloLegame::presidenteCda, RuoloLegame::liquidatore] as $ruolo) {
            foreach ((new LegameModel($this->dataMapper))->getCollegati($soggetto) as $legame) {
                if (($legame->ruolo === $ruolo) && $legame->isVigenteAl($data) && $legame->soggettoCollegato->isPersonaFisica()) {
                    return $legame->soggettoCollegato;
                }
            }
        }
        return null;
    }

    /**
     * @param array<int, array<int, array>> $perImmobile righi per immobile
     */
    private function sostituisciRighi(DichiarazioneImu $dichiarazione, array $perImmobile): void
    {
        foreach ((new RigoDichiarazioneImuModel($this->dataMapper))->getRighi($dichiarazione) as $rigo) {
            $this->dataMapper->delete($rigo);
        }
        $numeri = ['A' => 0, 'B' => 0];
        $progressivi = ['A' => 0, 'B' => 0];
        foreach ($perImmobile as $righi) {
            $quadri = [];
            foreach ($righi as $dati) {
                $quadro = $dichiarazione->isEnc() ? $dati['quadro'] : 'A';
                $quadri[$quadro] ??= ++$progressivi[$quadro];
                $rigo = new RigoDichiarazioneImu($this->dataMapper);
                $rigo->dichiarazioneImu = $dichiarazione;
                $rigo->quadro = $quadro;
                $rigo->numeroOrdine = ++$numeri[$quadro];
                $rigo->progressivoImmobile = $quadri[$quadro];
                foreach ($dati as $proprieta => $valore) {
                    if ($proprieta !== 'quadro') {
                        $rigo->$proprieta = $valore;
                    }
                }
                $this->dataMapper->save($rigo);
            }
        }
    }

    /**
     * @return array<int, array{immobile: Immobile, possessi: Possesso[]}>
     */
    private function getPossessiPerImmobile(Soggetto $soggetto, int $anno): array
    {
        $immobili = [];
        foreach ((new PossessoModel($this->dataMapper))->getStoricoSoggetto($soggetto) as $possesso) {
            if (CalcoloImposta::isSoggettoPassivo($possesso->titolo) === false) {
                continue;
            }
            $immobili[$possesso->immobile->id]['immobile'] = $possesso->immobile;
            $immobili[$possesso->immobile->id]['possessi'][] = $possesso;
        }
        return array_values(array_filter($immobili, fn(array $voce) => count(array_filter($voce['possessi'], fn(Possesso $possesso) => self::interseca($possesso, $anno))) > 0));
    }

    private static function interseca(Possesso $possesso, int $anno): bool
    {
        return ($possesso->validoDal->format('Y-m-d') <= $anno . '-12-31') && (($possesso->validoAl === null) || ($possesso->validoAl->format('Y-m-d') >= $anno . '-01-01'));
    }

    /**
     * Vicende da dichiarare per l'immobile nell'anno.
     *
     * @param Possesso[] $possessi
     * @return array<int, array<string, mixed>> dati dei righi
     */
    private function getVicende(Soggetto $soggetto, Immobile $immobile, array $possessi, int $anno, bool $enc): array
    {
        $caratteristicheModel = new CaratteristicheImuModel($this->dataMapper);
        $agevolazioneModel = new AgevolazioneImuModel($this->dataMapper);
        $date = [];
        foreach ($possessi as $possesso) {
            $date[] = $possesso->validoDal->format('Y-m-d');
            if ($possesso->validoAl !== null) {
                $date[] = self::giornoDopo($possesso->validoAl);
            }
        }
        foreach ([$caratteristicheModel->getStorico($immobile), $agevolazioneModel->getStorico($soggetto, $immobile)] as $storico) {
            foreach ($storico as $versione) {
                $date[] = $versione->validoDal->format('Y-m-d');
                if ($versione->validoAl !== null) {
                    $date[] = self::giornoDopo($versione->validoAl);
                }
            }
        }
        $date = array_values(array_unique(array_filter($date, fn(string $data) => ($data >= $anno . '-01-01') && ($data <= $anno . '-12-31'))));
        sort($date);
        $righi = [];
        if ($enc) {
            $inizio = $anno . '-01-01';
            $primo = array_filter($possessi, fn(Possesso $possesso) => self::interseca($possesso, $anno));
            $dataIniziale = max($inizio, min(array_map(fn(Possesso $possesso) => $possesso->validoDal->format('Y-m-d'), $primo)));
            $stato = $this->getStato($soggetto, $immobile, $possessi, $dataIniziale);
            $righi[] = $this->creaRigo($soggetto, $immobile, $stato, $anno, $enc) + ($dataIniziale > $inizio ? ['acquisto' => true, 'dataVariazione' => new SismaDate($dataIniziale)] : [])
                    + ['motivo' => 'Ente non commerciale: dichiarazione annuale di tutti gli immobili (comma 768-bis)'];
            $date = array_values(array_filter($date, fn(string $data) => $data !== $dataIniziale));
        }
        foreach ($date as $data) {
            $prima = $this->getStato($soggetto, $immobile, $possessi, self::giornoPrima($data));
            $dopo = $this->getStato($soggetto, $immobile, $possessi, $data);
            foreach ($this->confronta($prima, $dopo) as $vicenda) {
                $stato = $vicenda['cessione'] ?? false ? $prima : $dopo;
                $righi[] = array_merge($this->creaRigo($soggetto, $immobile, $stato, $anno, $enc), $vicenda, ['dataVariazione' => new SismaDate($data)]);
            }
        }
        foreach ((new CostoContabileImuModel($this->dataMapper))->getElenco($immobile) as $costo) {
            if (($costo->anno === $anno) && ($this->getStato($soggetto, $immobile, $possessi, $anno . '-12-31')['possesso'] !== null)) {
                $stato = $this->getStato($soggetto, $immobile, $possessi, $anno . '-12-31');
                if ($stato['caratteristiche']?->valoreContabile === true) {
                    $righi[] = array_merge($this->creaRigo($soggetto, $immobile, $stato, $anno, $enc), ['altro' => true, 'altroDescrizione' => 'Costi incrementativi sostenuti nell\'anno',
                        'dataVariazione' => new SismaDate($anno . '-12-31'), 'motivo' => 'Fabbricato a valore contabile con costi aggiuntivi nell\'anno']);
                    break;
                }
            }
        }
        return $righi;
    }

    /**
     * Situazione dell'immobile per il contribuente alla data.
     *
     * @param Possesso[] $possessi
     * @return array{possesso: ?Possesso, caratteristiche: ?CaratteristicheImu, agevolazione: ?AgevolazioneImu, data: string}
     */
    private function getStato(Soggetto $soggetto, Immobile $immobile, array $possessi, string $data): array
    {
        $giorno = new SismaDate($data);
        $possesso = null;
        foreach ($possessi as $versione) {
            if ($versione->isVigenteAl($giorno)) {
                $possesso = $versione;
            }
        }
        return [
            'possesso' => $possesso,
            'caratteristiche' => (new CaratteristicheImuModel($this->dataMapper))->getVigenteAl($immobile, $giorno),
            'agevolazione' => (new AgevolazioneImuModel($this->dataMapper))->getVigenteAl($soggetto, $immobile, $giorno),
            'data' => $data,
        ];
    }

    /**
     * Vicende da dichiarare tra due situazioni consecutive.
     *
     * @return array<int, array<string, mixed>>
     */
    private function confronta(array $prima, array $dopo): array
    {
        $vicende = [];
        if (($prima['possesso'] === null) && ($dopo['possesso'] !== null)) {
            if (self::isDaDichiarare($dopo)) {
                $vicende[] = ['acquisto' => true, 'motivo' => 'Acquisto di ' . self::getMotivoDichiarazione($dopo)];
            }
            return $vicende;
        }
        if (($prima['possesso'] !== null) && ($dopo['possesso'] === null)) {
            if (self::isDaDichiarare($prima)) {
                $vicende[] = ['cessione' => true, 'motivo' => 'Cessione di ' . self::getMotivoDichiarazione($prima)];
            }
            return $vicende;
        }
        if ($dopo['possesso'] === null) {
            return $vicende;
        }
        $cp = $prima['caratteristiche'];
        $cd = $dopo['caratteristiche'];
        $ap = $prima['agevolazione'];
        $ad = $dopo['agevolazione'];
        foreach (['storicoArtistico' => [$cp, $cd, 'Interesse storico o artistico'], 'comodatoParenti' => [$ap, $ad, 'Comodato a parenti in linea retta'], 'pensionatoEstero' => [$ap, $ad, 'Pensionato residente all\'estero']] as $proprieta => [$da, $a, $descrizione]) {
            $primaAttiva = $da?->$proprieta === true;
            $dopoAttiva = $a?->$proprieta === true;
            if ($primaAttiva !== $dopoAttiva) {
                $vicende[] = ['riduzione' => self::RIDUZIONI[$proprieta], 'inizioTermine' => $dopoAttiva ? 'I' : 'T', 'motivo' => $descrizione . ($dopoAttiva ? ': inizio' : ': termine') . ' della riduzione'];
            }
        }
        if (($cp?->inagibile === true) && ($cd?->inagibile !== true)) {
            $vicende[] = ['riduzione' => '2', 'inizioTermine' => 'T', 'motivo' => 'Fine dell\'inagibilità (la dichiarazione è dovuta solo quando si perde la riduzione)'];
        }
        if (($cp?->areaFabbricabile === true) !== ($cd?->areaFabbricabile === true)) {
            $vicende[] = ['altro' => true, 'altroDescrizione' => $cd?->areaFabbricabile === true ? 'Terreno divenuto area fabbricabile' : 'Area non più fabbricabile', 'motivo' => 'Variazione della natura di area fabbricabile'];
        } elseif (($cd?->areaFabbricabile === true) && ((float) $cp?->valoreVenale !== (float) $cd->valoreVenale)) {
            $vicende[] = ['altro' => true, 'altroDescrizione' => 'Variazione del valore venale dell\'area', 'motivo' => 'Nuovo valore dell\'area fabbricabile'];
        }
        if (($ap?->conduzioneAgricola === true) !== ($ad?->conduzioneAgricola === true)) {
            $vicende[] = ['esenzione' => '3', 'inizioTermine' => $ad?->conduzioneAgricola === true ? 'I' : 'T', 'motivo' => 'Conduzione da coltivatore diretto o IAP (comma 758, lett. a)'];
        }
        if ($cp?->esenzione !== $cd?->esenzione) {
            $esenzione = $cd?->esenzione ?? $cp->esenzione;
            $vicende[] = ['esenzione' => $esenzione === EsenzioneImu::occupatoAbusivamente ? '1' : '3', 'inizioTermine' => $cd?->esenzione !== null ? 'I' : 'T', 'motivo' => $esenzione->getLabel() . ($cd?->esenzione !== null ? ': inizio' : ': termine') . ' dell\'esenzione']
                    + ($esenzione === EsenzioneImu::occupatoAbusivamente ? ['nonUtilizzabileTipo' => '2'] : []);
        }
        if (($cp?->beneMerce === true) !== ($cd?->beneMerce === true)) {
            $vicende[] = ['esenzione' => '3', 'inizioTermine' => $cd?->beneMerce === true ? 'I' : 'T', 'motivo' => 'Fabbricato merce (comma 751)'];
        }
        $equiparazione = fn(?AgevolazioneImu $agevolazione) => match ($agevolazione?->assimilazione) {
            AssimilazioneAbitazione::alloggioSociale => '1',
            AssimilazioneAbitazione::forzeArmate => '2',
            default => null,
        };
        if ($equiparazione($ap) !== $equiparazione($ad)) {
            $vicende[] = ['altro' => true, 'altroDescrizione' => $equiparazione($ad) !== null ? 'Inizio dell\'equiparazione all\'abitazione principale' : 'Termine dell\'equiparazione all\'abitazione principale',
                'motivo' => 'Alloggio sociale o del personale delle Forze armate (comma 768-bis)'];
        }
        return $vicende;
    }

    private static function isDaDichiarare(array $stato): bool
    {
        return self::getMotivoDichiarazione($stato) !== '';
    }

    private static function getMotivoDichiarazione(array $stato): string
    {
        $c = $stato['caratteristiche'];
        $a = $stato['agevolazione'];
        return implode(', ', array_keys(array_filter([
            'area fabbricabile' => $c?->areaFabbricabile === true,
            'immobile in locazione finanziaria' => $stato['possesso']?->titolo === TitoloPossesso::locazioneFinanziaria,
            'fabbricato a valore contabile' => $c?->valoreContabile === true,
            'immobile di interesse storico o artistico' => $c?->storicoArtistico === true,
            'immobile esente' => $c?->esenzione !== null,
            'fabbricato merce' => $c?->beneMerce === true,
            'terreno condotto da coltivatore diretto o IAP' => $a?->conduzioneAgricola === true,
            'immobile in comodato a parenti' => $a?->comodatoParenti === true,
            'immobile di pensionato estero' => $a?->pensionatoEstero === true,
        ])));
    }

    /**
     * Dati del rigo alla situazione indicata.
     *
     * @return array<string, mixed>
     */
    private function creaRigo(Soggetto $soggetto, Immobile $immobile, array $stato, int $anno, bool $enc): array
    {
        $possesso = $stato['possesso'];
        $caratteristiche = $stato['caratteristiche'];
        $agevolazione = $stato['agevolazione'];
        $datiCatastali = (new DatiCatastaliModel($this->dataMapper))->getVigenteAl($immobile, new SismaDate($stato['data']));
        $caratteristica = match (true) {
            $caratteristiche?->areaFabbricabile === true => CaratteristicaDichiarata::areaFabbricabile,
            $immobile->tipoCatasto === TipoCatasto::terreni => CaratteristicaDichiarata::terreno,
            $caratteristiche?->valoreContabile === true => CaratteristicaDichiarata::fabbricatoContabile,
            $enc => CaratteristicaDichiarata::fabbricatoRendita,
            $caratteristiche?->beneMerce === true => CaratteristicaDichiarata::beneMerce,
            $possesso?->utilizzo === UtilizzoImmobile::abitazionePrincipale => CaratteristicaDichiarata::abitazionePrincipale,
            $possesso?->utilizzo === UtilizzoImmobile::pertinenzaAbitazionePrincipale => CaratteristicaDichiarata::pertinenza,
            default => CaratteristicaDichiarata::fabbricatoRendita,
        };
        $valore = match ($caratteristica) {
            CaratteristicaDichiarata::areaFabbricabile => (float) $caratteristiche->valoreVenale,
            CaratteristicaDichiarata::terreno => $datiCatastali === null ? 0.0 : ValoreImmobile::terreno($datiCatastali),
            CaratteristicaDichiarata::fabbricatoContabile => (new ValoreImmobile($this->dataMapper))->contabile($immobile, $anno, $possesso?->validoDal->format('Y') === (string) $anno)['valore'],
            default => $datiCatastali === null ? 0.0 : ValoreImmobile::fabbricato($datiCatastali),
        };
        $riduzione = '0';
        foreach (['storicoArtistico' => $caratteristiche, 'comodatoParenti' => $agevolazione, 'pensionatoEstero' => $agevolazione] as $proprieta => $origine) {
            if (($riduzione === '0') && ($origine?->$proprieta === true)) {
                $riduzione = self::RIDUZIONI[$proprieta];
            }
        }
        if (($riduzione === '0') && ($caratteristiche?->inagibile === true)) {
            $riduzione = '2';
        }
        if ($enc && in_array($riduzione, ['3', '4'], true)) {
            $riduzione = '3';
        }
        $utilizzoEnc = $enc ? (new UtilizzoMistoEncModel($this->dataMapper))->getPerAnno($soggetto, $immobile, $anno) : null;
        $quadroB = ($utilizzoEnc !== null) && ($utilizzoEnc->isInteramenteImponibile() === false);
        $esente = ($caratteristiche?->esenzione !== null) || ($agevolazione?->conduzioneAgricola === true) || (($caratteristiche?->beneMerce === true) && ($anno >= 2022));
        return [
            'immobile' => $immobile,
            'quadro' => $quadroB ? 'B' : 'A',
            'caratteristica' => $caratteristica,
            'valore' => round($valore),
            'percentualePossesso' => (float) ($possesso?->quota ?? 100),
            'detrazione' => $caratteristica === CaratteristicaDichiarata::abitazionePrincipale ? $this->getDetrazione($soggetto, $immobile, $anno) : null,
            'equiparazione' => match ($agevolazione?->assimilazione) {
                AssimilazioneAbitazione::alloggioSociale => '1',
                AssimilazioneAbitazione::forzeArmate => '2',
                default => null,
            },
            'riduzione' => $riduzione,
            'esenzione' => $quadroB ? (($utilizzoEnc->esente || $esente) ? '1' : '0') : ($caratteristiche?->esenzione === EsenzioneImu::occupatoAbusivamente ? '1' : ($esente ? '3' : '0')),
        ];
    }

    private function getDetrazione(Soggetto $soggetto, Immobile $immobile, int $anno): ?float
    {
        $calcolo = (new CalcoloImuModel($this->dataMapper))->getPerAnno($soggetto, $anno);
        if ($calcolo === null) {
            return null;
        }
        $detrazione = 0.0;
        foreach ((new RigoCalcoloImuModel($this->dataMapper))->getRighi($calcolo) as $rigo) {
            if ($rigo->immobile->id === $immobile->id) {
                $detrazione += $rigo->detrazioneAnnua;
            }
        }
        return $detrazione > 0 ? round($detrazione) : null;
    }

    private static function giornoDopo(\DateTimeInterface $data): string
    {
        return (new \DateTimeImmutable($data->format('Y-m-d')))->modify('+1 day')->format('Y-m-d');
    }

    private static function giornoPrima(string $data): string
    {
        return (new \DateTimeImmutable($data))->modify('-1 day')->format('Y-m-d');
    }
}
