<?php

namespace Contabilita\Application\Services\FatturaPa;

/**
 * File della fattura elettronica letto: formato di trasmissione
 * (FPR12 tra privati, FPA12 verso la PA), cedente, cessionario e uno o
 * più corpi (lotto di fatture).
 */
class DocumentoFatturaPa
{

    public ?string $formato = null;
    public SoggettoFattura $cedente;
    public SoggettoFattura $cessionario;

    /** @var CorpoFattura[] */
    public array $corpi = [];

    public function getCorpo(int $indice): ?CorpoFattura
    {
        foreach ($this->corpi as $corpo) {
            if ($corpo->indice === $indice) {
                return $corpo;
            }
        }
        return null;
    }
}
