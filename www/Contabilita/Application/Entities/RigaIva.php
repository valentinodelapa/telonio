<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Enumerations\NaturaIva;
use Contabilita\Application\Enumerations\QualificaOperazione;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Riga del registro IVA: codice IVA, imponibile e imposta con il segno
 * del documento (negativi nelle note di credito), percentuale detraibile
 * sugli acquisti, acquisto di beni per la rivendita (base della
 * ventilazione dei corrispettivi; "altri prodotti" se non compresi nelle
 * categorie dell'art. 1 D.M. 24 febbraio 1973, per il limite del 50%
 * dell'art. 4) e conto di costo o ricavo di
 * contropartita. Soggetto,
 * registro e data di competenza sono copiati dalla registrazione
 * (Services\SalvataggioRegistrazione) per le interrogazioni per periodo.
 */
class RigaIva extends BaseEntity
{

    /** Percentuali di compensazione del regime agricolo (art. 34; righi VE1-VE11, VF39-VF49) */
    public const PERCENTUALI_COMPENSAZIONE = [2.0, 4.0, 6.4, 7.0, 7.3, 7.5, 8.3, 8.5, 8.8, 10.0, 12.3];

    protected int $id;
    protected RegistrazioneContabile $registrazioneContabile;
    protected Soggetto $soggetto;
    protected RegistroIva $registroIva;
    protected SismaDate $dataCompetenzaIva;
    protected int $ordine = 0;
    protected CodiceIva $codiceIva;
    protected float $imponibile = 0.0;
    protected float $imposta = 0.0;
    protected float $detraibilita = 100.0;
    protected bool $rivendita = false;
    protected bool $altriProdotti = false;
    protected ?NaturaIva $naturaInversione = null;
    protected ?QualificaOperazione $qualifica = null;
    protected ?float $aliquotaTeorica = null;
    protected ?float $percentualeCompensazione = null;
    protected bool $operazioneDiversa = false;

    /** Coefficiente di ammortamento oltre il 25%: non ammortizzabile per la rettifica della detrazione (art. 19-bis2, c. 5) */
    protected bool $coefficienteElevato = false;
    protected ?string $ripartizione = null;
    protected ?Conto $conto = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * Imposta detraibile (acquisti), arrotondata al centesimo.
     */
    public function getImpostaDetraibile(): float
    {
        return round($this->imposta * $this->detraibilita / 100, 2);
    }

    public function getImpostaIndetraibile(): float
    {
        return round($this->imposta - $this->getImpostaDetraibile(), 2);
    }

    /**
     * IVA detraibile dell'acquisto destinato alle operazioni diverse delle
     * imprese agricole miste: solo beni non ammortizzabili e servizi (art.
     * 34, c. 5, D.P.R. 633/1972; rigo VF53).
     */
    public function getDetrazioneOperazioneDiversa(): float
    {
        return $this->operazioneDiversa && ($this->isBeneAmmortizzabile() === false) ? $this->getImpostaDetraibile() : 0.0;
    }

    public function isBeneAmmortizzabile(): bool
    {
        return self::isImmobilizzazione($this->conto?->voceCee?->value);
    }

    /**
     * Voce CEE delle immobilizzazioni immateriali (B.I) e materiali (B.II)
     * dell'attivo, i cui acquisti sono beni ammortizzabili.
     */
    public static function isImmobilizzazione(?string $voceCee): bool
    {
        return ($voceCee !== null) && (str_starts_with($voceCee, 'A.B.I.') || str_starts_with($voceCee, 'A.B.II.'));
    }

    /**
     * Quote degli acquisti promiscui per numero di attività separata.
     *
     * @return array<int, float> percentuali (somma 100)
     */
    public function getRipartizione(): array
    {
        return self::leggiRipartizione($this->ripartizione);
    }

    /**
     * @return array<int, float>
     */
    public static function leggiRipartizione(?string $ripartizione): array
    {
        $quote = [];
        foreach (array_filter(explode(';', (string) $ripartizione)) as $quota) {
            [$numero, $percentuale] = array_pad(explode(':', $quota, 2), 2, '0');
            if (ctype_digit(trim($numero)) && is_numeric(trim($percentuale)) && ((float) $percentuale > 0)) {
                $quote[(int) $numero] = (float) $percentuale;
            }
        }
        ksort($quote);
        return $quote;
    }

    /**
     * @param array<int, float> $quote
     */
    public static function scriviRipartizione(array $quote): ?string
    {
        $quote = array_filter($quote, fn(float $percentuale) => $percentuale > 0);
        ksort($quote);
        return count($quote) === 0 ? null : implode(';', array_map(fn(int $numero, float $percentuale) => $numero . ':' . rtrim(rtrim(number_format($percentuale, 2, '.', ''), '0'), '.'), array_keys($quote), $quote));
    }
}
