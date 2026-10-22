<?php

namespace Deleghe\Application\Enumerations;

/**
 * Modelli di delega: ordinario, semplificato (sezioni Erario, Regioni e
 * tributi locali in un unico elenco di 10 righi, senza contributi) ed
 * elementi identificativi (ELIDE: imposte di registro, tasse
 * automobilistiche, ... con il solo importo a debito), accise (sezioni
 * dell'ordinario senza INAIL e altri enti, più la sezione accise, monopoli
 * e altri versamenti non ammessi in compensazione).
 *
 * Nel flusso telematico il semplificato si trasmette come ordinario.
 */
enum ModelloF24: string
{

    case ordinario = 'O';
    case semplificato = 'S';
    case elide = 'E';
    case accise = 'A';

    public const RIGHI_SEMPLIFICATO = 10;

    public function getLabel(): string
    {
        return match ($this) {
            self::ordinario => 'F24 ordinario',
            self::semplificato => 'F24 semplificato',
            self::elide => 'F24 elementi identificativi (ELIDE)',
            self::accise => 'F24 Accise',
        };
    }

    /**
     * @return SezioneF24[]
     */
    public function getSezioni(): array
    {
        return match ($this) {
            self::ordinario => [SezioneF24::erario, SezioneF24::inps, SezioneF24::regioni, SezioneF24::tributiLocali, SezioneF24::inail, SezioneF24::altriEnti],
            self::semplificato => [SezioneF24::erario, SezioneF24::regioni, SezioneF24::tributiLocali],
            self::elide => [SezioneF24::elide],
            self::accise => [SezioneF24::erario, SezioneF24::inps, SezioneF24::regioni, SezioneF24::tributiLocali, SezioneF24::accise],
        };
    }

    public function ammetteSezione(SezioneF24 $sezione): bool
    {
        return in_array($sezione, $this->getSezioni(), true);
    }

    public function ammetteCrediti(): bool
    {
        return $this !== self::elide;
    }

    /**
     * Tipo modello del record V del flusso telematico.
     */
    public function getTipoTelematico(): string
    {
        return match ($this) {
            self::elide => '8',
            self::accise => 'B',
            default => 'A',
        };
    }
}
