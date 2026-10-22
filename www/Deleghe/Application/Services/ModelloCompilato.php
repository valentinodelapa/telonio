<?php

namespace Deleghe\Application\Services;

use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneF24;

/**
 * Un modello F24 (un modulo cartaceo o un record V del flusso telematico)
 * risultante dalla ripartizione della delega: righi per sezione con gli
 * importi in centesimi. Un credito può essere diviso su più modelli: il
 * rigo riporta allora solo la quota compensata nel modello.
 */
class ModelloCompilato
{

    /** @var array<string, array<array{riga: Versamento, debito: int, credito: int}>> per valore di SezioneF24 */
    public array $righe = [];
    public bool $conDetrazioneImu = false;

    public function __construct()
    {
        foreach (SezioneF24::cases() as $sezione) {
            $this->righe[$sezione->value] = [];
        }
    }

    public function aggiungi(Versamento $riga, int $debito, int $credito): void
    {
        $this->righe[$riga->sezione->value][] = ['riga' => $riga, 'debito' => $debito, 'credito' => $credito];
    }

    public function contaRighe(?SezioneF24 $sezione = null): int
    {
        return $sezione === null ? array_sum(array_map('count', $this->righe)) : count($this->righe[$sezione->value]);
    }

    /**
     * @return array{debito: int, credito: int}
     */
    public function getTotaliSezione(SezioneF24 $sezione): array
    {
        return [
            'debito' => array_sum(array_column($this->righe[$sezione->value], 'debito')),
            'credito' => array_sum(array_column($this->righe[$sezione->value], 'credito')),
        ];
    }

    public function getDebito(): int
    {
        return array_sum(array_map(fn($righe) => array_sum(array_column($righe, 'debito')), $this->righe));
    }

    public function getCredito(): int
    {
        return array_sum(array_map(fn($righe) => array_sum(array_column($righe, 'credito')), $this->righe));
    }

    public function getSaldo(): int
    {
        return $this->getDebito() - $this->getCredito();
    }

    /**
     * Codice dell'ente della sezione altri enti previdenziali (unico per
     * modello), null se la sezione è vuota.
     */
    public function getCodiceAltroEnte(): ?string
    {
        return $this->righe[SezioneF24::altriEnti->value][0]['riga']->codiceEnte ?? null;
    }
}
