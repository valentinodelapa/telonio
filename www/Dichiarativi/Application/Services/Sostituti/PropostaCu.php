<?php

namespace Dichiarativi\Application\Services\Sostituti;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\NazioneModel;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Enumerations\CausaleCu;
use Contabilita\Application\Services\RitenuteOperate;
use Deleghe\Application\Services\DatiContribuente;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;
use Telonio\Application\HelperClasses\CodiceFiscale;

/**
 * Proposta della Certificazione Unica dei compensi di lavoro autonomo,
 * delle provvigioni e dei redditi diversi pagati dall'azienda come
 * sostituto d'imposta (art. 4, c. 6-ter, D.P.R. 322/1998; istruzioni e
 * specifiche tecniche CU 2026): una certificazione (modulo della
 * dichiarazione) per percipiente, dalle ritenute d'acconto operate
 * nell'anno e registrate in contabilità con il percipiente come
 * controparte (RitenuteOperate), per cassa.
 *
 * - DA1: dati del sostituto (domicilio fiscale, recapiti, codice
 *   attività);
 * - DA2: dati del percipiente (cognome e nome della controparte; sesso,
 *   data e luogo di nascita ricavati dal codice fiscale);
 * - DA3: data e firma;
 * - AU1: causale della controparte (in mancanza "A", con un avviso),
 *   imponibile ricavato dalle ritenute con l'aliquota della causale,
 *   compenso lordo dall'imponibile con la quota soggetta a ritenuta (per
 *   i diritti d'autore 60 per cento invece di 75 se il percipiente ha
 *   meno di 35 anni al pagamento), la parte non soggetta nel punto 7 con
 *   il codice 21 (quota delle provvigioni) o 22 (deduzioni forfetarie dei
 *   diritti d'autore e delle vendite a domicilio), ritenute a titolo
 *   d'acconto.
 *
 * Compensi senza ritenuta (regime forfetario, codici 24 e 25 del punto
 * 6), rimborsi spese (punto 20) e contributi previdenziali si aggiungono a
 * mano: i valori inseriti a mano restano alle proposte successive e i
 * moduli dei percipienti senza ritenute con dati inseriti a mano non sono
 * eliminati.
 */
class PropostaCu
{

    public const QUADRI = ['DA', 'AU'];

    /** Codici del punto 6 per la parte del compenso non soggetta a ritenuta (getCodiceNonSoggette) */
    public const CODICE_NON_SOGGETTE = 21;
    public const CODICE_NON_REDDITO = 22;

    /** @var string[] */
    private array $avvisi = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return string[] avvisi
     */
    public function proponi(Dichiarazione $dichiarazione, ?QuadriDichiarazione $quadri = null): array
    {
        $this->avvisi = [];
        $quadri ??= new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $percipienti = $this->getPercipienti($dichiarazione->soggetto, $dichiarazione->anno);
        // le certificazioni già proposte conservano il loro progressivo (modulo)
        $moduli = [];
        foreach ($quadri->getModuli() as $modulo) {
            $codiceFiscale = $quadri->get('DA002001', $modulo);
            if (($codiceFiscale !== null) && isset($percipienti[$codiceFiscale]) && (in_array($codiceFiscale, $moduli, true) === false)) {
                $moduli[$modulo] = $codiceFiscale;
            }
        }
        $prossimo = max([0, ...$quadri->getModuli()]) + 1;
        foreach (array_keys($percipienti) as $codiceFiscale) {
            if (in_array($codiceFiscale, $moduli, true) === false) {
                $moduli[$prossimo++] = $codiceFiscale;
            }
        }
        $sostituto = $this->getDatiSostituto($dichiarazione);
        foreach ($quadri->getModuli() as $modulo) {
            if (isset($moduli[$modulo])) {
                continue;
            }
            // percipiente senza più ritenute: resta solo con dati inseriti a mano
            $quadri->usaModulo($modulo)->avviaProposta(...self::QUADRI);
            if ($this->haValoriManuali($quadri, $modulo)) {
                self::proponiSostituto($quadri, $sostituto);
            } else {
                $quadri->eliminaModulo($modulo);
            }
        }
        ksort($moduli);
        foreach ($moduli as $modulo => $codiceFiscale) {
            $percipiente = $percipienti[$codiceFiscale];
            $quadri->usaModulo($modulo)->avviaProposta(...self::QUADRI);
            self::proponiSostituto($quadri, $sostituto);
            $this->proponiPercipiente($quadri, $percipiente['controparte']);
            $causale = $percipiente['controparte']->causaleCu;
            if ($causale === null) {
                $causale = CausaleCu::professionista;
                $this->avvisi[] = sprintf('%s: causale della Certificazione Unica non indicata nella controparte, proposta "A" (lavoro autonomo abituale).', $percipiente['controparte']->denominazione);
            }
            self::proponiCompensi($quadri, $causale, $percipiente['ritenute'], $percipiente['ritenuteSotto35Anni']);
        }
        if (count($moduli) === 0) {
            $this->avvisi[] = 'Nessuna ritenuta d\'acconto registrata nell\'anno con un percipiente: le certificazioni si compilano a mano.';
        }
        $quadri->usaModulo(1);
        array_push($this->avvisi, ...$quadri->getDifferenzeManuali());
        $quadri->salva();
        $dichiarazione->dataElaborazione = new SismaDateTime();
        $dichiarazione->avvisi = count($this->avvisi) > 0 ? implode("\n", $this->avvisi) : null;
        $this->dataMapper->save($dichiarazione);
        return $this->avvisi;
    }

    /**
     * Percipienti con le ritenute operate nell'anno, per codice fiscale,
     * nell'ordine della denominazione; le controparti senza codice fiscale
     * sono segnalate.
     *
     * @return array<string, array{controparte: Controparte, ritenute: float, ritenuteSotto35Anni: float}>
     */
    public function getPercipienti(Soggetto $soggetto, int $anno): array
    {
        $percipienti = [];
        foreach ((new RitenuteOperate($this->dataMapper))->getPerMese($soggetto, $anno) as $mese) {
            foreach ($mese['righe'] as ['riga' => $riga, 'importo' => $importo]) {
                $controparte = $riga->registrazioneContabile->controparte;
                $codiceFiscale = CodiceFiscale::normalizza((string) $controparte->codiceFiscale);
                if ($codiceFiscale === '') {
                    $this->avvisi[] = sprintf('%s: codice fiscale mancante nella controparte, ritenute di € %s non certificate.', $controparte->denominazione, number_format($importo, 2, ',', '.'));
                    continue;
                }
                $percipienti[$codiceFiscale] ??= ['controparte' => $controparte, 'ritenute' => 0.0, 'ritenuteSotto35Anni' => 0.0];
                $percipienti[$codiceFiscale]['ritenute'] = round($percipienti[$codiceFiscale]['ritenute'] + $importo, 2);
                if (self::isSotto35Anni($codiceFiscale, $riga->registrazioneContabile->dataRegistrazione)) {
                    $percipienti[$codiceFiscale]['ritenuteSotto35Anni'] = round($percipienti[$codiceFiscale]['ritenuteSotto35Anni'] + $importo, 2);
                }
            }
        }
        foreach ($percipienti as $codiceFiscale => $percipiente) {
            if ($percipiente['ritenute'] <= 0) {
                $this->avvisi[] = sprintf('%s: ritenute dell\'anno non positive (€ %s), certificazione non proposta.', $percipiente['controparte']->denominazione, number_format($percipiente['ritenute'], 2, ',', '.'));
                unset($percipienti[$codiceFiscale]);
            }
        }
        uasort($percipienti, fn(array $a, array $b) => strcasecmp($a['controparte']->denominazione, $b['controparte']->denominazione));
        return $percipienti;
    }

    /**
     * Il percipiente (persona fisica, età dal codice fiscale) non ha ancora
     * 35 anni alla data del pagamento.
     */
    public static function isSotto35Anni(string $codiceFiscale, \DateTimeInterface $pagamento): bool
    {
        if (CodiceFiscale::isValidoPersonaFisica($codiceFiscale) === false) {
            return false;
        }
        return CodiceFiscale::estraiDataNascita($codiceFiscale, $pagamento)->modify('+35 years') > $pagamento;
    }

    /**
     * Imponibile (ritenute diviso l'aliquota), compenso lordo (imponibile
     * diviso la quota soggetta a ritenuta) e parte non soggetta.
     *
     * @return array{lordo: float, imponibile: float, nonSoggette: float}
     */
    public static function getCompensi(CausaleCu $causale, float $ritenute, float $ritenuteSotto35Anni = 0.0): array
    {
        $imponibile = round($ritenute * 100 / $causale->getAliquota(), 2);
        // la parte pagata quando il percipiente aveva meno di 35 anni ha la propria quota soggetta (diritti d'autore)
        $lordo = round(($ritenute - $ritenuteSotto35Anni) * 100 / $causale->getAliquota() * 100 / $causale->getQuotaImponibile()
                + $ritenuteSotto35Anni * 100 / $causale->getAliquota() * 100 / $causale->getQuotaImponibile(true), 2);
        return ['lordo' => $lordo, 'imponibile' => $imponibile, 'nonSoggette' => round($lordo - $imponibile, 2)];
    }

    /**
     * Codice del punto 6 per la parte del compenso non soggetta a ritenuta
     * (istruzioni CU 2026): 21 "altri redditi non soggetti a ritenuta" per
     * la quota delle provvigioni, che resta reddito del percipiente; 22
     * "somme che non costituiscono reddito" per le deduzioni forfetarie
     * delle spese (diritti d'autore, vendite a domicilio).
     */
    public static function getCodiceNonSoggette(CausaleCu $causale): int
    {
        return in_array($causale, [CausaleCu::dirittiAutore, CausaleCu::venditaDomicilio], true) ? self::CODICE_NON_REDDITO : self::CODICE_NON_SOGGETTE;
    }

    public static function proponiCompensi(QuadriDichiarazione $quadri, CausaleCu $causale, float $ritenute, float $ritenuteSotto35Anni = 0.0): void
    {
        $compensi = self::getCompensi($causale, $ritenute, $ritenuteSotto35Anni);
        $quadri->proponi('AU001001', $causale->value);
        $quadri->proponi('AU001004', $compensi['lordo']);
        if ($compensi['nonSoggette'] > 0) {
            $quadri->proponi('AU001006', self::getCodiceNonSoggette($causale));
            $quadri->proponi('AU001007', $compensi['nonSoggette']);
        }
        $quadri->proponi('AU001008', $compensi['imponibile']);
        $quadri->proponi('AU001009', $ritenute);
    }

    /**
     * Dati del sostituto (DA1) e firma (DA3) comuni a tutte le certificazioni.
     *
     * @return array<string, string|null> valori per codice
     */
    public function getDatiSostituto(Dichiarazione $dichiarazione): array
    {
        $soggetto = $dichiarazione->soggetto;
        $domicilio = (new DatiContribuente($this->dataMapper))->getDomicilio($soggetto, new SismaDate(($dichiarazione->anno + 1) . '-01-01'));
        $recapiti = (new Frontespizio($this->dataMapper))->getRecapiti($soggetto);
        $ateco = (new Frontespizio($this->dataMapper))->getCodiceAttivita2025($soggetto, $dichiarazione->anno);
        if ($domicilio === null) {
            $this->avvisi[] = 'Domicilio fiscale del sostituto non indicato nell\'Anagrafica (DA1).';
        }
        return [
            'DA001001' => $soggetto->codiceFiscale,
            'DA001002' => mb_strtoupper($soggetto->isPersonaFisica() ? (string) $soggetto->cognome : $soggetto->nominativo),
            'DA001003' => $soggetto->isPersonaFisica() ? mb_strtoupper((string) $soggetto->nome) : null,
            'DA001004' => $domicilio?->comune === null ? null : mb_strtoupper($domicilio->comune->denominazione),
            'DA001005' => $domicilio?->comune?->siglaProvincia,
            'DA001006' => $domicilio?->cap,
            'DA001007' => $domicilio === null ? null : mb_strtoupper(trim($domicilio->indirizzo . ' ' . $domicilio->numeroCivico)),
            'DA001008' => $recapiti['telefono'],
            'DA001009' => $recapiti['email'],
            'DA001010' => $ateco,
            'DA003001' => ($dichiarazione->dataPresentazione ?? new SismaDate())->format('Y-m-d'),
            'DA003002' => '1',
        ];
    }

    /**
     * @param array<string, string|null> $sostituto
     */
    public static function proponiSostituto(QuadriDichiarazione $quadri, array $sostituto): void
    {
        foreach ($sostituto as $codice => $valore) {
            $quadri->proponi($codice, $valore);
        }
    }

    /**
     * Dati anagrafici del percipiente (DA2): per le persone fisiche sesso,
     * data e luogo di nascita dal codice fiscale.
     */
    public function proponiPercipiente(QuadriDichiarazione $quadri, Controparte $controparte): void
    {
        $codiceFiscale = CodiceFiscale::normalizza((string) $controparte->codiceFiscale);
        $quadri->proponi('DA002001', $codiceFiscale);
        if (CodiceFiscale::isValidoPersonaFisica($codiceFiscale) === false) {
            $quadri->proponi('DA002002', mb_strtoupper($controparte->denominazione));
            return;
        }
        if (($controparte->cognome === null) || ($controparte->nome === null)) {
            $this->avvisi[] = sprintf('%s: cognome e nome non indicati nella controparte, proposta la denominazione come cognome (DA2).', $controparte->denominazione);
        }
        $quadri->proponi('DA002002', mb_strtoupper($controparte->cognome ?? $controparte->denominazione));
        $quadri->proponi('DA002003', $controparte->nome === null ? null : mb_strtoupper($controparte->nome));
        $nascita = CodiceFiscale::estraiDataNascita($codiceFiscale);
        $quadri->proponi('DA002004', CodiceFiscale::estraiSesso($codiceFiscale));
        $quadri->proponi('DA002005', $nascita->format('Y-m-d'));
        $codiceCatastale = CodiceFiscale::estraiCodiceCatastale($codiceFiscale);
        if (str_starts_with($codiceCatastale, 'Z')) {
            $quadri->proponi('DA002006', mb_strtoupper((string) (new NazioneModel($this->dataMapper))->getByCodice($codiceCatastale)?->denominazione));
            return;
        }
        $comune = (new ComuneModel($this->dataMapper))->getByCodiceAl($codiceCatastale, $nascita) ?? (new ComuneModel($this->dataMapper))->getByCodice($codiceCatastale);
        if ($comune === null) {
            $this->avvisi[] = sprintf('%s: comune di nascita (codice %s) non trovato nelle tabelle dell\'Anagrafica (DA2).', $controparte->denominazione, $codiceCatastale);
            return;
        }
        $quadri->proponi('DA002006', mb_strtoupper($comune->denominazione));
        $quadri->proponi('DA002007', $comune->siglaProvincia);
    }

    /**
     * Totali delle certificazioni per il riepilogo della dichiarazione.
     *
     * @return array<string, float>
     */
    public static function getRiepilogo(QuadriDichiarazione $quadri): array
    {
        $totali = ['Certificazioni' => 0, 'Compensi lordi (punto 4)' => 0.0, 'Imponibile (punto 8)' => 0.0, "Ritenute a titolo d'acconto (punto 9)" => 0.0];
        foreach ($quadri->getModuli() as $modulo) {
            $totali['Certificazioni']++;
            $totali['Compensi lordi (punto 4)'] += $quadri->getDecimale('AU001004', $modulo);
            $totali['Imponibile (punto 8)'] += $quadri->getDecimale('AU001008', $modulo);
            $totali["Ritenute a titolo d'acconto (punto 9)"] += $quadri->getDecimale('AU001009', $modulo);
        }
        return array_filter($totali);
    }

    private function haValoriManuali(QuadriDichiarazione $quadri, int $modulo): bool
    {
        foreach ($quadri->getValori()[$modulo] ?? [] as $dati) {
            if ($dati['manuale']) {
                return true;
            }
        }
        return false;
    }
}
