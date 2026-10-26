<?php

namespace GestioneStudio\Application\Services;

use Telonio\Application\Entities\Utente;
use Telonio\Application\Services\Scadenza;
use GestioneStudio\Application\Entities\LavorazioneScadenza;
use GestioneStudio\Application\Enumerations\StatoLavorazione;

/**
 * Scadenza di un modulo con lo stato di lavorazione dello studio: la
 * scadenza che il modulo considera eseguita è completata; altrimenti vale
 * lo stato registrato (di default da fare). Il responsabile registrato
 * nella lavorazione prevale su quello indicato dal modulo.
 */
final class VoceScadenzario
{

    public readonly StatoLavorazione $stato;
    public readonly ?Utente $responsabile;

    public function __construct(public readonly Scadenza $scadenza, public readonly ?LavorazioneScadenza $lavorazione = null)
    {
        $this->stato = $scadenza->completata ? StatoLavorazione::completata : ($lavorazione?->stato ?? StatoLavorazione::daFare);
        $this->responsabile = $lavorazione?->responsabile ?? $scadenza->responsabile;
    }

    public function isScaduta(\DateTimeInterface $oggi): bool
    {
        return $this->stato->isAperta() && ($this->scadenza->data->format('Y-m-d') < $oggi->format('Y-m-d'));
    }

    /**
     * Stato completato dal modulo di origine: non modificabile dallo
     * scadenzario.
     */
    public function isCompletataDalModulo(): bool
    {
        return $this->scadenza->completata;
    }

    /**
     * Parametri dell'URL che identificano la scadenza (modulo, riferimento
     * codificato e data), per le azioni sulla lavorazione.
     */
    public function getPercorso(): string
    {
        return 'modulo/' . $this->scadenza->modulo->value . '/rif/' . self::codifica($this->scadenza->riferimento)
                . '/data/' . $this->scadenza->data->format('Y-m-d') . '/soggetto/' . ($this->scadenza->soggetto?->id ?? 0);
    }

    /**
     * Il riferimento può contenere caratteri non ammessi in un percorso
     * (es. "/"): nell'URL viaggia in base64 URL-safe.
     */
    public static function codifica(string $valore): string
    {
        return rtrim(strtr(base64_encode($valore), '+/', '-_'), '=');
    }

    public static function decodifica(string $valore): ?string
    {
        $decodificato = base64_decode(strtr($valore, '-_', '+/'), true);
        return $decodificato === false ? null : $decodificato;
    }
}
