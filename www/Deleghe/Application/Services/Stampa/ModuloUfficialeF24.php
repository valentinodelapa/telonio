<?php

namespace Deleghe\Application\Services\Stampa;

use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Entities\Soggetto;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\ModelloF24;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Services\ControlloDelega;
use Deleghe\Application\Services\ModelloCompilato;

/**
 * Compilazione dei modelli F24 ufficiali dell'Agenzia delle Entrate: per
 * ogni modello della ripartizione restituisce le pagine (sfondo SVG del
 * modello, generato con `sisma modelli-f24`) e i campi da sovrapporre, con
 * le coordinate in punti tipografici delle caselle del PDF ufficiale.
 *
 * I campi a caselle hanno il numero di celle: un carattere per cella. Gli
 * importi hanno i centesimi nelle ultime due celle (14,2 pt) e gli euro
 * allineati a destra prima della virgola prestampata.
 */
class ModuloUfficialeF24
{

    public const LARGHEZZA = 595.28;
    public const ALTEZZA = 841.89;
    private const RIGA = 12.0;
    private const CENTESIMI = 14.2;

    /**
     * Pagine del PDF ufficiale: 1ª e 2ª copia per la banca, copia per il
     * soggetto che effettua il versamento. Il semplificato ha una sola
     * pagina con la copia per il soggetto in alto e quella per la banca in
     * basso.
     */
    public const PAGINE = [
        ModelloF24::ordinario->value => 3,
        ModelloF24::semplificato->value => 1,
        ModelloF24::elide->value => 3,
        ModelloF24::accise->value => 3,
    ];
    public const PAGINA_SOGGETTO = 3;
    private const SFASAMENTO_SEMPLIFICATO = 408.0;

    /** @var array<int, array<string, mixed>> */
    private array $campi = [];

    public function __construct(private Delega $delega, private Soggetto $soggetto, private ?Indirizzo $domicilio)
    {

    }

    public static function getSfondo(ModelloF24 $modello, int $pagina): string
    {
        return '/svg/f24-' . self::getNome($modello) . '-' . $pagina . '.svg';
    }

    public static function getNome(ModelloF24 $modello): string
    {
        return match ($modello) {
            ModelloF24::ordinario => 'ordinario',
            ModelloF24::semplificato => 'semplificato',
            ModelloF24::elide => 'elide',
            ModelloF24::accise => 'accise',
        };
    }

    /**
     * @return array<int, array{sfondo: string, campi: array}>
     */
    public function getPagine(ModelloCompilato $modello, bool $tutteLeCopie): array
    {
        $tipo = $this->delega->modello;
        if ($tipo === ModelloF24::semplificato) {
            $campi = array_merge($this->compilaSemplificato($modello, 0.0, false), $this->compilaSemplificato($modello, self::SFASAMENTO_SEMPLIFICATO, true));
            return [['sfondo' => self::getSfondo($tipo, 1), 'campi' => $campi]];
        }
        $pagine = [];
        foreach ($tutteLeCopie ? range(1, self::PAGINE[$tipo->value]) : [self::PAGINA_SOGGETTO] as $pagina) {
            $pagine[] = ['sfondo' => self::getSfondo($tipo, $pagina), 'campi' => $this->compila($modello, $pagina !== self::PAGINA_SOGGETTO)];
        }
        return $pagine;
    }

    private function compila(ModelloCompilato $modello, bool $copiaBanca): array
    {
        $this->campi = [];
        return match ($this->delega->modello) {
            ModelloF24::ordinario => $this->compilaOrdinario($modello, $copiaBanca, 0.0),
            ModelloF24::accise => $this->compilaOrdinario($modello, $copiaBanca, -1.0),
            ModelloF24::elide => $this->compilaElide($modello),
            default => [],
        };
    }

    /**
     * Ordinario e accise: stesse caselle fino alla sezione IMU (l'accise è
     * spostato di 1 pt verso l'alto), poi INAIL e altri enti nell'ordinario,
     * sezione accise nell'accise.
     */
    private function compilaOrdinario(ModelloCompilato $modello, bool $copiaBanca, float $dy): array
    {
        $accise = $this->delega->modello === ModelloF24::accise;
        $this->contribuente(0.0, $dy, true);
        $importi = fn(float $y) => ['debito' => [322.2, 401.4, $y], 'credito' => [408.6, 487.8, $y]];
        $sezioneAtto = ControlloDelega::getSezioneCodiceAtto(array_column(array_merge(...array_values($modello->righe)), 'riga'));

        $this->sezione($modello, SezioneF24::erario, 240.0 + $dy, [
            [156.6, 214.2, 4, fn(Versamento $r) => $r->codiceTributo?->codice],
            [221.4, 264.6, 4, fn(Versamento $r) => $r->rateazione],
            [271.8, 315.0, 4, fn(Versamento $r) => $r->annoRiferimento],
        ], $importi, 312.0 + $dy);
        if ($sezioneAtto === SezioneF24::erario) {
            $this->celle(19.8, 63.0, 312.5 + $dy, 3, $this->delega->codiceUfficio);
            $this->celle($accise ? 79.3 : 77.4, $accise ? 238.2 : 236.3, 312.5 + $dy, 11, $this->delega->codiceAtto);
        }
        $this->sezione($modello, SezioneF24::inps, 348.3 + $dy, [
            [19.8, 48.6, 4, fn(Versamento $r) => $r->codiceSede],
            [55.8, 84.6, 4, fn(Versamento $r) => $r->causale],
            [91.8, 214.2, 17, fn(Versamento $r) => $r->posizione],
            [221.4, 264.6, 6, fn(Versamento $r) => $this->periodo($r->periodoDa)],
            [271.8, 315.0, 6, fn(Versamento $r) => $this->periodo($r->periodoA)],
        ], $importi, 396.3 + $dy);
        $this->sezione($modello, SezioneF24::regioni, 432.0 + $dy, [
            [19.9, 48.7, 2, fn(Versamento $r) => $r->codiceEnte],
            [156.6, 214.2, 4, fn(Versamento $r) => $r->codiceTributo?->codice],
            [221.4, 264.6, 4, fn(Versamento $r) => $r->rateazione],
            [271.8, 315.0, 4, fn(Versamento $r) => $r->annoRiferimento],
        ], $importi, 480.0 + $dy);
        $this->celle(314.9, 574.1, 494.8 + $dy, 18, $this->delega->identificativoOperazione, 8.5);
        $this->sezione($modello, SezioneF24::tributiLocali, 516.0 + $dy, [
            [20.3, 63.5, 4, fn(Versamento $r) => $r->codiceEnte],
            [73.6, 83.0, 1, fn(Versamento $r) => $this->x($r->ravvedimento)],
            [87.6, 97.0, 1, fn(Versamento $r) => $this->x($r->immobiliVariati)],
            [102.0, 111.4, 1, fn(Versamento $r) => $this->x($r->acconto)],
            [116.1, 125.4, 1, fn(Versamento $r) => $this->x($r->saldo)],
            [131.1, 145.5, 3, fn(Versamento $r) => $r->numeroImmobili],
            [156.6, 214.2, 4, fn(Versamento $r) => $r->codiceTributo?->codice],
            [221.4, 264.6, 4, fn(Versamento $r) => $r->rateazione],
            [271.8, 315.0, 4, fn(Versamento $r) => $r->annoRiferimento],
        ], $importi, 564.0 + $dy);
        if ($modello->conDetrazioneImu && ($this->delega->detrazioneImu !== null)) {
            $this->importo(73.6, 152.8, 564.6 + $dy, (int) round($this->delega->detrazioneImu * 100));
        }
        if ($accise) {
            $this->sezione($modello, SezioneF24::accise, 599.0, [
                [20.8, 42.4, 2, fn(Versamento $r) => $r->codiceTributo?->enteAccise],
                [42.4, 64.0, 2, fn(Versamento $r) => $r->provincia],
                [68.4, 111.6, 4, fn(Versamento $r) => $r->codiceTributo?->codice],
                [115.2, 198.0, 0, fn(Versamento $r) => $r->codiceIdentificativoEnte],
                [202.2, 245.4, 4, fn(Versamento $r) => $r->rateazione],
                [252.0, 266.6, 2, fn(Versamento $r) => $r->meseRiferimento === null ? null : sprintf('%02d', $r->meseRiferimento)],
                [271.9, 315.1, 4, fn(Versamento $r) => $r->annoRiferimento],
            ], fn(float $y) => ['debito' => [322.2, 401.4, $y]], 683.3);
            if ($sezioneAtto === SezioneF24::accise) {
                $this->celle(46.8, 90.0, 683.7, 3, $this->delega->codiceUfficio);
                $this->celle(115.2, 273.6, 683.7, 11, $this->delega->codiceAtto);
            }
            $this->importo(487.9, 574.3, 707.0, $modello->getSaldo());
            if ($copiaBanca) {
                $this->iban(122.8, 317.2, 801.5);
            }
            return $this->campi;
        }
        $this->sezione($modello, SezioneF24::inail, 600.0, [
            [99.0, 135.0, 5, fn(Versamento $r) => $r->codiceSede],
            [142.2, 199.8, 8, fn(Versamento $r) => $r->posizione],
            [207.0, 221.4, 2, fn(Versamento $r) => $r->cifraControllo],
            [236.0, 279.2, 6, fn(Versamento $r) => $r->numeroRiferimento],
            [286.2, 300.6, 1, fn(Versamento $r) => $r->causale],
        ], $importi, 636.0);
        $this->celle(19.8, 63.0, 660.0, 4, $modello->getCodiceAltroEnte());
        $this->sezione($modello, SezioneF24::altriEnti, 660.0, [
            [63.0, 99.0, 5, fn(Versamento $r) => $r->codiceSede],
            [106.2, 135.0, 4, fn(Versamento $r) => $r->causale],
            [142.2, 214.2, 9, fn(Versamento $r) => $r->posizione],
            [221.4, 264.6, 6, fn(Versamento $r) => $this->periodo($r->periodoDa)],
            [271.8, 315.0, 6, fn(Versamento $r) => $this->periodo($r->periodoA)],
        ], $importi, 684.0);
        $this->importo(487.9, 574.3, 708.0, $modello->getSaldo());
        if ($copiaBanca) {
            $this->iban(122.8, 317.2, 802.5);
        }
        return $this->campi;
    }

    private function compilaElide(ModelloCompilato $modello): array
    {
        $this->contribuente(2.0, -0.2, true);
        $this->celle(32.1, 75.3, 264.4, 3, $this->delega->codiceUfficio);
        $this->celle(87.2, 246.1, 264.4, 11, $this->delega->codiceAtto);
        $this->sezione($modello, SezioneF24::elide, 312.1, [
            [32.5, 46.9, 1, fn(Versamento $r) => $r->tipoElide],
            [57.7, 302.5, 17, fn(Versamento $r) => $r->elementiIdentificativi],
            [309.7, 352.9, 4, fn(Versamento $r) => $r->codiceTributo?->codice],
            [360.1, 403.3, 4, fn(Versamento $r) => $r->annoRiferimento],
        ], fn(float $y) => ['debito' => [410.5, 576.2, $y]], null);
        $this->importo(410.5, 576.2, 696.1, $modello->getSaldo());
        return $this->campi;
    }

    /**
     * Metà del semplificato (dy 0 copia per il soggetto, 408 per la banca):
     * dieci righi con la sezione (ER, RG, EL) e i campi comuni.
     */
    private function compilaSemplificato(ModelloCompilato $modello, float $dy, bool $copiaBanca): array
    {
        $this->campi = [];
        $this->contribuente(0.0, $dy - 13.0, false);
        $this->celle(354.9, 398.1, 96.8 + $dy, 3, $this->delega->codiceUfficio);
        $this->celle(408.1, 567.0, 96.8 + $dy, 11, $this->delega->codiceAtto);
        $this->celle(314.9, 574.1, 181.7 + $dy, 18, $this->delega->identificativoOperazione, 8.5);
        $sigle = [SezioneF24::erario->value => 'ER', SezioneF24::regioni->value => 'RG', SezioneF24::tributiLocali->value => 'EL'];
        $voci = array_merge(...array_map(fn(SezioneF24 $sezione) => $modello->righe[$sezione->value], ModelloF24::semplificato->getSezioni()));
        $detrazione = $modello->conDetrazioneImu ? $this->delega->detrazioneImu : null;
        foreach (array_values($voci) as $indice => ['riga' => $riga, 'debito' => $debito, 'credito' => $credito]) {
            $y = 203.9 + $dy + ($indice * self::RIGA);
            $locale = $riga->sezione === SezioneF24::tributiLocali;
            $this->celle(30.8, 66.8, $y, 2, $sigle[$riga->sezione->value]);
            $this->celle(77.4, 113.4, $y, 4, $riga->codiceTributo?->codice);
            $this->celle(117.7, 160.9, $y, 4, $riga->codiceEnte);
            if ($locale) {
                $this->celle(167.6, 180.6, $y, 1, $this->x($riga->ravvedimento));
                $this->celle(187.8, 200.6, $y, 1, $this->x($riga->immobiliVariati));
                $this->celle(207.6, 220.5, $y, 1, $this->x($riga->acconto));
                $this->celle(227.1, 240.1, $y, 1, $this->x($riga->saldo));
                $this->celle(245.7, 263.7, $y, 3, $riga->numeroImmobili);
            }
            $this->celle(268.2, 304.2, $y, 4, $riga->rateazione);
            $this->celle(308.3, 344.3, $y, 4, $riga->annoRiferimento);
            if ($locale && ($detrazione !== null)) {
                $this->importo(349.3, 399.7, $y, (int) round($detrazione * 100));
                $detrazione = null;
            }
            $this->importo(405.0, 484.3, $y, $debito);
            $this->importo(487.5, 567.0, $y, $credito);
        }
        $this->importo(480.6, 567.0, 324.4 + $dy, $modello->getSaldo());
        if ($copiaBanca) {
            $this->iban(372.6, 567.0, 336.4 + $dy);
        }
        return $this->campi;
    }

    /**
     * Riquadro del contribuente, con le coordinate dell'ordinario spostate
     * di dx e dy; il semplificato (13 pt più in alto) non ha il domicilio
     * fiscale e ha il coobbligato subito dopo i dati di nascita.
     */
    private function contribuente(float $dx, float $dy, bool $conDomicilio): void
    {
        $semplificato = $conDomicilio === false;
        $personaFisica = $this->soggetto->isPersonaFisica();
        $this->celle(113.4 + $dx, 343.8 + $dx, 105.6 + $dy, 16, $this->soggetto->codiceFiscale, 10.0, 14.4);
        $this->testo(113.4 + $dx, 408.6 + $dx, 129.6 + $dy, mb_strtoupper($personaFisica ? (string) $this->soggetto->cognome : $this->soggetto->nominativo));
        $this->testo(415.8 + $dx, 567.0 + $dx, 129.6 + $dy, mb_strtoupper($personaFisica ? (string) $this->soggetto->nome : ''));
        if ($personaFisica) {
            $nascita = $this->soggetto->dataNascita;
            $this->celle(113.4 + $dx, 142.2 + $dx, 153.6 + $dy, 2, $nascita?->format('d'), 10.0, 14.4);
            $this->celle(142.2 + $dx, 228.6 + $dx, 153.6 + $dy, 6, $nascita?->format('mY'), 10.0, 14.4);
            $this->celle(243.1 + $dx, 257.5 + $dx, 153.6 + $dy, 1, $this->soggetto->sesso?->value, 10.0, 14.4);
            $this->testo(271.8 + $dx, 530.1 + $dx, 153.6 + $dy, mb_strtoupper($this->soggetto->comuneNascita?->denominazione ?? $this->soggetto->nazioneNascita?->denominazione ?? ''), 14.4);
            $this->celle(538.0 + $dx, 566.8 + $dx, 153.6 + $dy, 2, $this->soggetto->comuneNascita?->siglaProvincia ?? 'EE', 10.0, 14.4);
        }
        $coobbligato = 201.6;
        if ($semplificato) {
            $coobbligato = 177.6;
        } else {
            $this->testo(113.4 + $dx, 322.2 + $dx, 177.6 + $dy, mb_strtoupper($this->domicilio?->comune?->denominazione ?? $this->domicilio?->localitaEstera ?? ''), 14.4);
            $this->celle(329.4 + $dx, 358.2 + $dx, 177.6 + $dy, 2, $this->domicilio?->comune?->siglaProvincia, 10.0, 14.4);
            $this->testo(365.4 + $dx, 567.0 + $dx, 177.6 + $dy, mb_strtoupper($this->domicilio?->getRiga1() ?? ''), 14.4);
        }
        $this->celle(185.4 + $dx, 415.8 + $dx, $coobbligato + $dy, 16, $this->delega->codiceFiscaleCoobbligato, 10.0, 14.4);
        $this->celle(538.0 + $dx, 566.8 + $dx, $coobbligato + $dy, 2, $this->delega->codiceIdentificativo?->value, 10.0, 14.4);
    }

    /**
     * @param array<int, array{0: float, 1: float, 2: int, 3: \Closure}> $colonne x iniziale, x finale, celle (0 = testo), valore
     * @param \Closure(float): array<string, array{0: float, 1: float, 2: float}> $importi colonne degli importi per il rigo
     */
    private function sezione(ModelloCompilato $modello, SezioneF24 $sezione, float $y0, array $colonne, \Closure $importi, ?float $yTotali): void
    {
        foreach (array_values($modello->righe[$sezione->value]) as $indice => $voce) {
            $y = $y0 + ($indice * self::RIGA);
            foreach ($colonne as [$x0, $x1, $celle, $valore]) {
                $testo = $valore($voce['riga']);
                $celle === 0 ? $this->testo($x0, $x1, $y, (string) $testo, self::RIGA, true) : $this->celle($x0, $x1, $y, $celle, $testo);
            }
            foreach ($importi($y) as $tipo => [$x0, $x1]) {
                $this->importo($x0, $x1, $y, $voce[$tipo]);
            }
        }
        if (($yTotali === null) || (count($modello->righe[$sezione->value]) === 0)) {
            return;
        }
        $totali = $modello->getTotaliSezione($sezione);
        foreach ($importi($yTotali) as $tipo => [$x0, $x1]) {
            $this->importo($x0, $x1, $yTotali, $totali[$tipo], true);
        }
        $this->importo(487.9, 574.3, $yTotali, $totali['debito'] - $totali['credito'], true);
    }

    private function celle(float $x0, float $x1, float $y, int $celle, int|string|null $valore, float $dimensione = 9.0, float $altezza = self::RIGA): void
    {
        if (($valore === null) || ($valore === '')) {
            return;
        }
        $testo = mb_strtoupper(mb_substr((string) $valore, 0, $celle));
        $this->campi[] = ['x' => $x0, 'y' => $y, 'larghezza' => $x1 - $x0, 'altezza' => $altezza, 'testo' => $testo, 'cella' => ($x1 - $x0) / $celle, 'dimensione' => $dimensione];
    }

    private function testo(float $x0, float $x1, float $y, string $testo, float $altezza = 14.5, bool $monospazio = false): void
    {
        if ($testo === '') {
            return;
        }
        $this->campi[] = ['x' => $x0 + 2.0, 'y' => $y, 'larghezza' => $x1 - $x0 - 4.0, 'altezza' => $altezza, 'testo' => $testo, 'cella' => null, 'dimensione' => $monospazio ? 8.5 : 8.0];
    }

    /**
     * Importo in centesimi: euro allineati a destra prima della virgola,
     * centesimi nelle due celle finali; zero lasciato in bianco tranne che
     * nei totali e nei saldi.
     */
    private function importo(float $x0, float $x1, float $y, int $centesimi, bool $ancheZero = false): void
    {
        if (($centesimi === 0) && ($ancheZero === false)) {
            return;
        }
        $assoluto = abs($centesimi);
        $euro = ($centesimi < 0 ? '-' : '') . number_format(intdiv($assoluto, 100), 0, '', '.');
        $this->campi[] = ['x' => $x0, 'y' => $y, 'larghezza' => $x1 - $x0 - self::CENTESIMI - 4.0, 'altezza' => self::RIGA, 'testo' => $euro, 'cella' => null, 'dimensione' => 9.0, 'destra' => true];
        $this->campi[] = ['x' => $x1 - self::CENTESIMI, 'y' => $y, 'larghezza' => self::CENTESIMI, 'altezza' => self::RIGA, 'testo' => sprintf('%02d', $assoluto % 100), 'cella' => self::CENTESIMI / 2, 'dimensione' => 9.0];
    }

    /**
     * IBAN del conto di addebito nelle copie per la banca: le prime due
     * celle hanno "IT" prestampato.
     */
    private function iban(float $x0, float $x1, float $y): void
    {
        $iban = $this->delega->contoAddebito?->iban;
        if (($iban === null) || (str_starts_with($iban, 'IT') === false)) {
            return;
        }
        $cella = ($x1 - $x0) / 27;
        $this->celle($x0 + (2 * $cella), $x1, $y, 25, substr($iban, 2), 8.5);
    }

    private function periodo(?string $periodo): ?string
    {
        return $periodo === null ? null : str_replace('/', '', $periodo);
    }

    private function x(bool $valore): ?string
    {
        return $valore ? 'X' : null;
    }
}
