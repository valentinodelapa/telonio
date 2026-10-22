<?php

namespace Deleghe\Application\Services;

use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Models\IndirizzoModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Dati anagrafici di un soggetto come richiesti dal modello F24: domicilio
 * fiscale (in mancanza, residenza o sede legale) e dati di nascita, con la
 * verifica di quelli obbligatori per le persone fisiche.
 */
class DatiContribuente
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public function getDomicilio(Soggetto $soggetto, \DateTimeInterface $data): ?Indirizzo
    {
        $indirizzi = [];
        foreach ((new IndirizzoModel($this->dataMapper))->getVigentiAl($soggetto, $data) as $indirizzo) {
            $indirizzi[$indirizzo->tipoIndirizzo->value] = $indirizzo;
        }
        return $indirizzi[TipoIndirizzo::domicilioFiscale->value] ?? $indirizzi[TipoIndirizzo::residenza->value] ?? $indirizzi[TipoIndirizzo::sedeLegale->value] ?? null;
    }

    /**
     * Luogo di nascita: comune e sigla della provincia, o stato estero ed "EE".
     *
     * @return array{0: string, 1: string}
     */
    public static function getLuogoNascita(Soggetto $soggetto): array
    {
        if ($soggetto->comuneNascita !== null) {
            return [$soggetto->comuneNascita->denominazione, (string) $soggetto->comuneNascita->siglaProvincia];
        }
        return [(string) $soggetto->nazioneNascita?->denominazione, 'EE'];
    }

    /**
     * Dati di nascita mancanti di una persona fisica (vuoto se completi).
     *
     * @return string[]
     */
    public static function getDatiMancanti(Soggetto $soggetto): array
    {
        if ($soggetto->isPersonaFisica() === false) {
            return [];
        }
        $mancanti = [];
        foreach (['cognome' => 'cognome', 'nome' => 'nome', 'sesso' => 'sesso', 'dataNascita' => 'data di nascita'] as $campo => $descrizione) {
            if ($soggetto->$campo === null) {
                $mancanti[] = $descrizione;
            }
        }
        if (($soggetto->comuneNascita === null) && ($soggetto->nazioneNascita === null)) {
            $mancanti[] = 'luogo di nascita';
        }
        return $mancanti;
    }
}
