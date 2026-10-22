<?php

namespace Successioni\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\CodiceIdentificativo;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\HelperClasses\Calendario;
use Deleghe\Application\Models\CodiceTributoModel;
use Deleghe\Application\Models\VersamentoModel;
use Deleghe\Application\Services\ArchivioVersamenti;
use Deleghe\Application\Services\CalcoloRavvedimento;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;
use Successioni\Application\Entities\DichiarazioneSuccessione;
use Successioni\Application\Enumerations\TempisticaPagamento;

/**
 * Versamenti F24 dell'imposta di successione autoliquidata, uno per
 * beneficiario sulla sua quota d'imposta (gli eredi sono obbligati in
 * solido, i legatari per la propria quota: art. 36 TUS), registrati
 * nell'archivio di Deleghe F24 con origine "Successioni".
 *
 * Modalità (risoluzione n. 2/E del 10 gennaio 2025): sezione Erario,
 * codice 1539 con rateazione "NNRR" ("0101" per l'unica soluzione e per
 * l'acconto), anno di riferimento = anno del decesso, codice fiscale del
 * beneficiario come contribuente e del defunto come secondo codice
 * fiscale con codice identificativo 08; interessi della rateazione con il
 * codice 1635.
 *
 * Tempistiche (rigo EF18ter): con pagamento contestuale l'imposta (o
 * l'acconto) è addebitata con la dichiarazione e non ha F24; altrimenti si
 * versa entro 90 giorni dal termine di presentazione. Rateazione (art. 38
 * TUS): acconto di almeno il 20%, resto in rate trimestrali di pari
 * importo (fino a 8, fino a 12 oltre 20.000 euro; non sotto i 1.000 euro)
 * che scadono l'ultimo giorno di ciascun trimestre dal versamento
 * dell'acconto, con gli interessi dal giorno successivo al versamento
 * dell'acconto.
 */
class PianoVersamenti
{

    public const ORIGINE = 'Successioni';
    public const CODICE_IMPOSTA = '1539';
    public const CODICE_INTERESSI = '1635';
    public const CODICE_SANZIONE_PRESENTAZIONE = '1549';
    public const CODICE_SANZIONE_RAVVEDIMENTO = '1535';
    public const CODICE_INTERESSI_RAVVEDIMENTO = '1537';
    public const MINIMO_RATEIZZABILE = 1000;
    public const SOGLIA_DODICI_RATE = 20000;
    public const ACCONTO_MINIMO = 0.20;

    /**
     * Tasso annuo degli interessi sulle rate, a scalare dal giorno
     * successivo al versamento dell'acconto (art. 38, c. 2, TUS). Misura
     * indicata per la rateazione dell'imposta di successione (3%, art. 5,
     * c. 3, D.M. 21 maggio 2009 come richiamato dalla prassi): da
     * verificare a ogni modifica normativa.
     */
    public const TASSO_INTERESSI = 3.0;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Rate dell'imposta da versare: unica soluzione o acconto, poi le rate.
     *
     * @return array<array{numero: int, scadenza: SismaDate, importo: int, interessi: int, conDichiarazione: bool, rateazione: string}>
     *         importi in centesimi per l'intera imposta
     */
    public function getRate(DichiarazioneSuccessione $dichiarazione, RisultatoLiquidazione $risultato): array
    {
        $daVersare = $risultato->successioneDaVersare * 100;
        if ($daVersare === 0) {
            return [];
        }
        $contestuale = $dichiarazione->tempisticaPagamento === TempisticaPagamento::contestuale;
        $dataPrimo = $contestuale ? ($dichiarazione->dataPresentazione ?? $dichiarazione->dataDichiarazione ?? new SismaDate()) : ($dichiarazione->dataVersamento ?? $dichiarazione->getScadenzaPagamento());
        $dataPrimo = new SismaDate($dataPrimo->format('Y-m-d'));
        if ($dichiarazione->numeroRate === null) {
            return [['numero' => 0, 'scadenza' => $dataPrimo, 'importo' => $daVersare, 'interessi' => 0, 'conDichiarazione' => $contestuale, 'rateazione' => '0101']];
        }
        $acconto = (int) $dichiarazione->acconto * 100;
        $rate = [['numero' => 0, 'scadenza' => $dataPrimo, 'importo' => $acconto, 'interessi' => 0, 'conDichiarazione' => $contestuale, 'rateazione' => '0101']];
        $numero = $dichiarazione->numeroRate;
        $residuo = $daVersare - $acconto;
        $base = intdiv($residuo, $numero);
        for ($rata = 1; $rata <= $numero; $rata++) {
            $importo = $rata === $numero ? $residuo - $base * ($numero - 1) : $base;
            $nominale = (new \DateTimeImmutable($dataPrimo->format('Y-m-01')))->modify('+' . (3 * $rata) . ' months')->modify('last day of this month');
            $giorni = (int) (new \DateTimeImmutable($dataPrimo->format('Y-m-d')))->diff($nominale)->days;
            $rate[] = ['numero' => $rata, 'scadenza' => Calendario::primoGiornoLavorativo($nominale), 'importo' => $importo,
                'interessi' => (int) round($importo * self::TASSO_INTERESSI / 100 * $giorni / 365), 'conDichiarazione' => false,
                'rateazione' => sprintf('%02d%02d', $rata, $numero)];
        }
        return $rate;
    }

    /**
     * Ripartisce un importo in centesimi tra i beneficiari in proporzione
     * all'imposta netta della loro quota (resti ai maggiori decimali).
     *
     * @return array<int, int> centesimi per id del beneficiario
     */
    public static function ripartisci(int $importo, RisultatoLiquidazione $risultato): array
    {
        $pesi = [];
        foreach ($risultato->quote as $id => $quota) {
            if ($quota->impostaNetta > 0) {
                $pesi[$id] = $quota->impostaNetta;
            }
        }
        $totale = array_sum($pesi);
        if (($totale <= 0) || ($importo === 0)) {
            return [];
        }
        $parti = $resti = [];
        foreach ($pesi as $id => $peso) {
            $esatto = $importo * $peso / $totale;
            $parti[$id] = (int) floor($esatto);
            $resti[$id] = $esatto - $parti[$id];
        }
        arsort($resti);
        foreach (array_slice(array_keys($resti), 0, $importo - array_sum($parti)) as $id) {
            $parti[$id]++;
        }
        return $parti;
    }

    /**
     * Versamenti F24 per beneficiario (non salvati).
     *
     * @return array<int, Versamento[]> per id del soggetto beneficiario
     */
    public function getVersamenti(DatiDichiarazione $dati, RisultatoLiquidazione $risultato): array
    {
        $dichiarazione = $dati->dichiarazione;
        $beneficiari = [];
        foreach ($dati->beneficiari as $beneficiario) {
            $beneficiari[$beneficiario->id] = $beneficiario;
        }
        $codiceTributoModel = new CodiceTributoModel($this->dataMapper);
        $codici = [];
        foreach ([self::CODICE_IMPOSTA, self::CODICE_INTERESSI, self::CODICE_SANZIONE_PRESENTAZIONE, self::CODICE_SANZIONE_RAVVEDIMENTO, self::CODICE_INTERESSI_RAVVEDIMENTO] as $codice) {
            $codici[$codice] = $codiceTributoModel->getByCodice(SezioneCodiceTributo::erario, $codice)
                    ?? throw new \RuntimeException('Codice tributo ' . $codice . ' non presente: aggiornare le tabelle F24 (sisma tabelle-f24).');
        }
        $imposta = $codici[self::CODICE_IMPOSTA];
        $interessi = $codici[self::CODICE_INTERESSI];
        $cognome = $dichiarazione->defunto->cognome;
        $versamenti = [];
        $rate = $this->getRate($dichiarazione, $risultato);
        $primaData = $rate[0]['scadenza'] ?? $dichiarazione->dataVersamento ?? $dichiarazione->getScadenzaPagamento();
        if ($risultato->sanzionePresentazione > 0) {
            foreach (self::ripartisci((int) round($risultato->sanzionePresentazione * 100), $risultato) as $id => $centesimi) {
                $versamenti[$beneficiari[$id]->soggetto->id][] = $this->crea($dichiarazione, $primaData, $codici[self::CODICE_SANZIONE_PRESENTAZIONE], null, $centesimi, 'Sanzione tardiva presentazione successione ' . $cognome);
            }
        }
        foreach ($rate as $rata) {
            if ($rata['conDichiarazione']) {
                continue;
            }
            if ($rata['numero'] === 0) {
                $this->ravvediTardivo($dichiarazione, $rata, $risultato, $beneficiari, $codici, $versamenti);
            }
            $quoteInteressi = self::ripartisci($rata['interessi'], $risultato);
            foreach (self::ripartisci($rata['importo'], $risultato) as $id => $centesimi) {
                $beneficiario = $beneficiari[$id];
                $descrizione = 'Imposta di successione ' . $dichiarazione->defunto->cognome . ($rata['numero'] === 0 ? ($dichiarazione->numeroRate === null ? '' : ' - acconto') : ' - rata ' . $rata['numero'] . '/' . $dichiarazione->numeroRate);
                $versamenti[$beneficiario->soggetto->id][] = $this->crea($dichiarazione, $rata['scadenza'], $imposta, $rata['rateazione'], $centesimi, $descrizione);
                if (($quoteInteressi[$id] ?? 0) > 0) {
                    $versamenti[$beneficiario->soggetto->id][] = $this->crea($dichiarazione, $rata['scadenza'], $interessi, null, $quoteInteressi[$id], 'Interessi rata ' . $rata['numero'] . '/' . $dichiarazione->numeroRate . ' successione ' . $dichiarazione->defunto->cognome);
                }
            }
        }
        return $versamenti;
    }

    /**
     * Unica soluzione o acconto versati con F24 dopo i 90 giorni dal termine
     * di presentazione: ravvedimento con sanzione ridotta (codice 1535) e
     * interessi legali (codice 1537), per beneficiario.
     */
    private function ravvediTardivo(DichiarazioneSuccessione $dichiarazione, array $rata, RisultatoLiquidazione $risultato, array $beneficiari, array $codici, array &$versamenti): void
    {
        $scadenza = $dichiarazione->getScadenzaPagamento();
        $giorni = Ravvedimento::giorni($scadenza, $rata['scadenza']);
        if ($giorni <= 0) {
            return;
        }
        $sanzione = (int) round($rata['importo'] * Ravvedimento::getPercentualePagamento($giorni) / 100);
        $interessi = (int) round((new CalcoloRavvedimento($this->dataMapper))->calcolaInteressi($rata['importo'] / 100, $scadenza, $rata['scadenza'])[0] * 100);
        $cognome = $dichiarazione->defunto->cognome;
        foreach ([[$sanzione, self::CODICE_SANZIONE_RAVVEDIMENTO, 'Sanzione ravvedimento imposta successione '], [$interessi, self::CODICE_INTERESSI_RAVVEDIMENTO, 'Interessi ravvedimento imposta successione ']] as [$importo, $codice, $descrizione]) {
            foreach (self::ripartisci($importo, $risultato) as $id => $centesimi) {
                $versamenti[$beneficiari[$id]->soggetto->id][] = $this->crea($dichiarazione, $rata['scadenza'], $codici[$codice], null, $centesimi, $descrizione . $cognome);
            }
        }
        $risultato->avviso(sprintf('Imposta di successione versata con %d giorni di ritardo: ravvedimento con F24 (codici 1535 e 1537).', $giorni));
    }

    private function crea(DichiarazioneSuccessione $dichiarazione, SismaDate $scadenza, $codiceTributo, ?string $rateazione, int $centesimi, string $descrizione): Versamento
    {
        $versamento = new Versamento($this->dataMapper);
        $versamento->scadenza = $scadenza;
        $versamento->descrizione = mb_substr($descrizione, 0, 100);
        $versamento->sezione = SezioneF24::erario;
        $versamento->codiceTributo = $codiceTributo;
        $versamento->rateazione = $rateazione;
        $versamento->annoRiferimento = (int) $dichiarazione->getDataApertura()->format('Y');
        $versamento->importoDebito = $centesimi / 100;
        $versamento->codiceFiscaleCoobbligato = $dichiarazione->defunto->codiceFiscale;
        $versamento->codiceIdentificativo = CodiceIdentificativo::defunto;
        return $versamento;
    }

    /**
     * Registra i versamenti nell'archivio di Deleghe F24 per ogni
     * beneficiario, sostituendo quelli registrati in precedenza (anche per
     * i beneficiari che non hanno più imposta da versare).
     *
     * @return string[] differenze con versamenti già in deleghe confermate
     */
    public function registra(DatiDichiarazione $dati, RisultatoLiquidazione $risultato): array
    {
        $dichiarazione = $dati->dichiarazione;
        $perSoggetto = $this->getVersamenti($dati, $risultato);
        $soggetti = [];
        foreach ($dati->beneficiari as $beneficiario) {
            $soggetti[$beneficiario->soggetto->id] = $beneficiario->soggetto;
        }
        foreach ($this->getSoggettiRegistrati($dichiarazione) as $soggetto) {
            $soggetti[$soggetto->id] ??= $soggetto;
        }
        $archivio = new ArchivioVersamenti($this->dataMapper);
        $differenze = [];
        foreach ($soggetti as $id => $soggetto) {
            $esito = $archivio->registra($soggetto, self::ORIGINE, $dichiarazione->getRiferimentoVersamenti(), $perSoggetto[$id] ?? []);
            foreach ($esito->differenze as $differenza) {
                $differenze[] = $soggetto->nominativo . ': ' . $differenza;
            }
        }
        return $differenze;
    }

    /**
     * Rimuove i versamenti non ancora in deleghe confermate (dichiarazione
     * eliminata).
     */
    public function annulla(DichiarazioneSuccessione $dichiarazione): void
    {
        $archivio = new ArchivioVersamenti($this->dataMapper);
        foreach ($this->getSoggettiRegistrati($dichiarazione) as $soggetto) {
            $archivio->registra($soggetto, self::ORIGINE, $dichiarazione->getRiferimentoVersamenti(), []);
        }
    }

    /**
     * @return Versamento[] versamenti registrati per la dichiarazione
     */
    public function getRegistrati(DichiarazioneSuccessione $dichiarazione): array
    {
        return iterator_to_array((new VersamentoModel($this->dataMapper))->getByStudioAndOrigineAndRiferimentoOrigine($dichiarazione->studio, self::ORIGINE, $dichiarazione->getRiferimentoVersamenti(), null, ['scadenza' => 'ASC', 'id' => 'ASC']), false);
    }

    /**
     * @return Soggetto[]
     */
    private function getSoggettiRegistrati(DichiarazioneSuccessione $dichiarazione): array
    {
        $soggetti = [];
        foreach ($this->getRegistrati($dichiarazione) as $versamento) {
            $soggetti[$versamento->soggetto->id] = $versamento->soggetto;
        }
        return $soggetti;
    }
}
